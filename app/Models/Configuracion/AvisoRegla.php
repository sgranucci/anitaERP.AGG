<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class AvisoRegla extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'aviso_regla';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'activo', 'prioridad',
        'evento', 'condiciones', 'acciones',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'prioridad' => 'integer',
        'condiciones' => 'array',
        'acciones' => 'array',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AvisoReglaLog::class, 'aviso_regla_id');
    }

    public function scopeActivasParaEvento($query, string $evento)
    {
        return $query->where('evento', $evento)
            ->where('activo', true)
            ->orderBy('prioridad')
            ->orderBy('id');
    }
}
