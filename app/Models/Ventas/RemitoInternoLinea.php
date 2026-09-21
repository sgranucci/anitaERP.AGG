<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Modulo;
use App\Models\Stock\Talle;
use App\Models\Stock\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class RemitoInternoLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'remito_interno_linea';

    protected $fillable = [
        'remito_interno_id',
        'orden',
        'articulo_id',
        'combinacion_id',
        'talle_id',
        'color_id',
        'modulo_id',
        'cantidad',
        'descripcion',
    ];

    protected $casts = [
        'orden' => 'integer',
        'cantidad' => 'float',
    ];

    public function remitoInterno(): BelongsTo
    {
        return $this->belongsTo(RemitoInterno::class, 'remito_interno_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function combinacion(): BelongsTo
    {
        return $this->belongsTo(Combinacion::class, 'combinacion_id');
    }

    public function talle(): BelongsTo
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }
}
