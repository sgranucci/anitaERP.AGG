<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Historia extends Model
{

    protected $table = 'ordencompra_historia';

    protected $fillable = [
        'ordencompra_id', 'sector_legajocompra_id', 'fecha', 'observacion', 'leyenda', 'creousuario_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function sector_legajocompras()
    {
        return $this->belongsTo(Sector_Legajocompra::class, 'sector_legajocompra_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}
