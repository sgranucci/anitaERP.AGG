<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class Padron_Iibb_Tasa extends Model
{
    protected $fillable = ['id', 'padron_iibb_id', 'provincia_id', 'desdefecha', 'hastafecha', 
                            'tasapercepcion', 'tasaretencion', 'tasapercepciondiferencial', 'tasaretenciondiferencial', 
                            'coeficiente', 'riesgofiscal', 'tipocontribuyente', 'excluido'];
    protected $table = 'padron_iibb_tasa';

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

}

