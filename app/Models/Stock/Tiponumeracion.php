<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Tiponumeracion extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'tiponumeracion';

	public function lineas()
    {
        return $this->hasMany(Linea::class);
    }
}
