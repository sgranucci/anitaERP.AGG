<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Comprobante_Proveedor_Archivo extends Model
{
    protected $table = 'comprobante_proveedor_archivo';

    protected $fillable = [
        'comprobante_proveedor_id', 'tipo', 'nombrearchivo', 'origen_externo',
        'ruta_externa', 'precarga_comprobante_proveedor_id',
    ];

    protected $casts = [
        'origen_externo' => 'boolean',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function precarga_comprobante_proveedores()
    {
        return $this->belongsTo(Precarga_Comprobante_Proveedor::class, 'precarga_comprobante_proveedor_id');
    }
}
