<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class MercadolibreNotificacion extends Model
{
    protected $table = 'mercadolibre_notificacion';

    protected $fillable = [
        'topic',
        'resource',
        'user_id',
        'application_id',
        'attempts',
        'sent_at',
        'estado',
        'error_mensaje',
        'payload_json',
        'procesada_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'procesada_at' => 'datetime',
        'payload_json' => 'array',
    ];
}
