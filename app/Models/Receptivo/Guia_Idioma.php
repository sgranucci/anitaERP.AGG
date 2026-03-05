<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Guia_Idioma extends Model
{
    protected $fillable = [
                            'guia_id', 'idioma_id'
                        ];
    protected $table = 'guia_idioma';

    public function idiomas()
    {
        return $this->belongsTo(Idioma::class, 'idioma_id');
    }
}

