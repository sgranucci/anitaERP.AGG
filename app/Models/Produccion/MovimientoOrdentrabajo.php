<?php

namespace App\Models\Produccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Models\Produccion\Tarea;
use App\Models\Produccion\Operacion;
use App\Traits\Produccion\MovimientoOrdenTrabajoTrait;

class MovimientoOrdentrabajo extends Model
{
	use SoftDeletes;
	use MovimientoOrdenTrabajoTrait;

    protected $fillable = ['ordentrabajo_id', 'ordentrabajo_tarea_id', 'tarea_id', 'operacion_id', 'empleado_id', 'fecha', 'estado', 'usuario_id'];
    protected $table = 'movimientoordentrabajo';

	public function estadoEnum()
	{
		return MovimientoOrdenTrabajoTrait::$enumEstado;
	}

    public function ordenestrabajo()
    {
        return $this->belongsTo(Ordentrabajo::class, 'ordentrabajo_id');
    }

    public function ordenestrabajo_tarea()
    {
        return $this->belongsTo(Ordentrabajo_Tarea::class, 'ordentrabajo_id');
    }

    public function ordenestrabajo_tarea_en_produccion()
    {
        return $this->belongsTo(Ordentrabajo_Tarea::class, 'ordentrabajo_id')
                    //->whereNotIn('ordentrabajo_tarea.tarea_id', [config('consprod.TAREA_TERMINADA'),config('consprod.TAREA_FACTURADA')]);
                    ->where('ordentrabajo_tarea.tarea_id', 32);
    }
    
    public function tareas()
    {
        return $this->hasOne(Tarea::class, 'id', 'tarea_id');
    }

    public function operaciones()
    {
        return $this->hasOne(Operacion::class, 'id', 'operacion_id');
    }

    public function empleados()
    {
        return $this->hasOne(Empleado::class, 'id', 'empleado_id');
    }

}
