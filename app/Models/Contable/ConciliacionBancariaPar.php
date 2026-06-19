<?php

namespace App\Models\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaPar extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_par';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'hash_contable',
        'hash_banco',
        'contable_json',
        'banco_json',
        'fecha_contable',
        'fecha_banco',
        'importe',
        'conciliado_por_usuario_id',
        'conciliado_at',
    ];

    protected function casts(): array
    {
        return [
            'contable_json' => 'array',
            'banco_json' => 'array',
            'fecha_contable' => 'date',
            'fecha_banco' => 'date',
            'importe' => 'decimal:2',
            'conciliado_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}
