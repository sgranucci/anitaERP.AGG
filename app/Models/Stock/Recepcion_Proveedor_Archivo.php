<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Recepcion_Proveedor_Archivo extends Model
{
    public const TIPO_ADJUNTO = 'ADJUNTO';

    public const TIPO_OCR = 'OCR';

    public const OCR_PENDIENTE = 'PENDIENTE';

    public const OCR_PROCESADO = 'PROCESADO';

    public const OCR_ERROR = 'ERROR';

    protected $table = 'recepcion_proveedor_archivo';

    protected $fillable = [
        'recepcion_proveedor_id', 'nombre', 'ruta', 'tipo_archivo', 'mime',
        'ocr_texto', 'ocr_datos', 'ocr_estado',
    ];

    protected $casts = [
        'ocr_datos' => 'array',
    ];

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }
}
