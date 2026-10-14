<?php

namespace App\Models\Compras;

use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ordencompra_Legajo_Nc_Imputacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const MODO_OC = 'oc';

    public const MODO_CUENTA = 'cuenta';

    protected $table = 'ordencompra_legajo_nc_imputacion';

    protected $fillable = [
        'ordencompra_id',
        'precarga_comprobante_proveedor_id',
        'anita_scan_documento_id',
        'modo',
        'cuentacontable_id',
    ];

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}
