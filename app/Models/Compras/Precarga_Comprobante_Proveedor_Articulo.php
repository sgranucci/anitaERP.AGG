<?php

namespace App\Models\Compras;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;

class Precarga_Comprobante_Proveedor_Articulo extends Model
{
    protected $table = 'precarga_comprobante_proveedor_articulo';

    protected $fillable = [
        'precarga_comprobante_proveedor_id',
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

    public function precarga_comprobante_proveedor()
    {
        return $this->belongsTo(Precarga_Comprobante_Proveedor::class, 'precarga_comprobante_proveedor_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
