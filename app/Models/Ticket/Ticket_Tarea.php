<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;

class Ticket_Tarea extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

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

    public function ticket_tarea_comentarios_usuario()
    {
        return $this->hasMany(Ticket_Tarea_Comentario_Usuario::class, 'ticket_tarea_id')->with('usuarios');
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

	public function estadoVisual(): string
	{
		if (! empty($this->fechafinalizacion) && $this->fechafinalizacion >= '2000-01-01') {
			return 'Finalizada';
		}

		$ultimaNovedad = $this->ticket_tarea_novedades->sortByDesc('id')->first();
		if ($ultimaNovedad && ! empty($ultimaNovedad->estado)) {
			return $ultimaNovedad->estado;
		}

		return 'Pendiente';
	}

	public function fechaTicketLegible(?string $fecha): string
	{
		if (empty($fecha) || $fecha < '2000-01-01') {
			return '';
		}

		return date('d/m/Y', strtotime($fecha));
	}

}
