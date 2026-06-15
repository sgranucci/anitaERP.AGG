<?php

namespace App\Models\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Database\Eloquent\Model;

class Comprobante_Proveedor_Recepcion extends Model
{
    protected $table = 'comprobante_proveedor_recepcion';

    protected $fillable = [
        'comprobante_proveedor_id', 'recepcion_proveedor_id', 'orden',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }
}
