<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Traits\Configuracion\ModeloetiquetaTrait;

class Modeloetiqueta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use ModeloetiquetaTrait;

    protected $fillable = ['nombre', 'codigoetiqueta', 'estado', 'creousuario_id'];
    protected $table = 'modeloetiqueta';

    public function creousuarios()
	{
    	return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

}

