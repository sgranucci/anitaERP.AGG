<?php

declare(strict_types=1);

namespace App\Models\Configuracion;

use App\Support\Configuracion\Sircip\SircipCampo7;
use Illuminate\Database\Eloquent\Model;

/**
 * Padrón único SIRCIP: una fila por período y CUIT.
 */
class Padron_Sircip extends Model
{
    protected $table = 'padron_sircip';

    protected $fillable = [
        'periodo', 'cuit', 'razon_social', 'jurisdiccion_sede', 'crc', 'alicuota_letra', 'campo7',
    ];

    /**
     * Código de estado del contribuyente en una jurisdicción (1…5), o null.
     */
    public function estadoEnJurisdiccion(int $jurisdiccion): ?int
    {
        return SircipCampo7::estado((string) $this->campo7, $jurisdiccion);
    }
}
