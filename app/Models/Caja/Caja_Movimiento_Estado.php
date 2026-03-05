<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\Caja\Caja_Movimiento_EstadoTrait;

class Caja_Movimiento_Estado extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	use Caja_Movimiento_EstadoTrait;

    protected $fillable = ['caja_movimiento_id', 'fecha', 'estado', 'observacion'];
    protected $table = 'caja_movimiento_estado';

	public function caja_movimientos()
	{
    	return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id', 'id');
	}

}
