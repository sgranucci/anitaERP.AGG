<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class InterbankingEnvio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const FUENTE_PROVEEDORES = 'proveedores';

    public const FUENTE_SOLICITUD_RETIRO = 'solicitud_retiro';

    protected $table = 'interbanking_envio';

    protected $fillable = [
        'empresa_id',
        'cuentacaja_id',
        'usuario_id',
        'fuente',
        'debit_account_number',
        'debit_bank_number',
        'debit_account_type',
        'debit_currency',
        'cantidad_total',
        'cantidad_ok',
        'cantidad_error',
        'total_importe',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_total' => 'integer',
            'cantidad_ok' => 'integer',
            'cantidad_error' => 'integer',
            'total_importe' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacaja(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(InterbankingEnvioLinea::class, 'interbanking_envio_id');
    }
}
