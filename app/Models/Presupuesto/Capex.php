<?php

namespace App\Models\Presupuesto;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use DB;

class Capex extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['empresa_id', 'presupuesto_id', 'centrocosto_id', 'nombre', 'detalle', 
							'codigoproyecto', 'estado', 'codigo', 'creousuario_id'];
    protected $table = 'capex';

    public function capex_estados()
	{
    	return $this->hasMany(Capex_Estado::class, 'capex_id');
	}

    public function capex_partidas()
	{
    	return $this->hasMany(Capex_Partida::class, 'capex_id')->with('capex_partida_montos');
	}

    public function capex_archivos()
	{
    	return $this->hasMany(Capex_Archivo::class, 'capex_id');
	}

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id');
	}

	public function presupuestos()
	{
    	return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
	}

    public function centrocostos()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

	public function scopeConUltimoEstado(Builder $query) 
	{ 
		$subquery = Login::select('capex_estado.estado') 
			->whereColumn('capex_estado.capex_id', 'capex.id') 
			->latest() 
			->limit(1); 
		$query->addSelect(['ultimo_estado' => $subquery]);
		$query->with('capex_estados'); 
	}
}
