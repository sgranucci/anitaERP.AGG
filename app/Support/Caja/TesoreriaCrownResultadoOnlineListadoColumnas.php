<?php

namespace App\Support\Caja;

use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownResultadoOnlineListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_resultado_online';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'fecha' => ['label' => 'Fecha', 'default' => true, 'filterable' => true, 'type' => 'fecha', 'source' => 'tesoreria_crown_resultado_online.fecha', 'group' => 'dia'],
        'coin_in' => ['label' => 'Coin in', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.coin_in', 'group' => 'slots'],
        'coin_out' => ['label' => 'Coin out', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.coin_out', 'group' => 'slots'],
        'pago_manual' => ['label' => 'Pago man.', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.pago_manual', 'group' => 'slots'],
        'cantidad_slots' => ['label' => 'Slots', 'default' => true, 'filterable' => true, 'type' => 'entero', 'source' => 'tesoreria_crown_resultado_online.cantidad_slots', 'group' => 'slots'],
        'slot_resultado' => ['label' => 'Res. slots', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.slot_resultado', 'group' => 'slots'],
        'subtotal_slot' => ['label' => 'Subtotal soft', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.subtotal_slot', 'group' => 'slots'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = [
        'dia' => 'Día',
        'slots' => 'Slots online',
    ];

    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (empty($meta['filterable']) || ($meta['source'] ?? '') === '') {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
            ];
        }

        return $out;
    }

    public static function camposOrdenables(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            $column = (string) ($meta['source'] ?? '');
            if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $column,
            ];
        }

        return $out;
    }

    public static function valorCelda(object $fila, string $key): string
    {
        if ($key === 'fecha') {
            return $fila->fecha?->format('d/m/Y') ?? '';
        }
        if ($key === 'cantidad_slots') {
            return number_format((int) ($fila->{$key} ?? 0), 0, ',', '.');
        }
        if (! isset(self::COLUMNAS[$key])) {
            return '';
        }
        if ($key === 'subtotal_slot' && $fila->subtotal_slot === null) {
            return '';
        }

        return number_format((float) ($fila->{$key} ?? 0), 2, ',', '.');
    }

    public static function esNumero(string $key): bool
    {
        $tipo = self::COLUMNAS[$key]['type'] ?? '';

        return in_array($tipo, ['decimal', 'entero'], true);
    }
}
