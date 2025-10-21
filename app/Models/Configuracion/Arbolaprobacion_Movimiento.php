<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Models\Ordenventa\Ordenventa;
use App\Traits\Configuracion\Arbolaprobacion_MovimientoTrait;

class Arbolaprobacion_Movimiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
	use Arbolaprobacion_MovimientoTrait;
    
    protected $fillable = [
                            'arbolaprobacion_id', 'fechaenvio', 'enviousuario_id', 'requisicion_id', 'ordencompra_id',
							'solicitudpago_id', 'ordenventa_id', 'hashaprobacion', 'hashrechazo', 'hashvisualizar', 'nivel',
							'destinatariousuario_id', 'fechaproceso', 'estado', 'observacion'
                        ];
    protected $table = 'arbolaprobacion_movimiento';

    public function arbolaprobaciones()
	{
    	return $this->belongsTo(Arbolaprobacion::class, 'arbolaprobacion_id');
	}

	public function ordenventas()
	{
    	return $this->belongsTo(Ordenventa::class, 'ordenventa_id');
	}

    public function enviousuarios()
	{
    	return $this->belongsTo(Usuario::class, 'enviousuario_id');
	}

	public function destinatariousuarios()
	{
    	return $this->belongsTo(Usuario::class, 'destinatariousuario_id');
	}

}

