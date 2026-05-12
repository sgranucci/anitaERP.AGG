<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Ordencompra_Archivo extends Model
{
    protected $table = 'ordencompra_archivo';

    protected $fillable = ['ordencompra_id', 'nombrearchivo'];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }
}
