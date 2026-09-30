<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ArticuloMarketplace extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'articulo_marketplace';

    protected $fillable = [
        'articulo_id',
        'marketplace_id',
        'combinacion_id',
        'codigo_combinacion',
        'orden',
    ];

    protected $casts = [
        'articulo_id' => 'integer',
        'marketplace_id' => 'integer',
        'combinacion_id' => 'integer',
        'orden' => 'integer',
    ];

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class, 'marketplace_id');
    }

    public function combinacion(): BelongsTo
    {
        return $this->belongsTo(Combinacion::class, 'combinacion_id');
    }
}
