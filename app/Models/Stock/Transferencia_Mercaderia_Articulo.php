<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Transferencia_Mercaderia_Articulo extends Model
{
    protected $table = 'transferencia_mercaderia_articulo';

    protected $fillable = [
        'transferencia_mercaderia_id',
        'item',
        'articulo_origen_id',
        'articulo_destino_id',
        'numeroparte',
        'cantidad_origen',
        'cantidad_destino',
        'caja',
        'pieza',
        'precio_costo_origen',
        'precio_costo_destino',
        'coeficienteconversion',
        'fl_conversion_formula',
    ];

    protected $casts = [
        'cantidad_origen' => 'float',
        'cantidad_destino' => 'float',
        'caja' => 'float',
        'pieza' => 'float',
        'precio_costo_origen' => 'float',
        'precio_costo_destino' => 'float',
        'coeficienteconversion' => 'float',
        'fl_conversion_formula' => 'boolean',
    ];

    public function transferencias()
    {
        return $this->belongsTo(Transferencia_Mercaderia::class, 'transferencia_mercaderia_id');
    }

    public function articuloOrigen()
    {
        return $this->belongsTo(Articulo::class, 'articulo_origen_id');
    }

    public function articuloDestino()
    {
        return $this->belongsTo(Articulo::class, 'articulo_destino_id');
    }
}
