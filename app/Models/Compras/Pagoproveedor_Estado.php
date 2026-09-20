<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Pagoproveedor_Estado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

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
