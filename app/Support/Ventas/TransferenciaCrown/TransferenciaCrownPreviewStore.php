<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda el preview ya leído para que confirmar no vuelva a Anita ni al archivo.
 */
final class TransferenciaCrownPreviewStore
{
    public function guardar(int $usuarioId, array $payload): string
    {
        $token = (string) Str::uuid();
        $payload['usuario_id'] = $usuarioId;
        $dir = storage_path('app/transferencia-crown');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo guardar el preview.');
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo guardar el preview.');
        }
        file_put_contents($dir.'/'.$token.'.json', $json);

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function leer(int $usuarioId, string $token): array
    {
        $token = trim($token);
        if (! preg_match('/^[0-9a-f\-]{36}$/i', $token)) {
            throw new RuntimeException('El preview venció. Consultá de nuevo.');
        }
        $path = storage_path('app/transferencia-crown/'.$token.'.json');
        if (! is_file($path)) {
            throw new RuntimeException('El preview venció. Consultá de nuevo.');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || (int) ($data['usuario_id'] ?? 0) !== $usuarioId) {
            throw new RuntimeException('El preview no corresponde a esta sesión.');
        }

        return $data;
    }

    public function olvidar(string $token): void
    {
        $token = trim($token);
        if (! preg_match('/^[0-9a-f\-]{36}$/i', $token)) {
            return;
        }
        $path = storage_path('app/transferencia-crown/'.$token.'.json');
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
