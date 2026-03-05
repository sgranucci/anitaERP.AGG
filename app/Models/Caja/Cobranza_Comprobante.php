<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Cliente_Cuentacorriente;
use OwenIt\Auditing\Contracts\Auditable;

class Cobranza_Comprobante extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['cobranza_id', 'cliente_cuentacorriente_id', 'montoaplicado', 'moneda_id',
							'cotizacion'];
    protected $table = 'cobranza_comprobante';

	public function cobranzas()
	{
    	return $this->belongsTo(Cobranza::class, 'cobranza_id', 'id');
	}

	public function cliente_cuentacorrientes()
	{
    	return $this->belongsTo(Cliente_Cuentacorriente::class, 'cliente_cuentacorriente_id')->with('ventas')
											->with('cliente_cuentacorriente_aplicaciones')
											->with('monedas');
	}

	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

}
