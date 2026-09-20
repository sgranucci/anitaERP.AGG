<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use App\ApiAnita;
use Carbon\Carbon;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Models\Ventas\Pedido_Combinacion;
use App\Traits\Ventas\OrdenTrabajoTrait;

class Ordentrabajo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
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
		foreach ($this->ordentrabajoCombinacionTallesVigentes() as $oct) {
			return $oct->pedido_combinacion_talles->pedidos_combinacion;
		}

		return null;
	}

	/**
	 * OCT con PCT y pedido_combinacion existentes.
	 * Import L8 / reedición de pedido pueden dejar filas huérfanas (FK checks off).
	 */
	public function ordentrabajoCombinacionTallesVigentes()
	{
		return $this->ordentrabajo_combinacion_talles->filter(function ($oct) {
			return $oct->pedido_combinacion_talles
				&& $oct->pedido_combinacion_talles->pedidos_combinacion;
		});
	}

}
