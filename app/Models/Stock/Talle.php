<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Talle extends Model
{
    protected $table = "talle";
    protected $fillable = ['nombre'];

	public function modulos()
	{
		return $this->belongsToMany(Modulo::class);
	}
}
