<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use App\Traits\Configuracion\CondicionivaTrait;

class Condicioniva extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	use CondicionivaTrait;

    protected $fillable = ['nombre', 'letra', 'coniva', 'coniibb', 'codigoexterno'];
    protected $table = 'condicioniva';

	public function getDescConIvaAttribute()
	{
	  	return Arr::get(Condicioniva::$enumIva, $this->coniva);
	}
	public function getDescConIibbAttribute()
	{
	  	return Arr::get(Condicioniva::$enumIibb, $this->coniibb);
	}
}

