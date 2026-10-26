<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownCierreFiscal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_cierre_fiscal';

    protected $fillable = [
        'comprobante_id',
        'sucursal',
        'fecha_jor',
        'hora',
        'nro_x',
        'nro_z',
        'tipo',
        'nombre_sucursal',
        'tot_venta',
        'tot_cred',
        'tot_iva',
        'tot_iva_cred',
        'ult_fact_b',
    ];

    protected $casts = [
        'sucursal' => 'integer',
        'fecha_jor' => 'date',
        'nro_x' => 'integer',
        'nro_z' => 'integer',
        'tot_venta' => 'decimal:2',
        'tot_cred' => 'decimal:2',
        'tot_iva' => 'decimal:2',
        'tot_iva_cred' => 'decimal:2',
        'ult_fact_b' => 'integer',
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCrownComprobante::class, 'comprobante_id');
    }

    public function clave(): string
    {
        return self::armarClave(
            (int) $this->sucursal,
            $this->fecha_jor?->format('Ymd') ?? '',
            (string) $this->hora,
            (int) $this->nro_x,
            (int) $this->nro_z
        );
    }

    public static function armarClave(int $sucursal, string $fecha, string $hora, int $nroX, int $nroZ): string
    {
        return $sucursal.'|'.$fecha.'|'.trim($hora).'|'.$nroX.'|'.$nroZ;
    }
}
