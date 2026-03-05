<?php

namespace App\Models\Produccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Produccion\OperacionTrait;

class Operacion extends Model
{
	use OperacionTrait;

    protected $fillable = ['nombre', 'tipooperacion'];
    protected $table = 'operacion';

	public function tipooperacionEnum()
	{
		return OperacionTrait::$enumTipoOperacion;
	}

}
