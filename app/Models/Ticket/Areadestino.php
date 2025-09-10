<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Areadestino extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'areadestino';
}

