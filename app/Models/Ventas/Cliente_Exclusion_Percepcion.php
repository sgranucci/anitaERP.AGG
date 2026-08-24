<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Provincia;
use App\Models\Seguridad\Usuario;
use App\Traits\Ventas\Cliente_Exclusion_PercepcionTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Exclusion_Percepcion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Cliente_Exclusion_PercepcionTrait;

    protected $table = 'cliente_exclusion_percepcion';

    protected $fillable = [
        'cliente_id',
        'tipo',
        'provincia_id',
        'porcentaje',
        'desdefecha',
        'hastafecha',
        'creousuario_id',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:4',
        'desdefecha' => 'date',
        'hastafecha' => 'date',
    ];

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}
