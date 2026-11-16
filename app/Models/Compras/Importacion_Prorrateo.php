<?php

declare(strict_types=1);

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Prorrateo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_prorrateo';

    protected $fillable = [
        'importacion_id',
        'usuario_id',
        'calculado_en',
        'aplicado',
        'aplicado_en',
        'detalle_json',
    ];

    protected $casts = [
        'aplicado' => 'boolean',
        'calculado_en' => 'datetime',
        'aplicado_en' => 'datetime',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(Importacion_Prorrateo_Linea::class, 'importacion_prorrateo_id');
    }
}
