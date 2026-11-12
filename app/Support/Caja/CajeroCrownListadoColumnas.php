<?php

namespace App\Support\Caja;

use App\Models\Caja\CajeroCrown;
use App\Support\Listado\ListadoOrdenamientoSupport;

final class CajeroCrownListadoColumnas
{
    public const RECURSO = 'caja.cajero_crown';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'codigo' => ['label' => 'Código', 'default' => true, 'filterable' => true, 'type' => 'entero', 'source' => 'cajero_crown.codigo', 'group' => 'cajero'],
        'nombre' => ['label' => 'Nombre', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'cajero_crown.nombre', 'group' => 'cajero'],
        'legajo' => ['label' => 'Legajo', 'default' => true, 'filterable' => true, 'type' => 'entero', 'source' => 'cajero_crown.legajo', 'group' => 'cajero'],
        'tarea' => ['label' => 'Tarea', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'cajero_crown.tipo_tarea', 'group' => 'cajero'],
        'usuario' => ['label' => 'Usuario', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'usuario.nombre', 'group' => 'cajero'],
        'caja' => ['label' => 'Caja', 'default' => false, 'filterable' => true, 'type' => 'texto', 'source' => 'caja.nombre', 'group' => 'cajero'],
        'mozo' => ['label' => 'Mozo', 'default' => false, 'filterable' => true, 'type' => 'texto', 'source' => 'mozo_gastronomia.nombre', 'group' => 'cajero'],
        'externos' => ['label' => 'Externos', 'default' => true, 'filterable' => false, 'type' => 'texto', 'source' => '', 'group' => 'cajero'],
        'activo' => ['label' => 'Activo', 'default' => true, 'filterable' => true, 'type' => 'bool', 'source' => 'cajero_crown.activo', 'group' => 'cajero'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = [
        'cajero' => 'Cajero',
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
        if (! $fila instanceof CajeroCrown) {
            return '';
        }

        return match ($key) {
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) $fila->nombre,
            'legajo' => $fila->legajo !== null ? (string) $fila->legajo : '',
            'tarea' => TesoreriaCrownTareaCatalogo::tarea((string) ($fila->tipo_tarea ?? '')),
            'usuario' => (string) ($fila->usuario->nombre ?? $fila->usuario->usuario ?? ''),
            'caja' => trim((string) (($fila->caja->codigo ?? '').' — '.($fila->caja->nombre ?? '')), ' —'),
            'mozo' => trim((string) (($fila->mozoGastronomia->codigo ?? '').' — '.($fila->mozoGastronomia->nombre ?? '')), ' —'),
            'externos' => $fila->externos
                ->map(fn ($e) => (string) $e->sistema)
                ->filter()
                ->unique()
                ->implode(', '),
            'activo' => $fila->activo ? 'Sí' : 'No',
            default => '',
        };
    }
}
