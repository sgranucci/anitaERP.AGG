<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class InterbankingMovimiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'interbanking_movimiento';

    protected $fillable = [
        'empresa_id',
        'bank_number',
        'account_number',
        'account_type',
        'currency',
        'movement_type',
        'process_date',
        'debit_credit_type',
        'amount',
        'operation_code_ib',
        'operation_code_bank',
        'code_description_ib',
        'code_description_bank',
        'customer_cuit',
        'depositor_code',
        'depositor_description',
        'voucher_number',
        'account_cbu',
        'grouping_code_ib',
        'branch_office_activity',
        'dedupe_hash',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'process_date' => 'datetime',
            'amount' => 'decimal:2',
            'voucher_number' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
