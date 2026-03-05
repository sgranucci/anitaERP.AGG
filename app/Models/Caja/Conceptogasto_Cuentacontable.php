<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use App\Models\Contable\Cuentacontable;

class Conceptogasto_Cuentacontable extends Model
{
    protected $fillable = ['conceptogasto_id', 'cuentacontable_id'];
    protected $table = 'conceptogasto_cuentacontable';

	public function conceptogastos()
    {
        return $this->belongsTo(Conceptogasto::class, 'conceptogasto_id');
    }

	public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id', 'id');
	}

}
