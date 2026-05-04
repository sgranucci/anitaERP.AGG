<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Requisicion_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['requisicion_id', 'nombrearchivo'];
    protected $table = 'requisicion_archivo';

    public function requisiciones()
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }
}
