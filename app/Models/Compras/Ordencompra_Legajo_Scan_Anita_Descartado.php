<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Ordencompra_Legajo_Scan_Anita_Descartado extends Model
{
    protected $table = 'ordencompra_legajo_scan_anita_descartado';

    protected $fillable = [
        'ordencompra_id',
        'empresa_id',
        'numeroordencompra',
        'documento_id',
        'letra',
        'sucursal',
        'numerocomprobante',
        'precarga_id_origen',
        'user_id',
    ];
}
