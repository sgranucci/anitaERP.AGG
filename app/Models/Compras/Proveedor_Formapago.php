<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Proveedor_Formapago extends Model
{
    protected $fillable = ['proveedor_id', 'nombre', 'formapago_id', 'cbu', 'tipocuentacaja_id', 'moneda_id',
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
