<?php

namespace App\Models\Caja\Estacionamiento;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CuentaEstacionamientoLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cuenta_estacionamiento_linea';

    protected $fillable = [
        'cuenta_estacionamiento_id',
        'numero_linea',
        'item_estacionamiento_id',
        'cantidad',
        'precio_unitario',
        'descripcion',
        'lista_precio_estacionamiento_item_id',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'precio_unitario' => 'float',
    ];

    protected $attributes = [
        'cantidad' => 1,
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaEstacionamiento::class, 'cuenta_estacionamiento_id');
    }

    public function itemEstacionamiento()
    {
        return $this->belongsTo(ItemEstacionamiento::class, 'item_estacionamiento_id');
    }

    public function listaPrecioItem()
    {
        return $this->belongsTo(ListaPrecioEstacionamientoItem::class, 'lista_precio_estacionamiento_item_id');
    }
}
