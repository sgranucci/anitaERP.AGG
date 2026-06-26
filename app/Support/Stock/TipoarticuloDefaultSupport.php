<?php

namespace App\Support\Stock;

use App\Models\Stock\Tipoarticulo;

final class TipoarticuloDefaultSupport
{
    /**
     * Tipo de artículo por defecto para importaciones desde Anita (stkagr, etc.).
     */
    public static function idParaImportacionAnita(): int
    {
        $existente = Tipoarticulo::query()->orderBy('id')->value('id');
        if ($existente !== null) {
            return (int) $existente;
        }

        return (int) Tipoarticulo::query()->create([
            'nombre' => 'Mercadería',
            'abreviatura' => 'Z',
        ])->id;
    }
}
