<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Reserva extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
						];
    protected $table = 'reserva';
	
}
