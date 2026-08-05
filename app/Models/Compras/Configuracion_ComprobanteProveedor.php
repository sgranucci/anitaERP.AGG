<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class Configuracion_ComprobanteProveedor extends Model
{
    protected $table = 'configuracion_comprobante_proveedor';

    protected $fillable = [
        'empresa_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function tolerancias()
    {
        return $this->hasMany(Configuracion_ComprobanteProveedorTolerancia::class, 'empresa_id', 'empresa_id');
    }
}
