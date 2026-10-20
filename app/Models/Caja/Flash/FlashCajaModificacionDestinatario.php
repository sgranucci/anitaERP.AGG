<?php

namespace App\Models\Caja\Flash;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class FlashCajaModificacionDestinatario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'flash_caja_modificacion_destinatario';

    protected $fillable = [
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
