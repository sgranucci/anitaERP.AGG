<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Impuesto extends Model
{
    protected $fillable = ['nombre', 'valor', 'fechavigencia', 'codigo', 'codigoarca'];
    protected $table = 'impuesto';

    public function impuesto_cuentacontables()
	{
    	return $this->hasMany(Impuesto_Cuentacontable::class, 'impuesto_id');
	}        
}
