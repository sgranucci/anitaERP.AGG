<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebPushSubscription extends Model
{
    protected $table = 'web_push_subscription';

    protected $fillable = [
        'usuario_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth',
        'content_encoding', 'user_agent', 'ultima_ok_en', 'ultimo_error_en', 'ultimo_error',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'ultima_ok_en' => 'datetime',
        'ultimo_error_en' => 'datetime',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
