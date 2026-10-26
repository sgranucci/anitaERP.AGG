<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownJornadaListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_jornada';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'fecha' => ['label' => 'Fecha', 'default' => true, 'filterable' => true, 'type' => 'fecha', 'source' => 'tesoreria_crown_jornada.fecha', 'group' => 'jornada'],
        'estado' => ['label' => 'Estado', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_jornada.estado', 'group' => 'jornada'],
        'asignaciones' => ['label' => 'Asignaciones', 'default' => true, 'filterable' => false, 'type' => 'entero', 'source' => '', 'group' => 'jornada'],
        'movimientos' => ['label' => 'Movimientos', 'default' => true, 'filterable' => false, 'type' => 'entero', 'source' => '', 'group' => 'jornada'],
        'cajeros' => ['label' => 'Quiénes trabajaron', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'cajero_crown.nombre', 'group' => 'jornada'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = ['jornada' => 'Jornada'];

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
            if ($key === 'cajeros') {
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
        return match ($key) {
            'fecha' => $fila->fecha?->format('d/m/Y') ?? '',
            'estado' => $fila instanceof TesoreriaCrownJornada && $fila->estaAbierta() ? 'Abierta' : 'Cerrada',
            'asignaciones' => (string) ($fila->asignaciones_count ?? ''),
            'movimientos' => (string) ($fila->comprobantes_count ?? ''),
            'cajeros' => $fila->asignaciones?->pluck('cajero.nombre')->filter()->unique()->implode(', ') ?? '',
            default => '',
        };
    }
}
