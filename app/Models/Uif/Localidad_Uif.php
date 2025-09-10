<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;

class Localidad_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigopostal', 'provincia_uif_id', 'codigo'];
    protected $table = 'localidad_uif';

    public function provincias()
    {
        return $this->belongsTo(Provincia_Uif::class, 'provincia_uif_id');
    }

}
