<?php

namespace App\Models\Caja;

use App\Traits\Caja\TalonariovoucherTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Talonariovoucher extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use TalonariovoucherTrait;

    protected $fillable = ['nombre', 'serie', 'origenvoucher_id', 'desdenumero', 'hastanumero',
        'fechainicio', 'fechacierre', 'estado'];

    protected $table = 'talonariovoucher';

    public function origenesvoucher()
    {
        return $this->belongsTo(Origenvoucher::class, 'origenvoucher_id', 'id');
    }
}
