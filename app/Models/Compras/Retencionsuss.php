<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Compras\RetencionsussTrait;

class Retencionsuss extends Model
{
	use RetencionsussTrait;

    protected $fillable = [
						'nombre', 'codigo', 'regimen', 'formacalculo', 'minimoimponible',
						'valorretencion'
						];
    protected $table = 'retencionsuss';
	
}
