<?php

namespace App\Models\Logistica;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaUidHistorial extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_uid_historial';

    protected $fillable = [
        'uid', 'solicitud_logistica_id', 'fecha', 'destino', 'usuario_id',
    ];

    protected $casts = [
        'solicitud_logistica_id' => 'integer',
        'usuario_id' => 'integer',
        'fecha' => 'date',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudLogistica::class, 'solicitud_logistica_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
