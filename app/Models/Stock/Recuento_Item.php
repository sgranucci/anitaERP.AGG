<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Recuento_Item extends Model
{
    protected $table = 'recuento_item';

    protected $fillable = [
        'recuento_id',
        'articulo_id',
        'detalle',
        'unidadmedida_id',
        'saldo_sistema',
        'cantidad_contada',
    ];

    protected $casts = [
        'saldo_sistema' => 'float',
        'cantidad_contada' => 'float',
    ];

    public function recuento()
    {
        return $this->belongsTo(Recuento::class, 'recuento_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function unidadmedida()
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_id');
    }

    public function diferencia(): float
    {
        return (float) $this->cantidad_contada - (float) $this->saldo_sistema;
    }
}
