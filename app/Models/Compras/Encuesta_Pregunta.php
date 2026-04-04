<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;

class Encuesta_Pregunta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected $fillable = [
                            'encuesta_id', 'nombre', 'desdepuntaje', 'hastapuntaje'
                        ];
    protected $table = 'encuesta_pregunta';

	public function encuestas()
	{
    	return $this->belongsTo(Encuesta::class, 'encuesta_id');
	}

}

