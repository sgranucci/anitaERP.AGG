<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class SolicitudLogisticaArchivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitud_logistica_archivo';

    protected $fillable = [
        'solicitud_logistica_id', 'nombre', 'ruta',
    ];

    protected $casts = [
        'solicitud_logistica_id' => 'integer',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudLogistica::class, 'solicitud_logistica_id');
    }
}
