<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Ventas\PuntoventaTrait;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Actividad_Arca;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Puntoventa extends Model implements Auditable
{
    use SoftDeletes;
	use PuntoventaTrait;

    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'codigo', 'empresa_id', 'domicilio', 'localidad_id', 
                            'provincia_id', 'pais_id', 'codigopostal', 'email', 'telefono', 
                            'leyenda', 'modofacturacion', 'estado', 'webservice', 'iva_ventas', 'es_remito', 'pathafip', 'actividad_arca_id',
                            'division', 'numeropoliza', 'puntoventa_remito'];
    protected $table = 'puntoventa';

    protected $casts = [
        'iva_ventas' => 'boolean',
        'es_remito' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Puntoventa $puntoventa): void {
            if ($puntoventa->codigo === null || $puntoventa->codigo === '') {
                return;
            }
            $normalizado = self::normalizarCodigoArca((string) $puntoventa->codigo);
            if ($normalizado !== null) {
                $puntoventa->codigo = $normalizado;
            }
        });
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

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
    
    public function actividad_arcas()
    {
        return $this->belongsTo(Actividad_Arca::class, 'actividad_arca_id');
    }

}

