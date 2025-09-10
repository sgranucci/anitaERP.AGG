<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use App\Traits\Ticket\Tarea_TicketTrait;
use App\Models\Ticket\Areadestino;

class Tarea_Ticket extends Model implements Auditable
{
    use Tarea_TicketTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'tipotarea', 'areadestino_id',
                            'tiempoestimado', 'enviacorreo'];
    protected $table = 'tarea_ticket';

    public function areadestinos()
    {
        return $this->belongsTo(Areadestino::class, 'areadestino_id');
    }

}

