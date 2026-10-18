<?php

namespace App\Models\Compras;

use App\Models\Caja\Banco;
use App\Models\Caja\Mediopago;
use App\Models\Caja\Tipocuentacaja;
use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Formapago;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Formapago extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    protected $fillable = ['proveedor_id', 'nombre', 'formapago_id', 'cbu', 'alias_cbu', 'tipocuentacaja_id', 'moneda_id',
							'numerocuenta', 'nroinscripcion', 'banco_id', 'mediopago_id', 'email'];
    protected $table = 'proveedor_formapago';

	public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id', 'id');
	}

	public function formpagos()
	{
    	return $this->belongsTo(Formapago::class, 'formapago_id');
	}

	public function tipocuentacajas()
	{
    	return $this->belongsTo(Tipocuentacaja::class, 'tipocuentacaja_id');
	}

	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

	public function bancos()
	{
    	return $this->belongsTo(Banco::class, 'banco_id');
	}

	public function mediopagos()
	{
    	return $this->belongsTo(Mediopago::class, 'mediopago_id');
	}

}
