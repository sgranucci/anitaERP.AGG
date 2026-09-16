<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\ApiAnita;
use Carbon\Carbon;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Models\Ventas\Pedido_Combinacion;
use App\Traits\Ventas\OrdenTrabajoTrait;

class Ordentrabajo extends Model
{
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

	/**
	 * Primera línea pedido_combinacion con talles aún existentes.
	 * Tras reeditar un pedido pueden quedar OCT huérfanos (FK checks off): el [0] no sirve.
	 */
	public function pedidoCombinacionVigente(): ?Pedido_Combinacion
	{
		foreach ($this->ordentrabajo_combinacion_talles as $oct) {
			$pedidoCombinacion = $oct->pedido_combinacion_talles->pedidos_combinacion ?? null;
			if ($pedidoCombinacion !== null) {
				return $pedidoCombinacion;
			}
		}

		return null;
	}

}
