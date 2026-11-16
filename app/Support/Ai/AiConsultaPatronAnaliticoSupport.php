<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\Compras\Proveedor;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Ventas\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Patrones analíticos gobernados (no SQL libre):
 * - duplicados: GROUP BY campo allowlisted HAVING COUNT > 1
 * - por_localidad: filtro por nombre de localidad (join / columna)
 *
 * Extensible: agregar entidad en catalogo() + aliases NL.
 */
final class AiConsultaPatronAnaliticoSupport
{
    public const PATRON_DUPLICADOS = 'duplicados';

    public const PATRON_POR_LOCALIDAD = 'por_localidad';

    private const MAX_FILAS = 40;

    /**
     * @return array<string, array{
     *   nombre: string,
     *   permiso: string,
     *   modelo: class-string,
     *   ruta_listado: string,
     *   ruta_detalle: string|null,
     *   permiso_detalle: string|null,
     *   duplicados: array<string, string>,
     *   localidad: array{tipo: string, columna?: string, relation?: string, relation_column?: string}|null,
     *   columnas_lista: list<string>,
     *   etiquetas: array<string, string>
     * }>
     */
    public static function catalogo(): array
    {
        return [
            'proveedor' => [
                'nombre' => 'Proveedores',
                'permiso' => 'listar-proveedores',
                'modelo' => Proveedor::class,
                'ruta_listado' => 'proveedor',
                'ruta_detalle' => 'editar_proveedor',
                'permiso_detalle' => 'editar-proveedor',
                'duplicados' => [
                    'nroinscripcion' => 'CUIT',
                    'codigo' => 'Código',
                ],
                'localidad' => [
                    'tipo' => 'relation',
                    'relation' => 'localidades',
                    'relation_column' => 'nombre',
                ],
                'columnas_lista' => ['codigo', 'nombre', 'nroinscripcion', 'localidad', 'fantasia'],
                'etiquetas' => [
                    'codigo' => 'Código',
                    'nombre' => 'Nombre',
                    'nroinscripcion' => 'CUIT',
                    'localidad' => 'Localidad',
                    'fantasia' => 'Fantasía',
                    'cantidad' => 'Cant.',
                ],
            ],
            'cliente' => [
                'nombre' => 'Clientes',
                'permiso' => 'listar-clientes',
                'modelo' => Cliente::class,
                'ruta_listado' => 'cliente',
                'ruta_detalle' => 'editar_cliente',
                'permiso_detalle' => 'editar-cliente',
                'duplicados' => [
                    'numerodocumento' => 'Documento',
                    'codigo' => 'Código',
                ],
                'localidad' => [
                    'tipo' => 'relation',
                    'relation' => 'localidades',
                    'relation_column' => 'nombre',
                ],
                'columnas_lista' => ['codigo', 'nombre', 'numerodocumento', 'localidad', 'fantasia'],
                'etiquetas' => [
                    'codigo' => 'Código',
                    'nombre' => 'Nombre',
                    'numerodocumento' => 'Documento',
                    'localidad' => 'Localidad',
                    'fantasia' => 'Fantasía',
                    'cantidad' => 'Cant.',
                ],
            ],
            'empleado_sueldos' => [
                'nombre' => 'Empleados',
                'permiso' => 'listar-empleado-sueldos',
                'modelo' => Empleado_Sueldos::class,
                'ruta_listado' => 'consultar_empleado_sueldos',
                'ruta_detalle' => 'editar_empleado_sueldos',
                'permiso_detalle' => 'editar-empleado-sueldos',
                'duplicados' => [
                    'cuil' => 'CUIL',
                    'legajo' => 'Legajo',
                ],
                'localidad' => [
                    'tipo' => 'columna',
                    'columna' => 'localidad',
                ],
                'columnas_lista' => ['legajo', 'nombre', 'cuil', 'localidad'],
                'etiquetas' => [
                    'legajo' => 'Legajo',
                    'nombre' => 'Nombre',
                    'cuil' => 'CUIL',
                    'localidad' => 'Localidad',
                    'cantidad' => 'Cant.',
                ],
            ],
        ];
    }

