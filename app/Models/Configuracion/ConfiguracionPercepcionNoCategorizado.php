<?php

declare(strict_types=1);

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionPercepcionNoCategorizado extends Model
{
    protected $table = 'configuracion_percepcion_no_categorizado';

    protected $fillable = [
        'habilitado',
        'tasa',
        'minimo',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'tasa' => 'float',
        'minimo' => 'float',
    ];
}
