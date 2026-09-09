<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket_Configuracion_Areadestino extends Model
{
    public const MODO_DISPATCH = 'dispatch';

    public const MODO_CLAIM = 'claim';

    protected $table = 'ticket_configuracion_areadestino';

    protected $fillable = [
        'areadestino_id',
        'modo_operacion',
    ];

    public function areadestino(): BelongsTo
    {
        return $this->belongsTo(Areadestino::class, 'areadestino_id');
    }

    public function esClaim(): bool
    {
        return $this->modo_operacion === self::MODO_CLAIM;
    }

    public function esDispatch(): bool
    {
        return ! $this->esClaim();
    }
}
