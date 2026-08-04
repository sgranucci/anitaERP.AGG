<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recuento_Item extends Model
{
    protected $table = 'recuento_item';

    protected $fillable = [
        'recuento_id',
        'articulo_id',
        'color_id',
        'talle_id',
        'detalle',
        'unidadmedida_id',
        'saldo_sistema',
        'cantidad_contada',
    ];

    protected $casts = [
        'color_id' => 'integer',
        'talle_id' => 'integer',
        'saldo_sistema' => 'float',
        'cantidad_contada' => 'float',
    ];

    public function recuento(): BelongsTo
    {
        return $this->belongsTo(Recuento::class, 'recuento_id');
    }

    public function articulos(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function talle(): BelongsTo
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function unidadmedida(): BelongsTo
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_id');
    }

    public function diferencia(): float
    {
        return (float) $this->cantidad_contada - (float) $this->saldo_sistema;
    }
}
