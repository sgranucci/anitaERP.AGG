<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class CotConfiguracion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
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
