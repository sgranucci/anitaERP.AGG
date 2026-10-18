<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class BienUsoRevaluo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso_revaluo';

    protected $fillable = [
        'bien_uso_id',
        'empresa_id',
        'periodo_anio',
        'valor_actual',
        'valor_residual',
        'valor_comienzo',
        'revaluo_acumulado',
    ];

    protected function casts(): array
    {
        return [
            'periodo_anio' => 'integer',
            'valor_actual' => 'decimal:2',
            'valor_residual' => 'decimal:2',
            'valor_comienzo' => 'decimal:2',
            'revaluo_acumulado' => 'decimal:2',
        ];
    }

    public function bienUso(): BelongsTo
    {
        return $this->belongsTo(BienUso::class, 'bien_uso_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
