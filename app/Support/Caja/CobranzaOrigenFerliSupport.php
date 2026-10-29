<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: separa cobranzas de Facturación Local (sucursales, tienda y marketplaces)
 * de los recibos de cuenta corriente cargados en administración.
 */
final class CobranzaOrigenFerliSupport
{
    public const TODAS = '';

    public const LOCALES = 'locales';

    public const ADMINISTRACION = 'administracion';

    public static function aplica(): bool
    {
        return EntornoEmpresaSupport::esFerli()
            && Schema::hasTable('local_venta')
            && Schema::hasTable('venta');
    }

    public static function normalizar(mixed $valor): string
    {
        if (! self::aplica()) {
            return self::TODAS;
        }

        $valor = strtolower(trim((string) $valor));

        return in_array($valor, [self::LOCALES, self::ADMINISTRACION], true)
            ? $valor
            : self::TODAS;
    }

    public static function etiqueta(string $origen): string
    {
        return match ($origen) {
            self::LOCALES => 'Locales',
            self::ADMINISTRACION => 'Administración',
            default => 'Todas',
        };
    }

    public static function aplicar(Builder $query, string $origen): void
    {
        $origen = self::normalizar($origen);
        if ($origen === self::TODAS) {
            return;
        }

        $existe = function ($sub): void {
            self::ventaDeLocal($sub);
        };

        if ($origen === self::LOCALES) {
            $query->whereExists($existe);

            return;
        }

        $query->whereNotExists($existe);
    }

    /**
     * La venta de la cobranza usa un punto de venta de algún local.
     */
    private static function ventaDeLocal($sub): void
    {
        $sub->selectRaw('1')
            ->from('venta as vloc')
            ->whereColumn('vloc.id', 'cobranza.venta_id')
            ->where(function ($w): void {
                $w->whereIn('vloc.puntoventa_id', function ($pv): void {
                    $pv->select('puntoventa_id')
                        ->from('local_venta')
                        ->whereNotNull('puntoventa_id');
                });
                if (Schema::hasTable('local_venta_puntoventa')) {
                    $w->orWhereIn('vloc.puntoventa_id', function ($pv): void {
                        $pv->select('puntoventa_id')->from('local_venta_puntoventa');
                    });
                }
            });
    }
}
