<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class Condicionventacuota extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['condicionventa_id', 'cuota', 'tipoplazo', 'plazo', 'fechavencimiento', 'porcentaje', 'interes'];
    protected $table = 'condicionventacuota';

    public function condicionesventa()
    {
        return $this->belongsTo(Condicionventa::class, 'condicionventa_id');
    }
}

