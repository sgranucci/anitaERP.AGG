<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Moneda;

class Capex_Partida extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['capex_id', 'nombre', 'proveedor_id', 'moneda_id', 'estado', 'codigo', 'creousuario_id'];
    protected $table = 'capex_partida';

	public function capexs()
	{
    	return $this->belongsTo(Capex::class, 'capex_id', 'id');
	}
	
	public function capex_partida_montos()
	{
    	return $this->hasMany(Capex_Partida_Monto::class, 'capex_partida_id');
	}

    public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id');
	}

    public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

}
