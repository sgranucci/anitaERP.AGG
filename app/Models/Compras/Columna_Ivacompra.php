<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Columna_Ivacompra extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'nombrecolumna', 'numerocolumna'];
    protected $table = 'columna_ivacompra';
}
