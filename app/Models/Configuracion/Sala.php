<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Sala extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'codigo', 'empresa_id'];
    protected $table = 'sala';

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
	}
}

