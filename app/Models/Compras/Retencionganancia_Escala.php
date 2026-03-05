<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Retencionganancia_Escala extends Model
{
    protected $fillable = [
                            'retencionganancia_id', 'desdemonto', 'hastamonto', 'montoretencion', 'porcentajeretencion', 'excedente'
                        ];
    protected $table = 'retencionganancia_escala';

    public function retencionesganancia()
    {
        return $this->belongsTo(Retencionganancia::class, 'retencionganancia_id');
    }
}

