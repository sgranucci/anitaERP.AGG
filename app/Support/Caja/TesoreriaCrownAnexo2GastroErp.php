<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use Illuminate\Support\Collection;

/**
 * GASTRO/HOTEL desde Crown: rendiciones G/H + cierres fiscales de la jornada.
 * Anulados = tot_cred del mismo cierre (p-cierrefact: NC* de venta).
 */
final class TesoreriaCrownAnexo2GastroErp
{
    /**
     * @param  Collection<int, TesoreriaCrownJornada>|list<TesoreriaCrownJornada>  $jornadas
     * @return array<string, array<string, mixed>>
     */
    public static function buckets($jornadas, string $fechaDiarioYmd): array
    {
        $buckets = [];
        $diaInt = (int) str_replace('-', '', $fechaDiarioYmd);

        foreach ($jornadas as $jornada) {
            $fechaInt = (int) $jornada->fecha->format('Ymd');
            self::aplicarRendiciones($buckets, $jornada, $fechaInt === $diaInt);
            self::aplicarCierresFiscales($buckets, $jornada, $fechaInt === $diaInt);
        }

        return $buckets;
    }

    /**
     * @param  array<string, array<string, mixed>>  $buckets
     */
    private static function aplicarRendiciones(array &$buckets, TesoreriaCrownJornada $jornada, bool $esDiario): void
    {
        $comps = TesoreriaCrownComprobante::query()
            ->with(['asignacion', 'cierresFiscales'])
            ->where('jornada_id', $jornada->id)
            ->where('tipo', TesoreriaCrownComprobanteSupport::RENDICION)
            ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
            ->whereHas('cajero', fn ($q) => $q->whereIn('tipo_tarea', ['G', 'H']))
            ->get();

        foreach ($comps as $comp) {
            $origen = strtoupper((string) (
                ($comp->asignacion ? $comp->asignacion->tipoTareaEfectiva() : '')
                ?: ($comp->cajero->tipo_tarea ?? 'G')
            ));
            $sucursal = self::sucursalDeComprobante($comp, $origen);
            $clave = $sucursal.'|'.$origen;
            $bucket = self::bucket($buckets, $clave, $sucursal, $origen);

            $rendicion = round((float) $comp->total_x + (float) $comp->total_nc, 2);
            $invitacion = round((float) $comp->invitacion, 2);
            $tarjeta = round(
                (float) $comp->tarjeta_credito
                + (float) $comp->tarjeta_debito
                + (float) $comp->total_qr,
                2
            );

            $bucket['arendicion'] = round($bucket['arendicion'] + $rendicion, 2);
            $bucket['ainvitacion'] = round($bucket['ainvitacion'] + $invitacion, 2);
            $bucket['atarjeta'] = round($bucket['atarjeta'] + $tarjeta, 2);
            if ($esDiario) {
                $bucket['rendicion'] = round($bucket['rendicion'] + $rendicion, 2);
                $bucket['invitacion'] = round($bucket['invitacion'] + $invitacion, 2);
                $bucket['tarjeta'] = round($bucket['tarjeta'] + $tarjeta, 2);
            }
            $buckets[$clave] = $bucket;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $buckets
     */
    private static function aplicarCierresFiscales(array &$buckets, TesoreriaCrownJornada $jornada, bool $esDiario): void
    {
        $cierres = TesoreriaCrownCierreFiscal::query()
            ->whereDate('fecha_jor', $jornada->fecha->format('Y-m-d'))
            ->get();

        foreach ($cierres as $cierre) {
            $sucursal = (int) $cierre->sucursal;
            $nombre = trim((string) $cierre->nombre_sucursal);
            $origen = str_starts_with($nombre, 'H') ? 'H' : 'G';
            $clave = $sucursal.'|'.$origen;
            $bucket = self::bucket($buckets, $clave, $sucursal, $origen, $nombre);
            $clave = $sucursal.'|'.(string) $bucket['origen'];

            $nroZ = (int) $cierre->nro_z;
            $venta = round((float) $cierre->tot_venta, 2);
            $cred = round((float) $cierre->tot_cred, 2);

            if ($nroZ !== 0) {
                // Hotel: las NC ya vienen en los X parciales; el Z repite el mismo crédito.
                $credZ = ($bucket['origen'] ?? '') === 'H' ? 0.0 : $cred;
                $bucket['aventa'] = round($bucket['aventa'] + $venta, 2);
                $bucket['aanulado'] = round($bucket['aanulado'] + $credZ, 2);
                if ($esDiario) {
                    $bucket['venta'] = round($bucket['venta'] + $venta, 2);
                    $bucket['anulado'] = round($bucket['anulado'] + $credZ, 2);
                }
            } elseif (($bucket['origen'] ?? '') === 'H') {
                $bucket['aanulado'] = round($bucket['aanulado'] + $cred, 2);
                if ($esDiario) {
                    $bucket['anulado'] = round($bucket['anulado'] + $cred, 2);
                }
            }
            $buckets[$clave] = $bucket;
        }
    }

    private static function sucursalDeComprobante(TesoreriaCrownComprobante $comp, string $origen): int
    {
        if ($origen === 'G') {
            return 0;
        }
        foreach ($comp->cierresFiscales as $cierre) {
            if ((int) $cierre->sucursal > 0) {
                return (int) $cierre->sucursal;
            }
        }

        return 131;
    }

    /**
     * @param  array<string, array<string, mixed>>  $buckets
     * @return array<string, mixed>
     */
    private static function bucket(array &$buckets, string $clave, int $sucursal, string $origen, ?string $nombre = null): array
    {
        if ($nombre !== null && $nombre !== '' && str_starts_with($nombre, 'H')) {
            $origen = 'H';
            $clave = $sucursal.'|H';
        }
        if (isset($buckets[$clave])) {
            if ($nombre !== null && $nombre !== '' && trim((string) $buckets[$clave]['desc']) === '') {
                $buckets[$clave]['desc'] = $nombre;
            }

            return $buckets[$clave];
        }

        $buckets[$clave] = [
            'sucursal' => $sucursal,
            'origen' => $origen,
            'desc' => $nombre ?? ($sucursal === 0 ? '' : ''),
            'venta' => 0.0,
            'anulado' => 0.0,
            'rendicion' => 0.0,
            'invitacion' => 0.0,
            'tarjeta' => 0.0,
            'aventa' => 0.0,
            'aanulado' => 0.0,
            'arendicion' => 0.0,
            'ainvitacion' => 0.0,
            'atarjeta' => 0.0,
        ];

        return $buckets[$clave];
    }

    /**
     * Formato de filas/totales a partir de buckets ERP (antes vivía en GastroAnita).
     *
     * @param  array<string, array<string, mixed>>  $buckets
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, float>,
     *     buckets: array<string, array<string, mixed>>
     * }
     */
    public static function armarFilas(array $buckets): array
    {
        $filas = [];
        $totales = self::totalesVacios();
        foreach ($buckets as $bucket) {
            $fila = self::filaDesdeBucket($bucket);
            if (! self::filaConMovimiento($fila)) {
                continue;
            }
            $filas[] = $fila;
            self::acumularTotales($totales, $fila);
        }

        if ($filas !== []) {
            $filas[] = [
                'etiqueta' => 'Total ingresos',
                'sucursal' => null,
                'origen' => '',
                'es_total' => true,
                'ventas_z' => $totales['ventas_z'],
                'rendiciones' => $totales['rendiciones'],
                'anulados' => $totales['anulados'],
                'invitaciones' => $totales['invitaciones'],
                'tarjetas' => $totales['tarjetas'],
                'acum_ventas_z' => $totales['acum_ventas_z'],
                'acum_rendiciones' => $totales['acum_rendiciones'],
                'acum_anulados' => $totales['acum_anulados'],
                'acum_invitaciones' => $totales['acum_invitaciones'],
                'acum_tarjetas' => $totales['acum_tarjetas'],
            ];
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'buckets' => $buckets,
        ];
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    private static function filaDesdeBucket(array $bucket): array
    {
        $sucursal = (int) $bucket['sucursal'];
        $etiqueta = $sucursal === 0
            ? 'Rendiciones de cajeros'
            : trim($sucursal.' '.trim((string) $bucket['desc']));

        $rendicion = (float) $bucket['rendicion'];
        $anulado = (float) $bucket['anulado'];
        $arendicion = (float) $bucket['arendicion'];
        $aanulado = (float) $bucket['aanulado'];

        // Si hay rendición, se muestra rendición − anulados (mismo criterio histórico).
        $rendicionMostrada = abs($rendicion) > 0.00001
            ? round($rendicion - $anulado, 2)
            : round($rendicion, 2);
        $arendicionMostrada = abs($arendicion) > 0.00001
            ? round($arendicion - $aanulado, 2)
            : round($arendicion, 2);

        return [
            'etiqueta' => $etiqueta,
            'sucursal' => $sucursal,
            'origen' => (string) $bucket['origen'],
            'es_total' => false,
            'ventas_z' => round((float) $bucket['venta'], 2),
            'rendiciones' => $rendicionMostrada,
            'anulados' => round($anulado, 2),
            'invitaciones' => round((float) $bucket['invitacion'], 2),
            'tarjetas' => round((float) $bucket['tarjeta'], 2),
            'acum_ventas_z' => round((float) $bucket['aventa'], 2),
            'acum_rendiciones' => $arendicionMostrada,
            'acum_anulados' => round($aanulado, 2),
            'acum_invitaciones' => round((float) $bucket['ainvitacion'], 2),
            'acum_tarjetas' => round((float) $bucket['atarjeta'], 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function filaConMovimiento(array $fila): bool
    {
        foreach ([
            'ventas_z', 'rendiciones', 'anulados', 'invitaciones', 'tarjetas',
            'acum_ventas_z', 'acum_rendiciones', 'acum_anulados', 'acum_invitaciones', 'acum_tarjetas',
        ] as $campo) {
            if (abs((float) ($fila[$campo] ?? 0)) > 0.00001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, float>
     */
    private static function totalesVacios(): array
    {
        return [
            'ventas_z' => 0.0,
            'rendiciones' => 0.0,
            'anulados' => 0.0,
            'invitaciones' => 0.0,
            'tarjetas' => 0.0,
            'acum_ventas_z' => 0.0,
            'acum_rendiciones' => 0.0,
            'acum_anulados' => 0.0,
            'acum_invitaciones' => 0.0,
            'acum_tarjetas' => 0.0,
        ];
    }

    /**
     * @param  array<string, float>  $totales
     * @param  array<string, mixed>  $fila
     */
    private static function acumularTotales(array &$totales, array $fila): void
    {
        foreach (array_keys($totales) as $campo) {
            $totales[$campo] = round($totales[$campo] + (float) ($fila[$campo] ?? 0), 2);
        }
    }
}
