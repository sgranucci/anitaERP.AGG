<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteSueldosDefinibleAclUsuario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'usuario_reporte_sueldos_definible';

    protected $fillable = [
        'usuario_id',
        'reporte_sueldos_definible_id',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'reporte_sueldos_definible_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }
}
