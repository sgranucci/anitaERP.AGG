<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Empleado_Grupo_Concepto_Sueldos extends Model
{
    protected $table = 'empleado_grupo_concepto_sueldos';

    protected $fillable = [
        'empleado_id', 'grupo_concepto_id', 'orden', 'origen',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'grupo_concepto_id' => 'integer',
        'orden' => 'integer',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo_Concepto_Sueldos::class, 'grupo_concepto_id');
    }
}
