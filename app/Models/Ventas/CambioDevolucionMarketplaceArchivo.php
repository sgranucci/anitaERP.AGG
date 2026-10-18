<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CambioDevolucionMarketplaceArchivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'cambio_devolucion_marketplace_archivo';

    protected $fillable = [
        'cambio_id',
        'nombrearchivo',
    ];

    protected $casts = [
        'cambio_id' => 'integer',
    ];

    public function cambio()
    {
        return $this->belongsTo(CambioDevolucionMarketplace::class, 'cambio_id');
    }
}
