<?php

namespace App\Models\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaExcepcion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_EN_CURSO = 'en_curso';

    public const ESTADO_RESUELTA = 'resuelta';

    public const ESTADO_DESCARTADA = 'descartada';

    protected $table = 'conciliacion_bancaria_excepcion';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'mes',
        'anio',
        'lado',
        'hash',
        'tipo',
        'estado',
        'fecha',
        'importe',
        'dias',
        'bucket',
        'detalle',
        'movimiento_json',
        'asignado_usuario_id',
        'resuelto_por_usuario_id',
        'resuelto_at',
        'resolucion',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'importe' => 'decimal:2',
            'movimiento_json' => 'array',
            'resuelto_at' => 'datetime',
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

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asignado_usuario_id');
    }
}
