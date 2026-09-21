<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CambioDevolucionMarketplaceEstado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cambio_devolucion_marketplace_estado';

    protected $fillable = [
        'cambio_id',
        'fecha',
        'estado',
        'usuario_id',
        'observacion',
    ];

    protected $casts = [
        'cambio_id' => 'integer',
        'usuario_id' => 'integer',
        'fecha' => 'datetime',
    ];

    public function cambio()
    {
        return $this->belongsTo(CambioDevolucionMarketplace::class, 'cambio_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
