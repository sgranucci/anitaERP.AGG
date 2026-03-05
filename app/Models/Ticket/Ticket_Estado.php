<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Ticket\Ticket_EstadoTrait;
use App\Models\Seguridad\Usuario;

class Ticket_Estado extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
	use Ticket_EstadoTrait;

    protected $fillable = ['ticket_id', 'fecha', 'estado', 'observacion', 'usuario_id'];
    protected $table = 'ticket_estado';

	public function tickets()
	{
    	return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}

}
