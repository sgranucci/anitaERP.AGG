<?php

namespace App\Models\Seguridad;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class IngresoProveedorAreaCorreo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ingreso_proveedor_area_correo';

    protected $fillable = [
        'area_id',
        'empresa_id',
        'email',
    ];

    public function areas(): BelongsTo
    {
        return $this->belongsTo(IngresoProveedorArea::class, 'area_id');
    }

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
