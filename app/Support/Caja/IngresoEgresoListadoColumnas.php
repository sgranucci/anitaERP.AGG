<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

final class IngresoEgresoListadoColumnas
{
    public const RECURSO = 'caja.ingresoegreso';

    public const GRUPO_MOVIMIENTO = 'movimiento';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_MOVIMIENTO => 'Movimiento',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'caja_movimiento.id',
            'attr' => 'id',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'numero' => [
            'label' => 'Número',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'caja_movimiento.numerotransaccion',
            'attr' => 'numerotransaccion',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'caja_movimiento.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'tipo' => [
            'label' => 'Tipo de transacción',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipotransaccion_caja.nombre',
            'attr' => 'nombretipotransaccion_caja',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'abreviatura' => [
            'label' => 'Abreviatura',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'tipotransaccion_caja.abreviatura',
            'attr' => 'abreviaturatipotransaccion_caja',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'concepto' => [
            'label' => 'Concepto',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'conceptogasto.nombre',
            'attr' => 'nombreconceptogasto',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'detalle' => [
            'label' => 'Detalle',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'caja_movimiento.detalle',
            'attr' => 'detalle',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'usuario' => [
            'label' => 'Usuario',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'usuario.nombre',
            'attr' => 'nombreusuario',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'solicitudpago' => [
            'label' => 'Solicitud de pago',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'caja_movimiento.solicitudpago_id',
            'attr' => 'solicitudpago_id',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'fecha_alta' => [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'caja_movimiento.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'ordenservicio' => [
            'label' => 'Orden de servicio',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'caja_movimiento.ordenservicio_id',
            'attr' => 'ordenservicio_id',
            'group' => self::GRUPO_MOVIMIENTO,
            'solo_iguassu' => true,
        ],
        'monto' => [
            'label' => 'Monto en $',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'decimal',
            'source' => '',
            'attr' => 'monto',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
        'movimientos' => [
            'label' => 'Movimientos',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'movimientos',
            'group' => self::GRUPO_MOVIMIENTO,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        $catalogo = self::COLUMNAS;
        if (config('app.empresa') !== 'Iguassu Travel') {
            unset($catalogo['ordenservicio']);
        }

        return $catalogo;
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
                'label' => (string) $meta['label'],
                'type' => (string) $meta['type'],
                'column' => (string) $meta['source'],
                'group' => (string) ($meta['group'] ?? self::GRUPO_MOVIMIENTO),
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
     * Tipos que tienen al menos un movimiento visible para el usuario.
     *
     * @return list<array{id: int, abreviatura: string, nombre: string}>
     */
    public static function tiposEnUso(): array
    {
        $query = Caja_Movimiento::query()
            ->join('tipotransaccion_caja', 'tipotransaccion_caja.id', '=', 'caja_movimiento.tipotransaccion_caja_id')
            ->select([
                'tipotransaccion_caja.id',
                'tipotransaccion_caja.abreviatura',
                'tipotransaccion_caja.nombre',
            ])
            ->distinct()
            ->orderBy('tipotransaccion_caja.abreviatura');

        app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($query, 'caja_movimiento.empresa_id');
        IngresoEgresoVisibilidadSupport::aplicarFiltroAlcance($query);

        $filas = [];
        foreach ($query->get() as $row) {
            $filas[] = [
                'id' => (int) $row->id,
                'abreviatura' => (string) ($row->abreviatura ?? ''),
                'nombre' => (string) ($row->nombre ?? ''),
            ];
        }

        return $filas;
    }

    /**
     * @return array{ingreso: float, egreso: float, monto: float, lineas: list<string>}
     */
    public static function resumenFila(object $data): array
    {
        $cache = $data->_ie_resumen ?? null;
        if (is_array($cache)) {
            return $cache;
        }

        return IngresoEgresoListadoMontoSupport::resumen($data);
    }

    public static function valorCelda(object $data, string $key): string
    {
        if ($key === 'tipo') {
            $abrev = trim((string) ($data->abreviaturatipotransaccion_caja ?? ''));
            $nombre = trim((string) ($data->nombretipotransaccion_caja ?? ''));

            return $abrev !== '' ? $abrev.' — '.$nombre : $nombre;
        }
        if ($key === 'monto') {
            return number_format(self::resumenFila($data)['monto'], 2, ',', '.');
        }
        if ($key === 'movimientos') {
            return implode(' | ', self::resumenFila($data)['lineas']);
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
        if ($valor === null) {
            return '';
        }

        return (string) $valor;
    }

    public static function valorAgrupacion(object $data, string $key): string
    {
        $texto = trim(self::valorCelda($data, $key));

        return $texto === '' ? '(vacío)' : $texto;
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::catalogoActivo()[$key] ?? []);
    }
}
