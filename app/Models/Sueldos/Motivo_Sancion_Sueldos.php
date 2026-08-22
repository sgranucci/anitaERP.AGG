<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Motivo_Sancion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'motivo_sancion_sueldos';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'activo' => 'boolean',
    ];

    public function sanciones(): HasMany
    {
        return $this->hasMany(Empleado_Sancion_Sueldos::class, 'motivo_sancion_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
