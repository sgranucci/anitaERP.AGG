<?php

namespace App\Models\Solicitudpago;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Solicitudpago extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_solicitudpago';

    protected $fillable = [
        'codigo',
        'nombre',
        'sector_solicitudpago_id',
        'forma_pago',
        'estado',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'sector_solicitudpago_id' => 'integer',
    ];

    public function sectores()
    {
        return $this->belongsTo(Sector_Solicitudpago::class, 'sector_solicitudpago_id');
    }

    public function usuarios()
    {
        return $this->hasMany(Concepto_Solicitudpago_Usuario::class, 'concepto_solicitudpago_id')
            ->orderBy('nivel')
            ->orderBy('id');
    }

    public function cuentas()
    {
        return $this->hasMany(Concepto_Solicitudpago_Cuenta::class, 'concepto_solicitudpago_id')
            ->orderBy('id');
    }

    public function formapagos()
    {
        return $this->hasMany(Concepto_Solicitudpago_Formapago::class, 'concepto_solicitudpago_id')
            ->orderBy('id');
    }
}
