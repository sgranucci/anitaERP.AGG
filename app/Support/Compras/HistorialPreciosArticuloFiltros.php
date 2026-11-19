<?php

namespace App\Support\Compras;

use App\Models\Stock\Categoria;
use App\Models\Stock\Subcategoria;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Usoarticulo;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe de historial de precios de compra por artículo/proveedor.
 */
final class HistorialPreciosArticuloFiltros
{
    public const MODO_RESUMEN = 'resumen';

    public const MODO_DETALLE = 'detalle';

    /** Precios del período encolumnados por mes + variación % entre meses. */
    public const MODO_MENSUAL = 'mensual';

    /**
     * Una fila por compra/OC con el precio en la columna del mes (estilo control items / solapa Reporte).
     */
    public const MODO_MENSUAL_OC = 'mensual_oc';

    public const AGRUPACION_ARTICULO = 'articulo';

    public const AGRUPACION_ARTICULO_PROVEEDOR = 'articulo_proveedor';

    /** Máximo de meses a encolumnar (evita tablas inmanejables). */
    public const MAX_MESES_COLUMNAS = 24;

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MODO = [
        ['valor' => self::MODO_RESUMEN, 'etiqueta' => 'Resumen (último / anterior / variación)'],
        ['valor' => self::MODO_DETALLE, 'etiqueta' => 'Detalle de compras (todas las variaciones)'],
        ['valor' => self::MODO_MENSUAL, 'etiqueta' => 'Por mes (último precio del mes + variación %)'],
        ['valor' => self::MODO_MENSUAL_OC, 'etiqueta' => 'Por mes y OC (una fila por compra, como control items)'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_AGRUPACION = [
        ['valor' => self::AGRUPACION_ARTICULO, 'etiqueta' => 'Por artículo'],
        ['valor' => self::AGRUPACION_ARTICULO_PROVEEDOR, 'etiqueta' => 'Por artículo y proveedor'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $modo = trim((string) $request->input('modo', self::MODO_RESUMEN));
        if (! self::modoValido($modo)) {
            $modo = self::MODO_RESUMEN;
        }

        $agrupacion = trim((string) $request->input('agrupacion', self::AGRUPACION_ARTICULO));
        if (! self::agrupacionValida($agrupacion)) {
            $agrupacion = self::AGRUPACION_ARTICULO;
        }

        $articuloId = (int) $request->input('articulo_id', 0);
        $tipo = self::resolverTipoarticulo(
            self::enteroOpcional($request->input('tipoarticulo_id')),
            trim((string) $request->input('tipoarticulo_codigo', '')),
        );
        $uso = self::resolverUsoarticulo(
            self::enteroOpcional($request->input('usoarticulo_id')),
            trim((string) $request->input('usoarticulo_codigo', '')),
        );
        $categoria = self::resolverCategoria(
            self::enteroOpcional($request->input('categoria_id')),
            trim((string) $request->input('categoria_codigo', '')),
        );
        $subcategoria = self::resolverSubcategoria(
            self::enteroOpcional($request->input('subcategoria_id')),
            trim((string) $request->input('subcategoria_codigo', '')),
        );

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'articulo_id' => $articuloId > 0 ? $articuloId : null,
            'sku' => trim((string) $request->input('sku', '')),
            'proveedores' => trim((string) $request->input('proveedores', '')),
            'tipoarticulo_id' => $tipo['id'],
            'tipoarticulo_codigo' => $tipo['codigo'],
            'tipoarticulo_nombre' => $tipo['nombre'],
            'usoarticulo_id' => $uso['id'],
            'usoarticulo_codigo' => $uso['codigo'],
            'usoarticulo_nombre' => $uso['nombre'],
            'categoria_id' => $categoria['id'],
            'categoria_codigo' => $categoria['codigo'],
            'categoria_nombre' => $categoria['nombre'],
            'subcategoria_id' => $subcategoria['id'],
            'subcategoria_codigo' => $subcategoria['codigo'],
            'subcategoria_nombre' => $subcategoria['nombre'],
            'modo' => $modo,
            'agrupacion' => $agrupacion,
            'solo_con_variacion' => $request->boolean('solo_con_variacion'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'articulo_id' => ! empty($filtros['articulo_id']) ? (int) $filtros['articulo_id'] : null,
            'sku' => ($filtros['sku'] ?? '') !== '' ? ($filtros['sku'] ?? null) : null,
            'proveedores' => ($filtros['proveedores'] ?? '') !== '' ? ($filtros['proveedores'] ?? null) : null,
            'tipoarticulo_id' => ! empty($filtros['tipoarticulo_id']) ? (int) $filtros['tipoarticulo_id'] : null,
            'tipoarticulo_codigo' => ($filtros['tipoarticulo_codigo'] ?? '') !== '' ? ($filtros['tipoarticulo_codigo'] ?? null) : null,
            'usoarticulo_id' => ! empty($filtros['usoarticulo_id']) ? (int) $filtros['usoarticulo_id'] : null,
            'usoarticulo_codigo' => ($filtros['usoarticulo_codigo'] ?? '') !== '' ? ($filtros['usoarticulo_codigo'] ?? null) : null,
            'categoria_id' => ! empty($filtros['categoria_id']) ? (int) $filtros['categoria_id'] : null,
            'categoria_codigo' => ($filtros['categoria_codigo'] ?? '') !== '' ? ($filtros['categoria_codigo'] ?? null) : null,
            'subcategoria_id' => ! empty($filtros['subcategoria_id']) ? (int) $filtros['subcategoria_id'] : null,
            'subcategoria_codigo' => ($filtros['subcategoria_codigo'] ?? '') !== '' ? ($filtros['subcategoria_codigo'] ?? null) : null,
            'modo' => ($filtros['modo'] ?? self::MODO_RESUMEN) !== self::MODO_RESUMEN
                ? ($filtros['modo'] ?? null)
                : null,
            'agrupacion' => ($filtros['agrupacion'] ?? self::AGRUPACION_ARTICULO) !== self::AGRUPACION_ARTICULO
                ? ($filtros['agrupacion'] ?? null)
                : null,
            'solo_con_variacion' => ! empty($filtros['solo_con_variacion']) ? 1 : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_desde']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $hoy = Carbon::now();

        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => $hoy->copy()->subYear()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => $hoy->copy()->endOfMonth()->format('Y-m-d'),
            'articulo_id' => null,
            'sku' => '',
            'proveedores' => '',
            'tipoarticulo_id' => null,
            'tipoarticulo_codigo' => '',
            'tipoarticulo_nombre' => '',
            'usoarticulo_id' => null,
            'usoarticulo_codigo' => '',
            'usoarticulo_nombre' => '',
            'categoria_id' => null,
            'categoria_codigo' => '',
            'categoria_nombre' => '',
            'subcategoria_id' => null,
            'subcategoria_codigo' => '',
            'subcategoria_nombre' => '',
            'modo' => self::MODO_RESUMEN,
            'agrupacion' => self::AGRUPACION_ARTICULO,
            'solo_con_variacion' => false,
        ];
    }

    public static function modoValido(string $modo): bool
    {
        return in_array($modo, [
            self::MODO_RESUMEN,
            self::MODO_DETALLE,
            self::MODO_MENSUAL,
            self::MODO_MENSUAL_OC,
        ], true);
    }

    public static function usaColumnasMes(string $modo): bool
    {
        return $modo === self::MODO_MENSUAL || $modo === self::MODO_MENSUAL_OC;
    }

    public static function esMensualOc(string $modo): bool
    {
        return $modo === self::MODO_MENSUAL_OC;
    }

    /**
     * Meses YYYY-MM del período del filtro (inclusive), acotados a MAX_MESES_COLUMNAS.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public static function mesesDelPeriodo(array $filtros): array
    {
        $desde = $filtros['fecha_desde'] ?? null;
        $hasta = $filtros['fecha_hasta'] ?? null;
        if ($desde === null || $desde === '') {
            return [];
        }

        try {
            $inicio = Carbon::parse($desde)->startOfMonth();
            $fin = ($hasta !== null && $hasta !== '')
                ? Carbon::parse($hasta)->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            return [];
        }

        if ($fin->lt($inicio)) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        $meses = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fin) && count($meses) < self::MAX_MESES_COLUMNAS) {
            $meses[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $meses;
    }

    public static function etiquetaMes(string $ym): string
    {
        static $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        try {
            $fecha = Carbon::createFromFormat('Y-m', $ym);

            return ($meses[(int) $fecha->month] ?? $fecha->format('m')).'/'.$fecha->format('y');
        } catch (\Throwable) {
            return $ym;
        }
    }

    public static function agrupacionValida(string $agrupacion): bool
    {
        return in_array($agrupacion, [self::AGRUPACION_ARTICULO, self::AGRUPACION_ARTICULO_PROVEEDOR], true);
    }

    public static function etiquetaModo(string $modo): string
    {
        foreach (self::OPCIONES_MODO as $opcion) {
            if ($opcion['valor'] === $modo) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_MODO[0]['etiqueta'];
    }

    public static function etiquetaAgrupacion(string $agrupacion): string
    {
        foreach (self::OPCIONES_AGRUPACION as $opcion) {
            if ($opcion['valor'] === $agrupacion) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_AGRUPACION[0]['etiqueta'];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = $filtros['fecha_desde'] ?? '';
        $hasta = $filtros['fecha_hasta'] ?? '';

        $fmt = static function (?string $fecha): string {
            if ($fecha === null || $fecha === '') {
                return '';
            }
            try {
                return Carbon::parse($fecha)->format('d/m/Y');
            } catch (\Throwable) {
                return $fecha;
            }
        };

        if ($desde !== '' && $hasta !== '') {
            return $fmt($desde).' — '.$fmt($hasta);
        }
        if ($desde !== '') {
            return 'Desde '.$fmt($desde);
        }
        if ($hasta !== '') {
            return 'Hasta '.$fmt($hasta);
        }

        return 'Sin período';
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function enteroOpcional($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array{id: ?int, codigo: string, nombre: string}
     */
    private static function resolverTipoarticulo(?int $id, string $codigo): array
    {
        $vacio = ['id' => null, 'codigo' => '', 'nombre' => ''];
        if ($id !== null) {
            $fila = Tipoarticulo::query()->select('id', 'abreviatura', 'nombre')->find($id);
            if ($fila) {
                return [
                    'id' => (int) $fila->id,
                    'codigo' => (string) ($fila->abreviatura ?? ''),
                    'nombre' => (string) ($fila->nombre ?? ''),
                ];
            }
        }
        if ($codigo === '') {
            return $vacio;
        }
        $fila = Tipoarticulo::query()
            ->select('id', 'abreviatura', 'nombre')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [mb_strtoupper($codigo)])
            ->first();
        if (! $fila && ctype_digit($codigo)) {
            $fila = Tipoarticulo::query()->select('id', 'abreviatura', 'nombre')->find((int) $codigo);
        }
        if (! $fila) {
            return ['id' => null, 'codigo' => $codigo, 'nombre' => ''];
        }

        return [
            'id' => (int) $fila->id,
            'codigo' => (string) ($fila->abreviatura ?? ''),
            'nombre' => (string) ($fila->nombre ?? ''),
        ];
    }

    /**
     * Uso no tiene código propio: el campo editable acepta ID o nombre exacto.
     *
     * @return array{id: ?int, codigo: string, nombre: string}
     */
    private static function resolverUsoarticulo(?int $id, string $codigo): array
    {
        $vacio = ['id' => null, 'codigo' => '', 'nombre' => ''];
        if ($id !== null) {
            $fila = Usoarticulo::query()->select('id', 'nombre')->find($id);
            if ($fila) {
                return [
                    'id' => (int) $fila->id,
                    'codigo' => (string) $fila->id,
                    'nombre' => (string) ($fila->nombre ?? ''),
                ];
            }
        }
        if ($codigo === '') {
            return $vacio;
        }
        $fila = null;
        if (ctype_digit($codigo)) {
            $fila = Usoarticulo::query()->select('id', 'nombre')->find((int) $codigo);
        }
        if (! $fila) {
            $fila = Usoarticulo::query()
                ->select('id', 'nombre')
                ->whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper($codigo)])
                ->first();
        }
        if (! $fila) {
            return ['id' => null, 'codigo' => $codigo, 'nombre' => ''];
        }

        return [
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->id,
            'nombre' => (string) ($fila->nombre ?? ''),
        ];
    }

