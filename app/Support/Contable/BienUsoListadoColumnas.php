<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Contable\BienUso;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo único de columnas del workbench de bienes de uso
 * (pantalla, export, QBE y etiquetas).
 *
 * Incluye el schema ampliado Anita; catalogoActivo() solo expone columnas
 * cuya física ya existe en bien_uso (o su FK de join).
 */
final class BienUsoListadoColumnas
{
    public const RECURSO = 'contable.bien_uso';

    /** @var list<array{key: string, column: string, label: string}> */
    public const MEDIDAS = [
        ['key' => 'valor_origen', 'column' => 'bien_uso.valor_origen', 'label' => 'Valor origen'],
        ['key' => 'valor_baja', 'column' => 'bien_uso.valor_baja', 'label' => 'Valor baja'],
        ['key' => 'monto_factura', 'column' => 'bien_uso.monto_factura', 'label' => 'Monto factura'],
    ];

    public const GRUPO_IDENTIFICACION = 'identificacion';

    public const GRUPO_CLASIFICACION = 'clasificacion';

    public const GRUPO_CONTABLE = 'contable';

    public const GRUPO_FACTURA = 'factura';

    public const GRUPO_TECNOLOGIA = 'tecnologia';

    public const GRUPO_ESTADO = 'estado';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_IDENTIFICACION => 'Identificación',
        self::GRUPO_CLASIFICACION => 'Clasificación',
        self::GRUPO_CONTABLE => 'Contable / amortización',
        self::GRUPO_FACTURA => 'Factura / compra',
        self::GRUPO_TECNOLOGIA => 'Tecnología / inventario',
        self::GRUPO_ESTADO => 'Estado / control',
    ];

    /** @var array<string, string> */
    public const LABELS_FL_AMORT = [
        '0' => 'Unidades',
        '1' => 'Año',
        '2' => 'Mes',
        '3' => 'Sin amortización',
        '4' => 'Real mensual',
    ];

    /**
     * FK en bien_uso requerida para columnas con source de otra tabla.
     *
     * @var array<string, string>
     */
    private const JOIN_FK = [
        'empresa.nombre' => 'empresa_id',
        'centrocosto.codigo' => 'centrocosto_id',
        'centrocosto.nombre' => 'centrocosto_id',
        'rubro.nombre' => 'rubro_id',
        'subrubro.nombre' => 'subrubro_id',
        'cta_origen.codigo' => 'cuentacontable_origen_id',
        'cta_amort.codigo' => 'cuentacontable_amort_id',
        'cta_contrap.codigo' => 'cuentacontable_contrap_id',
        'moneda.nombre' => 'moneda_id',
        'proveedor.nombre' => 'proveedor_id',
        'bien_reemplazo.codigo_bien' => 'bien_reemplazo_id',
        'bien_reemplazo.descripcion' => 'bien_reemplazo_id',
    ];

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
            'source' => 'bien_uso.id',
            'attr' => 'id',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'codigo_bien' => [
            'label' => 'Cód. bien',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'bien_uso.codigo_bien',
            'attr' => 'codigo_bien',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'descripcion' => [
            'label' => 'Descripción',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.descripcion',
            'attr' => 'descripcion',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'codigo_inventario' => [
            'label' => 'Cód. inventario',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'bien_uso.codigo_inventario',
            'attr' => 'codigo_inventario',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'uid' => [
            'label' => 'UID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.uid',
            'attr' => 'uid',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'rubro' => [
            'label' => 'Rubro',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'rubro.nombre',
            'attr' => 'nombrerubro',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'subrubro' => [
            'label' => 'Subrubro',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'subrubro.nombre',
            'attr' => 'nombresubrubro',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'tipo_bien' => [
            'label' => 'Tipo de bien',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.tipo_bien',
            'attr' => 'tipo_bien',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'centrocosto' => [
            'label' => 'Centro de costo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'centrocosto.codigo',
            'attr' => 'codigocentrocosto',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'centrocosto_nombre' => [
            'label' => 'Nombre CC',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'centrocosto.nombre',
            'attr' => 'nombrecentrocosto',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'fecha_alta' => [
            'label' => 'Fecha alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'bien_uso.fecha_alta',
            'attr' => 'fecha_alta',
            'group' => self::GRUPO_CONTABLE,
        ],
        'fecha_compra' => [
            'label' => 'Fecha compra',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'bien_uso.fecha_compra',
            'attr' => 'fecha_compra',
            'group' => self::GRUPO_CONTABLE,
        ],
        'fecha_baja' => [
            'label' => 'Fecha baja',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'bien_uso.fecha_baja',
            'attr' => 'fecha_baja',
            'group' => self::GRUPO_CONTABLE,
        ],
        'vida_util' => [
            'label' => 'Vida útil',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'bien_uso.vida_util',
            'attr' => 'vida_util',
            'group' => self::GRUPO_CONTABLE,
        ],
        'valor_origen' => [
            'label' => 'Valor origen',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'bien_uso.valor_origen',
            'attr' => 'valor_origen',
            'group' => self::GRUPO_CONTABLE,
        ],
        'valor_baja' => [
            'label' => 'Valor baja',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'bien_uso.valor_baja',
            'attr' => 'valor_baja',
            'group' => self::GRUPO_CONTABLE,
        ],
        'cuenta_origen' => [
            'label' => 'Cta. origen',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cta_origen.codigo',
            'attr' => 'codigo_cuenta_origen',
            'group' => self::GRUPO_CONTABLE,
        ],
        'cuenta_amort' => [
            'label' => 'Cta. amortización',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cta_amort.codigo',
            'attr' => 'codigo_cuenta_amort',
            'group' => self::GRUPO_CONTABLE,
        ],
        'cuenta_contrap' => [
            'label' => 'Cta. contrapartida',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cta_contrap.codigo',
            'attr' => 'codigo_cuenta_contrap',
            'group' => self::GRUPO_CONTABLE,
        ],
        'moneda' => [
            'label' => 'Moneda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'moneda.nombre',
            'attr' => 'nombremoneda',
            'group' => self::GRUPO_CONTABLE,
        ],
        'fl_amort_unid' => [
            'label' => 'Amortización',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.fl_amort_unid',
            'attr' => 'fl_amort_unid',
            'group' => self::GRUPO_CONTABLE,
        ],
        'bien_reemplazo' => [
            'label' => 'Bien reemplazo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_reemplazo.descripcion',
            'attr' => 'descripcion_bien_reemplazo',
            'group' => self::GRUPO_CONTABLE,
        ],
        'marca' => [
            'label' => 'Marca',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.marca',
            'attr' => 'marca',
            'group' => self::GRUPO_FACTURA,
        ],
        'numero_serie' => [
            'label' => 'Nº serie',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.numero_serie',
            'attr' => 'numero_serie',
            'group' => self::GRUPO_FACTURA,
        ],
        'proveedor' => [
            'label' => 'Proveedor',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.nombre',
            'attr' => 'nombreproveedor',
            'group' => self::GRUPO_FACTURA,
        ],
        'tipo_factura' => [
            'label' => 'Tipo factura',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.tipo_factura',
            'attr' => 'tipo_factura',
            'group' => self::GRUPO_FACTURA,
        ],
        'letra_factura' => [
            'label' => 'Letra',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.letra_factura',
            'attr' => 'letra_factura',
            'group' => self::GRUPO_FACTURA,
        ],
        'sucursal_factura' => [
            'label' => 'Sucursal factura',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'bien_uso.sucursal_factura',
            'attr' => 'sucursal_factura',
            'group' => self::GRUPO_FACTURA,
        ],
        'nro_factura' => [
            'label' => 'Nro. factura',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'bien_uso.nro_factura',
            'attr' => 'nro_factura',
            'group' => self::GRUPO_FACTURA,
        ],
        'monto_factura' => [
            'label' => 'Monto factura',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'bien_uso.monto_factura',
            'attr' => 'monto_factura',
            'group' => self::GRUPO_FACTURA,
        ],
        'fecha_factura' => [
            'label' => 'Fecha factura',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'bien_uso.fecha_factura',
            'attr' => 'fecha_factura',
            'group' => self::GRUPO_FACTURA,
        ],
        'hostname' => [
            'label' => 'Hostname',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.hostname',
            'attr' => 'hostname',
            'group' => self::GRUPO_TECNOLOGIA,
        ],
        'ip' => [
            'label' => 'IP',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.ip',
            'attr' => 'ip',
            'group' => self::GRUPO_TECNOLOGIA,
        ],
        'modelo' => [
            'label' => 'Modelo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.modelo',
            'attr' => 'modelo',
            'group' => self::GRUPO_TECNOLOGIA,
        ],
        'vendor' => [
            'label' => 'Vendor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.vendor',
            'attr' => 'vendor',
            'group' => self::GRUPO_TECNOLOGIA,
        ],
        'tema' => [
            'label' => 'Tema / juego',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.tema',
            'attr' => 'tema',
            'group' => self::GRUPO_TECNOLOGIA,
        ],
        'observaciones' => [
            'label' => 'Observaciones',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'bien_uso.observaciones',
            'attr' => 'observaciones',
            'group' => self::GRUPO_TECNOLOGIA,
        ],
        'fecha_registro' => [
            'label' => 'Fecha de registro',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'bien_uso.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_ESTADO,
        ],
        'fecha_modificacion' => [
            'label' => 'Fecha de modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'bien_uso.updated_at',
            'attr' => 'updated_at',
            'group' => self::GRUPO_ESTADO,
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
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (self::fuenteDisponible((string) ($meta['source'] ?? ''))) {
                $out[$key] = $meta;
            }
        }

        return $out;
    }

    /**
     * @return list<array{key: string, column: string, label: string}>
     */
    public static function medidasActivas(): array
    {
        $out = [];
        foreach (self::MEDIDAS as $medida) {
            if (self::fuenteDisponible($medida['column'])) {
                $out[] = $medida;
            }
        }

        return $out;
    }

    private static function fuenteDisponible(string $source): bool
    {
        if ($source === '' || ! Schema::hasTable('bien_uso')) {
            return false;
        }

        if (str_starts_with($source, 'bien_uso.')) {
            $col = substr($source, strlen('bien_uso.'));

            return Schema::hasColumn('bien_uso', $col);
        }

        $fk = self::JOIN_FK[$source] ?? null;
        if ($fk === null) {
            return false;
        }

        return Schema::hasColumn('bien_uso', $fk);
    }

    /**
     * @param  list<string>|null  $solicitadas
     * @return list<string>
     */
    public static function normalizarVisibles(?array $solicitadas): array
    {
        $catalogo = self::catalogoActivo();
        $permitidas = array_keys($catalogo);
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

        if ($key === 'estado') {
            return BienUso::labelEstado($data->estado ?? null);
        }
        if ($key === 'tipo_bien') {
            return BienUso::labelTipoBien($data->tipo_bien ?? null);
        }
        if ($key === 'fl_amort_unid') {
            $cod = (string) ($data->fl_amort_unid ?? '');

            return self::LABELS_FL_AMORT[$cod] ?? $cod;
        }
        if ($key === 'centrocosto') {
            $cod = trim((string) ($data->codigocentrocosto ?? ''));
            $nom = trim((string) ($data->nombrecentrocosto ?? ''));
            if ($cod === '' && $nom === '') {
                return '';
            }
            if ($nom === '') {
                return $cod;
            }
            if ($cod === '') {
                return $nom;
            }

            return $cod.' — '.$nom;
        }

        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? '';
        $type = (string) ($meta['type'] ?? 'texto');
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
        return ListadoCortesSupport::sqlAgrupacionDefault(
            $key,
            self::catalogoActivo()[$key] ?? []
        );
    }
}
