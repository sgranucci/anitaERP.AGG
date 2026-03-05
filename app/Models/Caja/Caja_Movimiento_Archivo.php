<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Caja_Movimiento_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['caja_movimiento_id', 'nombrearchivo'];
    protected $table = 'caja_movimiento_archivo';

	public function caja_movimientos()
	{
    	return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id', 'id');
	}

}
