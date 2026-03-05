<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Compras\Condicionpagocuota;
use App\Traits\Compras\CondicionpagoTrait;

class Condicionpago extends Model
{
	use CondicionpagoTrait;

    protected $fillable = ['nombre', 'aplicacion', 'codigo'];
    protected $table = 'condicionpago';
	
	public function condicionpagocuotas()
	{
    	return $this->hasMany(Condicionpagocuota::class);
	}

}
