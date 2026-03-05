<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Columna_Ivacompra extends Model
{
    protected $fillable = ['nombre', 'nombrecolumna', 'numerocolumna'];
    protected $table = 'columna_ivacompra';
}
