<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ProgramaPagoOp extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** Una fila por proveedor del programa, sin partir por mes. */
    public const CLAVE_PROVEEDOR = 'proveedor';

    protected $table = 'programa_pago_op';

    protected $fillable = [
        'programa_pago_linea_id',
        'clave',
        'pagoproveedor_id',
    ];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(ProgramaPagoLinea::class, 'programa_pago_linea_id');
    }

    public function pagoproveedor(): BelongsTo
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }
}
