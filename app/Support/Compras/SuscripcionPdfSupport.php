<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use Carbon\Carbon;

/**
 * Datos que la OC impresa necesita cuando es una suscripción.
 *
 * El PDF es lo que ve el gerente al autorizar y Cuentas a pagar al liquidar, así que
 * tiene que decir por sí solo qué se autorizó: hasta cuánto, con qué tarjeta y hasta cuándo.
 */
final class SuscripcionPdfSupport
{
    /**
     * @return array<string, mixed>|null null cuando la OC no es una suscripción
     */
    public static function datos(?Ordencompra $oc): ?array
    {
        if (! $oc || ! (bool) ($oc->es_suscripcion ?? false)) {
            return null;
        }

        $monto = (float) ($oc->suscripcion_monto_periodo ?? 0);
        $tolerancia = (float) ($oc->suscripcion_tolerancia_pct ?? SuscripcionSupport::TOLERANCIA_DEFAULT_PCT);
        $moneda = optional($oc->contrato_monedas)->abreviatura
            ?: (optional($oc->contrato_monedas)->nombre ?: '');
        $autoRenovable = (bool) ($oc->contrato_auto_renovable ?? false);
        $aprobacion = self::aprobacion((int) $oc->id);

        return [
            'moneda' => trim((string) $moneda),
            'servicio' => (string) ($oc->suscripcion_nombre ?: $oc->detalle),
            'periodicidad' => SuscripcionSupport::etiquetaPeriodicidad($oc->suscripcion_periodicidad),
            'tratamiento' => 'OC ABIERTA · RECURRENTE',
            'condicion_pago' => 'Débito tarjeta corporativa ••'.($oc->suscripcion_tarjeta_ult4 ?: '····'),
            'tarjeta_etiqueta' => trim((string) optional($oc->suscripcion_tarjetas)->etiqueta),
            'auto_renovable' => $autoRenovable,
            'auto_renovable_texto' => $autoRenovable
                ? 'Sí — preaviso '.(int) ($oc->contrato_dias_preaviso ?? 0).' días'
                : 'No — requiere revalidación expresa',
            'tolerancia_pct' => $tolerancia,
            'monto_periodo' => $monto,
            'tope_autorizado' => SuscripcionSupport::topeAutorizado($monto, $tolerancia),
            'vigencia_desde' => self::fecha($oc->contrato_vigencia_desde),
            'vigencia_hasta' => self::fecha($oc->contrato_vigencia_hasta),
            'area' => (string) ($oc->suscripcion_area ?: ''),
            'owner' => (string) (optional($oc->suscripcion_owners)->nombre ?: ''),
            'solicitante' => (string) ($oc->suscripcion_solicitante ?: ''),
            'aprobo' => $aprobacion['nombre'],
            'aprobo_fecha' => $aprobacion['fecha'],
            'condiciones' => self::condiciones($oc, $monto, $tolerancia, (string) $moneda, $autoRenovable),
        ];
    }

    /**
     * El texto que fija qué se está autorizando, en los términos del contrato de suscripción.
     *
     * @return list<string>
     */
    private static function condiciones(
        Ordencompra $oc,
        float $monto,
        float $tolerancia,
        string $moneda,
        bool $autoRenovable
    ): array {
        $mon = fn (float $v) => trim($moneda.' '.number_format($v, 2, ',', '.'));
        $tope = SuscripcionSupport::topeAutorizado($monto, $tolerancia);
        $periodo = strtolower(SuscripcionSupport::etiquetaPeriodicidad($oc->suscripcion_periodicidad));

        $condiciones = [
            'Orden de compra abierta, sin recepción de mercadería: el servicio se consume de forma continua.',
            'Importe '.$periodo.' de referencia '.$mon($monto).', con una tolerancia de variación del '
                .number_format($tolerancia, 2, ',', '.').'%.',
            'Tope autorizado por cargo: '.$mon($tope).'. Un débito por encima de ese importe no queda cubierto '
                .'por esta autorización y vuelve al gerente del sector para revalidación.',
            'Débito automático contra la tarjeta corporativa ••'.($oc->suscripcion_tarjeta_ult4 ?: '····')
                .'. El cargo se concilia mes a mes contra esta orden.',
        ];

        $condiciones[] = $autoRenovable
            ? 'Renovación automática con preaviso de '.(int) ($oc->contrato_dias_preaviso ?? 0)
                .' días. La baja debe notificarse antes de ese plazo.'
            : 'Sin renovación automática: al vencimiento se interrumpe salvo revalidación expresa.';

        if ($oc->contrato_vigencia_hasta) {
            $condiciones[] = 'Vigencia hasta el '.self::fecha($oc->contrato_vigencia_hasta)
                .'. Avisos de vencimiento a los 60, 30 y 15 días.';
        }

        return $condiciones;
    }

    /**
     * Quién autorizó la suscripción por el árbol propio, no por el de órdenes de compra.
     *
     * @return array{nombre: string, fecha: string}
     */
    private static function aprobacion(int $ordencompraId): array
    {
        $vacio = ['nombre' => '—', 'fecha' => ''];
        if ($ordencompraId <= 0) {
            return $vacio;
        }

        $nombreAprobado = self::nombreEstado('A');

        $mov = Arbolaprobacion_Movimiento::query()
            ->with('destinatariousuarios')
            ->where('ordencompra_id', $ordencompraId)
            ->where('estado', $nombreAprobado)
            ->whereHas('arbolaprobaciones', fn ($q) => $q->where('tipoarbol', 'Suscripciones'))
            ->orderByDesc('nivel')
            ->orderByDesc('id')
            ->first();

        if (! $mov) {
            return $vacio;
        }

        return [
            'nombre' => (string) (optional($mov->destinatariousuarios)->nombre ?: '—'),
            'fecha' => $mov->fecharespuesta
                ? Carbon::parse($mov->fecharespuesta)->format('d/m/Y H:i')
                : '',
        ];
    }

    private static function nombreEstado(string $valor): string
    {
        foreach (Arbolaprobacion_Movimiento::$enumEstado ?? [] as $item) {
            if (($item['valor'] ?? '') === $valor) {
                return (string) $item['nombre'];
            }
        }

        return 'Aprobado';
    }

    private static function fecha(mixed $valor): string
    {
        if (! $valor) {
            return '—';
        }

        try {
            return Carbon::parse($valor)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }
}
