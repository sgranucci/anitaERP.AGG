<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class AperturaGastoEmpresa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'apertura_gasto_empresa';

    protected $fillable = [
        'apertura_gasto_id',
        'empresa_id',
        'cuentacontable_id',
        'cuentacontable_contrapartida_id',
        'centrocosto_id',
    ];

    public function aperturaGasto()
    {
        return $this->belongsTo(AperturaGasto::class, 'apertura_gasto_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontable()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function cuentacontableContrapartida()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_contrapartida_id');
    }

    public function centrocosto()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
