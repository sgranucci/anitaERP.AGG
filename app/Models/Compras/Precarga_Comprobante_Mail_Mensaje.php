<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría y dedupe de la ingesta de facturas por correo.
 * Una fila por adjunto PDF de cada mensaje procesado.
 */
class Precarga_Comprobante_Mail_Mensaje extends Model
{
    public const ESTADO_PROCESADO = 'PROCESADO';

    public const ESTADO_ERROR = 'ERROR';

    public const ESTADO_IGNORADO = 'IGNORADO';

    protected $table = 'precarga_comprobante_mail_mensaje';

    protected $fillable = [
        'message_id',
        'uid',
        'carpeta',
        'remitente',
        'asunto',
        'fecha_mensaje',
        'adjunto_nombre',
        'adjunto_hash',
        'numero_oc',
        'estado',
        'precarga_id',
        'mensaje_error',
    ];

    protected $casts = [
        'fecha_mensaje' => 'datetime',
        'precarga_id' => 'integer',
    ];

    public function precarga(): BelongsTo
    {
        return $this->belongsTo(Precarga_Comprobante_Proveedor::class, 'precarga_id');
    }
}
