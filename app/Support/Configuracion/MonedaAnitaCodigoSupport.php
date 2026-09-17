<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Moneda;

/**
 * Código de moneda hacia Anita (compras/che_ban): numérico 1, 2, …
 *
 * No usar moneda.codigo ERP (PES/DOL): en Ferli muchas columnas son CHAR(1)
 * y quedan "P"/"D" — Anita no lista el movimiento.
 */
final class MonedaAnitaCodigoSupport
{
    /**
     * @param  Moneda|null  $moneda  Relación cargada (opcional)
     */
    public static function desdeMoneda(?Moneda $moneda, ?int $monedaId = null): string
    {
        $id = $monedaId !== null && $monedaId > 0
            ? $monedaId
            : (int) ($moneda?->id ?? 0);

        $codigoRaw = $moneda && filled($moneda->codigo)
            ? trim((string) $moneda->codigo)
            : null;

        if ($codigoRaw === null && $id > 0) {
            $codigoRaw = trim((string) (Moneda::query()->whereKey($id)->value('codigo') ?? ''));
            if ($codigoRaw === '') {
                $codigoRaw = null;
            }
        }

        if ($codigoRaw !== null) {
            $digits = preg_replace('/\D/', '', $codigoRaw) ?? '';
            // Solo aceptar si el código ERP es numérico (p.ej. "1"); PES/DOL → moneda_id.
            if ($digits !== '' && $digits === $codigoRaw) {
                return (string) max(1, (int) $digits);
            }
        }

        return (string) max(1, $id > 0 ? $id : 1);
    }

    /** Normaliza id ERP o texto (PES/1) a código Anita numérico. */
    public static function normalizar(mixed $valor, ?int $fallbackMonedaId = null): string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw !== '' && ctype_digit($raw)) {
            return (string) max(1, (int) $raw);
        }

        $fallback = $fallbackMonedaId !== null && $fallbackMonedaId > 0
            ? $fallbackMonedaId
            : 1;

        return self::desdeMoneda(null, $fallback);
    }
}
