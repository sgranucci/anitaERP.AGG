<?php

namespace App\Support\Ventas;

use App\Models\Ventas\CotConfiguracion;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Modo operativo del COT ARBA: por_reparto (Bierzo) o por_guia (Ferli).
 * Persistido en cot_configuracion; no hardcodear por entorno fuera del seed.
 */
final class CotConfiguracionSupport
{
    public const MODO_POR_REPARTO = 'por_reparto';

    public const MODO_POR_GUIA = 'por_guia';

    private const CACHE_KEY = 'cot_configuracion.modo';

    public static function modo(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && self::esModoValido($cached)) {
            return $cached;
        }

        $modo = CotConfiguracion::query()->value('modo');
        if (! is_string($modo) || ! self::esModoValido($modo)) {
            $modo = self::modoDefaultEntorno();
        }

        Cache::forever(self::CACHE_KEY, $modo);

        return $modo;
    }

    public static function esPorGuia(): bool
    {
        return self::modo() === self::MODO_POR_GUIA;
    }

    public static function esPorReparto(): bool
    {
        return self::modo() === self::MODO_POR_REPARTO;
    }

    public static function modoDefaultEntorno(): string
    {
        return EntornoEmpresaSupport::esFerli()
            ? self::MODO_POR_GUIA
            : self::MODO_POR_REPARTO;
    }

    public static function esModoValido(string $modo): bool
    {
        return in_array($modo, [self::MODO_POR_REPARTO, self::MODO_POR_GUIA], true);
    }

    /**
     * @return list<array{valor: string, etiqueta: string, ayuda: string}>
     */
    public static function opcionesModo(): array
    {
        return [
            [
                'valor' => self::MODO_POR_REPARTO,
                'etiqueta' => 'Por reparto (remitos del día)',
                'ayuda' => 'Elige fecha y uno o más repartos; consulta remitos/facturas del día y presenta a ARBA. Flujo típico El Bierzo.',
            ],
            [
                'valor' => self::MODO_POR_GUIA,
                'etiqueta' => 'Por guía (control de remitos)',
                'ayuda' => 'Arma una guía numerada con facturas en grilla, guarda y envía a ARBA desde la misma pantalla. Flujo típico Ferli.',
            ],
        ];
    }

    public static function guardarModo(string $modo): CotConfiguracion
    {
        if (! self::esModoValido($modo)) {
            throw new \InvalidArgumentException('Modo COT inválido: '.$modo);
        }

        $row = CotConfiguracion::query()->first();
        if ($row === null) {
            $row = CotConfiguracion::query()->create([
                'modo' => $modo,
                'updated_by' => Auth::id(),
            ]);
        } else {
            $row->update([
                'modo' => $modo,
                'updated_by' => Auth::id(),
            ]);
        }

        Cache::forget(self::CACHE_KEY);
        Cache::forever(self::CACHE_KEY, $modo);

        return $row->fresh() ?? $row;
    }

    public static function etiquetaModo(?string $modo = null): string
    {
        $modo = $modo ?? self::modo();
        foreach (self::opcionesModo() as $op) {
            if ($op['valor'] === $modo) {
                return $op['etiqueta'];
            }
        }

        return $modo;
    }
}
