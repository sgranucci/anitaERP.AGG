<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Recuento_Archivo extends Model
{
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
