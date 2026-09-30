<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class TiendanubeStockSubida extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ORIGEN_CRON = 'cron';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_SIMULACION = 'simulacion';

    public const ESTADO_PROCESO = 'en_proceso';

    public const ESTADO_OK = 'ok';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_ERROR = 'error';

    public static function etiquetaOrigen(?string $origen): string
    {
        return match ($origen) {
            self::ORIGEN_MANUAL => 'Manual',
            self::ORIGEN_SIMULACION => 'Simulación',
            default => 'Diaria',
        };
    }

    protected $table = 'tiendanube_stock_subida';

    protected $fillable = [
        'store_id',
        'origen',
        'usuario_id',
        'hora_programada',
        'marketplace_codigo',
        'inicio_at',
        'fin_at',
        'estado',
        'variantes_ok',
        'variantes_error',
        'variantes_omitidas',
        'mensaje',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'marketplace_codigo' => 'integer',
        'inicio_at' => 'datetime',
        'fin_at' => 'datetime',
        'variantes_ok' => 'integer',
        'variantes_error' => 'integer',
        'variantes_omitidas' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TiendanubeStockSubidaLinea::class, 'subida_id');
    }
}
