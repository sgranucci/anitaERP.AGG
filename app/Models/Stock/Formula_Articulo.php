<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\FormulaArticuloAnitaSyncService;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use OwenIt\Auditing\Contracts\Auditable;

class Formula_Articulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'formula_articulo';

    protected $fillable = [
        'articulo_id', 'codigo', 'anita_stkcm_formula', 'detalle', 'cantidadunidad', 'estado', 'creousuario_id',
    ];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function formula_articulo_hijos()
    {
        return $this->hasMany(Formula_Articulo_Hijo::class, 'formula_articulo_id');
    }

    public function formula_articulo_estados()
    {
        return $this->hasMany(Formula_Articulo_Estado::class, 'formula_articulo_id');
    }

    public function formula_articulo_archivos()
    {
        return $this->hasMany(Formula_Articulo_Archivo::class, 'formula_articulo_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    /**
     * Sincroniza tablas Anita stkcmae / stkcmov contra el ERP vía {@see \App\ApiAnita} (mismo puente que {@see Articulo::sincronizarConAnita()}).
     *
     * @return array{formulas:int, lineas:int, advertencias:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $usuarioId = Auth::check() ? (int) Auth::id() : 1;

        return App::make(FormulaArticuloAnitaSyncService::class)->sincronizarDesdeApi($usuarioId);
    }
}
