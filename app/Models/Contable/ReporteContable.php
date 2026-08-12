<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteContable extends Model
{
    protected $table = 'reporte_contable';

    protected $fillable = [
        'codigo',
        'nombre',
        'titulo1',
        'titulo2',
        'tipo',
        'origen',
        'anita_informe',
        'activo',
        'observaciones',
        'layout_default_id',
        'version_actual',
        'estado_publicacion',
        'valido_desde',
        'valido_hasta',
        'publicado_at',
        'publicado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'codigo' => 'integer',
        'anita_informe' => 'integer',
        'layout_default_id' => 'integer',
        'version_actual' => 'integer',
        'publicado_at' => 'datetime',
        'publicado_por' => 'integer',
        'valido_desde' => 'date',
        'valido_hasta' => 'date',
    ];

    public function rubros(): HasMany
    {
        return $this->hasMany(ReporteContableRubro::class, 'reporte_contable_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(ReporteContableLayout::class, 'reporte_contable_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ReporteContableVersion::class, 'reporte_contable_id')
            ->orderByDesc('version');
    }

    public function eliReglas(): HasMany
    {
        return $this->hasMany(ReporteContableEliRegla::class, 'reporte_contable_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function participaciones(): HasMany
    {
        return $this->hasMany(ReporteContableParticipacion::class, 'reporte_contable_id')
            ->orderBy('empresa_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(ReporteContableAlerta::class, 'reporte_contable_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(UsuarioReporteContable::class, 'reporte_contable_id')
            ->orderBy('usuario_id');
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(ReporteContableVariante::class, 'reporte_contable_id')
            ->orderBy('nombre');
    }
}
