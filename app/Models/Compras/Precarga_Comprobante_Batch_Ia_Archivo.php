<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Auditoría y dedupe de la carpeta caliente BATCH_IA. */
class Precarga_Comprobante_Batch_Ia_Archivo extends Model
{
    public const ESTADO_ENCOLADO = 'ENCOLADO';
    public const ESTADO_PROCESANDO = 'PROCESANDO';
    public const ESTADO_PROCESADO = 'PROCESADO';
    public const ESTADO_ERROR = 'ERROR';
    public const ESTADO_DUPLICADO = 'DUPLICADO';

    protected $table = 'precarga_comprobante_batch_ia_archivo';

    protected $fillable = [
        'archivo_nombre',
        'archivo_hash',
        'ruta_procesando',
        'ruta_archivo',
        'estado',
        'numero_oc',
        'precarga_id',
        'mensaje_error',
    ];

    protected $casts = [
        'precarga_id' => 'integer',
    ];

    public function precarga(): BelongsTo
    {
        return $this->belongsTo(Precarga_Comprobante_Proveedor::class, 'precarga_id');
    }
}
