<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownValor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_valor';

    protected $fillable = [
        'sala_id',
        'tipo_valor_id',
        'serie',
        'descripcion',
        'denominacion',
        'orden',
        'activo',
    ];

    protected $casts = [
        'denominacion' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function tipoValor(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownTipoValor::class, 'tipo_valor_id');
    }
}
