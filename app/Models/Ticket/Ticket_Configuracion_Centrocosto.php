<?php

namespace App\Models\Ticket;

use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket_Configuracion_Centrocosto extends Model
{
    protected $table = 'ticket_configuracion_centrocosto';

    protected $fillable = [
        'centrocosto_id',
        'notificar_comentario_a_cc',
    ];

    protected $casts = [
        'notificar_comentario_a_cc' => 'boolean',
    ];

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
