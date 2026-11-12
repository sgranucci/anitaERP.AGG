<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class InterbankingEnvioLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ORIGEN_PAGOPROVEEDOR = 'pagoproveedor';

    public const ORIGEN_CAJA_MOVIMIENTO = 'caja_movimiento';

    public const ORIGEN_SOLICITUD_RETIRO = 'solicitud_retiro';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDO = 'omitido_duplicado';

    protected $table = 'interbanking_envio_linea';

    protected $fillable = [
        'interbanking_envio_id',
        'origen_tipo',
        'origen_id',
        'origen_clave',
        'dedupe_key',
        'beneficiario',
        'credit_cbu',
        'amount',
        'concept',
        'referencia',
        'estado',
        'http_code',
        'api_response',
        'error_mensaje',
    ];

    protected function casts(): array
    {
        return [
            'origen_id' => 'integer',
            'amount' => 'decimal:2',
            'http_code' => 'integer',
            'api_response' => 'array',
        ];
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(InterbankingEnvio::class, 'interbanking_envio_id');
    }

    public static function dedupeKeyPagoproveedor(int $id): string
    {
        return self::ORIGEN_PAGOPROVEEDOR.':'.$id;
    }

    public static function dedupeKeyCajaMovimiento(int $id): string
    {
        return self::ORIGEN_CAJA_MOVIMIENTO.':'.$id;
    }

    public static function dedupeKeySolicitudRetiro(string $operacion, string $cbu, float $amount): string
    {
        return self::ORIGEN_SOLICITUD_RETIRO.':'.trim($operacion).'|'.$cbu.'|'.number_format($amount, 2, '.', '');
    }
}
