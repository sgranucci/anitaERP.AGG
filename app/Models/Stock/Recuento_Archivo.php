<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Recuento_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'recuento_archivo';

    protected $fillable = [
        'recuento_id',
        'nombrearchivo',
    ];

    public function recuento()
    {
        return $this->belongsTo(Recuento::class, 'recuento_id');
    }
}
