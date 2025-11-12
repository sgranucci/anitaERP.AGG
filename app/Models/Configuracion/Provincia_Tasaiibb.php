<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Cuentacontable;

class Provincia_Tasaiibb extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['provincia_id', 'condicioniibb_id', 'tasa', 'minimoneto', 
                            'minimopercepcion', 'creousuario_id'];
    protected $table = 'provincia_tasaiibb';

	public function provincias()
	{
    	return $this->belongsTo(Provincia::class, 'provincia_id', 'id');
	}

	public function condicioniibbs()
	{
    	return $this->belongsTo(CondicionIIBB::class, 'condicioniibb_id', 'id');
	}

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

}
