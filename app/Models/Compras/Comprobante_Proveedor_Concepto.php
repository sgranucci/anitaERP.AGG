<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Comprobante_Proveedor_Concepto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'comprobante_proveedor_concepto';

    protected $fillable = [
        'comprobante_proveedor_id', 'concepto_ivacompra_id', 'orden', 'monto',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function concepto_ivacompras()
    {
        return $this->belongsTo(Concepto_Ivacompra::class, 'concepto_ivacompra_id');
    }
}
