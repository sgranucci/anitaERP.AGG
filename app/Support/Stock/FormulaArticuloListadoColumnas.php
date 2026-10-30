<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo del workbench de fórmulas de artículos (pantalla, QBE, PDF y Excel).
 */
final class FormulaArticuloListadoColumnas
{
    public const RECURSO = 'stock.formula_articulo';

    /** @var list<array{key: string, column: string, label: string}> */
    public const MEDIDAS = [
        ['key' => 'cantidadunidad', 'column' => 'formula_articulo.cantidadunidad', 'label' => 'Cant. unidad'],
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const COLUMNAS = [
        'numero' => [
            'label' => 'Número',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'formula_articulo.codigo',
            'attr' => 'codigo',
        ],
        'id' => [
            'label' => 'ID',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'formula_articulo.id',
            'attr' => 'id',
        ],
        'sku' => [
            'label' => 'SKU',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'art_padre.sku',
            'attr' => 'articulo_sku',
        ],
        'articulo' => [
            'label' => 'Artículo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'art_padre.descripcion',
            'attr' => 'articulo_descripcion',
        ],
        'articulo_id' => [
            'label' => 'ID artículo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'formula_articulo.articulo_id',
            'attr' => 'articulo_id',
        ],
        'cantidadunidad' => [
            'label' => 'Cant. unidad',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'formula_articulo.cantidadunidad',
            'attr' => 'cantidadunidad',
            'alinea' => 'derecha',
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'formula_articulo.estado',
            'attr' => 'estado',
        ],
        'detalle' => [
            'label' => 'Detalle',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'formula_articulo.detalle',
            'attr' => 'detalle',
        ],
        'items' => [
            'label' => 'Ítems',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'items',
        ],
        'usuario' => [
            'label' => 'Usuario alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'usuario.nombre',
            'attr' => 'nombreusuario',
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
        ],
        'fecha_alta' => [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'formula_articulo.created_at',
            'attr' => 'created_at',
        ],
        'fecha_modificacion' => [
            'label' => 'Fecha de modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'formula_articulo.updated_at',
            'attr' => 'updated_at',
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        $cols = self::COLUMNAS;
        $mostrarCodigo = FormulaArticuloNumero::mostrarCodigo();
        $cols['id']['default'] = ! $mostrarCodigo;
        $cols['numero']['label'] = $mostrarCodigo ? 'Número' : 'Código';

        return $cols;
    }

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
     * @return array<string, array{label: string, type: string, column: string, attr: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (empty($meta['filterable'])) {
                continue;
            }
            $out[$key] = [
                'label' => (string) $meta['label'],
                'type' => (string) $meta['type'],
                'column' => (string) $meta['source'],
                'attr' => (string) ($meta['attr'] ?? $key),
            ];
        }

        $out['item_sku'] = [
            'label' => 'Ítem SKU',
            'type' => 'texto',
            'column' => 'art_item.sku',
            'attr' => 'item_sku',
        ];
        $out['item_descripcion'] = [
            'label' => 'Ítem descripción',
            'type' => 'texto',
            'column' => 'art_item.descripcion',
            'attr' => 'item_descripcion',
        ];

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, attr: string}>
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
                'label' => (string) $meta['label'],
                'type' => (string) ($meta['type'] ?? 'texto'),
                'column' => $column,
                'attr' => (string) ($meta['attr'] ?? $key),
            ];
        }

        return $out;
    }

    public static function valorCelda(object $data, string $key): string
    {
        $catalogo = self::catalogoActivo();
        $meta = $catalogo[$key] ?? null;
        if ($meta === null) {
            return '';
        }

        if ($key === 'numero') {
            return FormulaArticuloNumero::paraFormula($data);
        }
        if ($key === 'items') {
            return self::textoItems($data);
        }
        if ($key === 'cantidadunidad') {
            return number_format((float) ($data->cantidadunidad ?? 0), 2, ',', '.');
        }

        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? '';
        if (($meta['type'] ?? '') === 'fecha') {
            return ListadoQbeSupport::formatearFecha($valor);
        }

        return trim((string) $valor);
    }

    public static function textoItems(object $data): string
    {
        $lineas = [];
        foreach ($data->formula_articulo_hijos ?? [] as $hijo) {
            $sku = trim((string) ($hijo->articulos->sku ?? ''));
            $desc = trim((string) ($hijo->articulos->descripcion ?? ''));
            if ($sku === '' && $desc === '' && ! empty($hijo->formula_hija_id)) {
                $sub = $hijo->formula_hija ?? null;
                $num = $sub ? FormulaArticuloNumero::paraFormula($sub) : (string) $hijo->formula_hija_id;
                $sku = 'Fórmula '.$num;
                $desc = trim((string) (optional(optional($sub)->articulos)->descripcion ?? ''));
            }
            $texto = trim($sku.($desc !== '' ? ' — '.$desc : ''));
            if ($texto !== '') {
                $lineas[] = $texto;
            }
            if (count($lineas) >= 12) {
                $lineas[] = '…';
                break;
            }
        }

        return implode(' | ', $lineas);
    }
}
