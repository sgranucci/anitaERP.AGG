<?php

namespace App\Support\Listado;

use Illuminate\Http\Request;

/**
 * Memoria de filtros de listados en sesión (última consulta del usuario).
 *
 * Patrón: con query de filtros se persiste; sin query se restaura; Limpiar olvida.
 * No usar localStorage ni meter chips externos en listado_vista.
 */
class ListadoFiltrosMemoriaSupport
{
    /**
     * Claves GET habituales de workbench / CRUD que indican “ya hay filtros en la URL”.
     *
     * @var list<string>
     */
    public const KEYS_TRAE_FILTROS_DEFAULT = [
        'filtro_valor',
        'filtro_campo',
        'filtro_operador',
        'filtro_valor_hasta',
        'filtro_busqueda_rapida',
        'filtro_estado',
        'filtro_modo',
        'filtro_codigo',
        'estado',
        'estado_todos',
        'empresa_id',
        'empresa_ids',
        'empresa_todas',
        'empresa_scope',
        'fecha_desde',
        'fecha_hasta',
        'page',
        'qbe',
        'sort',
        'group',
        'vista_id',
        'vista_estandar',
        'vista_modo',
        'graficos',
        'grafico',
        'grafico_click',
        'grafico_off',
        'formato',
        'calculadas',
        'aplicar_qbe',
        'quitar_qbe',
        'quitar_orden',
        'columnas',
        'busqueda',
        'consultar',
        'alcance',
        'tratamiento',
        'madre_hija',
        'origen',
        'consolidar',
        'apellido_nombre',
        'legajo_desde',
        'legajo_hasta',
        'tipo_sancion_id',
        'motivo_sancion_id',
        'solo_con_comentario',
    ];

    /**
     * @param  array<string, mixed>  $filtrosQuery
     */
    public static function persistir(string $clave, array $filtrosQuery): void
    {
        $limpio = self::sanitizarQuery($filtrosQuery);
        if ($limpio === []) {
            self::olvidar($clave);

            return;
        }

        session([$clave => $limpio]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function restaurar(string $clave): array
    {
        $guardados = session($clave, []);

        return is_array($guardados) ? self::sanitizarQuery($guardados) : [];
    }

    public static function olvidar(string $clave): void
    {
        session()->forget($clave);
    }

    /**
     * True si la request trae parámetros de listado (no es una visita “desnuda”).
     *
     * @param  list<string>|null  $keys
     */
    public static function requestTraeFiltros(Request $request, ?array $keys = null): bool
    {
        if ($request->boolean('limpiar_filtros') || $request->boolean('filtro_limpiar')) {
            return true;
        }

        // Sin lista explícita: cualquier parámetro GET cuenta (p. ej. solicitudpago_id, tipos, ordentrabajo_id),
        // así un link con query propia nunca se pisa con la memoria. Solo la URL “desnuda” restaura.
        if ($keys === null) {
            return $request->query->count() > 0;
        }

        foreach ($keys as $key) {
            if ($request->query->has($key) || $request->request->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True si el array (p. ej. query guardada en sesión) contiene alguna clave que
     * {@see requestTraeFiltros} reconoce; evita loops de redirect al restaurar.
     *
     * @param  array<string, mixed>  $query
     * @param  list<string>|null  $keys
     */
    public static function queryTraeFiltros(array $query, ?array $keys = null): bool
    {
        if ($keys === null) {
            return $query !== [];
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $query)) {
                return true;
            }
        }

        return false;
    }

    public static function solicitudLimpia(Request $request): bool
    {
        return $request->boolean('limpiar_filtros')
            || $request->boolean('filtro_limpiar')
            || FiltrosListadoRequest::solicitudLimpiaFiltros($request);
    }

    /**
     * @param  array<string, mixed>  $filtrosQuery
     * @return array<string, mixed>
     */
    public static function sanitizarQuery(array $filtrosQuery): array
    {
        $out = [];
        foreach ($filtrosQuery as $key => $valor) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            // No persistir flags de limpieza ni de una sola vez.
            if (in_array($key, ['limpiar_filtros', 'filtro_limpiar', 'quitar_orden', '_token'], true)) {
                continue;
            }
            if (is_array($valor)) {
                $out[$key] = $valor;
                continue;
            }
            if (is_bool($valor)) {
                $out[$key] = $valor ? 1 : 0;
                continue;
            }
            if (is_int($valor) || is_float($valor)) {
                $out[$key] = $valor;
                continue;
            }
            if (is_string($valor)) {
                $out[$key] = $valor;
            }
        }

        return $out;
    }
}
