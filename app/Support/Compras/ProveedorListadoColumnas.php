<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo único de columnas del workbench de proveedores
 * (pantalla, export, QBE y etiquetas).
 */
final class ProveedorListadoColumnas
{
    public const RECURSO = 'compras.proveedor';

    public const GRUPO_IDENTIFICACION = 'identificacion';

    public const GRUPO_CONTACTO = 'contacto';

    public const GRUPO_DOMICILIO = 'domicilio';

    public const GRUPO_FISCAL = 'fiscal';

    public const GRUPO_COMERCIAL = 'comercial';

    public const GRUPO_BANCARIO = 'bancario';

    public const GRUPO_ESTADO = 'estado';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_IDENTIFICACION => 'Identificación',
        self::GRUPO_CONTACTO => 'Contacto',
        self::GRUPO_DOMICILIO => 'Domicilio',
        self::GRUPO_FISCAL => 'Fiscal',
        self::GRUPO_COMERCIAL => 'Comercial',
        self::GRUPO_BANCARIO => 'Bancario',
        self::GRUPO_ESTADO => 'Estado / control',
    ];

    /**
     * @var array<string, array{
     *   label: string,
     *   default: bool,
     *   export: bool,
     *   filterable: bool,
     *   type: string,
     *   source: string,
     *   attr: string,
     *   group: string,
     *   sticky?: bool,
     *   formapago?: bool
     * }>
     */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'proveedor.id',
            'attr' => 'id',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'codigo' => [
            'label' => 'Código',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.codigo',
            'attr' => 'codigo',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'nombre' => [
            'label' => 'Nombre',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.nombre',
            'attr' => 'nombre',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'fantasia' => [
            'label' => 'Nombre de fantasía',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.fantasia',
            'attr' => 'fantasia',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'numerodocumento' => [
            'label' => 'C.U.I.T.',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'alinea' => 'izquierda',
            'source' => 'proveedor.nroinscripcion',
            'attr' => 'numerodocumento',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'tipoempresa' => [
            'label' => 'Tipo de empresa',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipoempresa.nombre',
            'attr' => 'nombretipoempresa',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'contacto' => [
            'label' => 'Contacto',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.contacto',
            'attr' => 'contacto',
            'group' => self::GRUPO_CONTACTO,
        ],
        'email' => [
            'label' => 'Email',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.email',
            'attr' => 'email',
            'group' => self::GRUPO_CONTACTO,
        ],
        'emailoc' => [
            'label' => 'Email OC',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.emailoc',
            'attr' => 'emailoc',
            'group' => self::GRUPO_CONTACTO,
        ],
        'telefono' => [
            'label' => 'Teléfono',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.telefono',
            'attr' => 'telefono',
            'group' => self::GRUPO_CONTACTO,
        ],
        'urlweb' => [
            'label' => 'Sitio web',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.urlweb',
            'attr' => 'urlweb',
            'group' => self::GRUPO_CONTACTO,
        ],
        'domicilio' => [
            'label' => 'Domicilio',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.domicilio',
            'attr' => 'domicilio',
            'group' => self::GRUPO_DOMICILIO,
        ],
        'localidad' => [
            'label' => 'Localidad',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'localidad.nombre',
            'attr' => 'nombrelocalidad',
            'group' => self::GRUPO_DOMICILIO,
        ],
        'provincia' => [
            'label' => 'Provincia',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'provincia.nombre',
            'attr' => 'nombreprovincia',
            'group' => self::GRUPO_DOMICILIO,
        ],
        'pais' => [
            'label' => 'País',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pais.nombre',
            'attr' => 'nombrepais',
            'group' => self::GRUPO_DOMICILIO,
        ],
        'codigopostal' => [
            'label' => 'Código postal',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.codigopostal',
            'attr' => 'codigopostal',
            'group' => self::GRUPO_DOMICILIO,
        ],
        'condicioniva' => [
            'label' => 'Condición IVA',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'condicioniva.nombre',
            'attr' => 'nombrecondicioniva',
            'group' => self::GRUPO_FISCAL,
        ],
        'nroIIBB' => [
            'label' => 'Nro. IIBB',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.nroIIBB',
            'attr' => 'nroIIBB',
            'group' => self::GRUPO_FISCAL,
        ],
        'regimenfacturacion' => [
            'label' => 'Régimen facturación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.regimenfacturacion',
            'attr' => 'regimenfacturacion',
            'group' => self::GRUPO_FISCAL,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'condicionpago' => [
            'label' => 'Condición de pago',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'condicionpago.nombre',
            'attr' => 'nombrecondicionpago',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'condicionentrega' => [
            'label' => 'Condición de entrega',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'condicionentrega.nombre',
            'attr' => 'nombrecondicionentrega',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'condicioncompra' => [
            'label' => 'Condición de compra',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'condicioncompra.nombre',
            'attr' => 'nombrecondicioncompra',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'tiposervicio' => [
            'label' => 'Tipo de servicio',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tiposervicio_proveedor.nombre',
            'attr' => 'nombretiposervicio',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'leyenda' => [
            'label' => 'Leyenda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.leyenda',
            'attr' => 'leyenda',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'cbu' => [
            'label' => 'CBU',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor_formapago.cbu',
            'attr' => 'cbu',
            'group' => self::GRUPO_BANCARIO,
            'formapago' => true,
        ],
        'alias_cbu' => [
            'label' => 'Alias CBU',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor_formapago.alias_cbu',
            'attr' => 'alias_cbu',
            'group' => self::GRUPO_BANCARIO,
            'formapago' => true,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_ESTADO,
        ],
        'tipoalta' => [
            'label' => 'Tipo de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.tipoalta',
            'attr' => 'tipoalta',
            'group' => self::GRUPO_ESTADO,
        ],
        'tiposuspension' => [
            'label' => 'Tipo de suspensión',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tiposuspensionproveedor.nombre',
            'attr' => 'nombretiposuspension',
            'group' => self::GRUPO_ESTADO,
        ],
        'semaforo' => [
            'label' => 'Semáforo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.semaforo',
            'attr' => 'semaforo',
            'group' => self::GRUPO_ESTADO,
        ],
        'apoc' => [
            'label' => 'APOC',
            'default' => true,
            'export' => false,
            'filterable' => true,
            'type' => 'booleano',
            'source' => 'proveedor.facturas_apocrifas',
            'attr' => 'facturas_apocrifas',
            'group' => self::GRUPO_ESTADO,
        ],
        'fecha_alta' => [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'proveedor.created_at',
            'attr' => 'fecha_alta',
            'group' => self::GRUPO_ESTADO,
        ],
        'fecha_modificacion' => [
            'label' => 'Fecha de modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'proveedor.updated_at',
            'attr' => 'fecha_modificacion',
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
            if ($meta['default']) {
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
        $out = self::COLUMNAS;
        if (! ProveedorListadoFiltros::filtroEmpresaActivo()) {
            unset($out['empresa']);
        }

        return $out;
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

        if ($keys === []) {
            return self::defaultsVisibles();
        }
        if (! in_array('id', $keys, true) && isset($catalogo['id'])) {
            array_unshift($keys, 'id');
        }

        return $keys;
    }

    /**
     * @param  list<string>  $visibles
     */
    public static function requiereDatosBancarios(array $visibles): bool
    {
        return in_array('cbu', $visibles, true) || in_array('alias_cbu', $visibles, true);
    }

    /**
     * Campos disponibles para criterios QBE (Advanced Find).
     *
     * @return array<string, array{label: string, type: string, column: string, formapago?: bool}>
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
                'formapago' => ! empty($meta['formapago']),
                'group' => $meta['group'],
            ];
        }

        return $out;
    }

    /**
     * Campos disponibles para ORDER BY multi-criterio (P13n / Saved Search).
     *
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

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function catalogoPorGrupo(): array
    {
        $porGrupo = [];
        foreach (self::GRUPOS as $gKey => $gLabel) {
            $porGrupo[$gKey] = [];
        }
        foreach (self::catalogoActivo() as $key => $meta) {
            $g = $meta['group'] ?? self::GRUPO_IDENTIFICACION;
            $porGrupo[$g][$key] = $meta;
        }

        return array_filter($porGrupo, static fn ($cols) => $cols !== []);
    }

    public static function valorCelda(object $data, string $key): string
    {
        if ($key === 'apoc') {
            return ! empty($data->facturas_apocrifas) ? 'Sí' : (! empty($data->facturas_apocrifas_consulta_at) ? 'No' : '');
        }
        if ($key === 'empresa') {
            $v = trim((string) ($data->nombreempresa ?? ''));

            return $v !== '' ? $v : 'Todas';
        }
        $attr = self::catalogoActivo()[$key]['attr'] ?? $key;
        $valor = $data->{$attr} ?? '';
        if ((self::catalogoActivo()[$key]['type'] ?? '') === 'fecha') {
            return ListadoQbeSupport::formatearFecha($valor);
        }

        return is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;
    }

    /**
     * Expresiones SQL para Pack C (GROUP BY alineado con valorCelda).
     *
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        return match ($key) {
            'apoc' => [
                'select' => [
                    'proveedor.facturas_apocrifas as facturas_apocrifas',
                    'proveedor.facturas_apocrifas_consulta_at as facturas_apocrifas_consulta_at',
                ],
                'groupBy' => ['proveedor.facturas_apocrifas', 'proveedor.facturas_apocrifas_consulta_at'],
            ],
            'empresa' => [
                'select' => ['empresa.nombre as nombreempresa'],
                'groupBy' => ['empresa.nombre'],
            ],
            default => \App\Support\Listado\ListadoCortesSupport::sqlAgrupacionDefault(
                $key,
                self::catalogoActivo()[$key] ?? []
            ),
        };
    }
}
