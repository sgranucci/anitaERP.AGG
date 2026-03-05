<?php

namespace App\Models\Ordenventa;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Ordenventa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'observacion', 'creousuario_id'];
    protected $table = 'concepto_ordenventa';

    public function concepto_cuentacontable_ordenventas()
	{
    	return $this->hasMany(Concepto_Cuentacontable_Ordenventa::class, 'concepto_ordenventa_id');
	}    
}
