<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;

class Concepto_Ivacompra_Empresa extends Model
{
    protected $table = 'concepto_ivacompra_empresa';

    protected $fillable = [
        'concepto_ivacompra_id',
        'empresa_id',
        'cuentacontabledebe_id',
        'cuentacontablehaber_id',
    ];

    public function concepto_ivacompra()
    {
        return $this->belongsTo(Concepto_Ivacompra::class, 'concepto_ivacompra_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontabledebe()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontabledebe_id');
    }

    public function cuentacontablehaber()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontablehaber_id');
    }
}
