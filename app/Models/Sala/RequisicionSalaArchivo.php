<?php

namespace App\Models\Sala;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RequisicionSalaArchivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'requisicion_sala_archivo';

    protected $fillable = [
        'requisicion_sala_id', 'nombrearchivo',
    ];

    public function requisicion_salas()
    {
        return $this->belongsTo(RequisicionSala::class, 'requisicion_sala_id');
    }
}
