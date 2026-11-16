<?php

namespace App\Contracts\Configuracion;

use App\Models\Configuracion\EmpresaWhatsappCuenta;

interface WhatsappDriverInterface
{
    /**
     * @return array{ok: bool, provider_id?: string|null, error?: string|null}
     */
    public function enviarTexto(EmpresaWhatsappCuenta $cuenta, string $celularE164, string $texto): array;

    /**
     * Plantilla aprobada en Meta (fuera de ventana 24h).
     *
     * @param  list<string>  $parametrosCuerpo
     * @return array{ok: bool, provider_id?: string|null, error?: string|null}
     */
    public function enviarPlantilla(
        EmpresaWhatsappCuenta $cuenta,
        string $celularE164,
        string $nombrePlantilla,
        string $idioma,
        array $parametrosCuerpo = []
    ): array;
}
