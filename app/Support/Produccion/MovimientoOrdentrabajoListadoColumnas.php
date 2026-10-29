<?php

declare(strict_types=1);

namespace App\Support\Produccion;

use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use App\Traits\Produccion\MovimientoOrdenTrabajoTrait;

/**
 * Columnas del workbench de movimientos de orden de trabajo.
 */
final class MovimientoOrdentrabajoListadoColumnas
{
    public const RECURSO = 'produccion.movimiento_ot';

    /**
     * @var array<string, array<string, mixed>>
     */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'movimientoordentrabajo.id',
            'attr' => 'id',
        ],
        'codigo' => [
            'label' => 'Orden trabajo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'ordentrabajo.codigo',
            'attr' => 'codigo',
        ],
        'tarea' => [
            'label' => 'Tarea',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tarea.nombre',
            'attr' => 'nombretarea',
        ],
        'operacion' => [
            'label' => 'Operación',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'operacion.nombre',
            'attr' => 'nombreoperacion',
        ],
        'empleado' => [
            'label' => 'Empleado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado.nombre',
            'attr' => 'nombreempleado',
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'movimientoordentrabajo.fecha',
            'attr' => 'fecha',
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'movimientoordentrabajo.estado',
            'attr' => 'estado',
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    /**
     * @return list<string>
     */
    public static function defaultsVisibles(): array
    {
        $keys = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (! empty($meta['default'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>|null  $solicitadas
     * @return list<string>
     */
    public static function normalizarVisibles(?array $solicitadas): array
    {
        $permitidas = array_keys(self::COLUMNAS);
        if ($solicitadas === null || $solicitadas === []) {
            return self::defaultsVisibles();
        }

        $keys = [];
        foreach ($solicitadas as $key) {
            $key = (string) $key;
            if (in_array($key, $permitidas, true) && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? self::defaultsVisibles() : $keys;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, attr: string}>
     */
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
                'attr' => $meta['attr'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, attr: string}>
     */
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
                'type' => $meta['type'] ?? 'texto',
                'column' => $column,
                'attr' => (string) ($meta['attr'] ?? $key),
            ];
        }

        return $out;
    }

    public static function valorCelda(object $data, string $key): string
    {
        $meta = self::COLUMNAS[$key] ?? null;
        if ($meta === null) {
            return '';
        }

        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? '';

        if ($key === 'codigo') {
            $codigo = trim((string) $valor);

            return $codigo === '' ? '' : str_pad($codigo, 4, '0', STR_PAD_LEFT);
        }
        if ($key === 'estado') {
            return MovimientoOrdenTrabajoTrait::$enumEstado[(string) $valor] ?? (string) $valor;
        }
        if (($meta['type'] ?? '') === 'fecha') {
            return ListadoQbeSupport::formatearFecha($valor);
        }

        return trim((string) $valor);
    }

    public static function etiquetaEstadoTarea(string $estado): string
    {
        return match ($estado) {
            MovimientoOrdentrabajoListadoFiltros::ESTADO_TERMINADA => 'Terminada',
            MovimientoOrdentrabajoListadoFiltros::ESTADO_FACTURADA => 'Facturada',
            MovimientoOrdentrabajoListadoFiltros::ESTADO_TODAS => 'Todas',
            default => 'En curso',
        };
    }
}
