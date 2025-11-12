<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ventas\Cliente;
use App\Models\Configuracion\Moneda;

class Cliente_Cuentacorriente extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['fecha', 'fechavencimiento', 'cliente_id', 'total', 'moneda_id', 'venta_id',
                            'cobranza_id'];
	
    protected $table = 'cliente_cuentacorriente';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

}
