<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Deposito_Administrador extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'deposito_administrador';

    protected $fillable = [
        'deposito_id',
        'usuario_id',
        'principal',
        'recibe_avisos',
        'aprueba_recepcion',
        'aprueba_transferencia',
    ];

    protected $casts = [
        'principal' => 'boolean',
        'recibe_avisos' => 'boolean',
        'aprueba_recepcion' => 'boolean',
        'aprueba_transferencia' => 'boolean',
    ];

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
