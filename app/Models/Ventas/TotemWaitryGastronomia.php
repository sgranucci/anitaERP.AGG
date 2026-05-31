<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TotemWaitryGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['empresa_id', 'ubicacion_id', 'waitry_table_id', 'detalle'];

    protected $casts = [
        'waitry_table_id' => 'integer',
    ];

    protected $table = 'totem_waitry_gastronomia';

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(UbicacionGastronomia::class, 'ubicacion_id');
    }
}
