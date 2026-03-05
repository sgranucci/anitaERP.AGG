<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tipocuentacaja extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'tipocuentacaja';
}

