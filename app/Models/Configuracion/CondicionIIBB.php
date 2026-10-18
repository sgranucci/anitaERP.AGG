<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Configuracion\CondicionIIBBTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CondicionIIBB extends Model implements Auditable
{
	use CondicionIIBBTrait;

    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'formacalculo', 'estado'];
    protected $table = 'condicionIIBB';

	public function getDescFormaCalculoAttribute()
	{
	  	return Arr::get(CondicionIIBB::$enumFormaCalculo, $this->formacalculo);
	}
	
	public function getDescEstadoAttribute()
	{
	  	return Arr::get(CondicionIIBB::$enumEstado, $this->estado);
	}
}

