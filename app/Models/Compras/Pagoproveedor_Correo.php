<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Pagoproveedor_Correo extends Model
{
    protected $table = 'pagoproveedor_correo';

    protected $fillable = [
        'pagoproveedor_id',
        'pagoproveedor_estado_id',
        'fecha',
        'usuario_id',
        'destinatarios',
        'mensaje',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }

    public function pagoproveedor_estados()
    {
        return $this->belongsTo(Pagoproveedor_Estado::class, 'pagoproveedor_estado_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
