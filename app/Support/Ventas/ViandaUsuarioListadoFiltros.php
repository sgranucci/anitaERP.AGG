<?php

namespace App\Support\Ventas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ViandaUsuarioListadoFiltros
{
    /** @var list<string> */
    public const CAMPOS = ['codigo_usuario', 'nombre', 'estado', 'tipo_usuario'];

    /**
     * @return array{codigo_usuario:?string, nombre:?string, estado:?string, tipo_usuario:?string, busqueda:?string}
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $busqueda = trim((string) ($request->input('busqueda') ?? $busquedaRuta ?? ''));

        return [
            'codigo_usuario' => self::normalizarTexto($request->input('codigo_usuario')),
            'nombre' => self::normalizarTexto($request->input('nombre')),
            'estado' => self::normalizarTexto($request->input('estado')),
            'tipo_usuario' => self::normalizarTexto($request->input('tipo_usuario')),
            'busqueda' => $busqueda !== '' ? $busqueda : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): Builder
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return $query;
        }

        if (! empty($filtros['codigo_usuario'])) {
            $query->where('codigo_usuario', 'like', '%'.self::escLike($filtros['codigo_usuario']).'%');
        }

        if (! empty($filtros['nombre'])) {
            $query->where('nombre', 'like', '%'.self::escLike($filtros['nombre']).'%');
        }

        if (! empty($filtros['estado']) && in_array($filtros['estado'], ['A', 'I'], true)) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['tipo_usuario']) && ViandaUsuarioTipoSupport::tipoValido($filtros['tipo_usuario'])) {
            $query->where('tipo_usuario', strtoupper($filtros['tipo_usuario']));
        }

        if (! empty($filtros['busqueda'])) {
            $term = self::escLike($filtros['busqueda']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('nombre', 'like', '%'.$term.'%')
                    ->orWhere('codigo_usuario', 'like', '%'.$term.'%');
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['codigo_usuario', 'nombre', 'estado', 'tipo_usuario', 'busqueda'] as $key) {
            if (! empty($filtros[$key])) {
                $out[$key] = (string) $filtros[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        foreach (['codigo_usuario', 'nombre', 'estado', 'tipo_usuario', 'busqueda'] as $key) {
            if (! empty($filtros[$key])) {
                return true;
            }
        }

        return false;
    }

    private static function normalizarTexto(mixed $value): ?string
    {
        $texto = trim((string) ($value ?? ''));

        return $texto !== '' ? $texto : null;
    }

    private static function escLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
