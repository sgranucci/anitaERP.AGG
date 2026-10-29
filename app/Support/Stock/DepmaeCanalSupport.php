<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Canal del depósito, el mismo corte Local / Fábrica de los artículos.
 * El stock por OT arma los grupos Locales y Fábrica con este dato.
 */
final class DepmaeCanalSupport
{
    public const LOCAL = 'LOCAL';

    public const FABRICA = 'FABRICA';

    public static function columnaDisponible(): bool
    {
        return Schema::hasTable('depmae') && Schema::hasColumn('depmae', 'canal');
    }

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return [
            self::LOCAL => 'Local',
            self::FABRICA => 'Fábrica',
        ];
    }

    public static function normalizar(?string $canal): string
    {
        $canal = strtoupper(trim((string) $canal));

        return array_key_exists($canal, self::opciones()) ? $canal : self::FABRICA;
    }

    public static function etiqueta(?string $canal): string
    {
        return self::opciones()[self::normalizar($canal)];
    }

    /**
     * @return list<int>
     */
    public static function idsLocales(): array
    {
        if (! self::columnaDisponible()) {
            return [];
        }

        return Depmae::query()
            ->where('canal', self::LOCAL)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    public static function esLocal(int $depositoId): bool
    {
        if ($depositoId <= 0 || ! self::columnaDisponible()) {
            return false;
        }

        return Depmae::query()
            ->whereKey($depositoId)
            ->where('canal', self::LOCAL)
            ->exists();
    }

    /**
     * El depósito elegido en un local queda en canal Local.
     */
    public static function marcarComoLocal(int $depositoId): void
    {
        if (! EntornoEmpresaSupport::esFerli() || $depositoId <= 0 || ! self::columnaDisponible()) {
            return;
        }

        $deposito = Depmae::query()->find($depositoId);
        if ($deposito === null || (string) $deposito->canal === self::LOCAL) {
            return;
        }

        $deposito->canal = self::LOCAL;
        $deposito->save();
    }
}
