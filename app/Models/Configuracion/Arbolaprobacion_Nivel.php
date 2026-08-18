<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Centrocosto;
use App\Models\Configuracion\Moneda;

class Arbolaprobacion_Nivel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected $fillable = [
                            'arbolaprobacion_id', 'nivel', 'centrocosto_id', 'usuario_id', 'usuario_orig_id', 'desdemonto', 'hastamonto', 'moneda_id',
                            'documento_estado_al_aprobar', 'doble_aprobacion',
                        ];
    protected $table = 'arbolaprobacion_nivel';

    public function arbolaprobaciones()
	{
    	return $this->belongsTo(Arbolaprobacion::class, 'arbolaprobacion_id');
	}

    public function centrocosto_ids()
	{
    	return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
	}

    public function usuarios()
	{
    	return $this->belongsTo(Usuario::class, 'usuario_id');
	}

    public function usuarios_orig()
	{
    	return $this->belongsTo(Usuario::class, 'usuario_orig_id');
	}

    public function moneda_ids()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

}

