<?php

namespace App\Models\Solicitudpago;

use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Sector_Solicitudpago extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'sector_solicitudpago';

    protected $fillable = [
        'codigo',
        'nombre',
        'centrocosto_id',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'centrocosto_id' => 'integer',
    ];

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}
