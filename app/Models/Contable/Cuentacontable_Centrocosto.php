<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;

class Cuentacontable_Centrocosto extends Model
{
    protected $fillable = ['cuentacontable_id', 'centrocosto_id'];
    protected $table = 'cuentacontable_centrocosto';

	public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id', 'id');
	}

	public function centrocostos()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}

}
