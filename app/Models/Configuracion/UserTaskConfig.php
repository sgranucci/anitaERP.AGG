<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class UserTaskConfig extends Model
{
    protected $table = 'user_task_config';

    protected $fillable = ['sla_horas', 'bandeja_desde_tabla'];

    protected $casts = [
        'sla_horas' => 'integer',
        'bandeja_desde_tabla' => 'boolean',
    ];

    public static function actual(): self
    {
        if (! Schema::hasTable('user_task_config')) {
            $cfg = new self;
            $cfg->sla_horas = max(1, (int) config('arbolaprobacion.user_task_sla_horas', 48));
            $cfg->bandeja_desde_tabla = (bool) config('arbolaprobacion.user_task_bandeja_desde_tabla', false);

            return $cfg;
        }

        $row = self::query()->orderBy('id')->first();
        if ($row) {
            return $row;
        }

        return self::query()->create([
            'sla_horas' => max(1, (int) config('arbolaprobacion.user_task_sla_horas', 48)),
            'bandeja_desde_tabla' => (bool) config('arbolaprobacion.user_task_bandeja_desde_tabla', false),
        ]);
    }
}
