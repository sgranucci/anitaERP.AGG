<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Mediopago extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigo', 'cuentacaja_id', 'empresa_id'];

    protected $table = 'mediopago';

    public function cuentacajas()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

}
