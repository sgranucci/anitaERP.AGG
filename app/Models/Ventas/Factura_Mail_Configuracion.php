<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Factura_Mail_Configuracion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'factura_mail_configuracion';

    protected $fillable = [
        'empresa_id',
        'habilitado',
        'envio_automatico',
        'incluir_remito',
        'incluir_envio',
        'asunto',
        'cuerpo',
        'bcc',
        'exigir_flag_cliente',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'habilitado' => 'boolean',
        'envio_automatico' => 'boolean',
        'incluir_remito' => 'boolean',
        'incluir_envio' => 'boolean',
        'exigir_flag_cliente' => 'boolean',
    ];
}
