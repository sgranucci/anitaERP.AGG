<?php

namespace App\Models\Ordenventa;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;

class Concepto_Cuentacontable_Ordenventa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['concepto_ordenventa_id', 'empresa_id', 'cuentacontable_id', 'creousuario_id'];
    protected $table = 'concepto_cuentacontable_ordenventa';

	public function concepto_ordenventas()
	{
    	return $this->belongsTo(Concepto_Ordenventa::class, 'concepto_ordenventa_id', 'id');
	}

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
	}

	public function cuentacontables()
	{
    	return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id', 'id');
	}

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

}
