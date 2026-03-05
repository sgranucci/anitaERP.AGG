<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Moneda;
use OwenIt\Auditing\Contracts\Auditable;

class Caja_Movimiento_Cuentacaja extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['caja_movimiento_id', 'cuentacaja_id', 'fecha', 'monto', 'moneda_id',
							'cotizacion', 'observacion'];
    protected $table = 'caja_movimiento_cuentacaja';

	public function caja_movimientos()
	{
    	return $this->belongsTo(Caja_Movimiento::class, 'caja_movimiento_id', 'id');
	}

	public function cuentacajas()
	{
    	return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
	}

	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

}
