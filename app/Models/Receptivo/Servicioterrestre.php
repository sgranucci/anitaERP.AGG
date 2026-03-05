<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Receptivo\ServicioterrestreTrait;

class Servicioterrestre extends Model
{
	use ServicioterrestreTrait;

    protected $fillable = [
						 'nombre', 'codigo', 'tiposervicioterrestre_id', 'moneda_id', 'observacion', 
						 'abreviatura', 'ubicacion', 'impuesto_id', 'precioindividual', 'monedacosto_id',
						 'costoconiva', 
						 'modoexento', 'valorexento', 'porcentajeganancia', 'prepago'
						];
    protected $table = 'servicioterrestre';

	public function tiposervicioterrestres()
    {
        return $this->belongsTo(Tiposervicioterrestre::class, 'tiposervicioterrestre_id');
    }

}
