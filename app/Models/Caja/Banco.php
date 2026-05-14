<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Banco extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigo', 'domicilio', 'provincia_id', 'localidad_id',
        'codigopostal', 'telefono', 'email', 'nroinscripcion', 'condicioniva_id'];

    protected $table = 'banco';

    public function localidades()
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function condicionivas()
    {
        return $this->belongsTo(Condicioniva::class, 'condicioniva_id');
    }
}
