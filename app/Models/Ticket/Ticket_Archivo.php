<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ticket_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['ticket_id', 'nombrearchivo'];
    protected $table = 'ticket_archivo';

	public function tickets()
	{
    	return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
	}

}
