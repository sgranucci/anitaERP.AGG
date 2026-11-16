<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Bridge de lectura Anita para despachos/COM vinculados a importación (Fase 5).
 * No escribe ctamov: respeta subdiario como fuente nativa.
 */
final class ImportacionAnitaBridgeSupport
{
    /**
     * @return array{soportado: bool, mensaje: string, campos_lectura: list<string>}
     */
    public static function diagnostico(): array
    {
        return [
            'soportado' => true,
            'mensaje' => 'La lectura de despachos/COM desde Anita se hace vía ApiAnita (subdiario/ctamov) sin duplicar asientos. Activar por carpeta cuando el cliente opere el despacho en Anita.',
            'campos_lectura' => [
                'subd_nro_operacion',
                'subd_cuenta',
                'subd_contrapartida',
                'subd_importe',
                'subd_tipo_mov',
                'subd_desc_mov',
            ],
        ];
    }
}
