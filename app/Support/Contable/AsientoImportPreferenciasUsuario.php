<?php

namespace App\Support\Contable;

use Illuminate\Support\Facades\Cache;

/**
 * Preferencias de la pantalla de importación de asientos (por usuario).
 */
final class AsientoImportPreferenciasUsuario
{
    private const CACHE_KEY = 'asiento-import-preferencias-v1';

    /** @var list<string> */
    private const CAMPOS = [
        'modo_columnas',
        'modo_importe',
        'csv_separador',
        'col_cuenta',
        'col_debe',
        'col_haber',
        'col_monto',
        'col_dh',
        'col_centrocosto',
        'col_moneda',
        'col_cotizacion',
        'col_detalle',
        'fila_encabezado',
        'moneda_id',
        'tipoasiento_id',
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
     * @param  array<string, mixed>  $entrada
     */
    public static function persistir(array $entrada): void
    {
        if (! auth()->id()) {
            return;
        }

        $actual = self::leer();
        $nuevo = $actual;

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
            $nuevo['modo_columnas'] = AsientoImportColumnasSupport::normalizarModoColumnas(
                (string) $nuevo['modo_columnas']
            );
        }
        if (isset($nuevo['modo_importe'])) {
            $nuevo['modo_importe'] = AsientoImportColumnasSupport::normalizarModoImporte(
                (string) $nuevo['modo_importe']
            );
        }
        if (array_key_exists('csv_separador', $nuevo) && $nuevo['csv_separador'] !== null && $nuevo['csv_separador'] !== '') {
            $normalizado = AsientoImportColumnasSupport::normalizarSeparadorCsv((string) $nuevo['csv_separador']);
            $nuevo['csv_separador'] = AsientoImportColumnasSupport::valorFormularioSeparadorCsv($normalizado);
        }
        if (array_key_exists('fila_encabezado', $nuevo) && $nuevo['fila_encabezado'] !== null && $nuevo['fila_encabezado'] !== '') {
            $nuevo['fila_encabezado'] = max(1, min(50, (int) $nuevo['fila_encabezado']));
        }
        if (array_key_exists('moneda_id', $nuevo) && $nuevo['moneda_id'] !== null && $nuevo['moneda_id'] !== '') {
            $nuevo['moneda_id'] = (int) $nuevo['moneda_id'];
        }
        if (array_key_exists('tipoasiento_id', $nuevo) && $nuevo['tipoasiento_id'] !== null && $nuevo['tipoasiento_id'] !== '') {
            $nuevo['tipoasiento_id'] = (int) $nuevo['tipoasiento_id'];
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
