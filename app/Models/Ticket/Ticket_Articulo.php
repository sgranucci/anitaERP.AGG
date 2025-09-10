<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Stock\Articulo;

class Ticket_Articulo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $fillable = ['ticket_id', 'articulo_id', 'cantidad', 'requisicion_id', 'recepcion_id',
							'creousuario_id'];
    protected $table = 'ticket_articulo';

	public function tickets()
	{
    	return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
	}

	public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'creousuario_id');
	}

}
