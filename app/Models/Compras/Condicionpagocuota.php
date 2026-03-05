<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Condicionpagocuota extends Model
{
    protected $fillable = ['condicionpago_id', 'cuota', 'tipoplazo', 'plazo', 'fechavencimiento', 'porcentaje', 'interes'];
    protected $table = 'condicionpagocuota';

    public function condicionespago()
    {
        return $this->belongsTo(Condicionpago::class, 'condicionpago_id');
    }
}

