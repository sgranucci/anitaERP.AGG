<?php

namespace App\Services\Configuracion\Whatsapp;

use App\Contracts\Configuracion\WhatsappDriverInterface;
use App\Models\Configuracion\EmpresaWhatsappCuenta;
use Illuminate\Support\Facades\Log;

/**
 * No envía: deja traza. Útil hasta cablear Meta Cloud API.
 */
class NullWhatsappDriver implements WhatsappDriverInterface
{
    public function enviarTexto(EmpresaWhatsappCuenta $cuenta, string $celularE164, string $texto): array
    {
        Log::info('WhatsApp NullDriver: no envía', [
            'empresa_id' => $cuenta->empresa_id,
            'celular' => $celularE164,
            'chars' => mb_strlen($texto),
        ]);

        return ['ok' => true, 'provider_id' => null, 'error' => null];
    }

    public function enviarPlantilla(
        EmpresaWhatsappCuenta $cuenta,
        string $celularE164,
        string $nombrePlantilla,
        string $idioma,
        array $parametrosCuerpo = []
    ): array {
        Log::info('WhatsApp NullDriver: plantilla no enviada', [
            'empresa_id' => $cuenta->empresa_id,
            'celular' => $celularE164,
            'plantilla' => $nombrePlantilla,
            'idioma' => $idioma,
            'params' => count($parametrosCuerpo),
        ]);

        return ['ok' => true, 'provider_id' => null, 'error' => null];
    }
}
