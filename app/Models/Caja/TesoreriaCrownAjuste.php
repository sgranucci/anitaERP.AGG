<?php

namespace App\Models\Caja;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownAjuste extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const HABILITACION = 'H';

    public const RENDICION = 'F';

    public const RESULTADO = 'G';

    /** @var array<string, string> */
    public const CORRIGE = [
        self::HABILITACION => 'Habilitación',
        self::RENDICION => 'Rendición',
        self::RESULTADO => 'Resultado de gastronomía u hotel',
    ];

    protected $table = 'tesoreria_crown_ajuste';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'jornada_id',
        'cajero_crown_id',
        'asignacion_id',
        'cierre_id',
        'numero',
        'corrige',
        'importe',
        'motivo',
        'auditor_usuario_id',
        'auditor_at',
        'tesoreria_usuario_id',
        'tesoreria_at',
    ];

    protected $casts = [
        'numero' => 'integer',
        'importe' => 'decimal:2',
        'auditor_at' => 'datetime',
        'tesoreria_at' => 'datetime',
    ];

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(CajeroCrown::class, 'cajero_crown_id');
    }

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownCierre::class, 'cierre_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'auditor_usuario_id');
    }

    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tesoreria_usuario_id');
    }

    public function concepto(): string
    {
        return self::CORRIGE[$this->corrige] ?? $this->corrige;
    }
}
