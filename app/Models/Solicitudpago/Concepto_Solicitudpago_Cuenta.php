<?php

namespace App\Models\Solicitudpago;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Solicitudpago_Cuenta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_solicitudpago_cuenta';

    protected $fillable = [
        'concepto_solicitudpago_id',
        'empresa_id',
        'cuentacontable_id',
        'centrocosto_id',
        'debe_haber',
    ];

    protected $casts = [
        'concepto_solicitudpago_id' => 'integer',
        'empresa_id' => 'integer',
        'cuentacontable_id' => 'integer',
        'centrocosto_id' => 'integer',
    ];

    public function conceptos()
    {
        return $this->belongsTo(Concepto_Solicitudpago::class, 'concepto_solicitudpago_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
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
