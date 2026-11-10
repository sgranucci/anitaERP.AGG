<?php

namespace App\Support\Caja;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros de la bandeja de avisos de auditoría Crown.
 * Sin parámetros muestra los abiertos: es lo que hay que mirar.
 */
final class TesoreriaCrownAvisoListadoFiltros
{
    public const ESTADO_TODOS = 'T';

    /**
     * @return array{estado: string, severidad: string, regla: string, desde: string, hasta: string, texto: string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $estado = strtoupper(trim((string) $request->query('estado', TesoreriaCrownAvisoSupport::ABIERTO)));
        if ($estado !== self::ESTADO_TODOS && ! isset(TesoreriaCrownAvisoSupport::ESTADOS[$estado])) {
            $estado = TesoreriaCrownAvisoSupport::ABIERTO;
        }
        $severidad = strtoupper(trim((string) $request->query('severidad', '')));
        $regla = trim((string) $request->query('regla', ''));

        return [
            'estado' => $estado,
            'severidad' => isset(TesoreriaCrownAvisoSupport::SEVERIDADES[$severidad]) ? $severidad : '',
            'regla' => isset(TesoreriaCrownAvisoSupport::REGLAS[$regla]) ? $regla : '',
            'desde' => self::fecha((string) $request->query('desde', '')),
            'hasta' => self::fecha((string) $request->query('hasta', '')),
            'texto' => mb_substr(trim((string) $request->query('texto', '')), 0, 100),
        ];
    }

    /**
     * @param  array<string, string>  $filtros
     * @return array<string, string>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = array_filter($filtros, fn ($v) => $v !== '');
        if (($out['estado'] ?? '') === TesoreriaCrownAvisoSupport::ABIERTO) {
            unset($out['estado']);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::paraQueryString($filtros) !== [];
    }

    /**
     * @param  array<string, string>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['estado'] !== self::ESTADO_TODOS, fn ($q) => $q->where('estado', $filtros['estado']))
            ->when($filtros['severidad'] !== '', fn ($q) => $q->where('severidad', $filtros['severidad']))
            ->when($filtros['regla'] !== '', fn ($q) => $q->where('regla', $filtros['regla']))
            ->when($filtros['desde'] !== '', fn ($q) => $q->whereDate('fecha', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== '', fn ($q) => $q->whereDate('fecha', '<=', $filtros['hasta']))
            ->when($filtros['texto'] !== '', function ($q) use ($filtros) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filtros['texto']).'%';
                $q->where(fn ($w) => $w->where('titulo', 'like', $like)
                    ->orWhere('detalle', 'like', $like)
                    ->orWhere('comentario', 'like', $like));
            });
    }

    /**
     * @param  array<string, string>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [$filtros['estado'] === self::ESTADO_TODOS ? 'Todos los estados' : TesoreriaCrownAvisoSupport::estado($filtros['estado'])];
        if ($filtros['severidad'] !== '') {
            $partes[] = 'Severidad '.TesoreriaCrownAvisoSupport::severidad($filtros['severidad']);
        }
        if ($filtros['regla'] !== '') {
            $partes[] = TesoreriaCrownAvisoSupport::regla($filtros['regla']);
        }
        if ($filtros['desde'] !== '' || $filtros['hasta'] !== '') {
            $partes[] = 'Fecha '.($filtros['desde'] !== '' ? date('d/m/Y', strtotime($filtros['desde'])) : '…')
                .' a '.($filtros['hasta'] !== '' ? date('d/m/Y', strtotime($filtros['hasta'])) : '…');
        }
        if ($filtros['texto'] !== '') {
            $partes[] = 'Texto «'.$filtros['texto'].'»';
        }

        return implode(' · ', $partes);
    }

    private static function fecha(string $valor): string
    {
        $valor = trim($valor);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m)) {
            $valor = $m[3].'-'.$m[2].'-'.$m[1];
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : '';
    }
}
