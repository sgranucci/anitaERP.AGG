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
            && Schema::hasColumn('articulo', 'waitry_preparar');
    }

    public static function puedeOperar(): bool
    {
        return self::uiActiva() && can('enviar-articulo-waitry', false);
    }

    public static function skuEnviable(string $sku): bool
    {
        return self::uiActiva() && GastronomiaSkuCatalogoSupport::skuPermitido($sku);
    }

    public static function aplicarDesdeRequest(Request $request, int $articuloId): void
    {
        if (! self::uiActiva() || $articuloId <= 0 || ! $request->exists('waitry_preparar')) {
            return;
        }

        self::guardarMarcas($articuloId, $request->boolean('waitry_preparar'));
    }

    public static function guardarMarcas(int $articuloId, bool $preparar): void
    {
        if (! self::uiActiva() || $articuloId <= 0) {
            return;
        }

        DB::table('articulo')->where('id', $articuloId)->update([
            'waitry_preparar' => $preparar,
        ]);
    }

    /**
     * @return array{preparar:bool}
     */
    public static function marcasDe(?Articulo $articulo): array
    {
        if ($articulo === null) {
            return ['preparar' => true];
        }

        return [
            'preparar' => (bool) ($articulo->waitry_preparar ?? true),
        ];
    }
}