    /**
     * @return array{id: ?int, codigo: string, nombre: string}
     */
    private static function resolverCategoria(?int $id, string $codigo): array
    {
        $vacio = ['id' => null, 'codigo' => '', 'nombre' => ''];
        if ($id !== null) {
            $fila = Categoria::query()->select('id', 'codigo', 'nombre')->find($id);
            if ($fila) {
                return [
                    'id' => (int) $fila->id,
                    'codigo' => (string) ($fila->codigo ?? ''),
                    'nombre' => (string) ($fila->nombre ?? ''),
                ];
            }
        }
        if ($codigo === '') {
            return $vacio;
        }
        $fila = Categoria::query()->select('id', 'codigo', 'nombre')->where('codigo', $codigo)->first();
        if (! $fila) {
            $alt = ltrim($codigo, '0');
            if ($alt !== '' && $alt !== $codigo) {
                $fila = Categoria::query()->select('id', 'codigo', 'nombre')->where('codigo', $alt)->first();
            }
        }
        if (! $fila && ctype_digit($codigo)) {
            $fila = Categoria::query()->select('id', 'codigo', 'nombre')->find((int) $codigo);
        }
        if (! $fila) {
            return ['id' => null, 'codigo' => $codigo, 'nombre' => ''];
        }

        return [
            'id' => (int) $fila->id,
            'codigo' => (string) ($fila->codigo ?? ''),
            'nombre' => (string) ($fila->nombre ?? ''),
        ];
    }

