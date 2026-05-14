<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Tiposervicio_Proveedor extends Model
{
    public const UNICIDAD_CUIT_CONTROLA = 'CONTROLA';

    public const UNICIDAD_CUIT_NO_CONTROLA = 'NO CONTROLA';

    protected $fillable = ['nombre', 'controla_unicidad_cuit'];

    protected $table = 'tiposervicio_proveedor';
}
