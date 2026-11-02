<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\ApiAnita;
use RuntimeException;

/**
 * Lectura del bridge Anita para las transferencias Crown.
 */
final class TransferenciaCrownAnitaConsulta
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * @return list<object>
     */
    public function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        ?string $ifxServer = null,
    ): array {
        $payload = [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
            'path_sistema' => rtrim((string) config('anita.bdd_path', '/usr2/casino'), '/') ?: '/usr2/casino',
        ];
        if ($ifxServer !== null && trim($ifxServer) !== '') {
            $payload['ifx_server'] = trim($ifxServer);
        }

        $raw = $this->api->apiCall($payload);
        $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);
        if ($parsed['error_lectura'] !== null && $parsed['error_lectura'] !== '') {
            throw new RuntimeException('Anita '.$sistema.'.'.$tabla.': '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }
}
