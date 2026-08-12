<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Support\Compras\OrdencompraEstados;
use Illuminate\Support\Collection;

/**
 * Armado del gráfico de progreso del circuito OC → factura → pago → retenciones
 * para el expediente del portal de proveedores.
 */
final class PortalProveedorCircuitoOcSupport
{
    public const ETAPA_OC = 'oc';

    public const ETAPA_FACTURA = 'factura';

    public const ETAPA_APROBACION = 'aprobacion';

    public const ETAPA_PAGO = 'pago';

    public const ETAPA_RETENCIONES = 'retenciones';

    /**
     * @return array{
     *   etapas: list<array{clave: string, titulo: string, estado: string, detalle: string, porcentaje: int}>,
     *   progreso_pct: int,
     *   etapa_actual: string,
     *   resumen: string
     * }
     */
    public static function desdeOrden(Ordencompra $orden): array
    {
        $facturas = $orden->portal_facturas ?? collect();
        if (! $facturas instanceof Collection) {
            $facturas = collect($facturas);
        }
        $precargas = $orden->portal_precargas ?? collect();
        if (! $precargas instanceof Collection) {
            $precargas = collect($precargas);
        }
        $retenciones = $orden->portal_retenciones ?? collect();
        if (! $retenciones instanceof Collection) {
            $retenciones = collect($retenciones);
        }

        $estadoOc = (string) ($orden->estadoordencompra ?? '');
        $ocOk = in_array($estadoOc, [
            OrdencompraEstados::APROBADA,
            OrdencompraEstados::CUMPLIDA,
            OrdencompraEstados::CERRADA,
        ], true);
        $ocDetalle = $estadoOc !== '' ? 'Estado: '.$estadoOc : 'Sin estado';

        $tieneFacturaFormal = $facturas->isNotEmpty();
        $tienePrecarga = $precargas->isNotEmpty();
        $facturaEstado = 'pendiente';
        $facturaDetalle = 'Aún no presentó factura';
        if ($tieneFacturaFormal) {
            $facturaEstado = 'completo';
            $facturaDetalle = $facturas->count().' factura(s) asociada(s)';
        } elseif ($tienePrecarga) {
            $facturaEstado = 'en_curso';
            $facturaDetalle = $precargas->count().' en precarga / revisión';
        }

        $estadosAprobados = ['APROBADO', 'CONTABILIZADO'];
        $aprobadas = $facturas->filter(
            static fn ($f) => in_array((string) ($f->estado ?? ''), $estadosAprobados, true)
        );
        $aprobacionEstado = 'pendiente';
        $aprobacionDetalle = 'Esperando formalización / aprobación';
        if ($aprobadas->isNotEmpty()) {
            $aprobacionEstado = 'completo';
            $aprobacionDetalle = $aprobadas->count().' contabilizada(s) / aprobada(s)';
        } elseif ($tieneFacturaFormal || $tienePrecarga) {
            $aprobacionEstado = 'en_curso';
            $estados = $facturas->pluck('estado')->filter()->unique()->implode(', ');
            if ($estados === '' && $tienePrecarga) {
                $estados = $precargas->pluck('estado')->filter()->unique()->implode(', ');
            }
            $aprobacionDetalle = $estados !== '' ? 'En proceso: '.$estados : 'En revisión interna';
        }

        $totalFac = (float) $facturas->sum(static fn ($f) => (float) ($f->total ?? 0));
        $totalPagado = (float) $facturas->sum(static fn ($f) => (float) ($f->total_pagado_portal ?? 0));
        $pagoEstado = 'pendiente';
        $pagoDetalle = 'Sin pagos vinculados';
        if ($totalPagado > 0.009 && $totalFac > 0 && $totalPagado + 0.05 >= $totalFac) {
            $pagoEstado = 'completo';
            $pagoDetalle = 'Pagado '.number_format($totalPagado, 2, ',', '.');
        } elseif ($totalPagado > 0.009) {
            $pagoEstado = 'en_curso';
            $pagoDetalle = 'Pagado parcial '.number_format($totalPagado, 2, ',', '.')
                .' / '.number_format($totalFac, 2, ',', '.');
        } elseif ($aprobadas->isNotEmpty() || $tieneFacturaFormal) {
            $pagoEstado = 'en_curso';
            $pagoDetalle = 'Factura presentada; pago pendiente';
        }

        $retEstado = 'pendiente';
        $retDetalle = 'Sin certificados de retención en los pagos de esta OC';
        if ($retenciones->isNotEmpty()) {
            $retEstado = 'completo';
            $retDetalle = $retenciones->count().' certificado(s) disponible(s) para descarga';
        } elseif ($pagoEstado === 'completo' || $pagoEstado === 'en_curso') {
            $retEstado = 'en_curso';
            $retDetalle = 'Se emiten al confirmar la orden de pago (solo consulta/descarga)';
        }

        $etapas = [
            self::etapa(self::ETAPA_OC, 'Orden de compra', $ocOk ? 'completo' : 'en_curso', $ocDetalle),
            self::etapa(self::ETAPA_FACTURA, 'Factura presentada', $facturaEstado, $facturaDetalle),
            self::etapa(self::ETAPA_APROBACION, 'Aprobación / contabilidad', $aprobacionEstado, $aprobacionDetalle),
            self::etapa(self::ETAPA_PAGO, 'Pago', $pagoEstado, $pagoDetalle),
            self::etapa(self::ETAPA_RETENCIONES, 'Retenciones', $retEstado, $retDetalle),
        ];

        $pesos = ['completo' => 100, 'en_curso' => 50, 'pendiente' => 0];
        $suma = 0;
        foreach ($etapas as $e) {
            $suma += $pesos[$e['estado']] ?? 0;
        }
        $progreso = (int) round($suma / max(1, count($etapas)));

        $etapaActual = self::ETAPA_OC;
        foreach ($etapas as $e) {
            if ($e['estado'] !== 'completo') {
                $etapaActual = $e['clave'];
                break;
            }
            $etapaActual = $e['clave'];
        }

        $resumen = match ($etapaActual) {
            self::ETAPA_OC => 'La OC está en gestión interna.',
            self::ETAPA_FACTURA => 'Falta presentar la factura (PDF o mail).',
            self::ETAPA_APROBACION => 'La factura está en revisión / aprobación.',
            self::ETAPA_PAGO => 'Hay saldo pendiente de pago.',
            self::ETAPA_RETENCIONES => 'Circuito avanzado: consulte certificados de retención.',
            default => 'Seguimiento del circuito de la OC.',
        };

        return [
            'etapas' => $etapas,
            'progreso_pct' => $progreso,
            'etapa_actual' => $etapaActual,
            'resumen' => $resumen,
        ];
    }

    /**
     * @return array{clave: string, titulo: string, estado: string, detalle: string, porcentaje: int}
     */
    private static function etapa(string $clave, string $titulo, string $estado, string $detalle): array
    {
        $pct = match ($estado) {
            'completo' => 100,
            'en_curso' => 50,
            default => 0,
        };

        return [
            'clave' => $clave,
            'titulo' => $titulo,
            'estado' => $estado,
            'detalle' => $detalle,
            'porcentaje' => $pct,
        ];
    }
}
