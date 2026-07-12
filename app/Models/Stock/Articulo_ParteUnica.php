<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Articulo_ParteUnica extends Model
{
    protected $table = 'articulo_parte_unica';

    protected $fillable = [
        'articulo_id',
        'numeroparte',
        'estado',
        'fecha_baja',
        'motivo_baja',
        'movimientostock_id',
    ];

    protected $casts = [
        'fecha_baja' => 'datetime',
    ];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function movimientostock()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_id');
    }
}
