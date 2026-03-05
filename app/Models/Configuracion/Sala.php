<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Sala extends Model
{
    protected $fillable = ['nombre', 'codigo', 'empresa_id'];
    protected $table = 'sala';

    public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
	}
}

