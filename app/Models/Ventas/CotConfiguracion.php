<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotConfiguracion extends Model
{
    protected $table = 'cot_configuracion';

    protected $fillable = [
        'modo',
        'updated_by',
    ];

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'updated_by');
    }
}
