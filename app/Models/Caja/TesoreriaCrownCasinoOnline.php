<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownCasinoOnline extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_casino_online';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'attendance',
        'apostado',
        'pagado',
        'drop',
        'resultado',
        'porcentaje',
        'poder_publico',
        'betsur_attendance',
        'betsur_apostado',
        'betsur_pagado',
        'betsur_drop',
        'betsur_resultado',
        'betsur_porcentaje',
        'betsur_poder_publico',
        'ad_attendance',
        'ad_apostado',
        'ad_pagado',
        'ad_drop',
        'ad_resultado',
        'ad_porcentaje',
        'ad_poder_publico',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'attendance' => 'integer',
        'apostado' => 'decimal:2',
        'pagado' => 'decimal:2',
        'drop' => 'decimal:2',
        'resultado' => 'decimal:2',
        'porcentaje' => 'decimal:4',
        'poder_publico' => 'decimal:2',
        'betsur_attendance' => 'integer',
        'betsur_apostado' => 'decimal:2',
        'betsur_pagado' => 'decimal:2',
        'betsur_drop' => 'decimal:2',
        'betsur_resultado' => 'decimal:2',
        'betsur_porcentaje' => 'decimal:4',
        'betsur_poder_publico' => 'decimal:2',
        'ad_attendance' => 'integer',
        'ad_apostado' => 'decimal:2',
        'ad_pagado' => 'decimal:2',
        'ad_drop' => 'decimal:2',
        'ad_resultado' => 'decimal:2',
        'ad_porcentaje' => 'decimal:4',
        'ad_poder_publico' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public static function deFecha(string $fecha): ?self
    {
        if ($fecha === '') {
            return null;
        }

        return self::query()->whereDate('fecha', $fecha)->first();
    }
}
