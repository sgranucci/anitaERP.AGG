<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Catálogo único de columnas del workbench de clientes
 * (pantalla, export, QBE y etiquetas).
 */
final class ClienteListadoColumnas
{
    public const RECURSO = 'ventas.cliente';

    public const GRUPO_IDENTIFICACION = 'identificacion';

    public const GRUPO_CONTACTO = 'contacto';

    public const GRUPO_DOMICILIO = 'domicilio';

    public const GRUPO_FISCAL = 'fiscal';

    public const GRUPO_COMERCIAL = 'comercial';

    public const GRUPO_ESTADO = 'estado';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_IDENTIFICACION => 'Identificación',
        self::GRUPO_CONTACTO => 'Contacto',
        self::GRUPO_DOMICILIO => 'Domicilio',
        self::GRUPO_FISCAL => 'Fiscal',
        self::GRUPO_COMERCIAL => 'Comercial',
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
     *   composed?: bool
     * }>
     */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'cliente.id',
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
            'source' => 'cliente.codigo',
            'attr' => 'codigo',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'nombre' => [
            'label' => 'Nombre',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.nombre',
            'attr' => 'nombre',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'fantasia' => [
            'label' => 'Nombre de fantasía',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.fantasia',
            'attr' => 'fantasia',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'numerodocumento' => [
            'label' => 'C.U.I.T.',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.numerodocumento',
            'attr' => 'numerodocumento',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'tipoempresa' => [
            'label' => 'Tipo de empresa',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipoempresa_cliente.nombre',
            'attr' => 'nombretipoempresa',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'contacto' => [
            'label' => 'Contacto',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.contacto',
            'attr' => 'contacto',
            'group' => self::GRUPO_CONTACTO,
        ],
        'email' => [
            'label' => 'Email',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.email',
            'attr' => 'email',
            'group' => self::GRUPO_CONTACTO,
        ],
        'telefono' => [
            'label' => 'Teléfono',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.telefono',
            'attr' => 'telefono',
            'group' => self::GRUPO_CONTACTO,
        ],
        'urlweb' => [
            'label' => 'Sitio web',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.urlweb',
            'attr' => 'urlweb',
            'group' => self::GRUPO_CONTACTO,
        ],
        'domicilio' => [
            'label' => 'Domicilio',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.domicilio',
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
            'source' => 'cliente.codigopostal',
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
        'nroiibb' => [
            'label' => 'Nro. IIBB',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.nroiibb',
            'attr' => 'nroiibb',
            'group' => self::GRUPO_FISCAL,
        ],
        'vendedor' => [
            'label' => 'Vendedor',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => 'vendedor.nombre',
            'attr' => 'vendedor',
            'group' => self::GRUPO_COMERCIAL,
            'composed' => true,
        ],
        'vendedor_codigo' => [
            'label' => 'Vendedor (código)',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'vendedor.codigo',
            'attr' => 'cvendedor',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'vendedor_nombre' => [
            'label' => 'Vendedor (nombre)',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'vendedor.nombre',
            'attr' => 'nombrevendedor',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'transporte' => [
            'label' => 'Transporte',
            'default' => false,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => 'transporte.nombre',
            'attr' => 'transporte',
            'group' => self::GRUPO_COMERCIAL,
            'composed' => true,
        ],
        'transporte_codigo' => [
            'label' => 'Transporte (código)',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'transporte.codigo',
            'attr' => 'ctransporte',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'transporte_nombre' => [
            'label' => 'Transporte (nombre)',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'transporte.nombre',
            'attr' => 'nombretransporte',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'zonavta' => [
            'label' => 'Zona de venta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'zonavta.nombre',
            'attr' => 'nombrezonavta',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'subzonavta' => [
            'label' => 'Subzona de venta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'subzonavta.nombre',
            'attr' => 'nombresubzonavta',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'cobrador' => [
            'label' => 'Cobrador',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cobrador.nombre',
            'attr' => 'nombrecobrador',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'condicionventa' => [
            'label' => 'Condición de venta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'condicionventa.nombre',
            'attr' => 'nombrecondicionventa',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'listaprecio' => [
            'label' => 'Lista de precio',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listaprecio.nombre',
            'attr' => 'nombrelistaprecio',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'descuento' => [
            'label' => 'Descuento',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.descuento',
            'attr' => 'descuento',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'descuentoventa' => [
            'label' => 'Descuento venta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'descuentoventa.nombre',
            'attr' => 'nombredescuentoventa',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'leyenda' => [
            'label' => 'Leyenda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.leyenda',
            'attr' => 'leyenda',
            'group' => self::GRUPO_COMERCIAL,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_ESTADO,
        ],
        'tipoalta' => [
            'label' => 'Tipo de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.tipoalta',
            'attr' => 'tipoalta',
            'group' => self::GRUPO_ESTADO,
        ],
        'tiposuspension' => [
            'label' => 'Tipo de suspensión',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tiposuspensioncliente.nombre',
            'attr' => 'nombretiposuspension',
            'group' => self::GRUPO_ESTADO,
        ],
        'apoc' => [
            'label' => 'APOC',
            'default' => true,
            'export' => false,
            'filterable' => true,
            'type' => 'booleano',
            'source' => 'cliente.facturas_apocrifas',
            'attr' => 'facturas_apocrifas',
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
        if (EntornoEmpresaSupport::esElBierzo()) {
            $out['transporte']['default'] = true;
            $out['transporte']['label'] = 'Reparto';
            $out['transporte_codigo']['label'] = 'Reparto (código)';
            $out['transporte_nombre']['label'] = 'Reparto (nombre)';
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
     * Campos disponibles para criterios QBE (Advanced Find).
     *
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
            return ! empty($data->facturas_apocrifas)
                ? 'Sí'
                : (! empty($data->facturas_apocrifas_consulta_at) ? 'No' : '');
        }
        if ($key === 'vendedor') {
            $cod = trim((string) ($data->cvendedor ?? ''));
            $nom = trim((string) ($data->nombrevendedor ?? ''));

            return $cod !== '' && $nom !== '' ? $cod.'-'.$nom : ($cod !== '' ? $cod : $nom);
        }
        if ($key === 'transporte') {
            $cod = trim((string) ($data->ctransporte ?? ''));
            $nom = trim((string) ($data->nombretransporte ?? ''));

            return $cod !== '' && $nom !== '' ? $cod.'-'.$nom : ($cod !== '' ? $cod : $nom);
        }
        if ($key === 'estado') {
            $estado = (string) ($data->estado ?? '');

            return match ($estado) {
                '1' => 'Suspendido',
                'R' => 'Regularizado',
                default => $estado === '' || $estado === '0' ? '' : $estado,
            };
        }
        $attr = self::catalogoActivo()[$key]['attr'] ?? $key;
        $valor = $data->{$attr} ?? '';

        return is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;
    }
}
