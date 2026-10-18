<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cotizacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['fecha', 'usuario_id'];
    protected $table = 'cotizacion';

    public function cotizacion_monedas()
    {
        return $this->hasMany(Cotizacion_Moneda::class, 'cotizacion_id')
            ->with('monedas');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
