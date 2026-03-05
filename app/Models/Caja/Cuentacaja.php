<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Cuentacontable;
use App\Models\Caja\Banco;
use App\Traits\Caja\CuentacajaTrait;

class Cuentacaja extends Model
{
    use CuentacajaTrait;

    protected $fillable = ['nombre', 'codigo', 'tipocuenta', 'banco_id', 
                            'empresa_id', 'cuentacontable_id', 'moneda_id', 'cbu'];
    protected $table = 'cuentacaja';

    public function bancos()
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}

