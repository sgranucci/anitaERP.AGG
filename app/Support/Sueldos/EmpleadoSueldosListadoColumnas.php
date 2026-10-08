<?php

declare(strict_types=1);

namespace App\Support\Sueldos;

use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo único de columnas del workbench de empleados de sueldos
 * (pantalla, export, QBE y etiquetas).
 */
final class EmpleadoSueldosListadoColumnas
{
    public const RECURSO = 'sueldos.empleado';

    /** @var list<array{key: string, column: string, label: string}> */
    public const MEDIDAS = [
        ['key' => 'sueldo_basico', 'column' => 'empleado_sueldos.sueldo_basico', 'label' => 'Sueldo básico'],
    ];

    public const GRUPO_IDENTIFICACION = 'identificacion';

    public const GRUPO_ORGANIZACION = 'organizacion';

    public const GRUPO_CONTACTO = 'contacto';

    public const GRUPO_LABORAL = 'laboral';

    public const GRUPO_ESTADO = 'estado';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_IDENTIFICACION => 'Identificación',
        self::GRUPO_ORGANIZACION => 'Organización',
        self::GRUPO_CONTACTO => 'Contacto',
        self::GRUPO_LABORAL => 'Laboral',
        self::GRUPO_ESTADO => 'Estado / control',
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'empleado_sueldos.id',
            'attr' => 'id',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'legajo' => [
            'label' => 'Legajo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'empleado_sueldos.legajo',
            'attr' => 'legajo',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'nombre' => [
            'label' => 'Nombre',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.nombre',
            'attr' => 'nombre',
            'group' => self::GRUPO_IDENTIFICACION,
            'sticky' => true,
        ],
        'cuil' => [
            'label' => 'CUIL',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.cuil',
            'attr' => 'cuil',
            'group' => self::GRUPO_IDENTIFICACION,
        ],
        'documento' => [
            'label' => 'Documento',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.documento',
            'attr' => 'documento',
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
            'group' => self::GRUPO_ORGANIZACION,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_ORGANIZACION,
        ],
        'categoria' => [
            'label' => 'Categoría',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'categoria_sueldos.descripcion',
            'attr' => 'nombrecategoria',
            'group' => self::GRUPO_ORGANIZACION,
        ],
        'centrocosto' => [
            'label' => 'Centro de costo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'centrocosto.nombre',
            'attr' => 'nombrecentrocosto',
            'group' => self::GRUPO_ORGANIZACION,
        ],
        'lugartrabajo' => [
            'label' => 'Lugar de trabajo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'lugartrabajo_sueldos.nombre',
            'attr' => 'nombrelugartrabajo',
            'group' => self::GRUPO_ORGANIZACION,
        ],
        'agrupamiento' => [
            'label' => 'Agrupamiento',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'agrupamiento_sueldos.descripcion',
            'attr' => 'nombreagrupamiento',
            'group' => self::GRUPO_ORGANIZACION,
        ],
        'email' => [
            'label' => 'Email',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.email',
            'attr' => 'email',
            'group' => self::GRUPO_CONTACTO,
        ],
        'telefono' => [
            'label' => 'Teléfono',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.telefono',
            'attr' => 'telefono',
            'group' => self::GRUPO_CONTACTO,
        ],
        'domicilio' => [
            'label' => 'Domicilio',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empleado_sueldos.domicilio',
            'attr' => 'domicilio',
            'group' => self::GRUPO_CONTACTO,
        ],
        'localidad' => [
            'label' => 'Localidad',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'localidad.nombre',
            'attr' => 'nombrelocalidad',
            'group' => self::GRUPO_CONTACTO,
        ],
        'provincia' => [
            'label' => 'Provincia',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'provincia.nombre',
            'attr' => 'nombreprovincia',
            'group' => self::GRUPO_CONTACTO,
        ],
        'fecha_ingreso' => [
            'label' => 'Ingreso',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sueldos.fecha_ingreso',
            'attr' => 'fecha_ingreso',
            'group' => self::GRUPO_LABORAL,
        ],
        'fecha_egreso' => [
            'label' => 'Egreso',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sueldos.fecha_egreso',
            'attr' => 'fecha_egreso',
            'group' => self::GRUPO_LABORAL,
        ],
        'fecha_nacimiento' => [
            'label' => 'Nacimiento',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sueldos.fecha_nacimiento',
            'attr' => 'fecha_nacimiento',
            'group' => self::GRUPO_LABORAL,
        ],
        'obrasocial' => [
            'label' => 'Obra social',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'obrasocial_sueldos.descripcion',
            'attr' => 'nombreobrasocial',
            'group' => self::GRUPO_LABORAL,
        ],
        'sindicato' => [
            'label' => 'Sindicato',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sindicato_sueldos.descripcion',
            'attr' => 'nombresindicato',
            'group' => self::GRUPO_LABORAL,
        ],
        'sueldo_basico' => [
            'label' => 'Sueldo básico',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'empleado_sueldos.sueldo_basico',
            'attr' => 'sueldo_basico',
            'group' => self::GRUPO_LABORAL,
        ],
        'confidencial' => [
            'label' => 'Confidencial',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'booleano',
            'source' => 'empleado_sueldos.confidencial',
            'attr' => 'confidencial',
            'group' => self::GRUPO_ESTADO,
        ],
        'fecha_alta' => [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sueldos.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_ESTADO,
        ],
        'fecha_modificacion' => [
            'label' => 'Fecha de modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'empleado_sueldos.updated_at',
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
        return self::COLUMNAS;
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
            return EmpleadoEstados::label($data->estado ?? null);
        }
        if ($key === 'confidencial') {
            if ($data->confidencial === null || $data->confidencial === '') {
                return '';
            }

            return ! empty($data->confidencial) ? 'Sí' : 'No';
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
        return \App\Support\Listado\ListadoCortesSupport::sqlAgrupacionDefault(
            $key,
            self::catalogoActivo()[$key] ?? []
        );
    }
}
