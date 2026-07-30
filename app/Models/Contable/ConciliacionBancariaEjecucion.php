<?php

namespace App\Models\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaEjecucion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_ejecucion';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'mes',
        'anio',
        'fecha_desde',
        'fecha_hasta',
        'saldo_banco',
        'saldo_contable',
        'diferencia',
        'resumen_json',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
            'saldo_banco' => 'decimal:2',
            'saldo_contable' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'resumen_json' => 'array',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function chequesPendientes(): HasMany
    {
        return $this->hasMany(ConciliacionBancariaChequePendiente::class, 'ejecucion_id');
    }
}
