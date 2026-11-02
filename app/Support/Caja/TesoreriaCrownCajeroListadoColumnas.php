<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownCajeroListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_cajero';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'turno' => ['label' => 'Turno', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_asignacion.turno', 'group' => 'caja'],
        'caja' => ['label' => 'Caja', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_deposito.nombre', 'group' => 'caja'],
        'cajero' => ['label' => 'Cajero', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'cajero_crown.nombre', 'group' => 'caja'],
        'tarea' => ['label' => 'Tarea', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_asignacion.tipo_tarea', 'group' => 'caja'],
        'estado' => ['label' => 'Estado', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_cierre.estado', 'group' => 'caja'],
        'habilitacion' => ['label' => 'Habilitación', 'default' => true, 'filterable' => false, 'type' => 'texto', 'source' => '', 'group' => 'caja'],
        'rendicion' => ['label' => 'Rendición', 'default' => true, 'filterable' => false, 'type' => 'texto', 'source' => '', 'group' => 'caja'],
        'cierre' => ['label' => 'Cierre', 'default' => true, 'filterable' => false, 'type' => 'texto', 'source' => '', 'group' => 'caja'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = ['caja' => 'Caja'];

    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (empty($meta['filterable'])) {
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
        if (! $fila instanceof TesoreriaCrownAsignacion) {
            return '';
        }

        return match ($key) {
            'turno' => TesoreriaCrownAsignacionCatalogo::turno((string) $fila->turno),
            'caja' => trim((string) (($fila->deposito->codigo ?? '').' — '.($fila->deposito->nombre ?? '')), ' —'),
            'cajero' => trim((string) (($fila->cajero->codigo ?? '').' — '.($fila->cajero->nombre ?? '')), ' —'),
            'tarea' => TesoreriaCrownAsignacionCatalogo::tarea((string) $fila->tipo_tarea),
            'estado' => $fila->cierre !== null
                ? 'Cerrada '.$fila->cierre->estadoTexto()
                : 'Abierta',
            'habilitacion' => (string) $fila->comprobantes->where('tipo', 'H')->count(),
            'rendicion' => (string) $fila->comprobantes->where('tipo', 'F')->count(),
            'cierre' => $fila->cierre !== null
                ? TesoreriaCrownNumeradorSupport::visible($fila->cierre->numero).' '.$fila->cierre->estadoTexto()
                : '',
            default => '',
        };
    }
}
