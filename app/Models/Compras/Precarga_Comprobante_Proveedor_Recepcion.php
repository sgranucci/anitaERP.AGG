<?php

namespace App\Models\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Database\Eloquent\Model;

class Precarga_Comprobante_Proveedor_Recepcion extends Model
{
    protected $table = 'precarga_comprobante_proveedor_recepcion';

    protected $fillable = [
        'precarga_comprobante_proveedor_id',
        'recepcion_proveedor_id',
        'orden',
    ];

    public function precarga_comprobante_proveedores()
    {
        return $this->belongsTo(Precarga_Comprobante_Proveedor::class, 'precarga_comprobante_proveedor_id');
    }

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }
}
