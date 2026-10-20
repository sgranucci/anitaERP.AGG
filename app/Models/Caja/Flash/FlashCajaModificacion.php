<?php

namespace App\Models\Caja\Flash;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class FlashCajaModificacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_HABILITADA = 'habilitada';

    public const ESTADO_USADA = 'usada';

    protected $table = 'flash_caja_modificacion';

    protected $fillable = [
        'flash_caja_id',
        'estado',
        'motivo',
        'solicitante_usuario_id',
        'solicitada_en',
        'habilitada_usuario_id',
        'habilitada_en',
        'usada_en',
    ];

    protected $casts = [
        'solicitada_en' => 'datetime',
        'habilitada_en' => 'datetime',
        'usada_en' => 'datetime',
    ];

    public function flash(): BelongsTo
    {
        return $this->belongsTo(FlashCaja::class, 'flash_caja_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_usuario_id');
    }

    public function habilitador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'habilitada_usuario_id');
    }
}
