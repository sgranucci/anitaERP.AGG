<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Padron_Coeficiente_Tucuman extends Model
{
    protected $fillable = ['id', 'cuit', 'nombre', 'desdefecha', 'hastafecha', 
                            'coeficiente', 'coeficientefinal', 'tipocontribuyente', 'excluido'];
    protected $table = 'padron_coeficiente_tucuman';
}
