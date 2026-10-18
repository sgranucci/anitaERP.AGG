<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor_Sala extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'proveedor_sala';

    protected $fillable = ['proveedor_id', 'sala_id'];

    public function proveedores(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function salas(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }
}
