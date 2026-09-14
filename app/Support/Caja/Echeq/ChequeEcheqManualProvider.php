<?php

namespace App\Support\Caja\Echeq;

/**
 * Provider manual / placeholder: no llama API bancaria.
 * Sirve para registrar estado operativo hasta cablear el banco de la empresa.
 */
final class ChequeEcheqManualProvider implements ChequeEcheqProviderInterface
{
    public function codigo(): string
    {
        return 'manual';
    }

    public function consultarEstado(string $nroEcheq, array $contexto = []): array
    {
        $estado = trim((string) ($contexto['estado_actual'] ?? 'activo'));
        if ($estado === '') {
            $estado = 'activo';
        }

        return [
            'ok' => true,
            'estado' => $estado,
            'mensaje' => 'Provider manual: sin API bancaria. Estado operativo ERP.',
        ];
    }

    public function depositar(string $nroEcheq, array $contexto = []): array
    {
        return [
            'ok' => true,
            'estado' => 'depositado_banco',
            'mensaje' => 'Provider manual: marcá el depósito eCheq en el banco y sincronizá estado.',
        ];
    }
}
