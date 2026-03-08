<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Compras\Proveedor;
use App\Models\Stock\Articulo;
use DB;

class Partidagasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['empresa_id', 'presupuesto_id', 'presupuesto_escenario_id', 'centrocosto_id', 'moneda_id', 
							'cuentacontable_id', 'articulo_id', 'proveedor_id', 'detalle', 
							'estado', 'codigo', 'creousuario_id'];
    protected $table = 'partidagasto';

	public function partidagasto_estados()
	{
    	return $this->hasMany(Partidagasto_Estado::class, 'partidagasto_id');
	}

    public function partidagasto_montos()
	{
    	return $this->hasMany(Partidagasto_Monto::class, 'partidagasto_id');
	}

    public function partidagasto_archivos()
	{
    	return $this->hasMany(Partidagasto_Archivo::class, 'partidagasto_id');
	}

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id');
	}

	public function presupuestos()
	{
    	return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
	}

	public function presupuesto_escenarios()
	{
    	return $this->belongsTo(Presupuesto_Escenario::class, 'presupuesto_escenario_id');
	}

    public function centrocostos()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}

    public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

    public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
	}

    public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id');
	}

    public function proveedores()
	{
    	return $this->belongsTo(Proveedor::class, 'proveedor_id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

	public function scopeConUltimoEstado(Builder $query) 
	{ 
		$subquery = Login::select('partidagasto_estado.estado') 
			->whereColumn('partidagasto_estado.partidagasto_id', 'partidagasto.id') 
			->latest() 
			->limit(1); 
		$query->addSelect(['ultimo_estado' => $subquery]);
		$query->with('partidagasto_estados'); 
	}
}
