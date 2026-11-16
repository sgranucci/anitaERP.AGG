<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class UserTaskSlaFuente extends Model
{
    protected $table = 'user_task_sla_fuente';

    protected $fillable = [
        'fuente',
        'sla_horas',
    ];

    protected $casts = [
        'sla_horas' => 'integer',
    ];
}
