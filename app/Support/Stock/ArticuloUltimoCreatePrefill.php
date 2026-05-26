<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\Auth;

class ArticuloUltimoCreatePrefill
{
    private const CAMPOS_VACIOS = [
        'id',
        'sku',
        'descripcion',
        'detalle',
        'leyenda',
        'foto',
        'created_at',
        'updated_at',
    ];

    public static function cargarProductoPrefill(): Articulo
    {
        $userId = Auth::id();
        if (! $userId) {
            return new Articulo;
        }

        $ultimo = Articulo::query()
            ->whereHas('articulo_estados', function ($q) use ($userId) {
                $q->where('usuario_id', $userId)
                    ->where('observacion', 'Alta de Artículo');
            })
            ->with('articulo_cuentacontables.cuentacontables')
            ->orderByDesc('id')
            ->first();

        if (! $ultimo) {
            return new Articulo;
        }

        $producto = new Articulo;
        $attrs = collect($ultimo->getAttributes())
            ->except(self::CAMPOS_VACIOS)
            ->all();
        $producto->forceFill($attrs);
        $producto->setRelation('articulo_cuentacontables', $ultimo->articulo_cuentacontables);
        $producto->setRelation('articulo_archivos', collect());

        return $producto;
    }
}
