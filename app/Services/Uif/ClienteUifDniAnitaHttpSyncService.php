<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Uif;
use App\Support\Uif\ClienteUifArchivoStorage;

/**
 * Copia {DNI}.pdf desde el Anita viejo (http://{host}/dni_uif) a /scan/tesoreria/dni_uif.
 * El sync de clientes no traía esos archivos: solo indexaba lo que ya estaba en /scan.
 */
final class ClienteUifDniAnitaHttpSyncService
{
    /**
     * @return array<int, string> basenames (ej. 31503720.pdf)
     */
    public static function parsearListadoHtml(string $html): array
    {
        if ($html === '' || ! preg_match_all('/href="([0-9]+\.(?:pdf|jpe?g|png|gif|webp))"/i', $html, $m)) {
            return [];
        }

        $out = [];
        foreach ($m[1] as $name) {
            $out[strtolower((string) $name)] = (string) $name;
        }

        return array_values($out);
    }

    public static function dniHttpBase(string $origen): string
    {
        $cfg = ClienteUifArchivoStorage::configOrigen($origen);
        $explicit = trim((string) ($cfg['dni_http'] ?? ''));
        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }
        $servidor = trim((string) ($cfg['servidor'] ?? ''));
        $servidor = preg_replace('#^https?://#i', '', $servidor) ?? '';

        return $servidor !== '' ? 'http://'.$servidor.'/dni_uif' : '';
    }

    /**
     * @return array{remotos:int, copiados:int, ya_estaban:int, fallidos:int, asociados:int}
     */
    public function sincronizar(string $origen, bool $dryRun = false, ?callable $log = null): array
    {
        $stats = ['remotos' => 0, 'copiados' => 0, 'ya_estaban' => 0, 'fallidos' => 0, 'asociados' => 0];
        $base = self::dniHttpBase($origen);
        $mount = ClienteUifFotoDocumento::anitaDniMount();
        if ($base === '' || $mount === '' || ! is_dir($mount)) {
            throw new \RuntimeException('Sin dni_http o dni_mount para origen '.$origen);
        }
        if (! $dryRun && ! is_writable($mount)) {
            throw new \RuntimeException('No se puede escribir en '.$mount);
        }

        $html = $this->httpGet($base.'/');
        $remotos = self::parsearListadoHtml($html);
        $stats['remotos'] = count($remotos);
        if ($log) {
            $log(sprintf('%s: %d DNI en %s', $origen, $stats['remotos'], $base));
        }

        foreach ($remotos as $i => $basename) {
            $basename = basename($basename);
            $dest = $mount.DIRECTORY_SEPARATOR.$basename;
            if (is_file($dest) && filesize($dest) > 1000) {
                $stats['ya_estaban']++;
                continue;
            }
            if ($dryRun) {
                $stats['copiados']++;
                continue;
            }
            $ok = $this->bajarSiEsDocumento($base.'/'.$basename, $dest);
            if ($ok) {
                $stats['copiados']++;
            } else {
                $stats['fallidos']++;
            }
            if ($log && (($i + 1) % 100 === 0 || $i + 1 === $stats['remotos'])) {
                $log(sprintf('[%s] %d/%d copiados=%d ya=%d fail=%d', $origen, $i + 1, $stats['remotos'], $stats['copiados'], $stats['ya_estaban'], $stats['fallidos']));
            }
        }

        $stats['asociados'] = $this->asociarClientes($origen);

        return $stats;
    }

    public function asociarClientes(string $origen): int
    {
        $mount = ClienteUifFotoDocumento::anitaDniMount();
        $n = 0;
        Cliente_Uif::query()
            ->where('anita_origen', $origen)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($mount, &$n) {
                foreach ($rows as $cliente) {
                    $stem = ClienteUifFotoDocumento::sanitizeNumeroDocumento((string) $cliente->numerodocumento);
                    if ($stem === '') {
                        continue;
                    }
                    $pdf = $mount.DIRECTORY_SEPARATOR.$stem.'.pdf';
                    if (! is_file($pdf) || filesize($pdf) <= 1000) {
                        continue;
                    }
                    $base = $stem.'.pdf';
                    if ((string) $cliente->fotodocumento === $base) {
                        continue;
                    }
                    $cliente->update(['fotodocumento' => $base]);
                    $n++;
                }
            });

        return $n;
    }

    private function bajarSiEsDocumento(string $url, string $dest): bool
    {
        $tmp = $dest.'.part.'.uniqid('', true);
        $body = $this->httpGet($url);
        if ($body === '' || strlen($body) < 1000) {
            return false;
        }
        $okDoc = str_starts_with($body, '%PDF')
            || str_starts_with($body, "\xFF\xD8\xFF")
            || str_starts_with($body, "\x89PNG");
        if (! $okDoc) {
            return false;
        }
        if (@file_put_contents($tmp, $body) === false) {
            return false;
        }
        if (! @rename($tmp, $dest)) {
            @unlink($tmp);

            return false;
        }
        @chmod($dest, 0664);

        return is_file($dest);
    }

    private function httpGet(string $url): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            return '';
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Accept: */*'],
        ]);
        $body = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($code !== 200 || ! is_string($body)) {
            return '';
        }

        return $body;
    }
}
