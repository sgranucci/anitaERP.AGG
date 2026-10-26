<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownAsignacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_asignacion';

    protected $fillable = [
        'sala_id',
        'jornada_id',
        'deposito_id',
        'cajero_crown_id',
        'tipo_tarea',
        'turno',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownJornada::class, 'jornada_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownDeposito::class, 'deposito_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(CajeroCrown::class, 'cajero_crown_id');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(TesoreriaCrownComprobante::class, 'asignacion_id');
    }

    public function cierre(): HasOne
    {
        return $this->hasOne(TesoreriaCrownCierre::class, 'asignacion_id');
    }
}
