<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Padron_Iibb_Arba extends Model
{
    protected $fillable = ['id', 'cuit', 'desdefecha', 'hastafecha', 
                            'tasapercepcion', 'tasaretencion', 'tipocontribuyente'];
    protected $table = 'padron_iibb_arba';
}
