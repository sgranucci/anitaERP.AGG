<?php

namespace App\Models\Listado;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ListadoVista extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'listado_vista';

    protected $fillable = [
        'recurso',
        'usuario_id',
        'nombre',
        'filtros_json',
        'columnas_json',
        'es_default',
        'compartida',
    ];

    protected $casts = [
        'filtros_json' => 'array',
        'columnas_json' => 'array',
        'es_default' => 'boolean',
        'compartida' => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
