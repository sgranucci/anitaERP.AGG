<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Cuentacontable;
use App\Traits\Stock\Articulo_CuentacontableTrait;

class Articulo_Cuentacontable extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
	use Articulo_CuentacontableTrait;
    protected $fillable = ['articulo_id', 'empresa_id', 'tipoimputacion', 'cuentacontable_id', 'creousuario_id'];
    protected $table = 'articulo_cuentacontable';

	public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id', 'id');
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
