<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ventas\Cliente;
use App\Models\Configuracion\Moneda;

class Cliente_Cuentacorriente_Aplicacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['fecha', 'cliente_cuentacorriente_id', 'total', 'moneda_id', 'cotizacion', 'ventaaplicado_id',
                            'cobranza_id', 'comprobanteaplicado', 'cliente_cuentacorriente_aplicado_id'];
	
    protected $table = 'cliente_cuentacorriente_aplicacion';

	public function cliente_cuentacorrientes()
	{
    	return $this->belongsTo(Cliente_Cuentacorriente::class, 'cliente_cuentacorriente_id', 'id');
	}

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

	public function cliente_cuentacorriente_aplicados()
	{
    	return $this->belongsTo(Cliente_Cuentacorriente::class, 'cliente_cuentacorriente_aplicado_id', 'id');
	}

}
