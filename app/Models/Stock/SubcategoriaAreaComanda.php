<?php

namespace App\Models\Stock;

use App\Models\Ventas\AreaComandaGastronomia;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class SubcategoriaAreaComanda extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'subcategoria_area_comanda';

    protected $fillable = ['subcategoria_id', 'area_comanda_gastronomia_id'];

    public function subcategoria()
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id');
    }

    public function areaComanda()
    {
        return $this->belongsTo(AreaComandaGastronomia::class, 'area_comanda_gastronomia_id');
    }
}
