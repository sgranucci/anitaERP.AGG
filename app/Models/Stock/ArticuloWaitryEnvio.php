<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class ArticuloWaitryEnvio extends Model
{
    public const ALTA = 'alta';

    public const BAJA = 'baja';

    protected $table = 'articulo_waitry_envio';

    protected $fillable = [
        'articulo_id',
        'empresa_id',
        'place_id',
        'operacion',
        'ok',
        'http_code',
        'mensaje',
        'pedido_json',
        'respuesta_json',
        'usuario_id',
    ];

    protected $casts = [
        'ok' => 'boolean',
    ];
}
