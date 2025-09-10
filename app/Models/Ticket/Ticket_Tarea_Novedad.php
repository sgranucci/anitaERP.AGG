<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Seguridad\Usuario;
use App\Traits\Ticket\Ticket_Tarea_NovedadTrait;

class Ticket_Tarea_Novedad extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
	use Ticket_Tarea_NovedadTrait;

    protected $fillable = ['ticket_tarea_id', 'desdefecha', 'hastafecha',
							'usuario_id', 'comentario', 'estado'];
    protected $table = 'ticket_tarea_novedad';

	public function ticket_tareas()
	{
    	return $this->belongsTo(Ticket_Tarea::class, 'ticket_tarea_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}

}
