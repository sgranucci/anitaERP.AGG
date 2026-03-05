<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Condicionventacuota extends Model
{
    protected $fillable = ['condicionventa_id', 'cuota', 'tipoplazo', 'plazo', 'fechavencimiento', 'porcentaje', 'interes'];
    protected $table = 'condicionventacuota';

    public function condicionesventa()
    {
        return $this->belongsTo(Condicionventa::class, 'condicionventa_id');
    }
}

