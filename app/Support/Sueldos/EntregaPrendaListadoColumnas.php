<?php

declare(strict_types=1);

namespace App\Support\Sueldos;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo de columnas de la consulta de entregas de indumentaria
 * (pantalla, export, QBE, cortes y gráficos). Una fila por prenda entregada.
 */
final class EntregaPrendaListadoColumnas
{
    public const RECURSO = 'sueldos.entrega_prenda';

    /** @var list<array{key: string, column: string, label: string}> */
    public const MEDIDAS = [
        ['key' => 'cantidad', 'column' => 'entrega_prenda_articulo_sueldos.cantidad', 'label' => 'Cantidad'],
    ];

    public const GRUPO_EMPLEADO = 'empleado';

    public const GRUPO_ENTREGA = 'entrega';

    public const GRUPO_PRENDA = 'prenda';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_EMPLEADO => 'Empleado',
        self::GRUPO_ENTREGA => 'Entrega',
        self::GRUPO_PRENDA => 'Prenda',
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const COLUMNAS = [
        'entrega_id' => [
            'label' => 'ID entrega',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'e.id',
            'attr' => 'entrega_id',
            'group' => self::GRUPO_ENTREGA,
        ],
        'legajo' => [
            'label' => 'Legajo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'emp.legajo',
            'attr' => 'legajo',
            'group' => self::GRUPO_EMPLEADO,
            'sticky' => true,
        ],
        'empleado' => [
            'label' => 'Empleado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'emp.nombre',
            'attr' => 'empleado_nombre',
            'group' => self::GRUPO_EMPLEADO,
            'sticky' => true,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'em.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_EMPLEADO,
        ],
        'agrupamiento' => [
            'label' => 'Agrupamiento',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'ag.descripcion',
            'attr' => 'agrupamiento_nombre',
            'group' => self::GRUPO_EMPLEADO,
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'e.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_ENTREGA,
        ],
        'anio' => [
            'label' => 'Año',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'e.anio',
            'attr' => 'anio',
            'group' => self::GRUPO_ENTREGA,
        ],
        'prenda_codigo' => [
            'label' => 'Cód. prenda',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pr.codigo',
            'attr' => 'prenda_codigo',
            'group' => self::GRUPO_PRENDA,
        ],
        'prenda' => [
            'label' => 'Prenda',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pr.descripcion',
            'attr' => 'prenda',
            'group' => self::GRUPO_PRENDA,
        ],
        'color' => [
            'label' => 'Color',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'co.nombre',
            'attr' => 'color',
            'group' => self::GRUPO_PRENDA,
        ],
        'talle' => [
            'label' => 'Talle',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'ta.nombre',
            'attr' => 'talle',
            'group' => self::GRUPO_PRENDA,
        ],
        'sku' => [
            'label' => 'SKU',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'entrega_prenda_articulo_sueldos.sku',
            'attr' => 'sku',
            'group' => self::GRUPO_PRENDA,
        ],
        'cantidad' => [
            'label' => 'Cantidad',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'entrega_prenda_articulo_sueldos.cantidad',
            'attr' => 'cantidad',
            'group' => self::GRUPO_PRENDA,
        ],
        'vence_el' => [
            'label' => 'Vence',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'entrega_prenda_articulo_sueldos.vence_el',
            'attr' => 'vence_el',
            'group' => self::GRUPO_PRENDA,
        ],
        'observacion' => [
            'label' => 'Observación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'e.observacion',
            'attr' => 'observacion',
            'group' => self::GRUPO_ENTREGA,
        ],
        'tulegajo_estado' => [
            'label' => 'Estado TuLegajo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'e.tulegajo_estado',
            'attr' => 'tulegajo_estado',
            'group' => self::GRUPO_ENTREGA,
        ],
        'deposito' => [
            'label' => 'Depósito',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'dep.nombre',
            'attr' => 'deposito_nombre',
            'group' => self::GRUPO_ENTREGA,
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
            if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
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
            if ($key === 'cantidad') {
                // Unidades: sin ceros de relleno (3 -> "3", 1.5 -> "1,5").
                return rtrim(rtrim(number_format((float) $valor, 3, ',', '.'), '0'), ',');
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
        return ListadoCortesSupport::sqlAgrupacionDefault(
            $key,
            self::catalogoActivo()[$key] ?? []
        );
    }
}
