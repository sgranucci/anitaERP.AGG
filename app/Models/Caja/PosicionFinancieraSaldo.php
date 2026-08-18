<?php

declare(strict_types=1);

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class PosicionFinancieraSaldo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'posicion_financiera_saldo';

    protected $fillable = [
        'empresa_id',
        'fecha_cierre',
        'saldo_inicial',
        'saldo_final',
        'origen',
        'filtros_json',
        'confirmado_por',
        'confirmado_at',
        'anulado_por',
        'anulado_at',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_cierre' => 'date',
        'saldo_inicial' => 'float',
        'saldo_final' => 'float',
        'filtros_json' => 'array',
        'confirmado_at' => 'datetime',
        'anulado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'confirmado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulado_por');
    }
}
