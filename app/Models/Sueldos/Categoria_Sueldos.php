<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Categoria_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'categoria_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'origen_bases',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];

    public function bases()
    {
        return $this->hasMany(Categoria_Base_Sueldos::class, 'categoria_id');
    }
}
