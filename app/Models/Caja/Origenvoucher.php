<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Origenvoucher extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'origenvoucher';
}

