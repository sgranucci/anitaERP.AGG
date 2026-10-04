<?php

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Compras\Pagoproveedor;
use App\Models\Contable\Asiento;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Services\Compras\PagoproveedorAnularRevertirService;
use App\Services\Solicitudpago\SolicitudpagoPagoDesdeCajaService;
use App\Support\Caja\ChequeOperacionActivaSupport;
use App\Support\Caja\IngresoEgresoSolicitudpagoOpaCuentacorrienteSupport;
use App\Support\Caja\CajaMovimientoEloquentDeleteSupport;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Caja\IngresoEgresoVisibilidadSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Auth;
use Carbon\Carbon;
use DB;
use RuntimeException;

/**
 * Anulación física (borra OP en ERP+Anita) y reversión (compensatorio + SP a AUTORIZADA).
 */
class IngresoEgresoAnularRevertirService
{
    public function __construct(
        private Caja_MovimientoRepositoryInterface $cajaMovimientoRepository,
        private Caja_Movimiento_CuentacajaRepositoryInterface $cuentacajaRepository,
        private Caja_Movimiento_EstadoRepositoryInterface $estadoRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private AsientoReversoSupport $asientoReversoSupport,
        private SolicitudpagoPagoDesdeCajaService $solicitudpagoPagoService,
        private PagoproveedorAnularRevertirService $pagoproveedorAnularRevertirService,
    ) {}

