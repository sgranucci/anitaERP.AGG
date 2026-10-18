<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Compras\RetencionivaTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Retencioniva extends Model implements Auditable
{
	use RetencionivaTrait;

    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
						'nombre', 'codigo', 'regimen', 'formacalculo', 'porcentajeretencion',
						'minimoimponible', 'baseimponible', 'cantidadperiodoacumula', 'valorunitario'
						];
    protected $table = 'retencioniva';
	
}
