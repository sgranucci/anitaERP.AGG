<?php

namespace App\Services\Uif;

use App\ApiAnita;
use App\Repositories\Uif\AnitaUifArchivosSync;

/**
 * Exportación de documentación UIF Wilde/Kandiko según fuente Anita legacy:
 * - abm_clientes_uif.php → DNI en /scan/tesoreria/dni_uif/{numerodocumento}.pdf
 * - abm_clientes_uif.php listado → premio en /mod_fotos/fotos_clientes/pago_{inropremioid}.jpg
 * - Clientes_uif.php → adjuntos en uif/archivos/clientes|premios
 */
final class ExportarFotosActivosWildeService
{
    private const EXT_IMAGEN = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'pdf'];

    private ApiAnita $api;

    /** @var array<int, string> inropremioid => basename en mod_fotos HTTP */
    private array $indicePremiosHttp = [];

    public function __construct(?ApiAnita $api = null)
    {
        $this->api = $api ?? new ApiAnita;
    }

    /**
     * @param  array<int, array{dni: string, titular: string, planilla: string}>  $titulares
     * @return array{ok: int, parcial: int, sin_datos: int, detalle: array<int, array<string, mixed>>}
     */
    public function exportar(array $titulares, array $opciones): array
    {
        $salida = rtrim((string) ($opciones['salida'] ?? ''), '/');
        if ($salida === '') {
            throw new \InvalidArgumentException('Debe indicar carpeta de salida.');
        }

        $dryRun = (bool) ($opciones['dry_run'] ?? false);
        $servidorKandiko = (string) ($opciones['servidor_kandiko'] ?? '192.168.20.100:8080');
        $servidorBiyemas = (string) ($opciones['servidor_biyemas'] ?? '10.20.30.200:8080');
        $dniPdfMount = rtrim((string) ($opciones['dni_pdf_mount'] ?? '/scan/tesoreria/dni_uif'), '/');
        $adjuntosMount = rtrim((string) ($opciones['adjuntos_mount'] ?? '/scan/uif/archivos'), '/');
        $premioHttp = rtrim((string) ($opciones['premio_http'] ?? 'http://192.168.20.100:8080/mod_fotos/fotos_clientes'), '/');
        $premioMountFallback = rtrim((string) ($opciones['premio_mount_fallback'] ?? '/scan/tesoreria/fotos_clientes'), '/');

        if (! $dryRun && ! is_dir($salida) && ! @mkdir($salida, 0755, true) && ! is_dir($salida)) {
            throw new \RuntimeException('No se pudo crear la carpeta de salida: '.$salida);
        }

        $this->indicePremiosHttp = $this->construirIndicePremiosHttp($premioHttp);

        $resumen = ['ok' => 0, 'parcial' => 0, 'sin_datos' => 0, 'detalle' => []];

        foreach ($titulares as $titular) {
            $dni = $titular['dni'];
            $dirDni = $salida.'/'.$dni;
            $fila = [
                'dni' => $dni,
                'titular' => $titular['titular'],
                'planilla' => $titular['planilla'],
                'fuente' => null,
                'inroclienteid' => null,
                'inropremioid' => null,
                'dni_archivos' => [],
                'premio_archivo' => null,
                'premio_origen' => null,
                'notas' => [],
            ];

            $clienteUif = $this->buscarClienteUif($servidorKandiko, $dni);
            if ($clienteUif !== null) {
                $fila['fuente'] = 'kandiko_uif';
                $fila['inroclienteid'] = (int) $clienteUif['inroclienteid'];
            } else {
                $fallback = $this->buscarClienteUif($servidorBiyemas, $dni);
                if ($fallback !== null) {
                    $fila['fuente'] = 'biyemas_uif';
                    $fila['inroclienteid'] = (int) $fallback['inroclienteid'];
                    $fila['notas'][] = 'No está en UIF Kandiko; tomado de base_admin Biyemas.';
                } else {
                    $maestro = $this->buscarMaestroBiyemas($servidorBiyemas, $dni);
                    if ($maestro !== null) {
                        $fila['fuente'] = 'biyemas_maestro';
                        $fila['notas'][] = 'Sin registro UIF; encontrado en maestro ventas Biyemas (climae).';
                        $fila['notas'][] = 'codigo_anita='.($maestro['clim_cliente'] ?? '');
                    } else {
                        $fila['fuente'] = 'no_encontrado';
                        $fila['notas'][] = 'Sin registro en UIF Kandiko/Biyemas ni en climae Biyemas.';
                    }
                }
            }

            $fila['dni_archivos'] = $this->resolverArchivosDni(
                $dni,
                $fila['inroclienteid'],
                $dniPdfMount,
                $adjuntosMount
            );
            if ($fila['dni_archivos'] === []) {
                $fila['notas'][] = 'Sin documento DNI (dni_uif/{DNI}.pdf) ni adjuntos UIF en scan.';
            }

            if ($fila['inroclienteid'] !== null && $fila['inroclienteid'] > 0) {
                $servidorPremio = $fila['fuente'] === 'biyemas_uif' ? $servidorBiyemas : $servidorKandiko;
                $premio = $this->buscarPrimerPremioConFoto(
                    $servidorPremio,
                    (int) $fila['inroclienteid'],
                    $premioHttp,
                    $premioMountFallback
                );
                if ($premio !== null) {
                    $fila['inropremioid'] = $premio['inropremioid'];
                    $fila['premio_archivo'] = $premio['basename'];
                    $fila['premio_origen'] = $premio['origen'];
                    $fila['premio_path'] = $premio['path'] ?? null;
                    $fila['premio_url'] = $premio['url'] ?? null;
                } else {
                    $fila['notas'][] = 'Sin foto de premio (no tuvo premio o no existe pago_{inropremioid} en mod_fotos/tesorería).';
                }
            }

            if (! $dryRun) {
                if (! is_dir($dirDni) && ! @mkdir($dirDni, 0755, true) && ! is_dir($dirDni)) {
                    $fila['notas'][] = 'Error creando carpeta '.$dirDni;
                } else {
                    foreach ($fila['dni_archivos'] as $arch) {
                        $tipo = (string) ($arch['tipo'] ?? 'adjunto');
                        $dest = $dirDni.'/'.$tipo.'_'.$arch['basename'];
                        $this->copiarArchivo($arch, $dest, $premioHttp);
                    }

                    if ($fila['premio_archivo'] !== null && $fila['inropremioid'] !== null) {
                        $destPremio = $dirDni.'/premio_'.$fila['inropremioid'].'_'.basename($fila['premio_archivo']);
                        $this->copiarArchivo([
                            'origen' => $fila['premio_origen'] ?? 'mod_fotos',
                            'path' => $fila['premio_path'] ?? null,
                            'url' => $fila['premio_url'] ?? ($premioHttp.'/'.$fila['premio_archivo']),
                        ], $destPremio, $premioHttp);
                    }

                    @file_put_contents(
                        $dirDni.'/metadata.json',
                        json_encode($fila, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
                    );
                }
            }

            $tieneDni = $fila['dni_archivos'] !== [];
            $tienePremio = $fila['premio_archivo'] !== null;
            if ($tieneDni && $tienePremio) {
                $resumen['ok']++;
            } elseif ($tieneDni || $tienePremio || $fila['inroclienteid'] !== null) {
                $resumen['parcial']++;
            } else {
                $resumen['sin_datos']++;
            }

            $resumen['detalle'][] = $fila;
        }

        if (! $dryRun) {
            @file_put_contents(
                $salida.'/resumen.json',
                json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
            );
        }

        return $resumen;
    }

    /**
     * @return array{inroclienteid: string, cnombre: string}|null
     */
    private function buscarClienteUif(string $servidor, string $dni): ?array
    {
        foreach ([$dni, ltrim($dni, '0')] as $valor) {
            if ($valor === '') {
                continue;
            }
            $filas = $this->listarAnita($servidor, 'base_admin', 'clientes_uif',
                'inroclienteid,inrodocumento,cnombre',
                " WHERE inrodocumento = '".$this->sqlLit($valor)."' "
            );
            if ($filas !== []) {
                return $filas[0];
            }
        }

        return null;
    }

    /**
     * @return array{clim_cliente: string, clim_nombre: string}|null
     */
    private function buscarMaestroBiyemas(string $servidor, string $dni): ?array
    {
        $filas = $this->listarAnita($servidor, 'ventas', 'climae',
            'clim_cliente,clim_nombre,clim_cuit',
            " WHERE clim_cuit = '".$this->sqlLit($dni)."' "
        );
        if ($filas !== []) {
            return $filas[0];
        }

        $filas = $this->listarAnita($servidor, 'ventas', 'climae',
            'clim_cliente,clim_nombre,clim_cuit',
            " WHERE clim_cuit MATCHES '*".$this->sqlLit($dni)."*' "
        );

        return $filas[0] ?? null;
    }

    /**
     * Primer premio con archivo existente (orden inropremioid ASC, como abm_clientes_uif).
     *
     * @return array{inropremioid: int, basename: string, origen: string, path?: string, url?: string}|null
     */
    private function buscarPrimerPremioConFoto(
        string $servidor,
        int $inroclienteid,
        string $premioHttp,
        string $premioMountFallback
    ): ?array {
        $filas = $this->listarAnita($servidor, 'base_admin', 'premios_uif',
            'inropremioid,inroclienteid,cextfoto,fpremio',
            " WHERE inroclienteid = '".$inroclienteid."' ",
            'inropremioid'
        );

        foreach ($filas as $fila) {
            $id = (int) ($fila['inropremioid'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $hintExt = trim((string) ($fila['cextfoto'] ?? ''));
            $resuelto = $this->resolverArchivoPremioAnita($id, $hintExt, $premioHttp, $premioMountFallback);
            if ($resuelto !== null) {
                return array_merge(['inropremioid' => $id], $resuelto);
            }
        }

        return null;
    }

    /**
     * Orden Anita (abm_clientes_uif.php): mod_fotos/pago_{id}.jpg → cextfoto → fallback tesorería.
     *
     * @return array{basename: string, origen: string, path?: string, url?: string}|null
     */
    private function resolverArchivoPremioAnita(
        int $inropremioid,
        string $hintExt,
        string $premioHttp,
        string $premioMountFallback
    ): ?array {
        $stem = 'pago_'.$inropremioid;
        $exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if ($hintExt !== '' && $hintExt !== '0') {
            array_unshift($exts, ltrim(strtolower($hintExt), '.'));
        }
        $exts = array_values(array_unique($exts));

        foreach ($exts as $ext) {
            $basename = $stem.'.'.$ext;
            if ($this->urlExisteHttp($premioHttp.'/'.$basename)) {
                return [
                    'basename' => $basename,
                    'origen' => 'mod_fotos_http',
                    'url' => $premioHttp.'/'.$basename,
                ];
            }
        }

        if (isset($this->indicePremiosHttp[$inropremioid])) {
            $basename = $this->indicePremiosHttp[$inropremioid];

            return [
                'basename' => $basename,
                'origen' => 'mod_fotos_http',
                'url' => $premioHttp.'/'.$basename,
            ];
        }

        if ($premioMountFallback !== '' && is_dir($premioMountFallback)) {
            foreach ($exts as $ext) {
                $candidate = $premioMountFallback.'/'.$stem.'.'.$ext;
                if (is_file($candidate)) {
                    return [
                        'basename' => basename($candidate),
                        'origen' => 'scan_tesoreria_fallback',
                        'path' => $candidate,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{tipo: string, origen: string, basename: string, path?: string, url?: string}>
     */
    private function resolverArchivosDni(
        string $numerodocumento,
        ?int $inroclienteid,
        string $dniPdfMount,
        string $adjuntosMount
    ): array {
        $out = [];
        $vistos = [];

        $pdfPath = $dniPdfMount.'/'.$numerodocumento.'.pdf';
        if (is_file($pdfPath)) {
            $vistos['documento_'.$numerodocumento.'.pdf'] = true;
            $out[] = [
                'tipo' => 'documento',
                'origen' => 'dni_uif',
                'basename' => $numerodocumento.'.pdf',
                'path' => $pdfPath,
            ];
        }

        if ($inroclienteid === null || $inroclienteid <= 0) {
            return $out;
        }

        $subcarpetas = ['clientes_KSA', 'clientes_RSA', 'clientes'];
        $prefixes = [
            (string) $inroclienteid.'-',
            str_pad((string) $inroclienteid, 6, '0', STR_PAD_LEFT).'-',
        ];

        foreach ($subcarpetas as $sub) {
            $dir = rtrim($adjuntosMount, '/').'/'.$sub;
            if (! is_dir($dir)) {
                continue;
            }
            foreach ($prefixes as $prefix) {
                foreach (glob($dir.'/'.$prefix.'*') ?: [] as $path) {
                    if (! is_file($path)) {
                        continue;
                    }
                    $base = basename($path);
                    if (isset($vistos[$base])) {
                        continue;
                    }
                    $vistos[$base] = true;
                    $out[] = [
                        'tipo' => 'adjunto',
                        'origen' => 'scan_'.$sub,
                        'basename' => $base,
                        'path' => $path,
                    ];
                }
            }
        }

        if (is_dir($adjuntosMount)) {
            foreach (AnitaUifArchivosSync::listarBasenamesClientePorPrefijo($adjuntosMount, $inroclienteid) as $base) {
                if (isset($vistos[$base])) {
                    continue;
                }
                $origen = AnitaUifArchivosSync::primeraRutaExistente(
                    AnitaUifArchivosSync::rutasOrigenCandidatas($adjuntosMount, $inroclienteid, $base)
                );
                if ($origen !== null && is_file($origen)) {
                    $vistos[$base] = true;
                    $out[] = [
                        'tipo' => 'adjunto',
                        'origen' => 'uif_archivos',
                        'basename' => $base,
                        'path' => $origen,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{origen?: string, path?: string|null, url?: string|null}  $arch
     */
    private function copiarArchivo(array $arch, string $dest, string $httpBase): void
    {
        $path = $arch['path'] ?? null;
        if ($path !== null && is_file($path)) {
            @copy($path, $dest);

            return;
        }
        $url = $arch['url'] ?? null;
        if ($url !== null && $url !== '') {
            $this->descargarHttp($url, $dest);
        }
    }

    /**
     * @return array<int, string>
     */
    private function construirIndicePremiosHttp(string $baseUrl): array
    {
        $html = $this->fetchUrl($baseUrl.'/');
        if ($html === null) {
            return [];
        }

        $indice = [];
        if (preg_match_all('/href="(pago_\d+\.[a-zA-Z0-9]+)"/', $html, $matches)) {
            foreach ($matches[1] as $basename) {
                if (preg_match('/^pago_(\d+)\./', $basename, $m)) {
                    $indice[(int) $m[1]] = $basename;
                }
            }
        }

        return $indice;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function listarAnita(
        string $servidor,
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        ?string $orderBy = null
    ): array {
        $payload = [
            'acc' => 'list',
            'servidor' => $servidor,
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ];
        if ($orderBy !== null && $orderBy !== '') {
            $payload['orderBy'] = $orderBy;
        }

        $raw = $this->api->apiCall($payload);
        $filas = ApiAnita::decodificarListaFilas($raw);
        $out = [];
        foreach ($filas as $fila) {
            if ($fila instanceof \stdClass) {
                $out[] = (array) $fila;
            } elseif (is_array($fila)) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    private function descargarHttp(string $url, string $dest): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 30]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || $data === '') {
            return false;
        }

        return @file_put_contents($dest, $data) !== false;
    }

    private function fetchUrl(string $url): ?string
    {
        $ctx = stream_context_create(['http' => ['timeout' => 60]]);
        $data = @file_get_contents($url, false, $ctx);

        return is_string($data) && $data !== '' ? $data : null;
    }

    private function urlExisteHttp(string $url): bool
    {
        $headers = @get_headers($url);
        if (! is_array($headers) || $headers === []) {
            return false;
        }

        return str_contains($headers[0], '200');
    }

    private function sqlLit(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
