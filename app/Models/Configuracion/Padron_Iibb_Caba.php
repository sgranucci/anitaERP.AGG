<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Padron_Iibb_Caba extends Model
{
    protected $fillable = ['id', 'cuit', 'nombre', 'desdefecha', 'hastafecha', 
                            'tasapercepcion', 'tasaretencion', 'tipocontribuyente'];
    protected $table = 'padron_iibb_caba';
}