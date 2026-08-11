<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Ordencompra_Articulo_Entrega extends Model
{
    protected $table = 'ordencompra_articulo_entrega';

    protected $fillable = [
        'ordencompra_articulo_id',
        'fecha',
        'cantidad',
        'orden',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'float',
        'orden' => 'integer',
    ];

    public function ordencompra_articulo()
    {
        return $this->belongsTo(Ordencompra_Articulo::class, 'ordencompra_articulo_id');
    }
}
