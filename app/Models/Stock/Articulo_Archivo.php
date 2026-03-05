<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Articulo_Archivo extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $fillable = ['articulo_id', 'nombrearchivo'];
    protected $table = 'articulo_archivo';

	public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id', 'id');
	}

}
