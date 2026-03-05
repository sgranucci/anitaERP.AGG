<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Auth;

class Cotizacion extends Model
{
    protected $fillable = ['fecha', 'usuario_id'];
    protected $table = 'cotizacion';

    public function cotizacion_monedas()
	{
    	return $this->hasMany(Cotizacion_Moneda::class, 'cotizacion_id')
                        ->with('monedas');
	}

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
