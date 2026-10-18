<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Receptivo\Guia_Idioma;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Pais;
use App\Traits\Receptivo\GuiaTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Guia extends Model implements Auditable
{
	use GuiaTrait;

    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
							'nombre', 'codigo', 'tipodocumento', 'numerodocumento', 'maneja', 'tipoguia', 'carnetguia',
							'carnetconducir', 'categoriacarnetconducir', 'carnetsanidad', 'observacion', 'email',
							'telefono', 'domicilio' ,'localidad_id', 'provincia_id', 'pais_id', 'codigopostal'
						];
    protected $table = 'guia';
	
	public function guia_idiomas()
	{
    	return $this->hasMany(Guia_Idioma::class)->with('idiomas');;
	}

	public function localidades()
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function paises()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

}
