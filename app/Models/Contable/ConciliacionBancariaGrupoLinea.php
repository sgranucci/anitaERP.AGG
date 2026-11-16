<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaGrupoLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const LADO_CONTABLE = 'C';

    public const LADO_BANCO = 'B';

    protected $table = 'conciliacion_bancaria_grupo_linea';

    protected $fillable = [
        'grupo_id',
        'cuentacaja_id',
        'lado',
        'hash',
        'movimiento_json',
        'fecha',
        'importe',
    ];

    protected function casts(): array
    {
        return [
            'movimiento_json' => 'array',
            'fecha' => 'date',
            'importe' => 'decimal:2',
        ];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(ConciliacionBancariaGrupo::class, 'grupo_id');
    }
}
