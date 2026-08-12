<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use App\Support\Compras\ProveedorDocumentoFiscalSupport;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Documento_Fiscal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'proveedor_documento_fiscal';

    protected $fillable = [
        'proveedor_id',
        'tipo',
        'nombrearchivo',
        'fecha_vencimiento',
        'anio_ejercicio',
        'origen',
        'presento_usuario_id',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'anio_ejercicio' => 'integer',
    ];

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function presentousuarios()
    {
        return $this->belongsTo(Usuario::class, 'presento_usuario_id');
    }

    public function etiquetaTipo(): string
    {
        return ProveedorDocumentoFiscalSupport::etiquetaTipo((string) $this->tipo);
    }

    public function estadoVigencia(): string
    {
        $fecha = $this->fecha_vencimiento
            ? $this->fecha_vencimiento->format('Y-m-d')
            : null;

        return ProveedorDocumentoFiscalSupport::estadoVigencia($fecha);
    }

    public function urlArchivo(): string
    {
        return ProveedorDocumentoFiscalSupport::urlArchivo(
            (int) $this->proveedor_id,
            (string) $this->nombrearchivo
        );
    }
}
