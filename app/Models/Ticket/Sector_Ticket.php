<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Sector_Ticket extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'sector_ticket';
}

