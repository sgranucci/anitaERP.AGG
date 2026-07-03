<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ViandaTipoMenuArticulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vianda_tipo_menu_articulo';

    protected $fillable = [
        'vianda_tipo_menu_id',
        'dia_semana',
        'articulo_id',
        'orden',
    ];

    public function tipoMenu()
    {
        return $this->belongsTo(ViandaTipoMenu::class, 'vianda_tipo_menu_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
