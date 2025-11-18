<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Models\Configuracion\Provincia;
use App\Traits\Ventas\Cliente_Cm05Trait;

class Cliente_Cm05 extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Cliente_Cm05Trait;
    protected $fillable = ['cliente_id', 'provincia_id', 'tipopercepcion', 'coeficiente', 'fechavigencia', 
                            'certificadonoretencion', 'desdefechanoretencion', 'hastafechanoretencion', 'creousuario_id'];
	
    protected $table = 'cliente_cm05';

    public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id');
	}

    public function provincias()
	{
    	return $this->belongsTo(Provincia::class, 'provincia_id', 'id');
	}

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

}
