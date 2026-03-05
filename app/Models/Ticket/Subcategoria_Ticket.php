<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Subcategoria_Ticket extends Model
{
    protected $fillable = [
                            'nombre', 'categoria_ticket_id'
                        ];
    protected $table = 'subcategoria_ticket';

    public function categoria_tickets()
	{
    	return $this->belongsTo(Categoria_Ticket::class, 'categoria_ticket_id');
	}

}

