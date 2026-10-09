<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Models\Listado\ListadoVista;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Vistas guardadas de listados (QBE grupos + columnas + sort + agrupación). Etapa 5/6 workbench.
 */
final class ListadoVistaSupport
{
    public static function tablasDisponibles(): bool
    {
        return Schema::hasTable('listado_vista');
    }

    /**
     * @return Collection<int, ListadoVista>
     */
    public static function listarParaUsuario(string $recurso, ?int $usuarioId): Collection
    {
        if (! self::tablasDisponibles() || $usuarioId === null || $usuarioId <= 0) {
            return collect();
        }

        return ListadoVista::query()
            ->where('recurso', $recurso)
            ->where(function ($q) use ($usuarioId) {
                $q->where('usuario_id', $usuarioId)
                    ->orWhere('compartida', true);
            })
            ->orderByDesc('es_default')
            ->orderBy('nombre')
            ->get();
    }

    public static function findParaUsuario(int $id, string $recurso, ?int $usuarioId): ?ListadoVista
    {
        if (! self::tablasDisponibles() || $usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        /** @var ListadoVista|null $vista */
        $vista = ListadoVista::query()
            ->whereKey($id)
            ->where('recurso', $recurso)
            ->where(function ($q) use ($usuarioId) {
                $q->where('usuario_id', $usuarioId)
                    ->orWhere('compartida', true);
            })
            ->first();

        return $vista;
    }

    /**
     * Guarda orden y agrupación en la vista activa (el selector vuelve a aplicarlos).
     *
     * @param  list<array{campo: string, dir: string}>  $orden
     * @param  list<string>  $agrupar
     */
    public static function recordarOrdenYAgrupar(ListadoVista $vista, array $orden, array $agrupar): void
    {
        if ((int) $vista->usuario_id !== (int) auth()->id()) {
            return;
        }

        $json = is_array($vista->filtros_json) ? $vista->filtros_json : [];
        $orden = array_values($orden);
        $agrupar = array_values($agrupar);
        if (($json['orden'] ?? []) == $orden && ($json['sort'] ?? []) == $orden && ($json['agrupar'] ?? []) == $agrupar) {
            return;
        }

        $json['orden'] = $orden;
        $json['sort'] = $orden;
        $json['agrupar'] = $agrupar;
        $vista->filtros_json = $json;
        $vista->save();
    }

    /**
     * El usuario apretó Buscar en el QBE o Quitar filtros. No cuenta la paginación ni un link de orden.
     */
    public static function envioQbeDeVista(Request $request): bool
    {
        if ($request->boolean('filtro_busqueda_rapida')) {
            return false;
        }

        return $request->boolean('quitar_qbe') || $request->boolean('aplicar_qbe');
    }

    /**
     * Lo que vino en el formulario manda sobre el QBE guardado en la vista, también si quedó vacío.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function prepararQbeContraVista(array $filtros, Request $request): array
    {
        if (! self::envioQbeDeVista($request)) {
            return $filtros;
        }

        $filtros['_qbe_explicito'] = true;
        if ($request->boolean('quitar_qbe') || ! ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            $filtros['qbe'] = ListadoQbeSupport::vacio();
            $filtros['modo'] = 'todos';
            $filtros['valor'] = '';
            $filtros['valor_hasta'] = '';
            $filtros['busqueda'] = '';
        }

        return $filtros;
    }

    /**
     * Graba el QBE en la vista del usuario. No toca una vista compartida de otro.
     *
     * @param  array<string, mixed>  $qbe
     */
    public static function recordarQbe(ListadoVista $vista, array $qbe): bool
    {
        if ((int) $vista->usuario_id !== (int) auth()->id()) {
            return false;
        }

        $tiene = ListadoQbeSupport::tieneCriterios($qbe);
        $modoGuardar = $tiene ? 'qbe' : 'todos';
        $qbeGuardar = $tiene ? $qbe : ListadoQbeSupport::vacio();
        $json = is_array($vista->filtros_json) ? $vista->filtros_json : [];
        if (($json['qbe'] ?? null) == $qbeGuardar && ($json['modo'] ?? 'todos') === $modoGuardar) {
            return true;
        }

        $json['qbe'] = $qbeGuardar;
        $json['modo'] = $modoGuardar;
        if ($modoGuardar !== 'qbe') {
            $json['valor'] = '';
            $json['busqueda'] = '';
            $json['valor_hasta'] = '';
        }
        $vista->filtros_json = $json;
        $vista->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function recordarQbeSiEnvio(?ListadoVista $vista, Request $request, array $filtros): bool
    {
        if ($vista === null || ! self::envioQbeDeVista($request)) {
            return false;
        }

        return self::recordarQbe($vista, (array) ($filtros['qbe'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnas
     */
    public static function guardar(
        string $recurso,
        int $usuarioId,
        string $nombre,
        array $filtros,
        array $columnas,
        bool $esDefault = false,
        bool $compartida = false,
        ?int $id = null
    ): ?ListadoVista {
        if (! self::tablasDisponibles() || $usuarioId <= 0) {
            return null;
        }

        $nombre = mb_substr(trim($nombre), 0, 120);
        if ($nombre === '') {
            return null;
        }

        if ($esDefault) {
            ListadoVista::query()
                ->where('recurso', $recurso)
                ->where('usuario_id', $usuarioId)
                ->update(['es_default' => false]);
        }

        $attrs = [
            'recurso' => $recurso,
            'usuario_id' => $usuarioId,
            'nombre' => $nombre,
            'filtros_json' => $filtros,
            'columnas_json' => array_values($columnas),
            'es_default' => $esDefault,
            'compartida' => $compartida,
        ];

        if ($id !== null && $id > 0) {
            $vista = ListadoVista::query()
                ->whereKey($id)
                ->where('recurso', $recurso)
                ->where('usuario_id', $usuarioId)
                ->first();
            if (! $vista) {
                return null;
            }
            $vista->fill($attrs);
            $vista->save();

            return $vista;
        }

        return ListadoVista::query()->create($attrs);
    }

    public static function eliminar(int $id, string $recurso, int $usuarioId): bool
    {
        if (! self::tablasDisponibles() || $usuarioId <= 0) {
            return false;
        }

        $vista = ListadoVista::query()
            ->whereKey($id)
            ->where('recurso', $recurso)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (! $vista) {
            return false;
        }

        ListadoVistaMenuSupport::quitarAlEliminarVista($vista);

        return (bool) $vista->delete();
    }

    public static function defaultDelUsuario(string $recurso, ?int $usuarioId): ?ListadoVista
    {
        if (! self::tablasDisponibles() || $usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        return ListadoVista::query()
            ->where('recurso', $recurso)
            ->where('usuario_id', $usuarioId)
            ->where('es_default', true)
            ->first();
    }
}
