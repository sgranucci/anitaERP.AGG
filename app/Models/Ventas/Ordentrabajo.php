<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\ApiAnita;
use Carbon\Carbon;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Traits\Ventas\OrdenTrabajoTrait;

class Ordentrabajo extends Model
{
	use SoftDeletes;
	use OrdenTrabajoTrait;

    protected $fillable = ['fecha', 'codigo', 'leyenda', 'estado', 'usuario_id'];
	protected $table = "ordentrabajo";

	public function ordentrabajo_combinacion_talles()
	{
    	return $this->hasMany(Ordentrabajo_Combinacion_Talle::class, 'ordentrabajo_id')->with("clientes")->with("pedido_combinacion_talles");
	}

	public function ordentrabajo_tareas()
	{
    	return $this->hasMany(Ordentrabajo_Tarea::class, 'ordentrabajo_id')->with('tareas')->with('empleados');
	}

}
