<?php

namespace App\Models\Solicitudpago;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Solicitudpago_Formapago extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_solicitudpago_formapago';

    protected $fillable = [
        'concepto_solicitudpago_id',
        'formapagosol_id',
    ];

    protected $casts = [
        'concepto_solicitudpago_id' => 'integer',
        'formapagosol_id' => 'integer',
    ];

    public function conceptos()
    {
        return $this->belongsTo(Concepto_Solicitudpago::class, 'concepto_solicitudpago_id');
    }

    public function formapagosol()
    {
        return $this->belongsTo(Formapagosol::class, 'formapagosol_id');
    }
}
