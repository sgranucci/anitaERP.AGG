<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Sancion_Archivo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_sancion_archivo_sueldos';

    protected $fillable = [
        'sancion_id',
        'nombre_original',
        'path',
        'usuario_id',
    ];

    protected $casts = [
        'sancion_id' => 'integer',
        'usuario_id' => 'integer',
    ];

    public function sancion(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sancion_Sueldos::class, 'sancion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
