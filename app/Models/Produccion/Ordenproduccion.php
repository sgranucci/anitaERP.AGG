<?php

namespace App\Models\Produccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Stock\Articulo;
use Illuminate\Support\Str;

class Ordenproduccion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['fechainicio', 'fechafinalizacion', 'lineallenado_id', 'numeroordenproduccion',
                        'articulo_id', 'cantidad', 'provienebin_id', 'lote', 'observacion', 'usuario_id'];
    protected $table = 'ordenproduccion';

    public function lineallenados()
    {
        return $this->belongsTo(Lineallenado::class, 'lineallenado_id');
    }

    public function provienebines()
    {
        return $this->belongsTo(Provienebin::class, 'provienebin_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }   

	public function usuarios()
	{
        return $this->belongsTo(Usuario::class, 'usuario_id');
	}

}
