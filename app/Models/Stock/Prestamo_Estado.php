<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Prestamo_Estado extends Model
{
    protected $table = 'prestamo_estado';

    protected $fillable = [
        'prestamo_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'observaciones',
        'ocurrio_el',
    ];

    protected $casts = [
        'ocurrio_el' => 'datetime',
    ];

    public function prestamos()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
