<?php

namespace App\Models\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaExtracto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_extracto';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'account_number',
        'numero_extracto',
        'fecha_extracto',
        'fecha_desde',
        'fecha_hasta',
        'saldo_inicial',
        'saldo_final',
        'control_debitos',
        'control_creditos',
        'control_cantidad',
        'moneda',
        'formato',
        'archivo_origen',
        'hash_archivo',
        'estado',
        'usuario_id',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'fecha_extracto' => 'date',
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
            'saldo_inicial' => 'decimal:2',
            'saldo_final' => 'decimal:2',
            'control_debitos' => 'decimal:2',
            'control_creditos' => 'decimal:2',
            'meta_json' => 'array',
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

    public function lineas(): HasMany
    {
        return $this->hasMany(ConciliacionBancariaExtractoLinea::class, 'extracto_id');
    }
}
