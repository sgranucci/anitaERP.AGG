<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Moneda;
use OwenIt\Auditing\Contracts\Auditable;

class Asiento_Movimiento extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    protected $fillable = ['asiento_id', 'cuentacontable_id', 'centrocosto_id', 'monto', 'moneda_id',
							'cotizacion', 'observacion'];
    protected $table = 'asiento_movimiento';

	public function asientos()
	{
    	return $this->belongsTo(Asiento::class, 'asiento_id', 'id');
	}

	public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
	}

	public function centrocostos()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}
	
	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

}