    /**
     * @param  bool  $verificarVisibilidadIe  false = ya validó acceso por SP (permiso revertir/anular pago)
     * @return array{mensaje: string, caja_movimiento_id: int}
     */
    public function anularFisicamente(int $id, bool $verificarVisibilidadIe = true): array
    {
        $movimiento = $this->cargarMovimiento($id);
        if ($verificarVisibilidadIe) {
            $this->assertAccesible($movimiento);
        }
        $this->assertNoEsCompensatorio($movimiento);
        if ((int) ($movimiento->caja_movimiento_revertido_por_id ?? 0) > 0) {
            throw new RuntimeException('El movimiento ya fue revertido; no se puede anular físicamente.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $movimiento->empresa_id,
            $this->fechaYmd($movimiento->fecha),
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $abrev = $this->abreviaturaTipo($movimiento);
        $nro = (string) ($movimiento->numerotransaccion ?? '');
        $leyendaSp = 'Anulación física '.$abrev.' '.$nro.' (IE id '.$movimiento->id.')';

        return DB::transaction(function () use ($movimiento, $leyendaSp) {
            IngresoEgresoSolicitudpagoOpaCuentacorrienteSupport::eliminarDesdeMovimiento($movimiento);
            $this->solicitudpagoPagoService->revertirAAutorizada($movimiento, $leyendaSp);
            $this->solicitudpagoPagoService->remarcPagadaSiQuedaPagoVigente($movimiento);

            $asientos = Asiento::query()->where('caja_movimiento_id', (int) $movimiento->id)->get();
            foreach ($asientos as $asiento) {
                $this->asientoRepository->delete((int) $asiento->id);
            }

            // Anita tesorería + cheques (antes de borrar ERP)
            IngresoEgresoAnitaTesmovSupport::eliminarDesdeMovimiento($movimiento);

            ChequeOperacionActivaSupport::anularPorCajaMovimiento($movimiento);
            if ((int) ($movimiento->cobranza_id ?? 0) > 0) {
                ChequeOperacionActivaSupport::marcarCobranzaRevertida((int) $movimiento->cobranza_id);
            }
            $movimiento->unsetRelation('cheques');
            foreach ($movimiento->cheques()->get() as $cheque) {
                $cheque->delete();
            }

            // No usar repo->delete: ya limpiamos Anita arriba.
            CajaMovimientoEloquentDeleteSupport::eliminarPorId((int) $movimiento->id);

            return [
                'mensaje' => 'ok',
                'caja_movimiento_id' => (int) $movimiento->id,
            ];
        });
    }

    /**
     * @param  bool  $verificarVisibilidadIe  false = ya validó acceso por SP (permiso revertir/anular pago)
     * @param  bool  $delegarOrdenPagoProveedor  true desde Ingresos/Egresos: la OP de proveedores
     *                                           entra en PagoproveedorAnularRevertirService (AOP).
     *                                           false desde solicitud de pago, que reabre la SP.
     * @return array{mensaje: string, caja_movimiento_id?: int, caja_movimiento_reverso_id?: int, numerotransaccion: string|int, asiento_id?: int, pagoproveedor_id?: int, pagoproveedor_reverso_id?: int, aviso?: string}
     */
    public function revertir(
        int $id,
        ?string $fecha = null,
        bool $verificarVisibilidadIe = true,
        bool $delegarOrdenPagoProveedor = false,
    ): array {
        $movimiento = $this->cargarMovimiento($id);
        if ($verificarVisibilidadIe) {
            $this->assertAccesible($movimiento);
        }
        $this->assertNoEsCompensatorio($movimiento);
        if ((int) ($movimiento->caja_movimiento_revertido_por_id ?? 0) > 0) {
            throw new RuntimeException('El movimiento ya fue revertido.');
        }

        if ($delegarOrdenPagoProveedor) {
            $pago = $this->ordenPagoProveedorParaCircuito($movimiento);
            if ($pago !== null) {
                return $this->pagoproveedorAnularRevertirService->revertir((int) $pago->id, $fecha);
            }
        }

        $fechaOp = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
            ? $fecha
            : date('Y-m-d');

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $movimiento->empresa_id,
            $fechaOp,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $abrev = $this->abreviaturaTipo($movimiento);
        $nroOrig = (string) ($movimiento->numerotransaccion ?? '');
        $leyenda = 'ANULA '.$abrev.' '.$nroOrig;

        return DB::transaction(function () use ($movimiento, $fechaOp, $leyenda, $abrev, $nroOrig) {
            IngresoEgresoSolicitudpagoOpaCuentacorrienteSupport::eliminarDesdeMovimiento($movimiento);
            $payload = [
                'empresa_id' => (int) $movimiento->empresa_id,
                'tipotransaccion_caja_id' => (int) $movimiento->tipotransaccion_caja_id,
                'fecha' => $fechaOp,
                'caja_id' => $movimiento->caja_id,
                'proveedor_id' => $movimiento->proveedor_id,
                'cliente_id' => $movimiento->cliente_id,
                'conceptogasto_id' => $movimiento->conceptogasto_id,
                'detalle' => $leyenda.': '.trim((string) ($movimiento->detalle ?? '')),
                'usuario_id' => Auth::id(),
                // No re-vincular SP: solo se reabre la original.
                'solicitudpago_id' => null,
                'caja_movimiento_origen_id' => (int) $movimiento->id,
            ];

            $reverso = $this->cajaMovimientoRepository->create($payload);
            if ($reverso === 'Error' || ! $reverso) {
                throw new RuntimeException('No se pudo grabar el movimiento de anulación.');
            }

            $lineasCaja = [
                'tipotransaccion_caja_id' => (int) $movimiento->tipotransaccion_caja_id,
                'fecha' => $fechaOp,
                'observaciones' => [],
                'cuentacaja_ids' => [],
                'montos' => [],
                'moneda_ids' => [],
                'cotizaciones' => [],
            ];
            foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
                $monto = (float) $linea->monto;
                $lineasCaja['cuentacaja_ids'][] = (int) $linea->cuentacaja_id;
                $lineasCaja['montos'][] = $monto >= 0 ? ($monto * -1.0) : abs($monto);
                $lineasCaja['moneda_ids'][] = (int) ($linea->moneda_id ?: 1);
                $lineasCaja['cotizaciones'][] = (float) ($linea->cotizacion ?: 1);
                $lineasCaja['observaciones'][] = $leyenda;
            }
            if ($lineasCaja['cuentacaja_ids'] !== []) {
                $this->cuentacajaRepository->create($lineasCaja, (int) $reverso->id);
            }

            $estadoData = [
                'fechas' => [$fechaOp],
                'estados' => [Caja_Movimiento_Estado::$enumEstado[0]['valor'] ?? 'A'],
                'observacionestados' => [$leyenda],
            ];
            $this->estadoRepository->create($estadoData, (int) $reverso->id);

            ChequeOperacionActivaSupport::anularPorCajaMovimiento($movimiento);
            if ((int) ($movimiento->cobranza_id ?? 0) > 0) {
                ChequeOperacionActivaSupport::marcarCobranzaRevertida((int) $movimiento->cobranza_id);
            }

            $asientoId = null;
            $asientoOrig = Asiento::query()
                ->with('asiento_movimientos')
                ->where('caja_movimiento_id', (int) $movimiento->id)
                ->orderBy('id')
                ->first();

            if ($asientoOrig) {
                $ref = $this->referenciaComprobanteAnita($reverso);
                $resAsiento = $this->asientoReversoSupport->generarDesdeAsiento(
                    $asientoOrig,
                    $fechaOp,
                    null,
                    $leyenda,
                    false,
                    (int) $reverso->id,
                    $ref,
                    PeriodoContableCierreSupport::ALCANCE_CAJA
                );
                $asientoId = (int) $resAsiento['asiento_id'];
            }

            $reversoFresh = $this->cajaMovimientoRepository->find((int) $reverso->id);
            IngresoEgresoAnitaTesmovSupport::grabarAnulacionDesdeMovimiento($reversoFresh, $movimiento);

            $movimiento->caja_movimiento_revertido_por_id = (int) $reverso->id;
            $movimiento->save();

            $this->estadoRepository->create([
                'fechas' => [$fechaOp],
                'estados' => ['R'],
                'observacionestados' => [$leyenda.' (compensatorio IE '.$reverso->id.')'],
            ], (int) $movimiento->id);

            $this->solicitudpagoPagoService->revertirAAutorizada(
                $movimiento,
                'Reversión '.$abrev.' '.$nroOrig.' → anulación N° '.$reverso->numerotransaccion
            );
            $this->solicitudpagoPagoService->remarcPagadaSiQuedaPagoVigente($movimiento);

            return [
                'mensaje' => 'ok',
                'caja_movimiento_id' => (int) $movimiento->id,
                'caja_movimiento_reverso_id' => (int) $reverso->id,
                'numerotransaccion' => $reverso->numerotransaccion,
                'asiento_id' => $asientoId,
            ];
        });
    }

    /**
     * IE vinculado a una SP (último no compensatorio).
     */
    public function movimientoPagoDeSolicitud(int $solicitudpagoId): ?Caja_Movimiento
    {
        return $this->movimientosPagoDeSolicitud($solicitudpagoId)->first();
    }

    /**
     * IEs de pago vinculados a una SP (no compensatorios), más reciente primero.
     *
     * @return \Illuminate\Support\Collection<int, Caja_Movimiento>
     */
    public function movimientosPagoDeSolicitud(int $solicitudpagoId)
    {
        if ($solicitudpagoId <= 0) {
            return collect();
        }

        return Caja_Movimiento::query()
            ->with(['tipotransaccioncajas:id,abreviatura,nombre'])
            ->where('solicitudpago_id', $solicitudpagoId)
            ->whereNull('caja_movimiento_origen_id')
            ->orderByDesc('id')
            ->get();
    }

    private function cargarMovimiento(int $id): Caja_Movimiento
    {
        return Caja_Movimiento::query()
            ->with([
                'caja_movimiento_cuentacajas.cuentacajas',
                'caja_movimiento_estados',
                'caja_movimiento_archivos',
                'cheques.cuentacajas',
                'cheques.proveedores',
                'cheques.chequeras',
                'tipotransaccioncajas',
                'solicitudpagos',
                'asientos.asiento_movimientos',
            ])
            ->findOrFail($id);
    }

    private function assertAccesible(Caja_Movimiento $movimiento): void
    {
        IngresoEgresoVisibilidadSupport::abortSiNoAccesible((int) $movimiento->id);
    }

    /**
     * OP cargada en Pago a proveedores (OPP, anticipo del módulo, retenciones).
     * El espejo OPA de una solicitud anticipada no entra: ese pago se borra
     * acá para que la solicitud vuelva a AUTORIZADA.
     */
    private function ordenPagoProveedorParaCircuito(Caja_Movimiento $movimiento): ?Pagoproveedor
    {
        $pagoId = (int) ($movimiento->pagoproveedor_id ?? 0);
        $pago = $pagoId > 0 ? Pagoproveedor::query()->find($pagoId) : null;
        if ($pago === null) {
            $pago = Pagoproveedor::query()
                ->where('caja_movimiento_id', (int) $movimiento->id)
                ->first();
        }
        if ($pago === null) {
            return null;
        }
        if ((int) ($pago->pagoproveedor_origen_id ?? 0) > 0) {
            return null;
        }
        if ($this->esOpaEspejoDeSolicitudAnticipada($movimiento, $pago)) {
            return null;
        }

        return $pago;
    }

    private function esOpaEspejoDeSolicitudAnticipada(Caja_Movimiento $movimiento, Pagoproveedor $pago): bool
    {
        $movimiento->loadMissing('solicitudpagos');
        if (! IngresoEgresoSolicitudpagoSupport::esPagoOpa($movimiento->solicitudpagos)) {
            return false;
        }

        $tipo = strtoupper(trim((string) ($pago->tipocomprobante ?? '')));
        $espejo = strtoupper(trim(IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($movimiento->solicitudpagos)));

        return $tipo !== '' && $tipo === $espejo;
    }

    private function assertNoEsCompensatorio(Caja_Movimiento $movimiento): void
    {
        if ((int) ($movimiento->caja_movimiento_origen_id ?? 0) > 0) {
            throw new RuntimeException('No se puede operar sobre un movimiento que es anulación de otro.');
        }
    }

    private function abreviaturaTipo(Caja_Movimiento $movimiento): string
    {
        $abrev = strtoupper(substr(trim((string) ($movimiento->tipotransaccioncajas->abreviatura ?? 'OPP')), 0, 3));

        return $abrev !== '' ? $abrev : 'OPP';
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $fecha)->format('Y-m-d');
        } catch (\Throwable $e) {
            return date('Y-m-d');
        }
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    private function referenciaComprobanteAnita(Caja_Movimiento $movimiento): array
    {
        $movimiento->loadMissing('tipotransaccioncajas');
        $tipo = $this->abreviaturaTipo($movimiento);
        $nro = (int) $movimiento->numerotransaccion;

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $movimiento->empresa_id);
        if ($empresaAnita <= 0) {
            $empresaAnita = (int) $movimiento->empresa_id;
        }

        $sucursalCfg = config('caja.ingresoegreso_anita_tesmov_sucursal');
        $sucursal = $sucursalCfg === null || $sucursalCfg === ''
            ? $empresaAnita
            : (int) $sucursalCfg;

        $letra = (string) config('caja.ingresoegreso_anita_tesmov_letra', ' ');
        if ($letra === '') {
            $letra = ' ';
        }

        return [
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'nro' => $nro,
        ];
    }
}
