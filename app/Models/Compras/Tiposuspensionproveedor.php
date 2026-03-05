<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tiposuspensionproveedor extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'tiposuspensionproveedor';
}

