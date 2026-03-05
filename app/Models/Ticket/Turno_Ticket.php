<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Turno_Ticket extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'turno_ticket';
}

