<?php

namespace App\Models\Compras;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;

class Comprobante_Proveedor_Articulo extends Model
{
    protected $table = 'comprobante_proveedor_articulo';

    protected $fillable = [
        'comprobante_proveedor_id',
        'orden',
        'articulo_id',
        'sku',
        'codigo_proveedor',
        'descripcion',
        'cantidad',
        'precio_unitario',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'precio_unitario' => 'float',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
