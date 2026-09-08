<?php

namespace App\Models\Tesoreria;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosicionBancariaCheque extends Model
{
    protected $table = 'posicion_bancaria_cheque';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'banco_canonico',
        'tip',
        'numero_cheque',
        'fecha_emision',
        'fecha_cheque',
        'fecha_entrega',
        'importe',
        'estado',
        'estado_banco',
        'entregado_a',
        'proveedor_codigo',
        'nro_op',
        'activo',
        'en_portfolio_posicion',
        'origen',
        'origen_json',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_cheque' => 'date',
        'fecha_entrega' => 'date',
        'importe' => 'decimal:2',
        'activo' => 'boolean',
        'en_portfolio_posicion' => 'boolean',
        'origen_json' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}
