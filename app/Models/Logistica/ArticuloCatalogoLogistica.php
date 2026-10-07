<?php

namespace App\Models\Logistica;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ArticuloCatalogoLogistica extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'articulo_catalogo_logistica';

    protected $fillable = ['articulo_id', 'catalogo_categoria_id', 'publicable', 'favorito'];

    protected $casts = [
        'articulo_id' => 'integer',
        'catalogo_categoria_id' => 'integer',
        'publicable' => 'boolean',
        'favorito' => 'boolean',
    ];

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(LogisticaCatalogoCategoria::class, 'catalogo_categoria_id');
    }
}
