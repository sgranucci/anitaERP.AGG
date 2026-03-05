<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ventas\Cliente;
use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Empresa;
use App\Models\Caja\Cobranza;

class Cliente_Cuentacorriente extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['fecha', 'fechavencimiento', 'cliente_id', 'total', 'moneda_id', 'cotizacion', 'venta_id',
                            'cobranza_id', 'empresa_id'];
	
    protected $table = 'cliente_cuentacorriente';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

    public function cliente_cuentacorriente_aplicaciones()
	{
    	return $this->hasMany(Cliente_Cuentacorriente_Aplicacion::class, 'cliente_cuentacorriente_id');
	}

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cobranzas()
	{
    	return $this->belongsTo(Cobranza::class, 'cobranza_id');
	}

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id');
	}

}
