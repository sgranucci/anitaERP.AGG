<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ListaPrecioEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lista_precio_estacionamiento';

    protected $fillable = [
        'empresa_id',
        'categoria_automovil_id',
        'moneda_id',
        'creousuario_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function categoriaAutomovil()
    {
        return $this->belongsTo(CategoriaAutomovil::class, 'categoria_automovil_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function creoUsuario()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function items()
    {
        return $this->hasMany(ListaPrecioEstacionamientoItem::class, 'lista_precio_estacionamiento_id');
    }
}
