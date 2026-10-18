<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Contabilidad_CuentaAutomatica extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'contabilidad_cuenta_automatica';

    protected $fillable = [
        'empresa_id',
        'clave',
        'cuentacontable_id',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}
