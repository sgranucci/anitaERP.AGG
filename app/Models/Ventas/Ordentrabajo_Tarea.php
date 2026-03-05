<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Produccion\Tarea;
use App\Models\Produccion\Empleado;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Ventas\Venta;

class Ordentrabajo_Tarea extends Model
{
    protected $fillable = ['ordentrabajo_id', 'tarea_id', 'empleado_id', 'pedido_combinacion_id', 'desdefecha', 'hastafecha', 'costo',
						'estado', 'usuario_id', 'venta_id'];
    protected $table = 'ordentrabajo_tarea';

    public function tareas()
	{
    	return $this->belongsTo(Tarea::class, 'tarea_id', 'id');
	}
    public function ordenestrabajo()
	{
    	return $this->belongsTo(Ordentrabajo::class, 'ordentrabajo_id', 'id');
	}
	public function usuarios()
	{
    	return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
	}
    public function pedidos_combinacion()
	{
    	return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id', 'id')->with('articulos');
		//return $this->belongsTo(Pedido_Combinacion::class, 'ordentrabajo_id', 'ot_id')->with('articulos');
	}
	public function empleados()
	{
    	return $this->belongsTo(Empleado::class, 'empleado_id', 'id');
	}
	public function ventas()
	{
    	return $this->belongsTo(Venta::class, 'venta_id', 'id');
	}
}

