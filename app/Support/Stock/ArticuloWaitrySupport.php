<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ArticuloWaitrySupport
{
    public static function uiActiva(): bool
    {
        return EntornoEmpresaSupport::esAgg()
            && Schema::hasTable('articulo')
            && Schema::hasColumn('articulo', 'waitry_dispara_comanda')
            && Schema::hasColumn('articulo', 'waitry_va_cocina');
    }

    public static function skuEnviable(string $sku): bool
    {
        return self::uiActiva() && GastronomiaSkuCatalogoSupport::skuPermitido($sku);
    }

    public static function aplicarDesdeRequest(Request $request, int $articuloId): void
    {
        if (! self::uiActiva() || $articuloId <= 0) {
            return;
        }

        if (! $request->exists('waitry_dispara_comanda') && ! $request->exists('waitry_va_cocina')) {
            return;
        }

        self::guardarMarcas(
            $articuloId,
            $request->boolean('waitry_dispara_comanda'),
            $request->boolean('waitry_va_cocina')
        );
    }

    public static function guardarMarcas(int $articuloId, bool $disparaComanda, bool $vaCocina): void
    {
        if (! self::uiActiva() || $articuloId <= 0) {
            return;
        }

        DB::table('articulo')->where('id', $articuloId)->update([
            'waitry_dispara_comanda' => $disparaComanda,
            'waitry_va_cocina' => $vaCocina,
        ]);
    }

    /**
     * @return array{dispara_comanda:bool,va_cocina:bool}
     */
    public static function marcasDe(?Articulo $articulo): array
    {
        if ($articulo === null) {
            return ['dispara_comanda' => true, 'va_cocina' => true];
        }

        return [
            'dispara_comanda' => (bool) ($articulo->waitry_dispara_comanda ?? true),
            'va_cocina' => (bool) ($articulo->waitry_va_cocina ?? true),
        ];
    }
}
