<?php

namespace App\Models\Caja;

use App\Models\Seguridad\Usuario;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownCierre extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_cierre';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'jornada_id',
        'asignacion_id',
        'numero',
        'efectivo_habilitado',
        'valores_rendidos',
        'resultado',
        'diferencia',
        'estado',
        'observacion',
        'tesoreria_usuario_id',
        'tesoreria_at',
        'cajero_at',
        'auditor_usuario_id',
        'auditor_at',
    ];

    protected $casts = [
        'numero' => 'integer',
        'efectivo_habilitado' => 'decimal:2',
        'valores_rendidos' => 'decimal:2',
        'resultado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'tesoreria_at' => 'datetime',
        'cajero_at' => 'datetime',
        'auditor_at' => 'datetime',
    ];

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownAsignacion::class, 'asignacion_id');
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownJornada::class, 'jornada_id');
    }

    public function tesoreriaUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tesoreria_usuario_id');
    }

    public function auditorUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'auditor_usuario_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(TesoreriaCrownCierreEvento::class, 'cierre_id');
    }

    public function estadoTexto(): string
    {
        return TesoreriaCrownCierreSupport::estado((string) $this->estado);
    }
}
