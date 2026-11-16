<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappEnvioLog extends Model
{
    protected $table = 'whatsapp_envio_log';

    protected $fillable = [
        'empresa_id', 'usuario_id', 'celular', 'modulo_aviso', 'entity_id',
        'estado', 'provider_id', 'error', 'driver', 'chars', 'uso_plantilla', 'enviado_en',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'usuario_id' => 'integer',
        'entity_id' => 'integer',
        'chars' => 'integer',
        'uso_plantilla' => 'boolean',
        'enviado_en' => 'datetime',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
