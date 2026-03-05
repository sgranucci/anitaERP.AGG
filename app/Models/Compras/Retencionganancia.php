<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Compras\Retencionganancia_Escala;
use App\Traits\Compras\RetenciongananciaTrait;

class Retencionganancia extends Model
{
	use RetenciongananciaTrait;

    protected $fillable = [
						'nombre', 'codigo', 'regimen', 'formacalculo', 'porcentajeinscripto', 'porcentajenoinscripto',
						'montoexcedente', 'minimoretencion', 'baseimponible', 'cantidadperiodoacumula', 'valorunitario'
						];
    protected $table = 'retencionganancia';
	
	public function retencionganancia_escalas()
	{
    	return $this->hasMany(Retencionganancia_Escala::class);
	}

}
