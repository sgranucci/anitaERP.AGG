<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Caja\Cheque;
use App\Models\Caja\Chequera;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Caja\ChequeConsultaChequeraSupport;
use App\Support\Caja\ChequePropioInstrumentoSupport;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionEscrituraSupport;
use App\Support\Compras\PagoproveedorAsientoArmadoSupport;
use App\Support\Compras\PagoproveedorDevolucionRetencionSupport;
use App\Support\Contable\AsientoBalanceSupport;
use App\Support\Contable\AsientoMonedaDesdeOperacionSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Contable\PeriodoContableCierreSupport;
use Auth;
use DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Devolución parcial de retenciones mal practicadas (sin revertir la OP completa).
 * Genera OP hija DEV RET + retenciones negativas + caja (transferencia/CHP) + asiento.
 */
final class PagoproveedorDevolverRetencionesService
{
    public function __construct(
        private readonly PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private readonly Caja_MovimientoRepositoryInterface $cajaMovimientoRepository,
        private readonly Caja_Movimiento_CuentacajaRepositoryInterface $cajaMovimientoCuentacajaRepository,
        private readonly Caja_Movimiento_EstadoRepositoryInterface $cajaMovimientoEstadoRepository,
        private readonly ChequeRepositoryInterface $chequeRepository,
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(int $id): array
    {
        $pago = $this->cargarOrigen($id);
        $disponibles = PagoproveedorDevolucionRetencionSupport::disponibles($pago);
        $pago->loadMissing(['proveedores:id,codigo,nombre,nroinscripcion', 'empresas:id,nombre']);

        return [
            'pagoproveedor_id' => (int) $pago->id,
            'comprobante' => sprintf(
                '%s %04d-%08d',
                (string) $pago->tipocomprobante,
                (int) $pago->sucursal,
                (int) $pago->numerotransaccion,
            ),
            'fecha_origen' => $pago->fecha?->format('Y-m-d'),
            'proveedor' => $pago->proveedores?->nombre,
            'proveedor_codigo' => $pago->proveedores?->codigo,
            'empresa' => $pago->empresas?->nombre,
            'estado' => (string) $pago->estado,
            'retenciones' => $disponibles,
            'puede_devolver' => collect($disponibles)->contains(fn (array $r) => (float) $r['disponible'] > 0.001),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     mensaje: string,
     *     pagoproveedor_id: int,
     *     pagoproveedor_devolucion_id: int,
     *     numerotransaccion: string,
     *     aviso?: string
     * }
     */
    public function devolver(int $id, array $payload): array
    {
        $pago = $this->cargarOrigen($id);
        $disponibles = PagoproveedorDevolucionRetencionSupport::disponibles($pago);
        $seleccion = PagoproveedorDevolucionRetencionSupport::validarSeleccion(
            $pago,
            array_values((array) ($payload['retenciones'] ?? [])),
            $disponibles,
        );

        $fechaOp = (string) ($payload['fecha'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaOp)) {
            $fechaOp = date('Y-m-d');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $pago->empresa_id,
            $fechaOp,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $medio = strtolower(trim((string) ($payload['medio'] ?? 'transferencia')));
        if (! in_array($medio, ['transferencia', 'chp'], true)) {
            throw new RuntimeException('Medio de pago inválido (transferencia o chp).');
        }

        $total = round(array_sum(array_column($seleccion, 'importe')), 2);
        if ($total < 0.001) {
            throw new RuntimeException('El total a devolver debe ser mayor a cero.');
        }

        $cuentacajaId = (int) ($payload['cuentacaja_id'] ?? 0);
        $chequeraId = (int) ($payload['chequera_id'] ?? 0);
        $chequera = null;
        if ($medio === 'chp') {
            $chequera = Chequera::query()->with('cuentacajas')->find($chequeraId);
            if ($chequera === null) {
                throw new RuntimeException('Seleccione una chequera válida.');
            }
            if ($cuentacajaId <= 0) {
                $cuentacajaId = (int) ($chequera->cuentacaja_id ?: 0);
            }
        }
        if ($cuentacajaId <= 0) {
            throw new RuntimeException('Seleccione la cuenta bancaria / caja del desembolso.');
        }
        $cuentaCaja = Cuentacaja::query()->find($cuentacajaId);
        if ($cuentaCaja === null || (int) ($cuentaCaja->cuentacontable_id ?? 0) <= 0) {
            throw new RuntimeException('La cuenta de caja no tiene cuenta contable asociada.');
        }

        $tipoOpp = Tipotransaccion_Caja::query()
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['OPP'])
            ->first();
        if ($tipoOpp === null) {
            throw new RuntimeException('No existe tipo de transacción OPP en caja.');
        }

        $local = DB::transaction(function () use (
            $pago,
            $seleccion,
            $fechaOp,
            $total,
            $medio,
            $cuentacajaId,
            $cuentaCaja,
            $chequera,
            $tipoOpp,
            $payload,
        ) {
            $actual = Pagoproveedor::query()->whereKey((int) $pago->id)->lockForUpdate()->first();
            if ($actual === null) {
                throw new RuntimeException('La OP ya no existe.');
            }
            $this->assertOrigenDevolvible($actual);

            $nro = PagoproveedorAnitaNumeracionSupport::siguienteNumeroConLock((int) $pago->empresa_id, 'OPP');
            $sucursal = PagoproveedorAnitaNumeracionSupport::sucursalParaOp((int) $pago->empresa_id);
            $leyenda = PagoproveedorDevolucionRetencionSupport::leyenda(
                $pago,
                trim((string) ($payload['observacion'] ?? 'Devolución de retenciones'))
            );

            $hija = $this->pagoproveedorRepository->create([
                'empresa_id' => (int) $pago->empresa_id,
                'tipotransaccion_caja_id' => (int) $tipoOpp->id,
                'tipocomprobante' => 'OPP',
                'letra' => (string) ($pago->letra ?? ' '),
                'sucursal' => $sucursal,
                'numerotransaccion' => (string) $nro,
                'fecha' => $fechaOp,
                'caja_id' => $pago->caja_id,
                'proveedor_id' => (int) $pago->proveedor_id,
                'detalle' => $leyenda,
                'estado' => 'CONFIRMADA',
                'monto' => $total,
                'cotizacion' => (float) ($pago->cotizacion ?: 1),
                'moneda_id' => (int) ($pago->moneda_id ?: 1),
                'modo_cotizacion' => (string) ($pago->modo_cotizacion ?? 'factura'),
                'usuario_id' => Auth::id(),
                'pagoproveedor_origen_id' => (int) $pago->id,
            ]);
            if (! $hija instanceof Pagoproveedor) {
                throw new RuntimeException('No se pudo grabar la OP de devolución.');
            }

            Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => (int) $hija->id,
                'fecha' => now(),
                'estado' => 'CONFIRMADA',
                'usuario_id' => Auth::id(),
                'observacion' => $leyenda,
            ]);

            $clonesAnita = [];
            foreach ($seleccion as $item) {
                /** @var Pagoproveedor_Retencion $origenRet */
                $origenRet = $item['retencion'];
                $importe = (float) $item['importe'];
                $det = is_array($origenRet->detalle_calculo) ? $origenRet->detalle_calculo : [];
                $det['origen_retencion_id'] = (int) $origenRet->id;
                $det['devolucion'] = true;

                Pagoproveedor_Retencion::query()->create([
                    'pagoproveedor_id' => (int) $hija->id,
                    'tiporetencion' => (string) $origenRet->tiporetencion,
                    'retencionganancia_id' => $origenRet->retencionganancia_id,
                    'retencioniva_id' => $origenRet->retencioniva_id,
                    'retencionsuss_id' => $origenRet->retencionsuss_id,
                    'provincia_id' => $origenRet->provincia_id,
                    'codigo_regimen' => $origenRet->codigo_regimen,
                    'codigo_retencion' => $origenRet->codigo_retencion,
                    'base_calculo' => abs((float) $origenRet->base_calculo),
                    'alicuota' => (float) $origenRet->alicuota,
                    'importe' => -abs($importe),
                    'nro_certificado' => $origenRet->nro_certificado,
                    'moneda_id' => (int) ($origenRet->moneda_id ?: $pago->moneda_id ?: 1),
                    'cotizacion' => (float) ($origenRet->cotizacion ?: $pago->cotizacion ?: 1),
                    'detalle_calculo' => $det,
                    'motivo' => 'devolucion_retencion',
                ]);

                $clone = $origenRet->replicate();
                $clone->importe = abs($importe);
                $clonesAnita[] = $clone;
            }

            $caja = $this->crearCajaYMedio(
                $hija,
                $pago,
                $fechaOp,
                $leyenda,
                $total,
                $medio,
                $cuentacajaId,
                $chequera,
                (int) $tipoOpp->id,
                $payload,
            );

            $this->crearAsientoDevolucion(
                $hija,
                $seleccion,
                $fechaOp,
                $leyenda,
                $total,
                (int) $cuentaCaja->cuentacontable_id,
            );

            return [
                'hija' => $hija->fresh(['pagoproveedor_retenciones']),
                'caja' => $caja,
                'clones_anita' => $clonesAnita,
            ];
        });

        /** @var Pagoproveedor $hija */
        $hija = $local['hija'];
        $salida = [
            'mensaje' => 'ok',
            'pagoproveedor_id' => (int) $pago->id,
            'pagoproveedor_devolucion_id' => (int) $hija->id,
            'numerotransaccion' => (string) $hija->numerotransaccion,
        ];

        try {
            if ($local['caja'] instanceof Caja_Movimiento) {
                IngresoEgresoAnitaTesmovSupport::grabarDesdeMovimiento($local['caja']->fresh());
            }
            PagoproveedorAnitaRetencionEscrituraSupport::grabarReversoDesdeRetenciones(
                $pago,
                $hija,
                $local['clones_anita'],
            );
        } catch (\Throwable $eAnita) {
            Log::error('pagoproveedor.devolucion_retencion.anita.sync_post_commit.fallo', [
                'pagoproveedor_id' => $pago->id,
                'devolucion_id' => $hija->id,
                'mensaje' => $eAnita->getMessage(),
            ]);
            $salida['aviso'] = 'Devolución grabada en ERP (OPP '.$hija->numerotransaccion
                .') pero falló la réplica Anita: '.$eAnita->getMessage()
                .'. No reintente a ciegas; revise sincronización.';
        }

        return $salida;
    }

