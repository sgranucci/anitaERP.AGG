<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class Categoria_Ticket extends Model
{
    protected $fillable = ['nombre', 'areadestino_id'];
    protected $table = 'categoria_ticket';

	public function subcategoria_tickets()
	{
    	return $this->hasMany(Subcategoria_Ticket::class, 'categoria_ticket_id');
	}

    public function areadestinos()
    {
        return $this->belongsTo(Areadestino::class, 'areadestino_id');
    }
}
