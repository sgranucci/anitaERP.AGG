<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Storage;
use App\Models\Ticket\Areadestino;
use App\Models\Seguridad\Usuario;

class Tecnico_Ticket extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'areadestino_id', 'usuario_id'];
    protected $table = 'tecnico_ticket';

    public function areadestinos()
    {
        return $this->belongsTo(Areadestino::class, 'areadestino_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

}

