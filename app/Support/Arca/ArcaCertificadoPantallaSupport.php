<?php

namespace App\Support\Arca;

use App\Services\Arca\ArcaCertificadoCsrService;
use Exception;
use Illuminate\Support\Facades\File;

/**
 * Webservices visibles en la pantalla de certificados ARCA.
 *
 * Destildar uno lo saca del listado y del mail de vencimiento
 * (arca:avisar-vencimiento-certificados). No cambia el webservice
 * del punto de venta ni el CSR / la instalación de certificados.
 */
final class ArcaCertificadoPantallaSupport
{
    private static function statePath(): string
    {
        return storage_path('app/arca/certificados_pantalla.json');
    }

    /**
     * @return list<string>
     */
    public static function serviciosVisibles(): array
    {
        $guardados = self::leerGuardados();
        if ($guardados === null) {
            return ArcaCertificadoCsrService::SERVICIOS;
        }

        return self::normalizar($guardados);
    }

    /**
     * @param  list<string>  $servicios
     */
    public static function guardar(array $servicios): void
    {
        $normalizados = self::normalizar($servicios);
        if ($normalizados === []) {
            throw new Exception('Deje al menos un webservice visible en la pantalla.');
        }

        $path = self::statePath();
        File::ensureDirectoryExists(dirname($path));
        $ok = @file_put_contents(
            $path,
            json_encode(['servicios' => $normalizados], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
            LOCK_EX
        );
        if ($ok === false) {
            throw new Exception('No se pudo guardar la configuración de webservices de esta pantalla.');
        }
        @chmod($path, 0664);
    }

    /**
     * @return list<string>|null null = todavía no hay configuración; se muestran todos
     */
    private static function leerGuardados(): ?array
    {
        $path = self::statePath();
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $servicios = $decoded['servicios'] ?? null;

        return is_array($servicios) ? array_map('strval', $servicios) : null;
    }

    /**
     * @param  list<string>  $servicios
     * @return list<string>
     */
    private static function normalizar(array $servicios): array
    {
        $elegidos = [];
        foreach ($servicios as $servicio) {
            $servicio = trim((string) $servicio);
            if ($servicio !== '' && in_array($servicio, ArcaCertificadoCsrService::SERVICIOS, true)) {
                $elegidos[$servicio] = true;
            }
        }

        $out = [];
        foreach (ArcaCertificadoCsrService::SERVICIOS as $servicio) {
            if (isset($elegidos[$servicio])) {
                $out[] = $servicio;
            }
        }

        return $out;
    }
}
