<?php

namespace App\Models\Ordenventa;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Ordenventa\Ordenventa_EstadoTrait;
use App\Models\Seguridad\Usuario;

class Ordenventa_Estado extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
	use Ordenventa_EstadoTrait;

    protected $fillable = ['ordenventa_id', 'fecha', 'estado', 'observacion', 'usuario_id'];
    protected $table = 'ordenventa_estado';

	public function ordenventas()
	{
    	return $this->belongsTo(Ordenventa::class, 'ordenventa_id', 'id');
	}

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}

}
