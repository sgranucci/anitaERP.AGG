<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Models\Configuracion\Sala;
use App\Models\Ticket\Sector_Ticket;
use App\Models\Seguridad\Usuario;
use DB;

class Ticket extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    protected $fillable = ['fecha', 'sala_id', 'subcategoria_ticket_id', 'areadestino_id', 'sector_id', 'detalle', 
							'usuario_id', 'bienuso_id', 'observacion', 'estado_ticket'];
    protected $table = 'ticket';

    public function ticket_estados()
	{
    	return $this->hasMany(Ticket_Estado::class, 'ticket_id');
	}

    public function ticket_tareas()
	{
    	return $this->hasMany(Ticket_Tarea::class, 'ticket_id')->with('ticket_tarea_novedades');
	}

    public function ticket_articulos()
	{
    	return $this->hasMany(Ticket_Articulo::class, 'ticket_id');
	}

    public function ticket_archivos()
	{
    	return $this->hasMany(Ticket_Archivo::class, 'ticket_id');
	}

    public function salas()
	{
    	return $this->belongsTo(Sala::class, 'sala_id');
	}

    public function sectores()
	{
    	return $this->belongsTo(Sector_Ticket::class, 'sector_id');
	}

	public function subcategoria_tickets()
	{
    	return $this->belongsTo(Subcategoria_Ticket::class, 'subcategoria_ticket_id');
	}

    public function areadestinos()
    {
        return $this->belongsTo(Areadestino::class, 'areadestino_id');
    }

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}

	public function scopeConUltimoEstado(Builder $query) 
	{ 
		$subquery = Login::select('ticket_estado.estado') 
			->whereColumn('ticket_estado.ticket_id', 'ticket.id') 
			->latest() 
			->limit(1); 
		$query->addSelect(['ultimo_estado' => $subquery]);
		$query->with('ticket_estados'); 
	}
}
