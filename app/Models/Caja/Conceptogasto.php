<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Contable\Cuentacontable;
use App\Models\Caja\Conceptogasto_Cuentacontable;

class Conceptogasto extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'conceptogasto';

    public function conceptogasto_cuentacontables()
	{
    	return $this->hasMany(Conceptogasto_cuentacontable::class)->with('cuentacontables');
	}

}

