<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownJornada extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ABIERTA = 'abierta';

    public const CERRADA = 'cerrada';

    protected $table = 'tesoreria_crown_jornada';

    protected $fillable = [
        'sala_id',
        'empresa_id',
        'fecha',
        'estado',
        'caja_final_dia',
        'caja_final_mes',
        'caja_final_viii',
        'clientes',
    ];

    protected $casts = [
        'fecha' => 'date',
        'caja_final_dia' => 'decimal:2',
        'caja_final_mes' => 'decimal:2',
        'caja_final_viii' => 'decimal:2',
        'clientes' => 'integer',
        'recalculo_pendiente' => 'boolean',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(TesoreriaCrownAsignacion::class, 'jornada_id');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(TesoreriaCrownComprobante::class, 'jornada_id');
    }

    public function cierresFiscales(): HasMany
    {
        return $this->hasMany(TesoreriaCrownCierreFiscal::class, 'jornada_id');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ABIERTA;
    }
}
