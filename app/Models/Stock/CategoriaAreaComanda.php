<?php

namespace App\Models\Stock;

use App\Models\Ventas\AreaComandaGastronomia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class CategoriaAreaComanda extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'categoria_area_comanda';

    protected $fillable = ['categoria_id', 'area_comanda_gastronomia_id'];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function areaComanda(): BelongsTo
    {
        return $this->belongsTo(AreaComandaGastronomia::class, 'area_comanda_gastronomia_id');
    }
}
