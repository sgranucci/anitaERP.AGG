<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Contable\cuentacontable;

class Rubrocontable extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'rubrocontable';

    public function cuentascontables()
    {
        return $this->hasMany(cuentacontable::class);
    }
}

