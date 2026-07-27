<?php

namespace App\Support\Ventas;

/**
 * Hipótesis de solo lectura sobre filas DIF de conciliación de turno gastronomía.
 * No muta cobranzas ni cierre.
 */
final class GastronomiaConciliacionTurnoExplicacionSupport
{
    private const TOLERANCIA = 0.02;

    /**
     * @param  list<array<string,mixed>>  $filasDif
     * @param  array<string,mixed>  $totales
     * @return array{
     *   score: float,
     *   parrafos: list<string>,
     *   hipotesis: list<array<string,mixed>>,
     *   resumen: array<string,int|float>
     * }
     */
    public static function evaluar(array $filasDif, array $totales = []): array
    {
        $hipotesis = [];
        foreach (array_slice($filasDif, 0, 40) as $fila) {
            $h = self::hipotesisFila($fila);
            if ($h !== null) {
                $hipotesis[] = $h;
            }
        }

        $parrafos = [];
        $nDif = count($filasDif);
        $parrafos[] = $nDif === 0
            ? 'No hay comprobantes con diferencia de cobranza (≥ $0,02) en el alcance del turno.'
            : 'Hay '.$nDif.' comprobante(s) con diferencia facturado vs cobrado.';

        $redondeoSug = (float) ($totales['redondeo_invitaciones_sugerido'] ?? 0);
        if (abs($redondeoSug) >= 0.005) {
            $parrafos[] = 'Redondeo invitaciones sugerido en totales del turno: '.self::fmt($redondeoSug).'.';
        }
        $difCob = (float) ($totales['diferencia_cobranza'] ?? 0);
        if (abs($difCob) >= self::TOLERANCIA) {
            $parrafos[] = 'Diferencia de cobranza agregada del turno: '.self::fmt($difCob).'.';
        }

        $porTipo = [];
        foreach ($hipotesis as $h) {
            $t = (string) ($h['tipo'] ?? 'desconocido');
            $porTipo[$t] = ($porTipo[$t] ?? 0) + 1;
            $parrafos[] = (string) ($h['mensaje'] ?? '');
        }
        $parrafos = array_values(array_filter($parrafos, static fn ($p) => $p !== ''));
        $parrafos = array_slice($parrafos, 0, 20);

        $score = self::calcularScore($nDif, $hipotesis);

        return [
            'score' => $score,
            'parrafos' => $parrafos,
            'hipotesis' => $hipotesis,
            'resumen' => [
                'filas_dif' => $nDif,
                'hipotesis' => count($hipotesis),
                'por_tipo' => $porTipo,
                'diferencia_cobranza' => $difCob,
                'redondeo_invitaciones_sugerido' => $redondeoSug,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $fila
     * @return array<string,mixed>|null
     */
    private static function hipotesisFila(array $fila): ?array
    {
        $ventaId = (int) ($fila['venta_id'] ?? 0);
        $codigo = (string) ($fila['codigo'] ?? '');
        $diff = (float) ($fila['diferencia'] ?? 0);
        $facturado = (float) ($fila['total_facturado'] ?? 0);
        $cobrado = (float) ($fila['total_cobrado'] ?? 0);
        $etiqueta = $codigo !== '' ? $codigo : ('venta #'.$ventaId);

        if (! empty($fila['es_nota_credito'])) {
            return [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'tipo' => 'nc',
                'severidad' => 'media',
                'diferencia' => $diff,
                'mensaje' => $etiqueta.': es nota de crédito'
                    .(isset($fila['venta_factura_origen_id']) ? ' (origen venta #'.$fila['venta_factura_origen_id'].')' : '')
                    .'. Dif. '.self::fmt($diff).' — revisar si la NC se cobró/imputó en otra PC o medio.',
            ];
        }

        if (! empty($fila['es_invitacion'])) {
            return [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'tipo' => 'invitacion',
                'severidad' => 'baja',
                'diferencia' => $diff,
                'mensaje' => $etiqueta.': marcada como invitación/cortesía. Dif. '.self::fmt($diff)
                    .' — si no cuadra, revisar redondeo de invitaciones en el cierre.',
            ];
        }

        if ($cobrado <= 0.005 && $facturado > self::TOLERANCIA) {
            return [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'tipo' => 'falta_cobranza',
                'severidad' => 'alta',
                'diferencia' => $diff,
                'mensaje' => $etiqueta.': facturado '.self::fmt($facturado).' sin cobranza registrada. Completar medios o revisar anulación.',
            ];
        }

        if ($cobrado > 0 && abs($diff) > 0 && abs($diff) <= self::TOLERANCIA + 0.001) {
            return [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'tipo' => 'redondeo',
                'severidad' => 'baja',
                'diferencia' => $diff,
                'mensaje' => $etiqueta.': diferencia menor ('.self::fmt($diff).') compatible con redondeo / residual de cierre.',
            ];
        }

        if ($cobrado > $facturado + self::TOLERANCIA) {
            return [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'tipo' => 'exceso_cobranza',
                'severidad' => 'alta',
                'diferencia' => $diff,
                'mensaje' => $etiqueta.': cobrado '.self::fmt($cobrado).' > facturado '.self::fmt($facturado)
                    .' (dif. '.self::fmt($diff).'). Posible medio duplicado o NC mal asociada.',
            ];
        }

        if ($cobrado + self::TOLERANCIA < $facturado) {
            return [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'tipo' => 'medio_desalineado',
                'severidad' => 'alta',
                'diferencia' => $diff,
                'mensaje' => $etiqueta.': cobrado '.self::fmt($cobrado).' < facturado '.self::fmt($facturado)
                    .' (dif. '.self::fmt($diff).'). Revisar medios en el modal de conciliación.',
            ];
        }

        return [
            'venta_id' => $ventaId,
            'codigo' => $codigo,
            'tipo' => 'desconocido',
            'severidad' => 'media',
            'diferencia' => $diff,
            'mensaje' => $etiqueta.': diferencia '.self::fmt($diff).' sin patrón claro — revisar detalle de medios.',
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $hipotesis
     */
    private static function calcularScore(int $nDif, array $hipotesis): float
    {
        if ($nDif === 0) {
            return 0.95;
        }
        $altas = 0;
        foreach ($hipotesis as $h) {
            if (($h['severidad'] ?? '') === 'alta') {
                $altas++;
            }
        }
        $score = 0.75 - min(0.4, $nDif * 0.03) - min(0.25, $altas * 0.05);

        return max(0.15, min(0.95, round($score, 4)));
    }

    private static function fmt(float $n): string
    {
        return '$ '.number_format($n, 2, ',', '.');
    }
}
