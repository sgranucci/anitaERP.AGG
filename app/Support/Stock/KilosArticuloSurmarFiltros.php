<?php

namespace App\Support\Stock;

use Illuminate\Http\Request;

/**
 * Filtros del listado de kilos por artículo de Surmar (remito, alta y recepción).
 */
final class KilosArticuloSurmarFiltros
{
    public const TIPO_TODOS = 'todos';

    public const TIPO_REM = 'rem';

    public const TIPO_AP = 'ap';

    public const TIPO_COM = 'com';

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_TODOS => 'Los tres (remito, alta y recepción)',
            self::TIPO_REM => 'Remito (REM)',
            self::TIPO_AP => 'Alta de producción (AP)',
            self::TIPO_COM => 'Recepción (COM)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $tipo = strtolower(trim((string) $request->input('tipo', self::TIPO_TODOS)));
        if (! array_key_exists($tipo, self::tipos())) {
            $tipo = self::TIPO_TODOS;
        }

        return [
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
            'tipo' => $tipo,
            'desde_sku' => self::sku($request->input('desde_sku')),
            'hasta_sku' => self::sku($request->input('hasta_sku')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'tipo' => ($filtros['tipo'] ?? self::TIPO_TODOS) !== self::TIPO_TODOS
                ? ($filtros['tipo'] ?? null)
                : self::TIPO_TODOS,
            'desde_sku' => $filtros['desde_sku'] ?? null,
            'hasta_sku' => $filtros['hasta_sku'] ?? null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $tipo = (string) ($filtros['tipo'] ?? self::TIPO_TODOS);
        $nombre = self::tipos()[$tipo] ?? self::tipos()[self::TIPO_TODOS];
        $partes = [
            'Surmar',
            $nombre,
            'Desde '.self::fechaHumana((string) ($filtros['fecha_desde'] ?? ''))
                .' hasta '.self::fechaHumana((string) ($filtros['fecha_hasta'] ?? '')),
        ];
        $desde = (string) ($filtros['desde_sku'] ?? '');
        $hasta = (string) ($filtros['hasta_sku'] ?? '');
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Artículos '.($desde !== '' ? $desde : '…').' a '.($hasta !== '' ? $hasta : '…');
        }

        return implode(' · ', $partes);
    }

    public static function fechaHumana(?string $ymd): string
    {
        $ymd = trim((string) $ymd);
        if ($ymd === '' || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return '…';
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }

    private static function sku(mixed $valor): string
    {
        return strtoupper(trim((string) $valor));
    }

    private static function fecha(mixed $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return '';
    }
}
