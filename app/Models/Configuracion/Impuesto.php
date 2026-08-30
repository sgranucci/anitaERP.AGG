<?php

namespace App\Models\Configuracion;

use App\Support\Configuracion\RegimenPercepcionSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Impuesto extends Model
{
    protected $fillable = ['nombre', 'valor', 'fechavigencia', 'codigo', 'codigoarca'];
    protected $table = 'impuesto';

    public function impuesto_cuentacontables()
	{
    	return $this->hasMany(Impuesto_Cuentacontable::class, 'impuesto_id');
	}

    public function esPercepcion(): bool
    {
        return RegimenPercepcionSupport::esCodigoSistema((string) $this->codigo);
    }

    public function scopeSoloNacionales(Builder $query): Builder
    {
        return $query->whereNotIn('codigo', RegimenPercepcionSupport::CODIGOS_SISTEMA);
    }

    public function scopeSoloPercepcion(Builder $query): Builder
    {
        return $query->whereIn('codigo', RegimenPercepcionSupport::CODIGOS_SISTEMA);
    }
}
