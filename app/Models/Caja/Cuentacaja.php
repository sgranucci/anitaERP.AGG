<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Cuentacontable;
use App\Traits\Caja\CuentacajaTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cuentacaja extends Model implements Auditable
{
    use CuentacajaTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigo', 'tipocuenta', 'banco_id',
        'empresa_id', 'cuentacontable_id', 'moneda_id', 'cbu', 'cuenta_interbanking'];

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

    public function usocuentacajas()
    {
        return $this->belongsToMany(Usocuentacaja::class, 'cuentacaja_usocuentacaja', 'cuentacaja_id', 'usocuentacaja_id');
    }
}