    /**
     * @return array{ok: bool, patron: string, entidad: string, campo?: string, valor?: string, interpretacion: string}|null
     */
    public static function resolverDesdeNorm(string $norm, string $textoOriginal = ''): ?array
    {
        $norm = self::normalizar($norm);
        if ($norm === '') {
            return null;
        }

        // Duplicados
        if (self::contieneAlguno($norm, [
            'cuit duplicado', 'cuit duplicados', 'cuil duplicado', 'cuil duplicados',
            'documento duplicado', 'documentos duplicados', 'duplicados de cuit',
            'con cuit duplicado', 'con cuil duplicado', 'con documento duplicado',
            'codigos duplicados', 'códigos duplicados', 'legajos duplicados',
        ]) || (str_contains($norm, 'duplicad') && self::contieneAlguno($norm, [
            'cuit', 'cuil', 'documento', 'codigo', 'código', 'legajo', 'proveedor', 'cliente', 'empleado',
        ]))) {
            $entidad = self::resolverEntidadDesdeNorm($norm) ?? 'proveedor';
            $cat = self::catalogo()[$entidad] ?? null;
            if ($cat === null) {
                return null;
            }
            $campo = self::resolverCampoDuplicado($norm, $cat['duplicados']);
            $etiqueta = $cat['duplicados'][$campo] ?? $campo;

            return [
                'ok' => true,
                'patron' => self::PATRON_DUPLICADOS,
                'entidad' => $entidad,
                'campo' => $campo,
                'interpretacion' => $cat['nombre'].' con '.$etiqueta.' duplicado',
            ];
        }

        // Por localidad: «proveedores de lanus», «clientes en avellaneda», «empleados de quilmes»
        if (preg_match(
            '/\b(proveedores?|clientes?|empleados?)\s+(?:de|del|en|de\s+la\s+ciudad\s+de)\s+([a-z0-9áéíóúüñ\.\-\s]{2,40})$/u',
            $norm,
            $m
        ) === 1
            || preg_match(
                '/\b(proveedores?|clientes?|empleados?)\s+(?:de|del|en)\s+([a-z0-9áéíóúüñ\.\-]{2,40})\b/u',
                $norm,
                $m
            ) === 1
        ) {
            $entidad = self::entidadDesdeToken($m[1]);
            $localidad = self::limpiarLocalidad($m[2]);
            if ($entidad === null || $localidad === '' || self::esRuidoLocalidad($localidad)) {
                return null;
            }
            $cat = self::catalogo()[$entidad] ?? null;
            if ($cat === null || ($cat['localidad'] ?? null) === null) {
                return null;
            }

            return [
                'ok' => true,
                'patron' => self::PATRON_POR_LOCALIDAD,
                'entidad' => $entidad,
                'valor' => $localidad,
                'interpretacion' => $cat['nombre'].' de '.$localidad,
            ];
        }

        // «en lanus» / «localidad lanus» con entidad mencionada
        if (self::contieneAlguno($norm, ['localidad', 'partido', 'ciudad'])
            && preg_match('/(?:localidad|partido|ciudad)\s+([a-z0-9áéíóúüñ\.\-\s]{2,40})/u', $norm, $mLoc) === 1
        ) {
            $entidad = self::resolverEntidadDesdeNorm($norm);
            $localidad = self::limpiarLocalidad($mLoc[1]);
            if ($entidad !== null && $localidad !== '' && ! self::esRuidoLocalidad($localidad)) {
                $cat = self::catalogo()[$entidad] ?? null;
                if ($cat !== null && ($cat['localidad'] ?? null) !== null) {
                    return [
                        'ok' => true,
                        'patron' => self::PATRON_POR_LOCALIDAD,
                        'entidad' => $entidad,
                        'valor' => $localidad,
                        'interpretacion' => $cat['nombre'].' de '.$localidad,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function ejecutar(array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_PATRON_ANALITICO;
        $patron = strtolower(trim((string) ($params['patron'] ?? '')));
        $entidad = strtolower(trim((string) ($params['entidad'] ?? '')));

        if ($patron === '' || $entidad === '') {
            $desde = self::resolverDesdeNorm(
                self::normalizar((string) ($params['pregunta'] ?? $params['valor'] ?? '')),
                (string) ($params['pregunta'] ?? $params['valor'] ?? '')
            );
            if ($desde !== null) {
                $patron = $desde['patron'];
                $entidad = $desde['entidad'];
                $params['campo'] = $params['campo'] ?? ($desde['campo'] ?? null);
                $params['valor'] = $params['valor'] ?? ($desde['valor'] ?? null);
            }
        }

        $cat = self::catalogo()[$entidad] ?? null;
        if ($cat === null) {
            return self::fallo($intent, 'Entidad no soportada para patrones analíticos.');
        }
        if (! can($cat['permiso'], false) && empty($params['_mcp_bridge'])) {
            return self::fallo($intent, 'Sin permiso para listar '.$cat['nombre'].'.');
        }

        return match ($patron) {
            self::PATRON_DUPLICADOS => self::ejecutarDuplicados($intent, $entidad, $cat, $params),
            self::PATRON_POR_LOCALIDAD => self::ejecutarPorLocalidad($intent, $entidad, $cat, $params),
            default => self::fallo($intent, 'Patrón no soportado. Use duplicados o por localidad.'),
        };
    }

    /**
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private static function ejecutarDuplicados(string $intent, string $entidad, array $cat, array $params): array
    {
        $campo = strtolower(trim((string) ($params['campo'] ?? '')));
        $permitidos = $cat['duplicados'];
        if ($campo === '' || ! isset($permitidos[$campo])) {
            $campo = array_key_first($permitidos) ?: '';
        }
        if ($campo === '' || ! isset($permitidos[$campo])) {
            return self::fallo($intent, 'Campo de duplicados no permitido para '.$cat['nombre'].'.');
        }

        /** @var class-string $modelo */
        $modelo = $cat['modelo'];
        $tabla = (new $modelo)->getTable();
        if (! Schema::hasColumn($tabla, $campo)) {
            return self::fallo($intent, 'Campo '.$campo.' no existe en '.$tabla.'.');
        }

        $max = isset($params['max_lineas']) ? max(1, min(self::MAX_FILAS, (int) $params['max_lineas'])) : 20;

        try {
            $dupQuery = DB::table($tabla)
                ->select($campo, DB::raw('COUNT(*) as cantidad'))
                ->whereNotNull($campo)
                ->where($campo, '!=', '')
                ->groupBy($campo)
                ->havingRaw('COUNT(*) > 1')
                ->orderByDesc('cantidad');

            if (Schema::hasColumn($tabla, 'deleted_at')) {
                $dupQuery->whereNull('deleted_at');
            }

            $grupos = $dupQuery->limit(200)->get();
            $valores = $grupos->pluck($campo)->all();
            $mapaCant = [];
            foreach ($grupos as $g) {
                $mapaCant[(string) $g->{$campo}] = (int) $g->cantidad;
            }

            $filas = [];
            $totalRegistros = 0;
            if ($valores !== []) {
                /** @var Builder $q */
                $q = $modelo::query()->whereIn($campo, $valores)->orderBy($campo)->orderBy('id');
                self::eagerLocalidad($q, $cat);
                $totalRegistros = (clone $q)->count();
                foreach ($q->limit($max)->get() as $row) {
                    $filas[] = self::filaLista($row, $cat, $campo, $mapaCant);
                }
            }
        } catch (Throwable $e) {
            return self::fallo($intent, 'No se pudo calcular duplicados: '.$e->getMessage());
        }

        $etiqueta = $permitidos[$campo];
        $parrafos = [
            $cat['nombre'].' con '.$etiqueta.' duplicado',
            'Grupos duplicados: '.$grupos->count().' · registros: '.$totalRegistros,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay '.$etiqueta.' duplicados.';
        }

        return self::ok($intent, $parrafos, $filas, $cat, [
            'patron' => self::PATRON_DUPLICADOS,
            'entidad' => $entidad,
            'campo' => $campo,
            'grupos' => $grupos->count(),
            'total' => $totalRegistros,
        ], array_merge(
            array_map(static fn ($k) => ['key' => $k, 'label' => $cat['etiquetas'][$k] ?? $k], $cat['columnas_lista']),
            [['key' => 'cantidad', 'label' => 'Cant.']]
        ));
    }

    /**
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private static function ejecutarPorLocalidad(string $intent, string $entidad, array $cat, array $params): array
    {
        $localidad = self::limpiarLocalidad((string) ($params['valor'] ?? $params['localidad'] ?? ''));
        if ($localidad === '') {
            return self::fallo($intent, 'Indique la localidad (ej.: «proveedores de Lanús»).');
        }
        $cfg = $cat['localidad'] ?? null;
        if (! is_array($cfg)) {
            return self::fallo($intent, $cat['nombre'].' no tiene patrón de localidad.');
        }

        $max = isset($params['max_lineas']) ? max(1, min(self::MAX_FILAS, (int) $params['max_lineas'])) : 20;
        $like = '%'.self::escapeLike(self::normalizar($localidad)).'%';

        try {
            /** @var class-string $modelo */
            $modelo = $cat['modelo'];
            /** @var Builder $q */
            $q = $modelo::query();
            self::eagerLocalidad($q, $cat);

            if (($cfg['tipo'] ?? '') === 'relation') {
                $rel = (string) ($cfg['relation'] ?? 'localidades');
                $col = (string) ($cfg['relation_column'] ?? 'nombre');
                $q->whereHas($rel, function ($sub) use ($col, $like) {
                    $sub->whereRaw(
                        'LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE('.$col.",'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u')) LIKE ?",
                        [$like]
                    );
                });
            } else {
                $col = (string) ($cfg['columna'] ?? 'localidad');
                $q->whereRaw(
                    'LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE('.$col.",'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u')) LIKE ?",
                    [$like]
                );
            }

            $total = (clone $q)->count();
            $filas = [];
            foreach ($q->orderBy('id')->limit($max)->get() as $row) {
                $filas[] = self::filaLista($row, $cat, null, []);
            }
        } catch (Throwable $e) {
            return self::fallo($intent, 'No se pudo filtrar por localidad: '.$e->getMessage());
        }

        $parrafos = [
            $cat['nombre'].' de '.$localidad,
            'Cantidad: '.$total,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay registros en esa localidad.';
        }

        $columnas = array_map(
            static fn ($k) => ['key' => $k, 'label' => $cat['etiquetas'][$k] ?? $k],
            $cat['columnas_lista']
        );

        return self::ok($intent, $parrafos, $filas, $cat, [
            'patron' => self::PATRON_POR_LOCALIDAD,
            'entidad' => $entidad,
            'valor' => $localidad,
            'total' => $total,
        ], $columnas);
    }

    /**
     * @param  array<string, mixed>  $cat
     * @param  array<string, int>  $mapaCant
     * @return array<string, string>
     */
    private static function filaLista(object $row, array $cat, ?string $campoDup, array $mapaCant): array
    {
        $out = [];
        foreach ($cat['columnas_lista'] as $key) {
            if ($key === 'localidad') {
                $out[$key] = mb_substr(self::nombreLocalidad($row, $cat), 0, 40);
                continue;
            }
            $out[$key] = mb_substr((string) ($row->{$key} ?? ''), 0, 40);
        }
        if ($campoDup !== null) {
            $v = (string) ($row->{$campoDup} ?? '');
            $out['cantidad'] = (string) ($mapaCant[$v] ?? '');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cat
     */
    private static function nombreLocalidad(object $row, array $cat): string
    {
        $cfg = $cat['localidad'] ?? null;
        if (! is_array($cfg)) {
            return '';
        }
        if (($cfg['tipo'] ?? '') === 'relation') {
            $rel = (string) ($cfg['relation'] ?? 'localidades');

            return (string) (optional($row->{$rel})->nombre ?? '');
        }

        return (string) ($row->{$cfg['columna'] ?? 'localidad'} ?? '');
    }

    /**
     * @param  array<string, mixed>  $cat
     */
    private static function eagerLocalidad(Builder $q, array $cat): void
    {
        $cfg = $cat['localidad'] ?? null;
        if (is_array($cfg) && ($cfg['tipo'] ?? '') === 'relation') {
            $q->with([(string) ($cfg['relation'] ?? 'localidades')]);
        }
    }

    /**
     * @param  list<string>  $parrafos
     * @param  list<array<string, string>>  $filas
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $datos
     * @param  list<array{key: string, label: string}>  $columnas
     * @return array<string, mixed>
     */
    private static function ok(
        string $intent,
        array $parrafos,
        array $filas,
        array $cat,
        array $datos,
        array $columnas
    ): array {
        $links = [];
        try {
            $links[] = [
                'etiqueta' => 'Listado '.$cat['nombre'],
                'url' => route($cat['ruta_listado']),
            ];
        } catch (Throwable) {
            //
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.91,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => $columnas,
                'filas' => $filas,
            ],
            'datos' => $datos,
        ];
    }

    /** @return array<string, mixed> */
    private static function fallo(string $intent, string $msg): array
    {
        return [
            'ok' => false,
            'intent' => $intent,
            'score' => 0.0,
            'parrafos' => [],
            'links' => [],
            'datos' => [],
            'error' => $msg,
        ];
    }

    /** @param  array<string, string>  $campos */
    private static function resolverCampoDuplicado(string $norm, array $campos): string
    {
        if (isset($campos['cuil']) && self::contieneAlguno($norm, ['cuit', 'cuil'])) {
            return 'cuil';
        }
        if (isset($campos['nroinscripcion']) && self::contieneAlguno($norm, ['cuit', 'cuil', 'inscripcion', 'nroinscripcion'])) {
            return 'nroinscripcion';
        }
        if (isset($campos['numerodocumento']) && self::contieneAlguno($norm, ['documento', 'dni', 'cuit'])) {
            return 'numerodocumento';
        }
        if (isset($campos['legajo']) && self::contieneAlguno($norm, ['legajo'])) {
            return 'legajo';
        }
        if (isset($campos['codigo']) && self::contieneAlguno($norm, ['codigo', 'código'])) {
            return 'codigo';
        }

        return array_key_first($campos) ?: '';
    }

    private static function resolverEntidadDesdeNorm(string $norm): ?string
    {
        if (self::contieneAlguno($norm, ['empleado', 'empleados', 'legajo', 'cuil'])) {
            return 'empleado_sueldos';
        }
        if (self::contieneAlguno($norm, ['cliente', 'clientes'])) {
            return 'cliente';
        }
        if (self::contieneAlguno($norm, ['proveedor', 'proveedores', 'cuit'])) {
            return 'proveedor';
        }

        return null;
    }

    private static function entidadDesdeToken(string $tok): ?string
    {
        $t = self::normalizar($tok);

        return match (true) {
            str_starts_with($t, 'proveedor') => 'proveedor',
            str_starts_with($t, 'cliente') => 'cliente',
            str_starts_with($t, 'empleado') => 'empleado_sueldos',
            default => null,
        };
    }

    private static function limpiarLocalidad(string $raw): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        $t = preg_replace('/\b(duplicad\w*|cuit|cuil|documento|codigo|código)\b/ui', '', $t) ?? $t;

        return trim($t, " \t\n\r\0\x0B.,;");
    }

    private static function esRuidoLocalidad(string $loc): bool
    {
        $n = self::normalizar($loc);

        return in_array($n, [
            'la', 'el', 'los', 'las', 'de', 'del', 'en', 'un', 'una',
            'pago', 'pagos', 'compra', 'compras', 'venta', 'ventas',
            'firma', 'aprobacion', 'aprobación', 'mes', 'hoy',
        ], true) || mb_strlen($n) < 2;
    }

    private static function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');

        return strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /** @param  list<string>  $needles */
    private static function contieneAlguno(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($haystack, self::normalizar($n))) {
                return true;
            }
        }

        return false;
    }

    private static function escapeLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $valor);
    }
}
