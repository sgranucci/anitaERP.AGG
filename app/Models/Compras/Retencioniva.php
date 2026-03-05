<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Compras\RetencionivaTrait;

class Retencioniva extends Model
{
	use RetencionivaTrait;

    protected $fillable = [
						'nombre', 'codigo', 'regimen', 'formacalculo', 'porcentajeretencion',
						'minimoimponible', 'baseimponible', 'cantidadperiodoacumula', 'valorunitario'
						];
    protected $table = 'retencioniva';
	
}
