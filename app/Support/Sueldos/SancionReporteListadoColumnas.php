<?php

declare(strict_types=1);

namespace App\Support\Sueldos;

use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo de columnas de la consulta de sanciones
 * (pantalla, export, QBE, cortes y gráficos).
 */
final class SancionReporteListadoColumnas
{
    public const RECURSO = 'sueldos.sancion_reporte';

    /** @var list<array{key: string, column: string, label: string}> */
    public const MEDIDAS = [
        ['key' => 'cant_dias', 'column' => 'empleado_sancion_sueldos.cant_dias', 'label' => 'Días'],
        ['key' => 'importe_perdida', 'column' => 'empleado_sancion_sueldos.importe_perdida', 'label' => 'Importe no cobrado'],
    ];

    public const GRUPO_EMPLEADO = 'empleado';

    public const GRUPO_SANCION = 'sancion';

    public const GRUPO_EXPEDIENTE = 'expediente';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_EMPLEADO => 'Empleado',
        self::GRUPO_SANCION => 'Sanción',
        self::GRUPO_EXPEDIENTE => 'Expediente',
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const COLUMNAS = [
        'legajo' => [
            'label' => 'Legajo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'empleado_sueldos.legajo',
            'attr' => 'legajo',
            'group' => self::GRUPO_EMPLEADO,
            'sticky' => true,
        ],
        'nombre' => [
            'label' => 'Apellido y nombre',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.nombre',
            'attr' => 'nombreempleado',
            'group' => self::GRUPO_EMPLEADO,
            'sticky' => true,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_EMPLEADO,
        ],
        'centrocosto' => [
            'label' => 'Centro de costo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'centrocosto.nombre',
            'attr' => 'nombrecentrocosto',
            'group' => self::GRUPO_EMPLEADO,
        ],
        'fecha_hecho' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sancion_sueldos.fecha_hecho',
            'attr' => 'fecha_hecho',
            'group' => self::GRUPO_SANCION,
        ],
        'tipo' => [
            'label' => 'Tipo de sanción',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipo_sancion_sueldos.nombre',
            'attr' => 'nombretipo',
            'group' => self::GRUPO_SANCION,
        ],
        'motivo' => [
            'label' => 'Motivo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'motivo_sancion_sueldos.nombre',
            'attr' => 'nombremotivo',
            'group' => self::GRUPO_SANCION,
        ],
        'cant_dias' => [
            'label' => 'Días',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'empleado_sancion_sueldos.cant_dias',
            'attr' => 'cant_dias',
            'group' => self::GRUPO_SANCION,
        ],
        'tipo_dias' => [
            'label' => 'Tipo de días',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sancion_sueldos.tipo_dias',
            'attr' => 'tipo_dias',
            'group' => self::GRUPO_SANCION,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sancion_sueldos.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_SANCION,
        ],
        'importe_perdida' => [
            'label' => 'Importe no cobrado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'empleado_sancion_sueldos.importe_perdida',
            'attr' => 'importe_perdida',
            'group' => self::GRUPO_SANCION,
        ],
        'fecha_desde' => [
            'label' => 'Desde suspensión',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sancion_sueldos.fecha_desde',
            'attr' => 'fecha_desde',
            'group' => self::GRUPO_EXPEDIENTE,
        ],
        'fecha_hasta' => [
            'label' => 'Hasta suspensión',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sancion_sueldos.fecha_hasta',
            'attr' => 'fecha_hasta',
            'group' => self::GRUPO_EXPEDIENTE,
        ],
        'fecha_notificacion' => [
            'label' => 'Notificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sancion_sueldos.fecha_notificacion',
            'attr' => 'fecha_notificacion',
            'group' => self::GRUPO_EXPEDIENTE,
        ],
        'fecha_recepcion' => [
            'label' => 'Recepción',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sancion_sueldos.fecha_recepcion',
            'attr' => 'fecha_recepcion',
            'group' => self::GRUPO_EXPEDIENTE,
        ],
        'nro_interno' => [
            'label' => 'Nº interno',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'empleado_sancion_sueldos.nro_interno',
            'attr' => 'nro_interno',
            'group' => self::GRUPO_EXPEDIENTE,
        ],
        'comentario' => [
            'label' => 'Comentario',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sancion_sueldos.comentario',
            'attr' => 'comentario',
            'group' => self::GRUPO_EXPEDIENTE,
        ],
    ];

    /**
     * @return list<string>
     */
    public static function defaultsVisibles(): array
    {
        $keys = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (! empty($meta['default'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    /**
     * @param  list<string>|null  $solicitadas
     * @return list<string>
     */
    public static function normalizarVisibles(?array $solicitadas): array
    {
        $permitidas = array_keys(self::catalogoActivo());
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
     * @return array<string, array{label: string, type: string, column: string, group?: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (empty($meta['filterable'])) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
                'group' => $meta['group'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            $column = (string) ($meta['source'] ?? '');
            if ($column === '' || ! \App\Support\Listado\ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'] ?? 'texto',
                'column' => $column,
            ];
        }

        return $out;
    }

    public static function valorCelda(object $data, string $key): string
    {
        $meta = self::catalogoActivo()[$key] ?? null;
        if ($meta === null) {
            return '';
        }

        if ($key === 'estado') {
            return EmpleadoSancionSupport::etiquetaEstado($data->estado ?? null);
        }

        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? '';
        $type = (string) ($meta['type'] ?? 'texto');
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y');
        }
        if ($type === 'fecha') {
            return ListadoQbeSupport::formatearFecha($valor);
        }
        if ($type === 'decimal') {
            if ($valor === null || $valor === '') {
                return '';
            }

            return number_format((float) $valor, 2, ',', '.');
        }

        return is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        return \App\Support\Listado\ListadoCortesSupport::sqlAgrupacionDefault(
            $key,
            self::catalogoActivo()[$key] ?? []
        );
    }
}
