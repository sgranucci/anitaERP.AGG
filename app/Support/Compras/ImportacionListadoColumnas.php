<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use DateTimeInterface;

/**
 * Catálogo de columnas del workbench de carpetas de importación.
 */
final class ImportacionListadoColumnas
{
    public const RECURSO = 'compras.importacion';

    public const GRUPO_CABECERA = 'cabecera';

    public const GRUPO_IMPORTES = 'importes';

    public const GRUPO_FECHAS = 'fechas';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_CABECERA => 'Cabecera',
        self::GRUPO_IMPORTES => 'Importes',
        self::GRUPO_FECHAS => 'Fechas',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'importacion.id',
            'attr' => 'id',
            'group' => self::GRUPO_CABECERA,
            'sticky' => true,
        ],
        'codigo' => [
            'label' => 'Código',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'importacion.codigo',
            'attr' => 'codigo',
            'group' => self::GRUPO_CABECERA,
            'sticky' => true,
            'ancho' => 118,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_CABECERA,
            'ancho' => 90,
        ],
        'proveedor' => [
            'label' => 'Proveedor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.nombre',
            'attr' => 'nombreproveedor',
            'group' => self::GRUPO_CABECERA,
            'ancho' => 170,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'importacion.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_CABECERA,
            'ancho' => 100,
        ],
        'moneda' => [
            'label' => 'Mon.',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'moneda.abreviatura',
            'attr' => 'abreviaturamoneda',
            'group' => self::GRUPO_CABECERA,
            'ancho' => 52,
        ],
        'fob_estimado' => [
            'label' => 'FOB',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'importacion.fob_estimado',
            'attr' => 'fob_estimado',
            'group' => self::GRUPO_IMPORTES,
            'medida' => true,
            'ancho' => 88,
        ],
        'gastos_estimados' => [
            'label' => 'Gastos est.',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'importacion.gastos_estimados',
            'attr' => 'gastos_estimados',
            'group' => self::GRUPO_IMPORTES,
            'medida' => true,
            'ancho' => 88,
        ],
        'gastos_reales' => [
            'label' => 'Gastos',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'importacion.gastos_reales',
            'attr' => 'gastos_reales',
            'group' => self::GRUPO_IMPORTES,
            'medida' => true,
            'ancho' => 88,
        ],
        'landed_cost' => [
            // En el form se muestra como Total (landed) bajo «Totales».
            'label' => 'Total',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'importacion.landed_cost',
            'attr' => 'landed_cost',
            'group' => self::GRUPO_IMPORTES,
            'medida' => true,
            'ancho' => 92,
        ],
        'coeficiente_embarque' => [
            'label' => 'Coef. embarque',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'decimales' => 6,
            'source' => 'importacion.coeficiente_embarque',
            'attr' => 'coeficiente_embarque',
            'group' => self::GRUPO_IMPORTES,
        ],
        'fecha_apertura' => [
            'label' => 'Apertura',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'importacion.fecha_apertura',
            'attr' => 'fecha_apertura',
            'group' => self::GRUPO_FECHAS,
            'ancho' => 78,
        ],
        'fecha_eta' => [
            'label' => 'ETA',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'importacion.fecha_eta',
            'attr' => 'fecha_eta',
            'group' => self::GRUPO_FECHAS,
            'ancho' => 78,
        ],
        'fecha_nacionalizacion' => [
            'label' => 'Nacionalización',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'importacion.fecha_nacionalizacion',
            'attr' => 'fecha_nacionalizacion',
            'group' => self::GRUPO_FECHAS,
        ],
        'fecha_alta' => [
            'label' => 'Alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'importacion.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_FECHAS,
        ],
        'fecha_modificacion' => [
            'label' => 'Modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'importacion.updated_at',
            'attr' => 'updated_at',
            'group' => self::GRUPO_FECHAS,
        ],
    ];

    /**
     * Catálogo activo del workbench. Los importes se alinean a la derecha (el diseñador
     * respeta esa alineación del catálogo).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        $out = self::COLUMNAS;
        foreach ($out as $key => $meta) {
            if (($meta['type'] ?? '') === 'decimal' && empty($meta['alinea'])) {
                $out[$key]['alinea'] = 'derecha';
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function keysDefault(): array
    {
        $keys = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (! empty($meta['default'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @return list<string> */
    public static function defaultsVisibles(): array
    {
        return self::keysDefault();
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

    /** @return array<string, string> */
    public static function medidasCortes(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (! empty($meta['medida'])) {
                $out[$key] = (string) $meta['source'];
            }
        }

        return $out;
    }

    /**
     * Medidas para ListadoCortesSupport::calcular (suma por corte).
     *
     * @param  array<string, string>  $etiquetas
     * @return list<array{key: string, column: string, label: string}>
     */
    public static function medidasParaCortes(array $etiquetas = []): array
    {
        $out = [];
        foreach (self::medidasCortes() as $key => $column) {
            $out[] = [
                'key' => $key,
                'column' => $column,
                'label' => $etiquetas[$key] ?? (string) (self::COLUMNAS[$key]['label'] ?? $key),
            ];
        }

        return $out;
    }

    /** @return array<string, string> labels para filtro de campo */
    public static function camposFiltro(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (! empty($meta['filterable'])) {
                $out[$key] = (string) $meta['label'];
            }
        }

        return $out;
    }

    /**
     * Campos para criterios QBE (fuente SQL = `source` del catálogo).
     *
     * @return array<string, array{label: string, type: string, column: string, group: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (empty($meta['filterable'])) {
                continue;
            }
            $out[$key] = [
                'label' => (string) $meta['label'],
                'type' => (string) $meta['type'],
                'column' => (string) $meta['source'],
                'group' => (string) ($meta['group'] ?? self::GRUPO_CABECERA),
            ];
        }

        return $out;
    }

    /**
     * Campos para ORDER BY / agrupar.
     *
     * @return array<string, array{label: string, type: string, column: string}>
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
                'label' => (string) $meta['label'],
                'type' => (string) ($meta['type'] ?? 'texto'),
                'column' => $column,
            ];
        }

        return $out;
    }

    /**
     * Texto de la celda (pantalla, PDF, cortes). Los importes salen con formato es-AR.
     */
    public static function valorCelda(object $data, string $key): string
    {
        $meta = self::COLUMNAS[$key] ?? null;
        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? null;

        if ($key === 'estado') {
            return ImportacionEstados::etiqueta((string) $valor);
        }

        $tipo = (string) ($meta['type'] ?? 'texto');
        if ($tipo === 'fecha') {
            if ($valor instanceof DateTimeInterface) {
                $valor = $valor->format('Y-m-d');
            }

            return ListadoQbeSupport::formatearFecha($valor);
        }
        if ($tipo === 'decimal') {
            if ($valor === null || $valor === '') {
                return '';
            }

            return number_format((float) $valor, (int) ($meta['decimales'] ?? 2), ',', '.');
        }

        if ($valor === null) {
            return '';
        }

        return is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;
    }

    /**
     * Valor numérico crudo para Excel/CSV (importes sin formato de texto).
     */
    public static function valorNumerico(object $data, string $key): ?float
    {
        $attr = (string) (self::COLUMNAS[$key]['attr'] ?? $key);
        $valor = $data->{$attr} ?? null;
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) $valor;
    }

    /**
     * Expresiones SQL para cortes (GROUP BY alineado con valorCelda).
     *
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::COLUMNAS[$key] ?? []);
    }
}
