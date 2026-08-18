<?php

namespace App\Models\Caja;

use App\Models\Receptivo\Guia;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Voucher_Guia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'voucher_id', 'guia_id', 'tipocomision',
        'porcentajecomision', 'montocomision', 'ordenservicio_id',
    ];

    protected $table = 'voucher_guia';

    public function vouchers()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function guias()
    {
        return $this->belongsTo(Guia::class, 'guia_id');
    }
}
