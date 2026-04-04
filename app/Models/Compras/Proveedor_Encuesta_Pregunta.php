<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Encuesta_Pregunta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected $fillable = ['proveedor_id', 'proveedor_encuesta_id', 'encuesta_id', 'encuesta_pregunta_id', 'puntaje'];
    protected $table = 'proveedor_encuesta_pregunta';

	public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id', 'id');
	}

	public function encuesta_preguntas()
	{
    	return $this->belongsTo(Encuesta_Pregunta::class, 'encuesta_pregunta_id', 'id');
	}

}
