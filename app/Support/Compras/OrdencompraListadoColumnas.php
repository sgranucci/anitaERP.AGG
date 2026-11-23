<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo único de columnas del workbench de órdenes de compra
 * (pantalla, QBE, cortes y etiquetas).
 */
final class OrdencompraListadoColumnas
{
    public const RECURSO = 'compras.ordencompra';

    public const GRUPO_ORDEN = 'orden';

    public const GRUPO_PARTES = 'partes';

    public const GRUPO_CIRCUITO = 'circuito';

    public const GRUPO_CONTROL = 'control';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_ORDEN => 'Orden',
        self::GRUPO_PARTES => 'Empresa / proveedor',
        self::GRUPO_CIRCUITO => 'Circuito',
        self::GRUPO_CONTROL => 'Control',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $catalogoCache = null;

    /**
     * Orden de instalación de la grilla. El diseñador puede cambiarlo.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        if (self::$catalogoCache !== null) {
            return self::$catalogoCache;
        }

        $columnas = [
            'numeroordencompra' => [
                'label' => 'Número',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'entero',
                'source' => 'ordencompra.numeroordencompra',
                'attr' => 'numeroordencompra',
                'group' => self::GRUPO_ORDEN,
            ],
            'nombreusuario' => [
                'label' => 'Solicitante',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'usuario.nombre',
                'attr' => 'nombreusuario',
                'group' => self::GRUPO_ORDEN,
            ],
            'fecha' => [
                'label' => 'Fecha',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'fecha',
                'source' => 'ordencompra.fecha',
                'attr' => 'fecha',
                'group' => self::GRUPO_ORDEN,
            ],
            'nombreempresa' => [
                'label' => EntornoEmpresaSupport::esCrown() ? 'Sala' : 'Empresa',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'empresa.nombre',
                'attr' => 'nombreempresa',
                'group' => self::GRUPO_PARTES,
            ],
            'nombrecentrocosto' => [
                'label' => 'Centro costo',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'centrocosto.nombre',
                'attr' => 'nombrecentrocosto',
                'group' => self::GRUPO_PARTES,
            ],
            'nombreproveedor' => [
                'label' => 'Proveedor',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'proveedor.nombre',
                'attr' => 'nombreproveedor',
                'group' => self::GRUPO_PARTES,
            ],
            'nombresector' => [
                'label' => 'Sector',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'sector_legajocompra.nombre',
                'attr' => 'nombresector',
                'group' => self::GRUPO_PARTES,
            ],
            'estadoordencompra' => [
                'label' => 'Estado',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'ordencompra.estadoordencompra',
                'attr' => 'estadoordencompra',
                'group' => self::GRUPO_ORDEN,
            ],
            'recepcion_numero' => [
                'label' => 'Recepción',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'oc_recep.recepcion_numero',
                'attr' => 'recepcion_numero',
                'group' => self::GRUPO_CIRCUITO,
                'enlace' => 'recepcion_proveedor',
                'enlace_ids' => 'recepcion_ids',
            ],
            'factura_numero' => [
                'label' => 'Factura',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'oc_fac.factura_numero',
                'attr' => 'factura_numero',
                'group' => self::GRUPO_CIRCUITO,
                'enlace' => 'comprobante_proveedor',
                'enlace_ids' => 'factura_ids',
            ],
            'pago_numero' => [
                'label' => 'Orden de pago',
                'default' => true,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'oc_pago.pago_numero',
                'attr' => 'pago_numero',
                'group' => self::GRUPO_CIRCUITO,
                'enlace' => 'pagoproveedor',
                'enlace_ids' => 'pago_ids',
            ],
            'monto_lineas' => [
                'label' => 'Σ ítems',
                'default' => true,
                'export' => true,
                'filterable' => false,
                'type' => 'decimal',
                'source' => '',
                'attr' => 'monto_lineas',
                'group' => self::GRUPO_ORDEN,
                'alinea' => 'derecha',
            ],
            'id' => [
                'label' => 'ID',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'entero',
                'source' => 'ordencompra.id',
                'attr' => 'id',
                'group' => self::GRUPO_ORDEN,
            ],
            'fechaentrega' => [
                'label' => 'Fecha entrega',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'fecha',
                'source' => 'ordencompra.fechaentrega',
                'attr' => 'fechaentrega',
                'group' => self::GRUPO_ORDEN,
            ],
            'codigocentrocosto' => [
                'label' => 'Cód. centro costo',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'centrocosto.codigo',
                'attr' => 'codigocentrocosto',
                'group' => self::GRUPO_PARTES,
            ],
            'codigoproveedor' => [
                'label' => 'Cód. proveedor',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'proveedor.codigo',
                'attr' => 'codigoproveedor',
                'group' => self::GRUPO_PARTES,
            ],
            'nombrecondicioncompra' => [
                'label' => 'Condición compra',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'condicioncompra.nombre',
                'attr' => 'nombrecondicioncompra',
                'group' => self::GRUPO_ORDEN,
            ],
            'numerorequisicion' => [
                'label' => 'Nº requisición',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'entero',
                'source' => 'requisicion.numerorequisicion',
                'attr' => 'numerorequisicion',
                'group' => self::GRUPO_CIRCUITO,
            ],
            'tratamiento' => [
                'label' => 'Tratamiento',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'ordencompra.tratamiento',
                'attr' => 'tratamiento',
                'group' => self::GRUPO_ORDEN,
            ],
            'motivotratamiento' => [
                'label' => 'Motivo tratamiento',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'requisicion.motivotratamiento',
                'attr' => 'motivotratamiento',
                'group' => self::GRUPO_CIRCUITO,
            ],
            'contrataciondirecta' => [
                'label' => 'Contratación directa',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'requisicion.contrataciondirecta',
                'attr' => 'contrataciondirecta',
                'group' => self::GRUPO_CIRCUITO,
            ],
            'comentario' => [
                'label' => 'Comentario',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'ordencompra.comentario',
                'attr' => 'comentario',
                'group' => self::GRUPO_ORDEN,
            ],
            'detalle' => [
                'label' => 'Detalle cabecera',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'ordencompra.detalle',
                'attr' => 'detalle',
                'group' => self::GRUPO_ORDEN,
            ],
        ];

        if (Schema::hasColumn('requisicion', 'nroinscripcion')) {
            $columnas['nroinscripcion'] = [
                'label' => 'Nro inscripción',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'requisicion.nroinscripcion',
                'attr' => 'nroinscripcion',
                'group' => self::GRUPO_CIRCUITO,
            ];
        }

        if (self::tieneSala()) {
            $columnas['codigosala'] = [
                'label' => 'Cód. sala',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'sala.codigo',
                'attr' => 'codigosala',
                'group' => self::GRUPO_PARTES,
            ];
            $columnas['nombresala'] = [
                'label' => 'Nombre de sala',
                'default' => false,
                'export' => true,
                'filterable' => true,
                'type' => 'texto',
                'source' => 'sala.nombre',
                'attr' => 'nombresala',
                'group' => self::GRUPO_PARTES,
            ];
        }

        $columnas['fecha_alta'] = [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'ordencompra.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_CONTROL,
        ];
        $columnas['fecha_modificacion'] = [
            'label' => 'Fecha de modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'ordencompra.updated_at',
            'attr' => 'updated_at',
            'group' => self::GRUPO_CONTROL,
        ];

        return self::$catalogoCache = $columnas;
    }

    public static function tieneSala(): bool
    {
        return EntornoEmpresaSupport::esCrown() && Schema::hasColumn('ordencompra', 'sala_id');
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

    /**
     * Crown muestra la sala en la columna de empresa.
     */
    public static function empresaVisible(object $data): string
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return trim((string) ($data->nombreempresa ?? ''));
        }

        $codigo = trim((string) ($data->codigosala ?? ''));
        if ($codigo === '') {
            return '';
        }

        return trim($codigo.' — '.trim((string) ($data->nombresala ?? '')), ' —');
    }

    public static function valorCelda(object $data, string $key): string
    {
        if ($key === 'nombreempresa') {
            return self::empresaVisible($data);
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
        if ($key === 'nombreempresa' && self::tieneSala()) {
            return [
                'select' => ['sala.codigo as codigosala', 'sala.nombre as nombresala'],
                'groupBy' => ['sala.codigo', 'sala.nombre'],
            ];
        }

        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::catalogoActivo()[$key] ?? []);
    }
}