    /**
     * @return array{id: ?int, codigo: string, nombre: string}
     */
    private static function resolverSubcategoria(?int $id, string $codigo): array
    {
        $vacio = ['id' => null, 'codigo' => '', 'nombre' => ''];
        if ($id !== null) {
            $fila = Subcategoria::query()->select('id', 'codigo', 'nombre')->find($id);
            if ($fila) {
                return [
                    'id' => (int) $fila->id,
                    'codigo' => (string) ($fila->codigo ?? ''),
                    'nombre' => (string) ($fila->nombre ?? ''),
                ];
            }
        }
        if ($codigo === '') {
            return $vacio;
        }
        $fila = Subcategoria::query()->select('id', 'codigo', 'nombre')->where('codigo', $codigo)->first();
        if (! $fila) {
            $alt = ltrim($codigo, '0');
            if ($alt !== '' && $alt !== $codigo) {
                $fila = Subcategoria::query()->select('id', 'codigo', 'nombre')->where('codigo', $alt)->first();
            }
        }
        if (! $fila && ctype_digit($codigo)) {
            $fila = Subcategoria::query()->select('id', 'codigo', 'nombre')->find((int) $codigo);
        }
        if (! $fila) {
            return ['id' => null, 'codigo' => $codigo, 'nombre' => ''];
        }

        return [
            'id' => (int) $fila->id,
            'codigo' => (string) ($fila->codigo ?? ''),
            'nombre' => (string) ($fila->nombre ?? ''),
        ];
    }
}
