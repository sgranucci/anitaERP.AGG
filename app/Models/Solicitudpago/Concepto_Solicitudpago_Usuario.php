<?php

namespace App\Models\Solicitudpago;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Solicitudpago_Usuario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_solicitudpago_usuario';

    protected $fillable = [
        'concepto_solicitudpago_id',
        'nivel',
        'usuario_id',
        'usuario_orig_id',
        'desde_monto',
    ];

    protected $casts = [
        'concepto_solicitudpago_id' => 'integer',
        'nivel' => 'integer',
        'usuario_id' => 'integer',
        'usuario_orig_id' => 'integer',
        'desde_monto' => 'decimal:2',
    ];

    public function conceptos()
    {
        return $this->belongsTo(Concepto_Solicitudpago::class, 'concepto_solicitudpago_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function usuarios_orig()
    {
        return $this->belongsTo(Usuario::class, 'usuario_orig_id');
    }
}
