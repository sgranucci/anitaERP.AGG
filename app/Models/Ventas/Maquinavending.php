<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Maquinavending extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'codigo_anita',
        'empresa_id',
        'nombre',
        'puntoventa_id',
        'ubicacion_id',
        'deposito_id',
        'listaprecio_id',
        'codigo_arca',
        'numero_serie',
    ];

    protected $casts = [
        'codigo_anita' => 'integer',
    ];

    protected $table = 'maquinavending';

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function puntoventa()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(UbicacionGastronomia::class, 'ubicacion_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function listaprecio()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function articulos()
    {
        return $this->hasMany(MaquinavendingArticulo::class, 'maquinavending_id')
            ->orderBy('numero_rulo');
    }
}
