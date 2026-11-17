<?php

namespace App\Support\Sueldos;

use App\Support\Contable\AsientoImportColumnasSupport;
use Illuminate\Support\Facades\Cache;

/**
 * Preferencias de la pantalla de importación de novedades (por usuario).
 */
final class NovedadSueldosImportPreferenciasUsuario
{
    private const CACHE_KEY = 'novedad-sueldos-import-preferencias-v1';

    /** @var list<string> */
    private const CAMPOS = [
        'modo_columnas',
        'csv_separador',
        'fila_encabezado',
        'empresa_id',
        'mapping',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function leer(): array
    {
        if (! auth()->id()) {
            return [];
        }

        $cached = Cache::get(generaKey(self::CACHE_KEY));

        return is_array($cached) ? $cached : [];
    }

    public static function valor(string $campo, mixed $default = null): mixed
    {
        $prefs = self::leer();
        if (! array_key_exists($campo, $prefs)) {
            return $default;
        }

        $valor = $prefs[$campo];
        if ($valor === null || $valor === '') {
            return $default;
        }

        return $valor;
    }

    /**
     * @return array<string, string>
     */
    public static function mapping(): array
    {
        $raw = self::valor('mapping', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (NovedadSueldosImportColumnasSupport::campos() as $campo) {
            if (! array_key_exists($campo, $raw)) {
                continue;
            }
            $valor = trim((string) ($raw[$campo] ?? ''));
            if ($valor !== '') {
                $out[$campo] = $valor;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    public static function persistir(array $entrada): void
    {
        if (! auth()->id()) {
            return;
        }

        $nuevo = self::leer();

        foreach (self::CAMPOS as $campo) {
            if (! array_key_exists($campo, $entrada)) {
                continue;
            }
            $valor = $entrada[$campo];
            if ($valor === null) {
                $nuevo[$campo] = null;

                continue;
            }
            if (is_string($valor)) {
                $valor = trim($valor);
            }
            $nuevo[$campo] = $valor;
        }

        if (isset($nuevo['modo_columnas'])) {
            $nuevo['modo_columnas'] = NovedadSueldosImportColumnasSupport::normalizarModoColumnas(
                (string) $nuevo['modo_columnas']
            );
        }

        if (array_key_exists('csv_separador', $nuevo) && $nuevo['csv_separador'] !== null && $nuevo['csv_separador'] !== '') {
            $normalizado = AsientoImportColumnasSupport::normalizarSeparadorCsv((string) $nuevo['csv_separador']);
            $nuevo['csv_separador'] = AsientoImportColumnasSupport::valorFormularioSeparadorCsv($normalizado);
        }

        if (array_key_exists('fila_encabezado', $nuevo)) {
            if ($nuevo['fila_encabezado'] === null || $nuevo['fila_encabezado'] === '') {
                $nuevo['fila_encabezado'] = null;
            } else {
                $nuevo['fila_encabezado'] = max(1, min(50, (int) $nuevo['fila_encabezado']));
            }
        }

        if (array_key_exists('empresa_id', $nuevo)) {
            if ($nuevo['empresa_id'] === null || $nuevo['empresa_id'] === '' || (int) $nuevo['empresa_id'] <= 0) {
                $nuevo['empresa_id'] = null;
            } else {
                $nuevo['empresa_id'] = (int) $nuevo['empresa_id'];
            }
        }

        if (array_key_exists('mapping', $nuevo)) {
            $mapa = is_array($nuevo['mapping']) ? $nuevo['mapping'] : [];
            $limpio = [];
            foreach (NovedadSueldosImportColumnasSupport::campos() as $campo) {
                $valor = trim((string) ($mapa[$campo] ?? ''));
                if ($valor !== '') {
                    $limpio[$campo] = $valor;
                }
            }
            $nuevo['mapping'] = $limpio;
        }

        Cache::forever(generaKey(self::CACHE_KEY), $nuevo);
    }

    /**
     * @param  \Illuminate\Http\Request|array<string, mixed>  $request
     */
    public static function persistirDesdeRequest($request): void
    {
        $input = is_array($request) ? $request : $request->all();
        $payload = [];
        foreach (self::CAMPOS as $campo) {
            if (array_key_exists($campo, $input)) {
                $payload[$campo] = $input[$campo];
            }
        }
        if ($payload === []) {
            return;
        }

        self::persistir($payload);
    }
}
