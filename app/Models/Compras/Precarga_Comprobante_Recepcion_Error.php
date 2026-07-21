<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Precarga_Comprobante_Recepcion_Error extends Model
{
    protected $table = 'precarga_comprobante_recepcion_error';

    protected $fillable = [
        'origen',
        'fase',
        'evento',
        'http_status',
        'mensaje',
        'trace_id',
        'numero_oc',
        'cuit_proveedor',
        'cuit_empresa',
        'tipo_comprobante',
        'archivo_nombre',
        'usuario_id',
        'precarga_id',
        'ip',
        'contexto_json',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'usuario_id' => 'integer',
        'precarga_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
