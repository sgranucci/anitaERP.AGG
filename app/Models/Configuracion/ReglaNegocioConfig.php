<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class ReglaNegocioConfig extends Model
{
    protected $table = 'regla_negocio_config';

    protected $fillable = [
        'motor_habilitado',
    ];

    protected $casts = [
        'motor_habilitado' => 'boolean',
    ];

    public static function actual(): self
    {
        $row = self::query()->orderBy('id')->first();
        if ($row) {
            return $row;
        }

        return self::query()->create(['motor_habilitado' => false]);
    }
}
