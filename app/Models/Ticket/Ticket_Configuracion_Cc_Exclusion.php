<?php

namespace App\Models\Ticket;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket_Configuracion_Cc_Exclusion extends Model
{
    protected $table = 'ticket_configuracion_cc_exclusion';

    protected $fillable = [
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
