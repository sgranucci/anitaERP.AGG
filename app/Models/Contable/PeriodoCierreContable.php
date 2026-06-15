<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoCierreContable extends Model
{
    protected $table = 'contable_periodo_cierre';

    protected $fillable = [
        'empresa_id',
        'fecha_hasta',
        'observacion',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_hasta' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function saldosCierre(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Cuentacontable_Saldo_Cierre::class, 'periodo_cierre_id');
    }
}
