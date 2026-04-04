<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Compras\EncuestaTrait;

class Encuesta extends Model implements Auditable
{
    protected $fillable = ['nombre', 'estado'];
    protected $table = 'encuesta';
    use \OwenIt\Auditing\Auditable;
    use EncuestaTrait;

	public function encuesta_preguntas()
	{
    	return $this->hasMany(Encuesta_Pregunta::class, 'encuesta_id');
	}
}
