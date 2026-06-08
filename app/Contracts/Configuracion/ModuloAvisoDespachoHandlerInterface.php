<?php

namespace App\Contracts\Configuracion;

use App\Models\Configuracion\ModuloAvisoTipo;

/**
 * Handler con envío personalizado (destinatarios dinámicos, mailables propios, tokens, etc.).
 */
interface ModuloAvisoDespachoHandlerInterface extends ModuloAvisoHandlerInterface
{
    /**
     * @param  array<string, mixed>  $opciones  Contexto extra (ej. vencido, mensaje, tipo_cambio).
     */
    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void;
}
