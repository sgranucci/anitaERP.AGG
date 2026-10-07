<?php

declare(strict_types=1);

namespace App\Support\Logistica;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

final class SolicitudLogisticaListadoColumnas
{
    public const RECURSO = 'logistica.solicitud';

    public const GRUPO_SOLICITUD = 'solicitud';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_SOLICITUD => 'Solicitud',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'numero' => [
            'label' => 'Número',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'solicitud_logistica.numero',
            'attr' => 'numero',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'compromiso' => [
            'label' => 'Compromiso',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'solicitud_logistica.fecha_compromiso',
            'attr' => 'fecha_compromiso',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'solicitud_logistica.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'solicitante' => [
            'label' => 'Solicitante',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'usuario.nombre',
            'attr' => 'nombre_solicitante',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'tipo' => [
            'label' => 'Tipo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipo.nombre',
            'attr' => 'nombre_tipo',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'centrocosto' => [
            'label' => 'Centro de costo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'centrocosto.nombre',
            'attr' => 'nombre_cc',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'prioridad' => [
            'label' => 'Prioridad',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'solicitud_logistica.prioridad',
            'attr' => 'prioridad',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'solicitud_logistica.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'total' => [
            'label' => 'Total estimado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'solicitud_logistica.total_estimado',
            'attr' => 'total_estimado',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'items' => [
            'label' => 'Ítems',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'entero',
            'source' => '',
            'attr' => 'items_count',
            'group' => self::GRUPO_SOLICITUD,
        ],
        'fecha_alta' => [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'solicitud_logistica.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_SOLICITUD,
        ],
    ];

    /** @var array<string, string> */
    public const ESTADOS = [
        '' => 'Todas',
        'enviada' => 'Enviada',
        'pendiente_aprobacion' => 'Pendiente de aprobación',
        'aprobada' => 'Aprobada',
        'en_preparacion' => 'En preparación',
        'entregada' => 'Entregada',
        'cerrada' => 'Cerrada',
        'rechazada' => 'Rechazada',
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

    public static function etiquetaEstado(?string $estado): string
    {
        return self::ESTADOS[$estado ?? ''] ?? (string) $estado;
    }

    public static function numeroVisible(object $data): string
    {
        $anio = '';
        if (! empty($data->fecha)) {
            $anio = date('Y', strtotime((string) $data->fecha));
        }
        $prefijo = 'SOL';
        if (($data->tipo_codigo ?? '') === 'trabajos') {
            $prefijo = ($data->trabajo_codigo ?? '') === 'retiro' ? 'RET' : 'TRB';
        }

        return $prefijo.'-'.$anio.'-'.str_pad((string) ($data->numero ?? 0), 4, '0', STR_PAD_LEFT);
    }

    public static function valorCelda(object $data, string $key): string
    {
        if ($key === 'numero') {
            return self::numeroVisible($data);
        }
        if ($key === 'estado') {
            return self::etiquetaEstado($data->estado ?? null);
        }
        if ($key === 'centrocosto') {
            return trim((string) (($data->codigo_cc ?? '').' '.($data->nombre_cc ?? '')));
        }
        $meta = self::catalogoActivo()[$key] ?? null;
        if ($meta === null) {
            return '';
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

        return (string) $valor;
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        if ($key === 'tipo') {
            return [
                'select' => ['COALESCE(trabajo.nombre, tipo.nombre) as nombre_tipo'],
                'groupBy' => ['nombre_tipo'],
            ];
        }

        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::catalogoActivo()[$key] ?? []);
    }
}
