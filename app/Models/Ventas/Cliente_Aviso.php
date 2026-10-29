<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Aviso extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cliente_aviso';

    protected $fillable = [
        'cliente_id',
        'disparador',
        'modo',
        'texto',
        'accion',
        'desdefecha',
        'hastafecha',
        'orden',
        'creousuario_id',
    ];

    protected $casts = [
        'desdefecha' => 'date',
        'hastafecha' => 'date',
        'orden' => 'integer',
    ];

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}
