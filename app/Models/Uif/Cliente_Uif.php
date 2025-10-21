<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Uif\Cliente_UifTrait;
use Illuminate\Support\Arr;

class Cliente_Uif extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;
    use Cliente_UifTrait;

    protected $fillable = ['nombre', 'tipodocumento_id', 'numerodocumento', 'cuit',
            'fechanacimiento', 'provincianacimiento_id', 'localidadnacimiento_id', 'paisnacimiento_id', 
            'sexo', 'estadocivil_uif_id', 
            'domicilio', 'piso', 'departamento', 'localidad_uif_id', 'codigopostal', 'provincia_uif_id', 
            'pais_uif_id', 'telefono', 'email', 'actividad_uif_id', 'estado', 'pep_uif_id' ,'resideparaisofiscal', 
            'resideexterior', 'fechafirmapep', 'fechaconfirmapep', 'fechainformepep', 'fechainformenosis', 
            'fechavencimientodni', 'fechavencimientoactividad', 'so_uif_id', 'cumplenormativaso', 
            'actividadso', 'firmodeclaracionjurada', 'riesgopep', 'nivelsocioeconomico_uif_id', 'fotodocumento', 
            'usuario_id'];

    protected $table = 'cliente_uif';

    public function cliente_premios_uif()
	{
    	return $this->hasMany(Cliente_Premio_Uif::class, 'cliente_uif_id');
	}

    public function cliente_riesgos_uif()
	{
    	return $this->hasMany(Cliente_Riesgo_Uif::class, 'cliente_uif_id');
	}

    public function cliente_archivos_uif()
	{
    	return $this->hasMany(Cliente_Archivo_Uif::class, 'cliente_uif_id');
	}

    public function provincia_nacimientos()
    {
        return $this->belongsTo(Provincia_Uif::class, 'provincianacimiento_id');
    }

    public function localidad_nacimientos()
    {
        return $this->belongsTo(Localidad_Uif::class, 'localidadnacimiento_id');
    }

    public function pais_nacimientos()
    {
        return $this->belongsTo(Pais_Uif::class, 'paisnacimiento_id');
    }

    public function estadociviles_uif()
    {
        return $this->belongsTo(Estadocivil_Uif::class, 'estadocivil_uif_id');
    }

    public function localidades_uif()
    {
        return $this->belongsTo(Localidad_Uif::class, 'localidad_uif_id');
    }

    public function provincias_uif()
    {
        return $this->belongsTo(Provincia_Uif::class, 'provincia_uif_id');
    }

    public function paises_uif()
    {
        return $this->belongsTo(Pais_Uif::class, 'pais_uif_id');
    }

    public function actividades_uif()
    {
        return $this->belongsTo(Actividad_Uif::class, 'actividad_uif_id');
    }

    public function peps_uif()
    {
        return $this->belongsTo(Pep_Uif::class, 'pep_uif_id');
    }

    public function sos_uif()
    {
        return $this->belongsTo(So_Uif::class, 'so_uif_id');
    }

    public function nivelsocioeconomicos_uif()
    {
        return $this->belongsTo(Nivelsocioeconomico_Uif::class, 'nivelsocioeconomico_uif_id');
    }
 
    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

}
