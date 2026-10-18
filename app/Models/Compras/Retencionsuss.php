<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Compras\RetencionsussTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Retencionsuss extends Model implements Auditable
{
	use RetencionsussTrait;

    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
						'nombre', 'codigo', 'regimen', 'formacalculo', 'minimoimponible',
						'valorretencion', 'minimoretencion',
						];
    protected $table = 'retencionsuss';
	
}
