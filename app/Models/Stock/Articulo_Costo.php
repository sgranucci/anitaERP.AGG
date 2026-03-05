<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Produccion\Tarea;

class Articulo_Costo extends Model
{
    protected $fillable = ['articulo_id', 'tarea_id', 'costo', 'fechavigencia'];
    protected $table = 'articulo_costo';
	protected $casts = [
			'fechavigencia' => 'datetime',
	];
	
    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id', 'id');
    }

    public function tareas()
    {
        return $this->belongsTo(Tarea::class, 'tarea_id', 'id');
    }

}
