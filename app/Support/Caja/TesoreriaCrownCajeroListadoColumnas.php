<?php

namespace App\Support\Caja;

use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownCajeroListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_cajero';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'cajero' => ['label' => 'Cajero', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'cajero_crown.nombre', 'group' => 'cajero'],
        'tarea' => ['label' => 'Tarea', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'cajero_crown.tipo_tarea', 'group' => 'cajero'],
        'estado' => ['label' => 'Estado', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_cierre.estado', 'group' => 'cajero'],
        'habilitacion' => ['label' => 'Habilitación', 'default' => true, 'filterable' => false, 'type' => 'texto', 'source' => '', 'group' => 'cajero'],
        'rendicion' => ['label' => 'Rendición', 'default' => true, 'filterable' => false, 'type' => 'texto', 'source' => '', 'group' => 'cajero'],
        'cierre' => ['label' => 'Cierre', 'default' => true, 'filterable' => false, 'type' => 'texto', 'source' => '', 'group' => 'cajero'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = ['cajero' => 'Cajero'];

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
        return match ($key) {
            'cajero' => trim((string) (($fila->cajero->codigo ?? '').' — '.($fila->cajero->nombre ?? '')), ' —'),
            'tarea' => TesoreriaCrownTareaCatalogo::tarea((string) ($fila->tipo_tarea ?? '')),
            'estado' => $fila->cierre !== null
                ? 'Cerrado '.$fila->cierre->estadoTexto()
                : 'Abierto',
            'habilitacion' => (string) $fila->comprobantes->where('tipo', 'H')->count(),
            'rendicion' => (string) $fila->comprobantes->where('tipo', 'F')->count(),
            'cierre' => $fila->cierre !== null
                ? TesoreriaCrownNumeradorSupport::visible($fila->cierre->numero).' '.$fila->cierre->estadoTexto()
                : '',
            default => '',
        };
    }
}
