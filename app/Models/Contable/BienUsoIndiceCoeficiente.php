<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class BienUsoIndiceCoeficiente extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso_indice_coeficiente';

    protected $fillable = [
        'bien_uso_indice_id',
        'anio',
        'mes',
        'coeficiente',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'coeficiente' => 'decimal:6',
        ];
    }

    public function indice(): BelongsTo
    {
        return $this->belongsTo(BienUsoIndice::class, 'bien_uso_indice_id');
    }
}
