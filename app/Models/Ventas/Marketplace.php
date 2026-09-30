<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Marketplace extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'marketplace';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'activo' => 'boolean',
    ];

    public function asignaciones(): HasMany
    {
        return $this->hasMany(ArticuloMarketplace::class, 'marketplace_id');
    }

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'articulo_marketplace', 'marketplace_id', 'articulo_id')
            ->withTimestamps();
    }
}
