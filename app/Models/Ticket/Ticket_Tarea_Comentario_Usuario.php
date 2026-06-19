<?php

namespace App\Models\Ticket;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Ticket_Tarea_Comentario_Usuario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $fillable = ['ticket_tarea_id', 'usuario_id', 'comentario'];

    protected $table = 'ticket_tarea_comentario_usuario';

    public function ticket_tareas()
    {
        return $this->belongsTo(Ticket_Tarea::class, 'ticket_tarea_id', 'id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
