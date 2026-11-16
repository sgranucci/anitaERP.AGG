<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

class BuscadorFtsDocumento extends Model
{
    protected $table = 'buscador_fts_documento';

    protected $fillable = [
        'origen_tipo',
        'origen_id',
        'padre_tipo',
        'padre_id',
        'empresa_id',
        'permiso_slug',
        'nombre_archivo',
        'ruta_relativa',
        'mime',
        'contenido_texto',
        'contenido_hash',
        'bytes_contenido',
        'indexado_at',
    ];

    protected $casts = [
        'indexado_at' => 'datetime',
    ];
}
