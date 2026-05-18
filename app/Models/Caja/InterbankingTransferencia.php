<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class InterbankingTransferencia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'interbanking_transferencia';

    protected $fillable = [
        'empresa_id',
        'debit_bank_number',
        'debit_account_number',
        'debit_account_type',
        'debit_currency',
        'request_date',
        'transfer_type_description',
        'transfer_type_code',
        'transfer_id',
        'network_number',
        'amount',
        'currency',
        'debit_account',
        'debit_account_json',
        'credit_account',
        'credit_account_json',
        'validation_code',
        'afip_json',
        'dedupe_hash',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'datetime',
            'amount' => 'decimal:2',
            'transfer_id' => 'integer',
            'network_number' => 'integer',
            'debit_account_json' => 'array',
            'credit_account_json' => 'array',
            'afip_json' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
