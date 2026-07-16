<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagoproveedor_Estado extends Model
{
    use SoftDeletes;

    protected $table = 'pagoproveedor_estado';

    protected $fillable = [
        'pagoproveedor_id', 'fecha', 'estado', 'usuario_id', 'observacion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
