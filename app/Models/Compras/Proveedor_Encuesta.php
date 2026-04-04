<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Encuesta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected $fillable = ['proveedor_id', 'encuesta_id', 'fecha', 'comentario', 'origen'];
    protected $table = 'proveedor_encuesta';

	public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id');
	}

    public function proveedor_encuesta_preguntas()
	{
    	return $this->hasMany(Proveedor_Encuesta_Pregunta::class, 'proveedor_encuesta_id');
	}    

}
