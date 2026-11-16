<?php

declare(strict_types=1);

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_archivo';

    protected $fillable = [
        'importacion_id',
        'nombre_original',
        'ruta',
        'mime',
        'tamano',
        'usuario_id',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
