<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Pagoproveedor_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pagoproveedor_archivo';

    protected $fillable = [
        'pagoproveedor_id', 'nombrearchivo',
    ];

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }
}
