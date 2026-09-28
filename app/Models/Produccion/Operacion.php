<?php

namespace App\Models\Produccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Produccion\OperacionTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Operacion extends Model implements Auditable
{
	use OperacionTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'tipooperacion'];
    protected $table = 'operacion';

	public function tipooperacionEnum()
	{
		return OperacionTrait::$enumTipoOperacion;
	}

}