    private function cargarOrigen(int $id): Pagoproveedor
    {
        $pago = $this->pagoproveedorRepository->findOrFail($id);
        $this->assertOrigenDevolvible($pago);

        return $pago;
    }

    private function assertOrigenDevolvible(Pagoproveedor $pago): void
    {
        if ((int) ($pago->pagoproveedor_origen_id ?? 0) > 0
            || PagoproveedorDevolucionRetencionSupport::esDevolucion($pago)
            || strtoupper((string) $pago->tipocomprobante) === 'AOP') {
            throw new RuntimeException('No se puede devolver retenciones desde un compensatorio o DEV RET.');
        }
        if ((int) ($pago->pagoproveedor_revertido_por_id ?? 0) > 0
            || in_array((string) $pago->estado, ['REVERTIDA', 'BAJA'], true)) {
            throw new RuntimeException('La OP está revertida o anulada.');
        }
        if (! in_array((string) $pago->estado, ['CONFIRMADA', 'PAGADA', 'CONCILIADA'], true)) {
            throw new RuntimeException('Solo se pueden devolver retenciones de OP confirmadas, pagadas o conciliadas.');
        }
    }

    /**
     * @param  list<array{retencion: Pagoproveedor_Retencion, importe: float}>  $seleccion
     */
    private function crearCajaYMedio(
        Pagoproveedor $hija,
        Pagoproveedor $origen,
        string $fechaOp,
        string $leyenda,
        float $total,
        string $medio,
        int $cuentacajaId,
        ?Chequera $chequera,
        int $tipoOppId,
        array $payload,
    ): Caja_Movimiento {
        $movimiento = $this->cajaMovimientoRepository->create([
            'empresa_id' => (int) $hija->empresa_id,
            'tipotransaccion_caja_id' => $tipoOppId,
            'numerotransaccion' => (string) $hija->numerotransaccion,
            'fecha' => $fechaOp,
            'caja_id' => $hija->caja_id,
            'proveedor_id' => (int) $hija->proveedor_id,
            'detalle' => $leyenda,
            'usuario_id' => Auth::id(),
            'pagoproveedor_id' => (int) $hija->id,
            'solicitudpago_id' => null,
        ]);
        if (! $movimiento instanceof Caja_Movimiento) {
            throw new RuntimeException('No se pudo grabar el movimiento de caja de la devolución.');
        }

        $monedaId = (int) ($hija->moneda_id ?: 1);
        $cotizacion = PagoproveedorAsientoArmadoSupport::cotizacionParaLinea(
            $monedaId,
            (float) ($hija->cotizacion ?: 1),
        );

        if ($medio === 'transferencia') {
            // Egreso bancario: signo negativo como en OP normales.
            $this->cajaMovimientoCuentacajaRepository->create([
                'tipotransaccion_caja_id' => $tipoOppId,
                'fecha' => $fechaOp,
                'cuentacaja_ids' => [$cuentacajaId],
                'montos' => [-abs($total)],
                'moneda_ids' => [$monedaId],
                'cotizaciones' => [$cotizacion],
                'observaciones' => [$leyenda],
            ], (int) $movimiento->id);
        } else {
            $origen->loadMissing(['proveedores']);
            $nombreProv = (string) ($origen->proveedores?->nombre ?? 'Proveedor');
            $dataCheque = [
                'numerocheque_emitidos' => [
                    trim((string) ($payload['numerocheque'] ?? '')) !== ''
                        ? (string) $payload['numerocheque']
                        : (string) ChequeConsultaChequeraSupport::siguienteNumero((int) $chequera->id, $chequera, true),
                ],
                'montocheque_emitidos' => [abs($total)],
                'chequera_emitido_ids' => [(int) $chequera->id],
                'cuentacaja_emitido_ids' => [$cuentacajaId],
                'fechapago_emitidos' => [
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($payload['fechapago'] ?? ''))
                        ? (string) $payload['fechapago']
                        : $fechaOp,
                ],
                'moneda_emitido_ids' => [$monedaId],
                'cotizacioncheque_emitidos' => [$cotizacion],
                'caracter_emitidos' => [ChequePropioInstrumentoSupport::caracterDefault()],
                'para_dep_emitidos' => [ChequePropioInstrumentoSupport::paraDepDefault()],
                'negociable_emitidos' => [ChequePropioInstrumentoSupport::negociableDesdeChequera(
                    (string) ($chequera->tipochequera ?: ChequePropioInstrumentoSupport::negociableDefault())
                )],
                'anombrede_emitidos' => [$nombreProv],
                'proveedor_emitido_ids' => [(int) $hija->proveedor_id],
                'pagoproveedor_id' => (int) $hija->id,
            ];
            $this->chequeRepository->guardarChequeIngresoEgreso($dataCheque, 'create', (int) $movimiento->id);
            Cheque::query()
                ->where('caja_movimiento_id', (int) $movimiento->id)
                ->whereNull('pagoproveedor_id')
                ->update(['pagoproveedor_id' => (int) $hija->id]);
        }

