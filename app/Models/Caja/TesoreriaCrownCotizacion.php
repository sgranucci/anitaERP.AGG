<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownCotizacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_cotizacion';

    protected $fillable = [
        'empresa_id',
        'sala_id',
        'jornada',
        'tipo_valor_id',
        'compra',
        'venta',
    ];

    protected $casts = [
        'jornada' => 'date',
        'compra' => 'decimal:6',
        'venta' => 'decimal:6',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function tipoValor(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownTipoValor::class, 'tipo_valor_id');
    }
}
