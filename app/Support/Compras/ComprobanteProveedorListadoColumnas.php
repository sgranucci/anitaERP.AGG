<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo único de columnas del workbench de comprobantes de proveedor
 * (pantalla, export, QBE y etiquetas).
 */
final class ComprobanteProveedorListadoColumnas
{
    public const RECURSO = 'compras.comprobante_proveedor';

    public const GRUPO_COMPROBANTE = 'comprobante';

    public const GRUPO_PARTES = 'partes';

    public const GRUPO_TESORERIA = 'tesoreria';

    public const GRUPO_CONTROL = 'control';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_COMPROBANTE => 'Comprobante',
        self::GRUPO_PARTES => 'Empresa / proveedor',
        self::GRUPO_TESORERIA => 'Tesorería / asiento',
        self::GRUPO_CONTROL => 'Control',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'comprobante_proveedor.id',
            'attr' => 'id',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_PARTES,
        ],
        'proveedor' => [
            'label' => 'Proveedor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.nombre',
            'attr' => 'nombre_proveedor_listado',
            'group' => self::GRUPO_PARTES,
        ],
        'tipo' => [
            'label' => 'Tipo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipotransaccion_compra.nombre',
            'attr' => 'nombre_tipo',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'numero' => [
            'label' => 'Número',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'numerocomprobante',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'letra' => [
            'label' => 'Letra',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.letra',
            'attr' => 'letra',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'sucursal' => [
            'label' => 'Sucursal',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'comprobante_proveedor.sucursal',
            'attr' => 'sucursal',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'numerocomprobante' => [
            'label' => 'Número comprobante',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'comprobante_proveedor.numerocomprobante',
            'attr' => 'numerocomprobante',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'oc' => [
            'label' => 'OC',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'ordencompra.numeroordencompra',
            'attr' => 'numero_oc',
            'group' => self::GRUPO_PARTES,
        ],
        'fechacomprobante' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'comprobante_proveedor.fechacomprobante',
            'attr' => 'fechacomprobante',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'fechaiva' => [
            'label' => 'F. IVA / contabiliz.',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'comprobante_proveedor.fechaiva',
            'attr' => 'fechaiva',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'total' => [
            'label' => 'Total',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'comprobante_proveedor.total',
            'attr' => 'total',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'fuera_pago' => [
            'label' => 'Fuera de pago',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'booleano',
            'source' => 'comprobante_proveedor.bloqueado_pago',
            'attr' => 'bloqueado_pago',
            'group' => self::GRUPO_COMPROBANTE,
            'alinea' => 'centro',
        ],
        'origen' => [
            'label' => 'Origen',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.origen_entrada',
            'attr' => 'origen_entrada',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'modo_carga' => [
            'label' => 'Modo carga',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.modo_carga',
            'attr' => 'modo_carga',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'tipo_tesoreria' => [
            'label' => 'Tipo tesorería',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.tipo_tesoreria',
            'attr' => 'tipo_tesoreria',
            'group' => self::GRUPO_TESORERIA,
        ],
        'moneda' => [
            'label' => 'Moneda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'moneda.abreviatura',
            'attr' => 'moneda_abreviatura',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'cotizacion' => [
            'label' => 'Cotización',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'comprobante_proveedor.cotizacion',
            'attr' => 'cotizacion',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'cuit' => [
            'label' => 'CUIT',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.identificacion_proveedor_cuit',
            'attr' => 'identificacion_proveedor_cuit',
            'group' => self::GRUPO_PARTES,
        ],
        'leyenda' => [
            'label' => 'Leyenda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.leyenda',
            'attr' => 'leyenda',
            'group' => self::GRUPO_COMPROBANTE,
        ],
        'numero_ie' => [
            'label' => 'Nº ingreso/egreso',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'caja_movimiento.numerotransaccion',
            'attr' => 'numero_ie',
            'group' => self::GRUPO_TESORERIA,
        ],
        'abreviatura_ie' => [
            'label' => 'Tipo ingreso/egreso',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipotransaccion_caja.abreviatura',
            'attr' => 'abreviatura_ie',
            'group' => self::GRUPO_TESORERIA,
        ],
        'numeroasiento' => [
            'label' => 'Nº asiento',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'asiento.numeroasiento',
            'attr' => 'numeroasiento_listado',
            'group' => self::GRUPO_TESORERIA,
        ],
        'fecha_alta' => [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'comprobante_proveedor.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_CONTROL,
        ],
        'fecha_modificacion' => [
            'label' => 'Fecha de modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'comprobante_proveedor.updated_at',
            'attr' => 'updated_at',
            'group' => self::GRUPO_CONTROL,
        ],
        'anita_sync' => [
            'label' => 'Sync Anita',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'comprobante_proveedor.anita_sync_estado',
            'attr' => 'anita_sync_estado',
            'group' => self::GRUPO_CONTROL,
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
     * @return array<string, array{label: string, type: string, column: string, group?: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (empty($meta['filterable']) || ($meta['source'] ?? '') === '') {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
                'group' => $meta['group'] ?? '',
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
        foreach (self::camposFiltrables() as $key => $meta) {
            $column = (string) $meta['column'];
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
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

    public static function numeroVisible(object $data): string
    {
        $letra = trim((string) ($data->letra ?? ''));
        $sucursal = trim((string) ($data->sucursal ?? ''));
        $numero = trim((string) ($data->numerocomprobante ?? ''));

        return $letra.$sucursal.'-'.$numero;
    }

    public static function movimientoIeVisible(object $data): string
    {
        $abrev = trim((string) ($data->abreviatura_ie ?? ''));
        $numero = trim((string) ($data->numero_ie ?? ''));
        if ($abrev === '' && $numero === '') {
            return '';
        }

        return trim($abrev.' '.$numero);
    }

    public static function valorCelda(object $data, string $key): string
    {
        if ($key === 'numero') {
            return self::numeroVisible($data);
        }
        if ($key === 'numero_ie') {
            return self::movimientoIeVisible($data);
        }
        if ($key === 'proveedor') {
            $nombre = trim((string) ($data->nombre_proveedor_listado ?? ''));
            if ($nombre !== '') {
                return $nombre;
            }

            return trim((string) ($data->proveedor_nombre_eventual ?? ''));
        }
        if ($key === 'tipo') {
            return trim((string) (($data->abreviatura_tipo ?? '').' '.($data->nombre_tipo ?? '')));
        }
        if ($key === 'estado') {
            $etiqueta = ComprobanteProveedorEstados::etiqueta($data->estado ?? null);
            if ((int) ($data->bloqueado_pago ?? 0) === 1) {
                $etiqueta = trim($etiqueta.' · Fuera de pago');
            }

            return $etiqueta;
        }
        if ($key === 'fuera_pago') {
            return (int) ($data->bloqueado_pago ?? 0) === 1 ? 'Sí' : '';
        }
        if ($key === 'origen') {
            return ComprobanteProveedorOrigenEntrada::etiqueta((string) ($data->origen_entrada ?? ''));
        }
        if ($key === 'modo_carga') {
            return ComprobanteProveedorModoCarga::etiqueta((string) ($data->modo_carga ?? ''));
        }

        $meta = self::catalogoActivo()[$key] ?? null;
        if ($meta === null) {
            return '';
        }
        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? '';
        $type = (string) ($meta['type'] ?? 'texto');
        if ($type === 'fecha') {
            if ($valor instanceof \DateTimeInterface) {
                $valor = $valor->format('Y-m-d H:i:s');
            }

            return ListadoQbeSupport::formatearFecha($valor);
        }
        if ($type === 'decimal') {
            if ($valor === null || $valor === '') {
                return '';
            }

            return number_format((float) $valor, 2, ',', '.');
        }

        return trim((string) $valor);
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        if ($key === 'proveedor') {
            $expr = SqlDialectSupport::coalesce(
                'proveedor.nombre',
                'comprobante_proveedor.proveedor_nombre_eventual'
            );

            return [
                'select' => [$expr.' as nombre_proveedor_listado'],
                'groupBy' => [$expr],
            ];
        }
        if ($key === 'tipo') {
            return [
                'select' => [
                    'tipotransaccion_compra.abreviatura as abreviatura_tipo',
                    'tipotransaccion_compra.nombre as nombre_tipo',
                ],
                'groupBy' => ['tipotransaccion_compra.abreviatura', 'tipotransaccion_compra.nombre'],
            ];
        }
        if ($key === 'numero_ie') {
            return [
                'select' => [
                    'tipotransaccion_caja.abreviatura as abreviatura_ie',
                    'caja_movimiento.numerotransaccion as numero_ie',
                ],
                'groupBy' => ['tipotransaccion_caja.abreviatura', 'caja_movimiento.numerotransaccion'],
            ];
        }

        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::catalogoActivo()[$key] ?? []);
    }
}
