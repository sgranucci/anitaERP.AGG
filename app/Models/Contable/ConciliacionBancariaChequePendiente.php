<?php

namespace App\Models\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliacionBancariaChequePendiente extends Model
{
    protected $table = 'conciliacion_bancaria_cheque_pendiente';

    protected $fillable = [
        'ejecucion_id',
        'empresa_id',
        'cuentacaja_id',
        'tip',
        'numero_cheque',
        'fecha_emision',
        'fecha_cheque',
        'fecha_entrega',
        'fecha_conciliacion',
        'importe',
        'estado',
        'estado_banco',
        'entregado_a',
        'proveedor_codigo',
        'nro_op',
        'para_dep',
        'incluye_caratula',
        'origen_json',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_cheque' => 'date',
            'fecha_entrega' => 'date',
            'fecha_conciliacion' => 'date',
            'importe' => 'decimal:2',
            'incluye_caratula' => 'boolean',
            'origen_json' => 'array',
        ];
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(ConciliacionBancariaEjecucion::class, 'ejecucion_id');
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
