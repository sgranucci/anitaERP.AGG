<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estadocheque_Banco extends Model
{
    protected $fillable = ['nombre', 'abreviatura', 'codigoexterno', 'banco_id'];
    protected $table = 'estadocheque_banco';

    public function bancos()
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }

}

