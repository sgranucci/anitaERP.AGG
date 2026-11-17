<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ReglaNegocio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'regla_negocio';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'dominio', 'punto',
        'activo', 'prioridad', 'stop_on_match', 'condiciones', 'acciones',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'stop_on_match' => 'boolean',
        'prioridad' => 'integer',
        'condiciones' => 'array',
        'acciones' => 'array',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(ReglaNegocioLog::class, 'regla_negocio_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ReglaNegocioVersion::class, 'regla_negocio_id');
    }

    public function scopeActivasParaPunto($query, string $punto)
    {
        return $query->where('punto', $punto)
            ->where('activo', true)
            ->orderBy('prioridad')
            ->orderBy('id');
    }
}
