<?php

namespace App\Support\Stock;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Decodifica códigos de barras/QR desde una foto (mismo motor ZXing C++ que lee tickets).
 */
final class CodigoBarrasImagenSupport
{
    /**
     * @return array{ok: bool, codigos: list<string>, mensaje: string}
     */
    public static function decodificarDesdePath(string $path): array
    {
        $path = trim($path);
        if ($path === '' || ! is_file($path)) {
            return [
                'ok' => false,
                'codigos' => [],
                'mensaje' => 'No se encontró el archivo de la foto.',
            ];
        }

        $script = base_path('scripts/decodificar_codigo_barras.py');
        if (! is_file($script)) {
            return [
                'ok' => false,
                'codigos' => [],
                'mensaje' => 'Falta el script de lectura de códigos.',
            ];
        }

        $process = new Process(
            ['python3', $script, $path],
            base_path(),
            [
                'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                'LANG' => getenv('LANG') ?: 'C.UTF-8',
                'PYTHONPATH' => base_path('scripts/py_zxing'),
            ],
            null,
            20
        );

        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo ejecutar el lector de códigos: '.$e->getMessage(), 0, $e);
        }

        $stdout = trim($process->getOutput());
        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            $err = trim($process->getErrorOutput());

            return [
                'ok' => false,
                'codigos' => [],
                'mensaje' => $err !== '' ? $err : 'El lector de códigos no devolvió un resultado válido.',
            ];
        }

        $codigos = [];
        foreach ($decoded['codigos'] ?? [] as $codigo) {
            $codigo = preg_replace('/\s+/', '', (string) $codigo);
            if ($codigo !== '' && ! in_array($codigo, $codigos, true)) {
                $codigos[] = $codigo;
            }
        }

        if ($codigos === []) {
            return [
                'ok' => false,
                'codigos' => [],
                'mensaje' => (string) ($decoded['mensaje'] ?? 'No se leyó un código de barras en la foto.'),
            ];
        }

        return [
            'ok' => true,
            'codigos' => $codigos,
            'mensaje' => count($codigos) === 1
                ? 'Leído: '.$codigos[0]
                : 'Se leyeron '.count($codigos).' códigos.',
        ];
    }
}