        $this->cajaMovimientoEstadoRepository->create([
            'fechas' => [$fechaOp],
            'estados' => [Caja_Movimiento_Estado::$enumEstado[0]['valor'] ?? 'A'],
            'observacionestados' => [$leyenda],
        ], (int) $movimiento->id);

        $this->pagoproveedorRepository->update([
            'caja_movimiento_id' => (int) $movimiento->id,
        ], (int) $hija->id);

        return $movimiento->fresh();
    }

    /**
     * @param  list<array{retencion: Pagoproveedor_Retencion, importe: float}>  $seleccion
     */
    private function crearAsientoDevolucion(
        Pagoproveedor $hija,
        array $seleccion,
        string $fechaOp,
        string $leyenda,
        float $total,
        int $cuentaBancoId,
    ): void {
        $tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');
        if (! $tipoasiento) {
            throw new RuntimeException('No existe tipo de asiento TES.');
        }

        $empresaId = (int) $hija->empresa_id;
        $monedaId = (int) ($hija->moneda_id ?: 1);
        $cotizacion = PagoproveedorAsientoArmadoSupport::cotizacionParaLinea(
            $monedaId,
            (float) ($hija->cotizacion ?: 1),
        );

        $cuentacontableIds = [];
        $debes = [];
        $haberes = [];
        $monedaIds = [];
        $cotizaciones = [];
        $centros = [];
        $observaciones = [];

        foreach ($seleccion as $item) {
            $cuentaRetId = $this->cuentaContableRetencion($item['retencion'], $empresaId);
            if ($cuentaRetId <= 0) {
                throw new RuntimeException(
                    'No hay cuenta contable configurada para retención '.$item['retencion']->etiquetaTipo().'.'
                );
            }
            $cuentacontableIds[] = $cuentaRetId;
            $debes[] = abs((float) $item['importe']);
            $haberes[] = '';
            $monedaIds[] = $monedaId;
            $cotizaciones[] = $cotizacion;
            $centros[] = 0;
            $observaciones[] = $leyenda;
        }

        $cuentacontableIds[] = $cuentaBancoId;
        $debes[] = '';
        $haberes[] = abs($total);
        $monedaIds[] = $monedaId;
        $cotizaciones[] = $cotizacion;
        $centros[] = 0;
        $observaciones[] = $leyenda;

        AsientoBalanceSupport::assertBalanceadoDesdePayload([
            'debes' => $debes,
            'haberes' => $haberes,
        ], 'asiento de devolución de retenciones');

        $payload = [
            'empresa_id' => $empresaId,
            'tipoasiento_id' => $tipoasiento->id,
            'fecha' => $fechaOp,
            'observacion' => $leyenda,
            'pagoproveedor_id' => (int) $hija->id,
            'usuario_id' => Auth::id(),
            'estado_aprobacion' => 'confirmado',
            'cuentacontable_ids' => $cuentacontableIds,
            'debes' => $debes,
            'haberes' => $haberes,
            'moneda_ids' => $monedaIds,
            'cotizaciones' => $cotizaciones,
            'centrocosto_ids' => $centros,
            'observaciones' => $observaciones,
            'tipo' => 'OPP',
            'letra' => (string) config('caja.ingresoegreso_anita_tesmov_letra', ' '),
            'sucursal' => (int) $hija->sucursal,
            'nro' => (int) $hija->numerotransaccion,
        ];
        $payload = AsientoMonedaDesdeOperacionSupport::aplicarEnPayload($payload, [[
            'moneda_id' => $monedaId,
            'monto' => abs($total),
            'cotizacion' => $cotizacion,
        ]]);

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new RuntimeException('Error al grabar el asiento de la devolución.');
        }
        $this->asientoMovimientoRepository->create($payload, $asiento->id);
        $this->pagoproveedorRepository->update(['asiento_id' => $asiento->id], (int) $hija->id);
    }

    private function cuentaContableRetencion(Pagoproveedor_Retencion $ret, int $empresaId): int
    {
        $tipo = strtoupper(trim((string) $ret->tiporetencion));
        if ($tipo === Pagoproveedor_Retencion::TIPO_IIBB && (int) ($ret->provincia_id ?? 0) > 0) {
            $reg = \App\Models\Compras\RetencionIIBB::query()
                ->where('provincia_id', (int) $ret->provincia_id)
                ->first();
            if ($reg && (int) ($reg->cuentacontable_id ?? 0) > 0) {
                return (int) $reg->cuentacontable_id;
            }
        }

        $clave = match ($tipo) {
            Pagoproveedor_Retencion::TIPO_GANANCIAS => CuentaAutomaticaClaves::PAGO_RETENCION_GANANCIAS,
            Pagoproveedor_Retencion::TIPO_IVA => CuentaAutomaticaClaves::PAGO_RETENCION_IVA,
            Pagoproveedor_Retencion::TIPO_SUSS => CuentaAutomaticaClaves::PAGO_RETENCION_SUSS,
            Pagoproveedor_Retencion::TIPO_IIBB => CuentaAutomaticaClaves::PAGO_RETENCION_IIBB,
            default => null,
        };
        if ($clave === null) {
            return 0;
        }

        return (int) (CuentaAutomaticaResolver::resolverId($empresaId, $clave) ?? 0);
    }
}
