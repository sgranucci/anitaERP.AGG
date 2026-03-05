<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Condicioniva;
use App\Traits\Ventas\TransporteTrait;

class Transporte extends Model
{
    use TransporteTrait;
    protected $fillable = ['nombre', 'codigo', 'domicilio', 'provincia_id', 'localidad_id', 'codigopostal', 'telefono', 'email', 'nroinscripcion', 'condicioniva_id', 'patentevehiculo', 'patenteacoplado', 'horarioentrega',
                            'tipoexpreso', 'copiaremito', 'copiapedido'];

    protected $table = 'transporte';

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

