<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Seguridad\Usuario;

class Ticket_Tarea extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $fillable = ['ticket_id', 'tarea_id', 'detalle', 'fechacarga', 'fechaprogramacion', 'fechafinalizacion',
							'tiempoinsumido', 'tecnico_id', 'turno_id', 'creousuario_id'];
    protected $table = 'ticket_tarea';

	public function tickets()
	{
    	return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
	}

    public function ticket_tarea_novedades()
	{
    	return $this->hasMany(Ticket_Tarea_Novedad::class, 'ticket_tarea_id')->with('usuarios');
	}

	public function tareas()
	{
    	return $this->belongsTo(Tarea_Ticket::class, 'tarea_id', 'id');
	}

	public function tecnicos()
	{
    	return $this->belongsTo(Tecnico_Ticket::class, 'tecnico_id', 'id');
	}

	public function turnos()
	{
    	return $this->belongsTo(Turno_Ticket::class, 'turno_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

}
