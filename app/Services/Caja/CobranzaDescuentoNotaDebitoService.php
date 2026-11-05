<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Cobranza_Descuento;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Services\Ventas\ClienteCuentacorrienteAplicacionAnitaSyncService;
use App\Services\Ventas\ClienteCuentacorrienteAplicacionService;
use App\Services\Ventas\FacturacionService;
use App\Support\Caja\CobranzaDescuentoConfigSupport;
use App\Support\Ventas\ArcaFceNcMostradorSupport;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
use App\Support\Ventas\NotaDebitoReversionNotaCreditoSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nota de débito que invierte fiscalmente la NCP de descuento de una cobranza
 * y la deja aplicada contra esa NCP en cuenta corriente.
 *
 * ARCA no participa de la transacción de la cobranza: se llama después del commit.
 */
final class CobranzaDescuentoNotaDebitoService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly ClienteCuentacorrienteAplicacionService $aplicacionService,
        private readonly ClienteCuentacorrienteAplicacionAnitaSyncService $anitaSyncService,
    ) {}

    /**
     * @param  list<int>  $ventaNcIds
     * @return array{codigos: list<string>, avisos: list<string>, trabajo: bool}
     */
    public function emitirYAplicar(array $ventaNcIds, string $fecha): array
    {
        $codigos = [];
        $avisos = [];
        $trabajo = false;
        $fecha = $this->fechaYmd($fecha);

        foreach (array_values(array_unique(array_filter(array_map('intval', $ventaNcIds)))) as $ventaNcId) {
            try {
                $resultado = $this->emitirUna($ventaNcId, $fecha);
            } catch (Throwable $e) {
                Log::error('cobranza.descuento.ndp', [
                    'venta_nc_id' => $ventaNcId,
                    'error' => $e->getMessage(),
                ]);
                $avisos[] = 'No se pudo invertir la nota de crédito '.$ventaNcId.': '.$e->getMessage();

                continue;
            }

            if ($resultado['codigo'] !== '') {
                $codigos[] = $resultado['codigo'];
            }
            if ($resultado['trabajo']) {
                $trabajo = true;
            }
            foreach ($resultado['avisos'] as $aviso) {
                $avisos[] = $aviso;
            }
        }

        return [
            'codigos' => $codigos,
            'avisos' => $avisos,
            'trabajo' => $trabajo,
        ];
    }

    /**
     * @return array{codigo: string, avisos: list<string>, trabajo: bool}
     */
    private function emitirUna(int $ventaNcId, string $fecha): array
    {
        $ncp = Venta::query()
            ->with(['tipotransacciones', 'venta_emisiones'])
            ->find($ventaNcId);
        if ($ncp === null) {
            return [
                'codigo' => '',
                'avisos' => ['La nota de crédito '.$ventaNcId.' ya no existe. No se emitió la nota de débito.'],
                'trabajo' => false,
            ];
        }

        $codigoNc = trim((string) ($ncp->codigo ?? ''));
        $etiquetaNc = $codigoNc !== '' ? $codigoNc : ('id '.$ventaNcId);
        $ya = NotaDebitoReversionNotaCreditoSupport::codigosNdPorNotaCredito([$ventaNcId])[$ventaNcId] ?? '';
        if ($ya !== '') {
            $this->liberarAplicacionEnFacturaOrigen($ncp);

            return $this->aplicarParSiPendiente($ncp, $fecha, $ya);
        }

        $error = NotaDebitoReversionNotaCreditoSupport::errorAlAbrir($ncp);
        if ($error !== null) {
            return ['codigo' => '', 'avisos' => [$error], 'trabajo' => false];
        }

        $tipoNd = NotaDebitoReversionNotaCreditoSupport::tipoNotaDebitoPara($ncp);
        if ($tipoNd === null) {
            return [
                'codigo' => '',
                'avisos' => ['No hay un tipo de nota de débito activo para revertir '.$etiquetaNc.'.'],
                'trabajo' => false,
            ];
        }

        $payload = $this->payload($ncp, (int) $tipoNd->id, $fecha);
        $errorCantidad = NotaDebitoReversionNotaCreditoSupport::errorAlEmitir(
            $ventaNcId,
            (int) $tipoNd->id,
            $payload
        );
        if ($errorCantidad !== null) {
            return ['codigo' => '', 'avisos' => [$errorCantidad], 'trabajo' => false];
        }

        // La emisión aplica la ND contra la NCP por el total: la NCP tiene que
        // estar libre o queda aplicada dos veces y la factura sigue descontada.
        $this->liberarAplicacionEnFacturaOrigen($ncp);

        $resultado = $this->facturacionService->generaComprobanteGeneral($payload);
        if (! empty($resultado['error'])) {
            $mensaje = is_string($resultado['error']) ? $resultado['error'] : 'Error al emitir la nota de débito en ARCA.';

            return ['codigo' => '', 'avisos' => [$etiquetaNc.': '.$mensaje], 'trabajo' => false];
        }

        $ventaNdId = (int) ($resultado['venta_id'] ?? 0);
        $ndp = $ventaNdId > 0 ? Venta::query()->find($ventaNdId) : null;
        $codigoNd = trim((string) ($ndp->codigo ?? $resultado['factura'] ?? ''));
        if ($ndp === null) {
            return [
                'codigo' => $codigoNd,
                'avisos' => ['Se emitió la nota de débito de '.$etiquetaNc.' pero no se pudo leer para aplicarla.'],
                'trabajo' => true,
            ];
        }

        $aplicacion = $this->aplicarParSiPendiente($ncp, $fecha, $codigoNd !== '' ? $codigoNd : $etiquetaNc);

        return [
            'codigo' => $aplicacion['codigo'] !== '' ? $aplicacion['codigo'] : $codigoNd,
            'avisos' => $aplicacion['avisos'],
            'trabajo' => true,
        ];
    }

    /**
     * @return array{codigo: string, avisos: list<string>, trabajo: bool}
     */
    private function aplicarParSiPendiente(Venta $ncp, string $fecha, string $codigoNd): array
    {
        $etiquetaNc = trim((string) ($ncp->codigo ?? '')) !== ''
            ? trim((string) $ncp->codigo)
            : ('id '.$ncp->id);

        $ccNc = $this->ccFiscal((int) $ncp->id, 'credito');
        $ndp = null;
        $hijas = Venta::query()
            ->with('tipotransacciones')
            ->where('venta_origen_id', (int) $ncp->id)
            ->orderByDesc('id')
            ->get();
        foreach ($hijas as $hija) {
            if ($hija->tipotransacciones && $hija->tipotransacciones->esNotaDebito()) {
                $ndp = $hija;
                break;
            }
        }
        if ($ccNc === null || $ndp === null) {
            return [
                'codigo' => $codigoNd,
                'avisos' => ['La nota de débito '.$codigoNd.' no quedó aplicada contra '.$etiquetaNc.': falta el movimiento de cuenta corriente.'],
                'trabajo' => false,
            ];
        }

        $ccNd = $this->ccFiscal((int) $ndp->id, 'deuda');
        if ($ccNd === null) {
            return [
                'codigo' => $codigoNd,
                'avisos' => ['La nota de débito '.$codigoNd.' no generó deuda en cuenta corriente para aplicarla contra '.$etiquetaNc.'.'],
                'trabajo' => false,
            ];
        }

        $pendienteNc = $this->pendiente($ccNc);
        $pendienteNd = $this->pendiente($ccNd);
        $monto = round(min($pendienteNc, $pendienteNd), 2);
        if ($monto < 0.01) {
            return [
                'codigo' => $codigoNd,
                'avisos' => $this->sincronizarParEnAnita((int) $ccNd->id, (int) $ccNc->id, $codigoNd),
                'trabajo' => false,
            ];
        }

        $fechaAplicacion = $this->fechaYmd($fecha);
        $fechaMinima = $this->fechaMaxima(
            $this->fechaYmd($ccNc->fecha),
            $this->fechaYmd($ccNd->fecha)
        );
        if ($fechaAplicacion < $fechaMinima) {
            $fechaAplicacion = $fechaMinima;
        }

        try {
            $this->aplicacionService->aplicar((int) $ncp->cliente_id, $fechaAplicacion, [[
                'credito_id' => (int) $ccNc->id,
                'deuda_id' => (int) $ccNd->id,
                'monto' => $monto,
            ]]);
        } catch (Throwable $e) {
            Log::error('cobranza.descuento.ndp.aplicar', [
                'venta_nc_id' => (int) $ncp->id,
                'venta_nd_id' => (int) $ndp->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'codigo' => $codigoNd,
                'avisos' => [
                    'La nota de débito '.$codigoNd.' quedó emitida y no se aplicó contra '.$etiquetaNc.': '.$e->getMessage()
                    .' No reintente la reversión ni la anulación: aplique los dos comprobantes en la cuenta corriente del cliente.',
                ],
                'trabajo' => false,
            ];
        }

        return ['codigo' => $codigoNd, 'avisos' => [], 'trabajo' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Venta $ncp, int $tipoNdId, string $fecha): array
    {
        $puntoventaId = CobranzaDescuentoConfigSupport::puntoventaIdDesdeFactura((int) ($ncp->puntoventa_id ?? 0));
        $codigo = trim((string) ($ncp->codigo ?? ''));
        $leyenda = 'Reversión total de '.($codigo !== '' ? $codigo : 'la nota de crédito '.$ncp->id).'.';
        if (mb_strlen($leyenda) > 255) {
            $leyenda = mb_substr($leyenda, 0, 255);
        }

        $articuloIds = [];
        $conceptoIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $impuestoIds = [];
        $incluye = [];

        $emisiones = ($ncp->relationLoaded('venta_emisiones')
            ? $ncp->venta_emisiones
            : Venta_Emision::query()->where('venta_id', (int) $ncp->id)->orderBy('id')->get()
        )->sortBy('id')->values();

        foreach ($emisiones as $emision) {
            $articuloIds[] = (int) ($emision->articulo_id ?? 0);
            $conceptoIds[] = (int) ($emision->concepto_venta_id ?? 0);
            $cantidades[] = abs((float) $emision->cantidad);
            $precios[] = abs((float) $emision->precio);
            $detalle = trim((string) ($emision->detalle ?? ''));
            $descripciones[] = $detalle !== '' ? $detalle : $leyenda;
            $impuestoIds[] = (int) ($emision->impuesto_id ?? 0);
            $incluye[] = trim((string) ($emision->incluyeimpuesto ?? '')) !== ''
                ? (string) $emision->incluyeimpuesto
                : '1';
        }

        $fechaNd = $this->fechaYmd($fecha);
        $fechaNc = $this->fechaYmd($ncp->fecha);
        if ($fechaNd < $fechaNc) {
            $fechaNd = $fechaNc;
        }

        $payload = [
            'venta_id' => (int) $ncp->id,
            NotaDebitoReversionNotaCreditoSupport::FLAG => 1,
            'tipotransaccion_id' => $tipoNdId,
            'puntoventa_id' => $puntoventaId,
            'fechafactura' => $fechaNd,
            'leyendafactura' => $leyenda,
            'actividad_arca_id' => CobranzaDescuentoConfigSupport::actividadArcaIdDesdeFactura(
                $ncp->actividad_arca_id !== null ? (int) $ncp->actividad_arca_id : null,
                $puntoventaId,
            ),
            'cliente_id' => (int) $ncp->cliente_id,
            'moneda_id' => (int) $ncp->moneda_id,
            'cotizacion' => (float) ($ncp->cotizacion ?: 0),
            'listaprecio_id' => 1,
            'descuentolinea' => 0.,
            'descuentopie' => 0.,
            'descuentoimportepie' => 0.,
            'articulo_ids' => $articuloIds,
            'concepto_venta_ids' => $conceptoIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'impuesto_ids' => $impuestoIds,
            'incluyeimpuestos' => $incluye,
            'opciones_emision' => [
                'omitir_movimiento_stock' => true,
            ],
        ];

        $asociado = ArcaFceNcMostradorSupport::parsearCodigoComprobante($codigo);
        if ($asociado !== null) {
            $payload['fce_comprobante_referenciado'] = $codigo;
            if (ArcaFceNcMostradorSupport::esTipoFacturaFce((int) $asociado['tipo'])) {
                $payload['fce_anulacion'] = 'S';
            }
        }

        if (trim((string) ($ncp->nombre ?? '')) !== '' || trim((string) ($ncp->numerodocumento ?? '')) !== '') {
            $payload['venta_receptor'] = [
                'nombre' => $ncp->nombre,
                'numerodocumento' => $ncp->numerodocumento,
                'domicilio' => $ncp->domicilio,
            ];
        }

        return $payload;
    }

    /**
     * Quita el par NCP↔factura que grabó la emisión (sin cobranza ni asiento).
     * Las aplicaciones manuales o de cobranza no se tocan.
     */
    private function liberarAplicacionEnFacturaOrigen(Venta $ncp): void
    {
        $descuento = Cobranza_Descuento::query()
            ->where('venta_nc_id', (int) $ncp->id)
            ->orderByDesc('id')
            ->first();
        $ccNcId = (int) ($descuento->cliente_cuentacorriente_nc_id ?? 0);
        $ccFacturaId = (int) ($descuento->cliente_cuentacorriente_origen_id ?? 0);
        if ($ccNcId <= 0 || $ccFacturaId <= 0) {
            return;
        }

        // desaplicar borra el par y recalcula climov/aplmov en Anita.
        $ids = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccFacturaId)
            ->where('cliente_cuentacorriente_aplicado_id', $ccNcId)
            ->where(function ($q) {
                $q->whereNull('cobranza_id')->orWhere('cobranza_id', 0);
            })
            ->where(function ($q) {
                $q->whereNull('asiento_id')->orWhere('asiento_id', 0);
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();
        foreach ($ids as $id) {
            try {
                $this->aplicacionService->desaplicar((int) $id, (int) $ncp->cliente_id);
            } catch (Throwable $e) {
                // Si el par ya no está, falló solo Anita: la ND se emite igual.
                if (Cliente_Cuentacorriente_Aplicacion::query()->whereKey((int) $id)->exists()) {
                    throw $e;
                }
                Log::error('cobranza.descuento.ndp.liberar_anita', [
                    'venta_nc_id' => (int) $ncp->id,
                    'aplicacion_id' => (int) $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * La emisión de la ND graba el par ND↔NCP solo en anitaERP.
     *
     * @return list<string>
     */
    private function sincronizarParEnAnita(int $ccNdId, int $ccNcId, string $codigoNd): array
    {
        $ids = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccNdId)
            ->where('cliente_cuentacorriente_aplicado_id', $ccNcId)
            ->pluck('id')
            ->all();
        if ($ids === []) {
            return [];
        }

        try {
            $this->anitaSyncService->syncPorIdsAplicacion($ids);
        } catch (Throwable $e) {
            Log::error('cobranza.descuento.ndp.anita', [
                'cc_nd_id' => $ccNdId,
                'cc_nc_id' => $ccNcId,
                'error' => $e->getMessage(),
            ]);

            return ['La nota de débito '.$codigoNd.' quedó aplicada en anitaERP pero no en Anita: '.$e->getMessage()];
        }

        return [];
    }

    private function ccFiscal(int $ventaId, string $lado): ?Cliente_Cuentacorriente
    {
        $filas = Cliente_Cuentacorriente::query()
            ->where('venta_id', $ventaId)
            ->where(function ($q) {
                $q->whereNull('cobranza_id')->orWhere('cobranza_id', 0);
            })
            ->orderBy('id')
            ->get();

        foreach ($filas as $fila) {
            $total = (float) $fila->total;
            if ($lado === 'credito' && $total < 0) {
                return $fila;
            }
            if ($lado === 'deuda' && $total > 0) {
                return $fila;
            }
        }

        return $filas->first();
    }

    private function pendiente(Cliente_Cuentacorriente $cc): float
    {
        $aplicado = (float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', (int) $cc->id)
            ->sum('total');

        return ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $cc->total, $aplicado);
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $raw = trim((string) $fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) === 1) {
            return substr($raw, 0, 10);
        }

        return date('Y-m-d');
    }

    private function fechaMaxima(string $a, string $b): string
    {
        return $a >= $b ? $a : $b;
    }
}
