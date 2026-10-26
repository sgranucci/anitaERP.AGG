<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownDeposito extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_deposito';

    protected $fillable = [
        'empresa_id',
        'sala_id',
        'tipo_deposito_id',
        'codigo',
        'nombre',
        'orden',
        'caja_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function tipoDeposito(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownTipoDeposito::class, 'tipo_deposito_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }
}
