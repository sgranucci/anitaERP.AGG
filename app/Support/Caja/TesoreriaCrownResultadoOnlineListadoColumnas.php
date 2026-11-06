<?php

namespace App\Support\Caja;

use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownResultadoOnlineListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_resultado_online';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'fecha' => ['label' => 'Fecha', 'default' => true, 'filterable' => true, 'type' => 'fecha', 'source' => 'tesoreria_crown_resultado_online.fecha', 'group' => 'dia'],
        'attendance' => ['label' => 'Asist.', 'default' => true, 'filterable' => true, 'type' => 'entero', 'source' => 'tesoreria_crown_resultado_online.attendance', 'group' => 'casino'],
        'apostado' => ['label' => 'Apostado', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.apostado', 'group' => 'casino'],
        'pagado' => ['label' => 'Pagado', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.pagado', 'group' => 'casino'],
        'drop' => ['label' => 'Drop', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.drop', 'group' => 'casino'],
        'resultado' => ['label' => 'Resultado', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.resultado', 'group' => 'casino'],
        'porcentaje' => ['label' => '%', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.porcentaje', 'group' => 'casino'],
        'poder_publico' => ['label' => 'P. público', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.poder_publico', 'group' => 'casino'],
        'coin_in' => ['label' => 'Coin in', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.coin_in', 'group' => 'slots'],
        'coin_out' => ['label' => 'Coin out', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.coin_out', 'group' => 'slots'],
        'pago_manual' => ['label' => 'Pago man.', 'default' => false, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.pago_manual', 'group' => 'slots'],
        'cantidad_slots' => ['label' => 'Slots', 'default' => true, 'filterable' => true, 'type' => 'entero', 'source' => 'tesoreria_crown_resultado_online.cantidad_slots', 'group' => 'slots'],
        'slot_resultado' => ['label' => 'Res. slots', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tesoreria_crown_resultado_online.slot_resultado', 'group' => 'slots'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = [
        'dia' => 'Día',
        'casino' => 'Casino online',
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
        if (in_array($key, ['attendance', 'cantidad_slots'], true)) {
            return number_format((int) ($fila->{$key} ?? 0), 0, ',', '.');
        }
        if ($key === 'porcentaje') {
            return number_format((float) ($fila->porcentaje ?? 0), 2, ',', '.');
        }
        if (! isset(self::COLUMNAS[$key])) {
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
