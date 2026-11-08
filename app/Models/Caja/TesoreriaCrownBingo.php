<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Un día de bingo por sala (a-movbingo.c / movbingo).
 * resultado = tot_carton − tot_premio − tot_rse.
 */
class TesoreriaCrownBingo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_bingo';

    protected $fillable = [
        'empresa_id',
        'sala_id',
        'fecha',
        'hora',
        'tot_carton',
        'tot_premio',
        'tot_rse',
        'resultado',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tot_carton' => 'decimal:2',
        'tot_premio' => 'decimal:2',
        'tot_rse' => 'decimal:2',
        'resultado' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public static function deSalaFecha(int $salaId, string $fecha): ?self
    {
        if ($salaId <= 0 || $fecha === '') {
            return null;
        }

        return self::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->first();
    }

    public static function calcularResultado(float $carton, float $premio, float $rse): float
    {
        return round($carton - $premio - $rse, 2);
    }
}
