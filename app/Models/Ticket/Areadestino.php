<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Areadestino extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['nombre', 'suspendido'];
    protected $table = 'areadestino';

    protected $casts = [
        'suspendido' => 'boolean',
    ];

    public function scopeActivas($query)
    {
        return $query->where('suspendido', false);
    }
}

