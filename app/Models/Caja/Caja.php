<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Caja extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'caja';
}

