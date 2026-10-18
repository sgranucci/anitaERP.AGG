<?php

namespace App\Models\Ticket;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Ticket_Configuracion_Cc_Exclusion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'ticket_configuracion_cc_exclusion';

    protected $fillable = [
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
