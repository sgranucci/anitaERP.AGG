<?php

declare(strict_types=1);

namespace App\Support\Ventas\Waitry;

/**
 * Diff Informe Z congelado al cierre vs relectura Waitry (comandas no transmitidas a tiempo).
 * El Z histórico no se pisa; se documenta el bloque transmision_faltante_z para Tesorería.
 */
final class WaitryInformeZTransmisionFaltanteSupport
{
    public const CLAVE_DETALLE = 'transmision_faltante_z';

    public const CLAVE_ORDENES_SNAPSHOT = 'informe_z_ordenes';

    /**
     * @param  list<array<string, mixed>>  $lineasActivas
     * @return list<array<string, mixed>>
     */
    public static function compactarOrdenesDesdeLineas(array $lineasActivas, int $empresaId = 0): array
    {
        $out = [];
        foreach ($lineasActivas as $ln) {
            if (! is_array($ln) || ! WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($ln)) {
                continue;
            }
            $fila = self::filaDesdeLinea($ln, $empresaId);
            if ($fila !== null) {
                $out[] = $fila;
            }
        }

        usort($out, static fn (array $a, array $b): int => ((int) $a['waitry_order_id']) <=> ((int) $b['waitry_order_id']));

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $ordenesSnapshot
     * @param  list<array<string, mixed>>  $ordenesFrescas
     * @return array<string, mixed>
     */
    public static function analizar(
        float $totalZHistorico,
        array $ordenesSnapshot,
        array $ordenesFrescas,
        float $tolerancia,
    ): array {
        $idsSnapshot = [];
        foreach ($ordenesSnapshot as $o) {
            $id = (int) ($o['waitry_order_id'] ?? 0);
            if ($id > 0) {
                $idsSnapshot[$id] = true;
            }
        }

        $totalFresco = 0.0;
        $faltantes = [];
        foreach ($ordenesFrescas as $o) {
            if (! is_array($o)) {
                continue;
            }
            $monto = round((float) ($o['monto'] ?? 0), 2);
            $totalFresco = round($totalFresco + $monto, 2);
            $id = (int) ($o['waitry_order_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($idsSnapshot === []) {
                continue;
            }
            if (! isset($idsSnapshot[$id])) {
                $faltantes[] = $o;
            }
        }

        if ($idsSnapshot === [] && abs($totalFresco - $totalZHistorico) > $tolerancia) {
            $faltantes = self::explicarDeltaSinIds($ordenesFrescas, round($totalFresco - $totalZHistorico, 2));
        }

        usort($faltantes, static fn (array $a, array $b): int => strcmp(
            (string) ($a['placed_at'] ?? ''),
            (string) ($b['placed_at'] ?? ''),
        ));

        $totalFaltante = 0.0;
        foreach ($faltantes as $f) {
            $totalFaltante = round($totalFaltante + (float) ($f['monto'] ?? 0), 2);
        }

        $diffTotales = round($totalFresco - $totalZHistorico, 2);
        $tiene = abs($diffTotales) > $tolerancia || $faltantes !== [];

        return [
            'tiene_diferencias' => $tiene,
            'total_z_historico' => round($totalZHistorico, 2),
            'total_z_relectura' => $totalFresco,
            'diff_totales' => $diffTotales,
            'total_faltante' => $totalFaltante,
            'total_tesoreria' => round($totalZHistorico + $totalFaltante, 2),
            'cantidad_comandas' => count($faltantes),
            'cantidad_ordenes_snapshot' => count($ordenesSnapshot),
            'cantidad_ordenes_relectura' => count($ordenesFrescas),
            'modo' => $idsSnapshot === [] ? 'sin_ids_snapshot' : 'diff_ids',
            'comandas' => $faltantes,
            'motivo' => 'Comandas en ventana de jornada no incluidas en el Informe Z del cierre (transmisión Waitry / snapshot de preview).',
            'calculado_en' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @return array<string, mixed>
     */
    public static function paraVista(array $bloque): array
    {
        if ($bloque === [] || empty($bloque['tiene_diferencias'])) {
            return [
                'tiene_diferencias' => false,
                'cantidad_comandas' => 0,
            ];
        }

        $comandas = [];
        foreach ($bloque['comandas'] ?? [] as $c) {
            if (! is_array($c)) {
                continue;
            }
            $placed = (string) ($c['placed_at'] ?? '');
            $comandas[] = array_merge($c, [
                'placed_at_fmt' => $placed !== ''
                    ? \Carbon\Carbon::parse($placed)->format('d/m/Y H:i')
                    : '—',
                'monto_fmt' => number_format((float) ($c['monto'] ?? 0), 2, ',', '.'),
            ]);
        }

        return array_merge($bloque, [
            'comandas' => $comandas,
            'total_z_historico_fmt' => number_format((float) ($bloque['total_z_historico'] ?? 0), 2, ',', '.'),
            'total_faltante_fmt' => number_format((float) ($bloque['total_faltante'] ?? 0), 2, ',', '.'),
            'total_tesoreria_fmt' => number_format((float) ($bloque['total_tesoreria'] ?? 0), 2, ',', '.'),
            'total_z_relectura_fmt' => number_format((float) ($bloque['total_z_relectura'] ?? 0), 2, ',', '.'),
            'calculado_en_fmt' => ! empty($bloque['calculado_en'])
                ? \Carbon\Carbon::parse((string) $bloque['calculado_en'])->format('d/m/Y H:i')
                : '—',
        ]);
    }

    /**
     * @param  array<string, mixed>  $ln
     * @return array<string, mixed>|null
     */
    private static function filaDesdeLinea(array $ln, int $empresaId): ?array
    {
        $id = (int) ($ln['waitry_order_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $monto = round((float) ($ln['monto_cobro_waitry'] ?? $ln['total'] ?? 0), 2);
        if ($monto <= 0.0001) {
            $monto = round((float) ($ln['total_amount_waitry'] ?? 0), 2);
        }
        if ($monto <= 0.0001) {
            return null;
        }

        $tipo = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea($ln, $empresaId)
            ?? (string) ($ln['waitry_tipo_pago'] ?? '');

        return [
            'waitry_order_id' => $id,
            'display_id' => (string) ($ln['display_id'] ?? ''),
            'monto' => $monto,
            'tipo_medio' => $tipo,
            'medio_label' => (string) ($ln['waitry_medio_label'] ?? $tipo),
            'waitry_table_id' => isset($ln['waitry_table_id']) ? (int) $ln['waitry_table_id'] : null,
            'waitry_table_name' => (string) ($ln['waitry_table_name'] ?? ''),
            'waitry_layout_name' => (string) ($ln['waitry_layout_name'] ?? ''),
            'placed_at' => (string) ($ln['placed_at'] ?? ''),
        ];
    }

    /**
     * Sin IDs del preview: toma las órdenes más nuevas hasta cubrir el exceso (fresh − histórico).
     *
     * @param  list<array<string, mixed>>  $ordenesFrescas
     * @return list<array<string, mixed>>
     */
    private static function explicarDeltaSinIds(array $ordenesFrescas, float $exceso): array
    {
        if ($exceso <= 0.02) {
            return [];
        }

        $candidatas = $ordenesFrescas;
        usort($candidatas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['placed_at'] ?? ''), (string) ($a['placed_at'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($b['waitry_order_id'] ?? 0)) <=> ((int) ($a['waitry_order_id'] ?? 0));
        });

        $elegidas = [];
        $acum = 0.0;
        foreach ($candidatas as $o) {
            $elegidas[] = $o;
            $acum = round($acum + (float) ($o['monto'] ?? 0), 2);
            if ($acum + 0.02 >= $exceso) {
                break;
            }
        }

        return $elegidas;
    }
}
