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
        'apostado' => ['label' => 'Apost. Del Río', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.apostado', 'group' => 'del_rio'],
        'pagado' => ['label' => 'Pag. Del Río', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.pagado', 'group' => 'del_rio'],
        'resultado' => ['label' => 'Res. Del Río', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.resultado', 'group' => 'del_rio'],
        'porcentaje' => ['label' => '% Del Río', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.porcentaje', 'group' => 'del_rio'],
        'attendance' => ['label' => 'Att. Del Río', 'default' => false, 'filterable' => true, 'type' => 'entero', 'source' => 'tesoreria_crown_resultado_online.attendance', 'group' => 'del_rio'],
        'drop' => ['label' => 'Drop Del Río', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.drop', 'group' => 'del_rio'],
        'poder_publico' => ['label' => 'PP Del Río', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.poder_publico', 'group' => 'del_rio'],
        'betsur_apostado' => ['label' => 'Apost. Betsur', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.betsur_apostado', 'group' => 'betsur'],
        'betsur_pagado' => ['label' => 'Pag. Betsur', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.betsur_pagado', 'group' => 'betsur'],
        'betsur_resultado' => ['label' => 'Res. Betsur', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.betsur_resultado', 'group' => 'betsur'],
        'betsur_porcentaje' => ['label' => '% Betsur', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.betsur_porcentaje', 'group' => 'betsur'],
        'betsur_attendance' => ['label' => 'Att. Betsur', 'default' => false, 'filterable' => true, 'type' => 'entero', 'source' => 'tesoreria_crown_resultado_online.betsur_attendance', 'group' => 'betsur'],
        'betsur_drop' => ['label' => 'Drop Betsur', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.betsur_drop', 'group' => 'betsur'],
        'betsur_poder_publico' => ['label' => 'PP Betsur', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.betsur_poder_publico', 'group' => 'betsur'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = [
        'dia' => 'Día',
        'slots' => 'Slots online',
        'del_rio' => 'Casinos del Río online',
        'betsur' => 'Betsur online',
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
        if (in_array($key, ['cantidad_slots', 'attendance', 'betsur_attendance'], true)) {
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
