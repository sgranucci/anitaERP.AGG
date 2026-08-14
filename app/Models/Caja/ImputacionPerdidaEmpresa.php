<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ImputacionPerdidaEmpresa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'imputacion_perdida_empresa';

    protected $fillable = [
        'imputacion_perdida_id',
        'empresa_id',
        'cuentacontable_id',
    ];

    protected $casts = [
        'imputacion_perdida_id' => 'integer',
        'empresa_id' => 'integer',
        'cuentacontable_id' => 'integer',
    ];

    public function imputacionPerdida()
    {
        return $this->belongsTo(ImputacionPerdida::class, 'imputacion_perdida_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontable()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}
