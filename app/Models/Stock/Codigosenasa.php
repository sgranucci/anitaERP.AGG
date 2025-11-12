<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;
use App\Traits\Stock\CodigosenasaTrait;

class Codigosenasa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use CodigosenasaTrait;
    protected $fillable = ['nombre', 'registro', 'envasesenasa_id', 'llevafrio', 'prefijo', 'codigo'];
    protected $table = 'codigosenasa';

    public function envasesenasas()
    {
        return $this->belongsTo(Envasesenasa::class, 'envasesenasa_id');
    }

}
