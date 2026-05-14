<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class InterbankingSaldoDiario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'interbanking_saldo_diario';

    protected $fillable = [
        'empresa_id',
        'bank_number',
        'account_number',
        'currency',
        'fecha',
        'total_debits',
        'total_credits',
        'day_balance',
        'countable_balance',
        'initial_operating_balance',
        'current_operating_balance',
        'projected_balance_24hs',
        'projected_balance_48hs',
        'account_name',
        'account_type',
        'account_label',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total_debits' => 'decimal:2',
            'total_credits' => 'decimal:2',
            'day_balance' => 'decimal:2',
            'countable_balance' => 'decimal:2',
            'initial_operating_balance' => 'decimal:2',
            'current_operating_balance' => 'decimal:2',
            'projected_balance_24hs' => 'decimal:2',
            'projected_balance_48hs' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
