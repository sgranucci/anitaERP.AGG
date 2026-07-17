<?php

namespace App\Models\Solicitudpago;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Solicitudpago_Cuota extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitudpago_cuota';

    protected $fillable = [
        'solicitudpago_id',
        'nro_cuota',
        'fecha_vencimiento',
        'monto',
        'solicitudpago_hija_id',
    ];

    protected $casts = [
        'solicitudpago_id' => 'integer',
        'nro_cuota' => 'integer',
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'solicitudpago_hija_id' => 'integer',
    ];

    public function solicitudpagos()
    {
        return $this->belongsTo(Solicitudpago::class, 'solicitudpago_id');
    }

    public function hijas()
    {
        return $this->belongsTo(Solicitudpago::class, 'solicitudpago_hija_id');
    }
}
