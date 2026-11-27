<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Marca de conciliado de un movimiento (ERP o banco), con la fecha en que se concilió.
 */
class ConciliacionBancariaMarca extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const LADO_ERP = 'C';

    public const LADO_BANCO = 'B';

    protected $table = 'conciliacion_bancaria_marca';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'lado',
        'hash',
        'fecha_conciliacion',
        'origen',
        'tipo',
        'numero',
        'fecha_mov',
        'importe',
        'detalle',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_conciliacion' => 'date',
            'fecha_mov' => 'date',
            'importe' => 'decimal:2',
        ];
    }
}
