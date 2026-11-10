<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FacturacionJuegoParametro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'facturacion_juego_parametro';

    protected $fillable = [
        'tipo_gan_mesa',
        'tipo_per_mesa',
        'tipo_gan_slot',
        'tipo_per_slot',
        'tipo_gan_ruleta_el',
        'tipo_per_ruleta_el',
        'letra',
        'tipoasiento_abreviatura',
    ];
}
