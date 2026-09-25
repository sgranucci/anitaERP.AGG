<?php

namespace App\Models\Compras;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Comprobante_Proveedor_Debe_Gasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'comprobante_proveedor_debe_gasto';

    protected $fillable = [
        'comprobante_proveedor_id',
        'orden',
        'cuentacontable_id',
        'importe',
        'centrocosto_id',
    ];

    protected $casts = [
        'orden' => 'integer',
        'importe' => 'float',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
