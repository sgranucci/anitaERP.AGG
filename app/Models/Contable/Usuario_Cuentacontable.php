<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;

class Usuario_Cuentacontable extends Model
{
    protected $fillable = ['usuario_id', 'cuentacontable_id'];
    protected $table = 'usuario_cuentacontable';

	public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

	public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id', 'id');
	}

}
