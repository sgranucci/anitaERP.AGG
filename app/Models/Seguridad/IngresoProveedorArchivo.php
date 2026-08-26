<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class IngresoProveedorArchivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const DISCO = 'public';

    public const CARPETA = 'ingreso_proveedor';

    protected $table = 'ingreso_proveedor_archivo';

    protected $fillable = [
        'ingreso_proveedor_id', 'nombre_original', 'nombre_archivo', 'mime', 'tamanio',
    ];

    public function ingreso(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IngresoProveedor::class, 'ingreso_proveedor_id');
    }

    public function rutaRelativa(): string
    {
        return self::CARPETA.'/'.$this->nombre_archivo;
    }

    public function urlPublica(): string
    {
        $url = Storage::disk(self::DISCO)->url($this->rutaRelativa());

        return \App\Support\Archivos\ArchivoAdjuntoCacheSupport::conVersion(
            $url,
            Storage::disk(self::DISCO)->path($this->rutaRelativa())
        );
    }
}
