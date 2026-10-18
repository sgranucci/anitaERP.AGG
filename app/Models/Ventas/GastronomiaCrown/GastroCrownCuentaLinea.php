<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Stock\Articulo;
use App\Models\Ventas\AreaComandaGastronomia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class GastroCrownCuentaLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'gastro_crown_cuenta_linea';

    protected $fillable = [
        'gastro_crown_cuenta_id',
        'orden',
        'articulo_id',
        'sku',
        'descripcion',
        'cantidad',
        'precio',
        'descuento_importe',
        'descuento_gastronomia_id',
        'observacion',
        'marcado',
        'es_separador',
        'prepara',
        'area_comanda_gastronomia_id',
        'gastro_crown_comanda_id',
        'fired_at',
        'fired_postre_at',
        'modifiers',
    ];

    protected $casts = [
        'orden' => 'integer',
        'cantidad' => 'float',
        'precio' => 'float',
        'descuento_importe' => 'float',
        'marcado' => 'boolean',
        'es_separador' => 'boolean',
        'prepara' => 'boolean',
        'fired_at' => 'datetime',
        'fired_postre_at' => 'datetime',
        'modifiers' => 'array',
    ];

    /**
     * Opcionales de fórmula (orden => selección), igual contrato que `CuentaGastronomiaLinea::opcionales_json`.
     * Crown los guarda en `modifiers`: no hay columna propia.
     *
     * @return array<string|int, mixed>
     */
    public function getOpcionalesJsonAttribute(): array
    {
        return is_array($this->modifiers) ? $this->modifiers : [];
    }

    /**
     * @param  array<string|int, mixed>|null  $valor
     */
    public function setOpcionalesJsonAttribute(?array $valor): void
    {
        $this->modifiers = $valor === null || $valor === [] ? null : $valor;
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(GastroCrownCuenta::class, 'gastro_crown_cuenta_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function areaComanda(): BelongsTo
    {
        return $this->belongsTo(AreaComandaGastronomia::class, 'area_comanda_gastronomia_id');
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(GastroCrownComanda::class, 'gastro_crown_comanda_id');
    }
}
