<?php

namespace App\Services\Arca;

use App\Models\Configuracion\Empresa;
use App\Support\Arca\ArcaCertificadoCsrSupport;
use Exception;

/**
 * Inventario de certificados ARCA del entorno y generación/instalación de CSR.
 *
 * No pisa cert.crt/privada.key vigentes al generar. Instalar exige --ejecutar
 * y un cert.crt emitido por ARCA en la carpeta de renovación.
 */
class ArcaCertificadoCsrService
{
    public const SERVICIO_WSFE = 'wsfe';

    public const SERVICIO_MTXCA = 'mtxca';

    public const SERVICIO_WSREMCARNE = 'wsremcarne';

    public const SERVICIO_PADRON = 'padron';

    public const SERVICIO_WSCDC = 'wscdc';

    public const SERVICIO_WSAPOC = 'wsapoc';

    /** @var list<string> */
    public const SERVICIOS = [
        self::SERVICIO_WSFE,
        self::SERVICIO_MTXCA,
        self::SERVICIO_WSREMCARNE,
        self::SERVICIO_PADRON,
        self::SERVICIO_WSCDC,
        self::SERVICIO_WSAPOC,
    ];

    /** Factura electrónica + remito (pedido típico de renovación). */
    public const SERVICIOS_FACTURA_Y_REMITO = [
        self::SERVICIO_WSFE,
        self::SERVICIO_MTXCA,
        self::SERVICIO_WSREMCARNE,
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function inventariar(): array
    {
        $filas = [];
        $filas = array_merge($filas, $this->inventarioPorEmpresa(
            self::SERVICIO_WSFE,
            'Factura electrónica WSFE',
            (string) config('arca_wsfe.base_storage', ''),
            (array) config('arca_wsfe.empresas', []),
            (string) config('arca_wsfe.wsaa_service_id', 'wsfe'),
            (string) (config('arca_wsfe.base_storage') ? rtrim((string) config('arca_wsfe.base_storage'), '/').'/ta' : '')
        ));
        $filas = array_merge($filas, $this->inventarioPorEmpresa(
            self::SERVICIO_MTXCA,
            'Factura electrónica MTXCA',
            (string) config('arca_mtxca.base_storage', ''),
            (array) config('arca_mtxca.empresas', []),
            (string) config('arca_mtxca.wsaa_service_id', 'wsmtxca'),
            (string) (config('arca_mtxca.base_storage') ? rtrim((string) config('arca_mtxca.base_storage'), '/').'/ta' : '')
        ));
        $filas = array_merge($filas, $this->inventarioPorEmpresa(
            self::SERVICIO_WSREMCARNE,
            'Remito electrónico (wsremcarne)',
            (string) config('arca_wsremcarne.base_storage', ''),
            (array) config('arca_wsremcarne.empresas', []),
            (string) config('arca_wsremcarne.wsaa_service_id', 'wsremcarne'),
            (string) config('arca_wsremcarne.ta_storage_dir', '')
        ));

        $filas[] = $this->inventarioSimple(
            self::SERVICIO_PADRON,
            'Padrón (constancia inscripción)',
            (string) config('arca.padron.cert_path', config('arca.cert_path', '')),
            (string) config('arca.padron.private_key_path', config('arca.private_key_path', '')),
            (string) config('arca.padron.private_key_passphrase', config('arca.private_key_passphrase', '')),
            (string) config('arca.padron.ws_sr_constancia_inscripcion.service_id', 'ws_sr_constancia_inscripcion'),
            (string) config('arca.padron.ta_storage_dir', config('arca.ta_storage_dir', ''))
        );
        $filas[] = $this->inventarioSimple(
            self::SERVICIO_WSCDC,
            'Constatación comprobantes WSCDC',
            (string) config('arca_wscdc.cert_path', ''),
            (string) config('arca_wscdc.private_key_path', ''),
            (string) config('arca_wscdc.private_key_passphrase', ''),
            (string) config('arca_wscdc.wsaa_service_id', 'wscdc'),
            (string) config('arca_wscdc.ta_storage_dir', '')
        );
        $filas[] = $this->inventarioSimple(
            self::SERVICIO_WSAPOC,
            'Facturas apócrifas WSAPOC',
            (string) config('arca_wsapoc.cert_path', ''),
            (string) config('arca_wsapoc.private_key_path', ''),
            (string) config('arca_wsapoc.private_key_passphrase', ''),
            (string) config('arca_wsapoc.wsaa_service_id', 'wsapoc'),
            (string) config('arca_wsapoc.ta_storage_dir', '')
        );

        return array_values(array_filter($filas, fn ($f) => is_array($f) && ($f['cert_path'] ?? '') !== ''));
    }

    /**
     * @param  list<array<string, mixed>>  $inventario
     * @param  list<string>|null  $servicios
     * @return list<array<string, mixed>>
     */
    public function filtrar(array $inventario, ?array $servicios, ?int $empresaId): array
    {
        return array_values(array_filter($inventario, function (array $f) use ($servicios, $empresaId) {
            if ($servicios !== null && $servicios !== []) {
                if (! in_array((string) $f['servicio'], $servicios, true)) {
                    return false;
                }
            }
            if ($empresaId !== null && $empresaId > 0) {
                if ((int) ($f['empresa_id'] ?? 0) !== $empresaId) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    public function generar(array $entrada, bool $reutilizarClave = false, ?string $aliasOverride = null, bool $force = false): array
    {
        if (empty($entrada['existe_cert'])) {
            throw new Exception('No hay certificado vigente en '.$entrada['cert_path']);
        }

        $leido = ArcaCertificadoCsrSupport::leerCertificado((string) $entrada['cert_path']);
        $dn = ArcaCertificadoCsrSupport::dnDesdeSubject($leido['subject'], $aliasOverride);
        $dir = $this->directorioRenovacion($entrada, $force);

        $generado = ArcaCertificadoCsrSupport::generar(
            $dir,
            $dn,
            $reutilizarClave,
            $reutilizarClave ? (string) $entrada['private_key_path'] : null,
            (string) ($entrada['private_key_passphrase'] ?? ''),
        );

        $instrucciones = ArcaCertificadoCsrSupport::instruccionesArca(
            $generado['alias'],
            $generado['csr_path']
        );
        @file_put_contents($dir.'/instrucciones.txt', $instrucciones);
        @file_put_contents(
            $dir.'/subject_origen.txt',
            ArcaCertificadoCsrSupport::formatearSubject($leido['subject'])."\n"
        );

        return array_merge($generado, [
            'id' => $entrada['id'],
            'servicio' => $entrada['servicio'],
            'etiqueta' => $entrada['etiqueta'],
            'cuit' => $leido['cuit'],
            'valid_to' => $leido['valid_to'],
        ]);
    }

    /**
     * Instala cert.crt + privada.key de la carpeta de renovación sobre producción.
     *
     * @param  array<string, mixed>  $entrada
     * @param  list<string>  $replicarIds  Otros webservices (opcional). El origen siempre se instala.
     * @return array{backup_dir: string, instalados: list<array{id: string, etiqueta: string, cert: string, key: string}>, ta_borrados: list<string>}
     */
    public function instalar(array $entrada, ?string $dirRenovacion, bool $force = false, array $replicarIds = []): array
    {
        $dir = $dirRenovacion !== null && $dirRenovacion !== ''
            ? rtrim($dirRenovacion, '/')
            : $this->ultimaRenovacion($entrada);

        $nuevoCert = $dir.'/cert.crt';
        $nuevaKey = $dir.'/privada.key';
        if (! is_readable($nuevoCert)) {
            throw new Exception(
                "Falta {$nuevoCert}. Descargue el certificado de ARCA y guárdelo como cert.crt en la carpeta de renovación."
            );
        }
        if (! is_readable($nuevaKey)) {
            throw new Exception("Falta {$nuevaKey} (clave generada junto al CSR).");
        }

        $pass = (string) ($entrada['private_key_passphrase'] ?? '');
        if (! ArcaCertificadoCsrSupport::certCoincideConClave($nuevoCert, $nuevaKey, $pass)) {
            throw new Exception('El cert.crt de ARCA no coincide con la privada.key de la renovación.');
        }

        $nuevo = ArcaCertificadoCsrSupport::leerCertificado($nuevoCert);
        $aliasEsperado = trim((string) ($entrada['alias'] ?? ''));
        if (! $force && $aliasEsperado !== '' && strcasecmp($nuevo['alias'], $aliasEsperado) !== 0) {
            throw new Exception(
                "El certificado descargado tiene alias «{$nuevo['alias']}», se esperaba «{$aliasEsperado}»."
            );
        }

        $destinos = $this->destinosInstalacion($entrada, $replicarIds);

        $backupDir = $dir.'/backup_produccion_'.date('YmdHis');
        if (! is_dir($backupDir) && ! @mkdir($backupDir, 0700, true) && ! is_dir($backupDir)) {
            throw new Exception("No se pudo crear backup en {$backupDir}");
        }

        $instalados = [];
        $taBorrados = [];
        foreach ($destinos as $dest) {
            $certDest = (string) $dest['cert'];
            $keyDest = (string) $dest['key'];
            $this->asegurarDirectorio(dirname($certDest));
            $prefijo = $this->idSeguro((string) $dest['id']);
            if (is_file($certDest)) {
                @copy($certDest, $backupDir.'/'.$prefijo.'_cert.crt');
            }
            if (is_file($keyDest)) {
                @copy($keyDest, $backupDir.'/'.$prefijo.'_privada.key');
            }
            if (is_file($certDest) && ! is_writable($certDest)) {
                @chmod($certDest, 0664);
            }
            if (is_file($keyDest) && ! is_writable($keyDest)) {
                @chmod($keyDest, 0660);
            }
            if (! @copy($nuevoCert, $certDest)) {
                throw new Exception("No se pudo copiar el certificado a {$certDest}");
            }
            if (! @copy($nuevaKey, $keyDest)) {
                throw new Exception("No se pudo copiar la clave a {$keyDest}");
            }
            @chmod($certDest, 0644);
            @chmod($keyDest, 0600);
            $instalados[] = [
                'id' => (string) $dest['id'],
                'etiqueta' => (string) $dest['etiqueta'],
                'cert' => $certDest,
                'key' => $keyDest,
            ];
            $taBorrados = array_merge($taBorrados, $this->borrarCacheTa($dest['entrada']));
        }

        return [
            'backup_dir' => $backupDir,
            'instalados' => $instalados,
            'ta_borrados' => $taBorrados,
        ];
    }

    public function parsearServicios(?string $csv): ?array
    {
        if ($csv === null || trim($csv) === '') {
            return null;
        }
        $raw = strtolower(trim($csv));
        if ($raw === 'all' || $raw === 'todos') {
            return self::SERVICIOS;
        }
        if ($raw === 'factura-remito' || $raw === 'factura_y_remito') {
            return self::SERVICIOS_FACTURA_Y_REMITO;
        }
        $parts = preg_split('/[,\s]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if ($p === 'factura' || $p === 'fe') {
                $out[] = self::SERVICIO_WSFE;
                $out[] = self::SERVICIO_MTXCA;
                continue;
            }
            if ($p === 'remito' || $p === 'remelec') {
                $out[] = self::SERVICIO_WSREMCARNE;
                continue;
            }
            if (! in_array($p, self::SERVICIOS, true)) {
                throw new Exception(
                    "Servicio desconocido «{$p}». Válidos: ".implode(', ', self::SERVICIOS).', factura, remito, factura-remito, all'
                );
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<int, array{carpeta_cert?: string, private_key_passphrase?: string}>  $empresas
     * @return list<array<string, mixed>>
     */
    private function inventarioPorEmpresa(
        string $servicio,
        string $etiqueta,
        string $baseStorage,
        array $empresas,
        string $wsaaServiceId,
        string $taDir,
    ): array {
        $base = rtrim($baseStorage, '/');
        $out = [];
        foreach ($empresas as $empresaId => $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            $carpeta = trim((string) ($cfg['carpeta_cert'] ?? ''));
            if ($carpeta === '' || $base === '') {
                continue;
            }
            $cdir = $base.'/certs/'.$carpeta;
            $certPath = $cdir.'/cert.crt';
            $keyPath = $cdir.'/privada.key';
            if ($servicio === self::SERVICIO_MTXCA && ! is_readable($certPath)) {
                $fallback = rtrim((string) config('arca_wsfe.base_storage'), '/').'/certs/'.$carpeta;
                if (is_readable($fallback.'/cert.crt')) {
                    $certPath = $fallback.'/cert.crt';
                    $keyPath = $fallback.'/privada.key';
                }
            }
            $out[] = $this->enriquecerFila([
                'id' => $servicio.':'.(int) $empresaId,
                'servicio' => $servicio,
                'etiqueta' => $etiqueta,
                'empresa_id' => (int) $empresaId,
                'carpeta' => $carpeta,
                'cert_path' => $certPath,
                'private_key_path' => $keyPath,
                'private_key_passphrase' => (string) ($cfg['private_key_passphrase'] ?? ''),
                'wsaa_service_id' => $wsaaServiceId,
                'ta_storage_dir' => $taDir !== '' ? $taDir : $base.'/ta',
                'cache_key' => $servicio === self::SERVICIO_WSREMCARNE
                    ? 'remcarne_emp'.$empresaId
                    : ($servicio === self::SERVICIO_MTXCA ? 'mtxca_emp'.$empresaId : 'fe_emp'.$empresaId),
            ]);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function inventarioSimple(
        string $servicio,
        string $etiqueta,
        string $certPath,
        string $keyPath,
        string $passphrase,
        string $wsaaServiceId,
        string $taDir,
    ): array {
        return $this->enriquecerFila([
            'id' => $servicio,
            'servicio' => $servicio,
            'etiqueta' => $etiqueta,
            'empresa_id' => null,
            'carpeta' => null,
            'cert_path' => $certPath,
            'private_key_path' => $keyPath,
            'private_key_passphrase' => $passphrase,
            'wsaa_service_id' => $wsaaServiceId,
            'ta_storage_dir' => $taDir,
            'cache_key' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function enriquecerFila(array $fila): array
    {
        $certPath = (string) $fila['cert_path'];
        $fila['existe_cert'] = is_readable($certPath);
        $fila['existe_key'] = is_readable((string) $fila['private_key_path']);
        $fila['empresa_nombre'] = null;
        if (! empty($fila['empresa_id'])) {
            $emp = Empresa::query()->find((int) $fila['empresa_id']);
            $fila['empresa_nombre'] = $emp?->nombre;
        }

        $fila['alias'] = null;
        $fila['cuit'] = null;
        $fila['organizacion'] = null;
        $fila['valid_from'] = null;
        $fila['valid_to'] = null;
        $fila['dias_restantes'] = null;
        $fila['fingerprint_sha256'] = null;
        $fila['subject'] = null;
        $fila['error'] = null;
        $fila['ruta_corta'] = $this->rutaCorta($certPath);

        if (! $fila['existe_cert']) {
            $fila['error'] = 'cert.crt no encontrado';

            return $fila;
        }

        try {
            $leido = ArcaCertificadoCsrSupport::leerCertificado($certPath);
            $fila['alias'] = $leido['alias'];
            $fila['cuit'] = $leido['cuit'];
            $fila['organizacion'] = $leido['organizacion'];
            $fila['valid_from'] = $leido['valid_from'];
            $fila['valid_to'] = $leido['valid_to'];
            $fila['dias_restantes'] = $leido['dias_restantes'];
            $fila['fingerprint_sha256'] = $leido['fingerprint_sha256'];
            $fila['subject'] = ArcaCertificadoCsrSupport::formatearSubject($leido['subject']);
        } catch (Exception $e) {
            $fila['error'] = $e->getMessage();
        }

        return $this->enriquecerRenovacion($fila);
    }

    /**
     * @return array<string, mixed>
     */
    public function buscarPorId(string $id): array
    {
        $id = trim($id);
        foreach ($this->inventariar() as $f) {
            if ((string) $f['id'] === $id) {
                return $f;
            }
        }

        throw new Exception("No hay un certificado configurado con id «{$id}».");
    }

    /**
     * Sube el .crt de ARCA, valida contra la clave de la renovación e instala.
     *
     * @param  array<string, mixed>  $entrada
     * @param  list<string>  $replicarIds
     * @return array<string, mixed>
     */
    public function instalarDesdeUpload(array $entrada, string $contenidoCrt, bool $force = false, array $replicarIds = []): array
    {
        $dir = $this->ultimaRenovacion($entrada);
        $pem = ArcaCertificadoCsrSupport::normalizarPemCertificado($contenidoCrt);
        $destinoCrt = $dir.'/cert.crt';
        if (@file_put_contents($destinoCrt, $pem) === false) {
            throw new Exception("No se pudo guardar el certificado en {$destinoCrt}.");
        }
        @chmod($destinoCrt, 0644);

        $validacion = $this->validarCrtRenovacion($entrada, $dir, $force);
        if (! $validacion['ok']) {
            @unlink($destinoCrt);
            throw new Exception(implode(' ', $validacion['errores']));
        }

        $instalado = $this->instalar($entrada, $dir, $force, $replicarIds);

        return array_merge($instalado, [
            'validacion' => $validacion,
            'dir' => $dir,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array{ok: bool, errores: list<string>, alias: ?string, cuit: ?string, valid_to: ?string}
     */
    public function validarCrtRenovacion(array $entrada, string $dir, bool $force = false): array
    {
        $certPath = rtrim($dir, '/').'/cert.crt';
        $keyPath = rtrim($dir, '/').'/privada.key';
        $errores = [];
        $alias = null;
        $cuit = null;
        $validTo = null;

        try {
            $nuevo = ArcaCertificadoCsrSupport::leerCertificado($certPath);
            $alias = $nuevo['alias'];
            $cuit = $nuevo['cuit'];
            $validTo = $nuevo['valid_to'];
        } catch (Exception $e) {
            return [
                'ok' => false,
                'errores' => [$e->getMessage()],
                'alias' => null,
                'cuit' => null,
                'valid_to' => null,
            ];
        }

        $pass = (string) ($entrada['private_key_passphrase'] ?? '');
        if (! is_readable($keyPath)) {
            $errores[] = 'No hay privada.key de la renovación. Genere el CSR primero.';
        } elseif (! ArcaCertificadoCsrSupport::certCoincideConClave($certPath, $keyPath, $pass)) {
            $errores[] = 'El certificado no coincide con la clave privada generada para este CSR.';
        }

        $aliasEsperado = trim((string) ($entrada['alias'] ?? ''));
        if (! $force && $aliasEsperado !== '' && strcasecmp((string) $alias, $aliasEsperado) !== 0) {
            $errores[] = "El alias del .crt es «{$alias}» y el vigente es «{$aliasEsperado}». Debe ser el mismo.";
        }

        $cuitEsperado = preg_replace('/\D+/', '', (string) ($entrada['cuit'] ?? '')) ?? '';
        $cuitNuevo = preg_replace('/\D+/', '', (string) $cuit) ?? '';
        if (! $force && $cuitEsperado !== '' && $cuitNuevo !== '' && $cuitEsperado !== $cuitNuevo) {
            $errores[] = "El CUIT del .crt es {$cuitNuevo} y el vigente es {$cuitEsperado}.";
        }

        if ($nuevo['valid_to_ts'] !== null && $nuevo['valid_to_ts'] <= time()) {
            $errores[] = 'El certificado subido ya está vencido.';
        }

        return [
            'ok' => $errores === [],
            'errores' => $errores,
            'alias' => $alias,
            'cuit' => $cuit,
            'valid_to' => $validTo,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function enriquecerRenovacion(array $fila): array
    {
        $fila['tiene_csr'] = false;
        $fila['tiene_crt_pendiente'] = false;
        $fila['renovacion_dir'] = null;
        $fila['csr_path'] = null;
        $fila['renovacion_stamp'] = null;

        try {
            $dir = $this->ultimaRenovacion($fila);
        } catch (Exception $e) {
            return $fila;
        }

        $fila['renovacion_dir'] = $dir;
        $fila['renovacion_stamp'] = basename($dir);
        $csr = $dir.'/pedido.csr';
        $crt = $dir.'/cert.crt';
        $fila['tiene_csr'] = is_readable($csr);
        $fila['csr_path'] = $fila['tiene_csr'] ? $csr : null;
        $fila['tiene_crt_pendiente'] = is_readable($crt);

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    private function directorioRenovacion(array $entrada, bool $force): string
    {
        $stamp = date('YmdHis');
        $localRoot = dirname((string) $entrada['cert_path']).'/renovacion';
        $fallbackRoot = storage_path('app/arca/renovacion/'.$this->idSeguro((string) $entrada['id']));

        $root = $this->directorioEscribible($localRoot) ? $localRoot : $fallbackRoot;
        $this->asegurarDirectorio($root);
        @chmod($root, 0770);

        $base = $root.'/'.$stamp;
        if (is_dir($base) && ! $force) {
            $base .= '_'.substr(bin2hex(random_bytes(2)), 0, 4);
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    private function ultimaRenovacion(array $entrada): string
    {
        $dirs = [];
        $certPath = (string) ($entrada['cert_path'] ?? '');
        $id = (string) ($entrada['id'] ?? '');
        $candidatos = [];
        if ($certPath !== '') {
            $candidatos[] = dirname($certPath).'/renovacion';
        }
        if ($id !== '') {
            $candidatos[] = storage_path('app/arca/renovacion/'.$this->idSeguro($id));
        }
        foreach ($candidatos as $root) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $dirs[] = $dir;
            }
        }
        rsort($dirs, SORT_STRING);
        foreach ($dirs as $dir) {
            if (is_readable($dir.'/privada.key')) {
                return $dir;
            }
        }

        throw new Exception('No hay un CSR generado para este certificado. Genere el pedido primero.');
    }

    private function directorioEscribible(string $dir): bool
    {
        if (is_dir($dir)) {
            return is_writable($dir);
        }
        $padre = dirname($dir);

        return is_dir($padre) && is_writable($padre);
    }

    private function idSeguro(string $id): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $id) ?? '';

        return $safe !== '' ? $safe : 'cert';
    }

    /**
     * Origen primero; réplicas solo las elegidas (nunca agrupa por fingerprint).
     *
     * @param  array<string, mixed>  $entrada
     * @param  list<string>  $replicarIds
     * @return list<array{id: string, etiqueta: string, cert: string, key: string, entrada: array<string, mixed>}>
     */
    private function destinosInstalacion(array $entrada, array $replicarIds): array
    {
        $destinos = [[
            'id' => (string) $entrada['id'],
            'etiqueta' => (string) ($entrada['etiqueta'] ?? $entrada['id']),
            'cert' => (string) $entrada['cert_path'],
            'key' => $entrada['private_key_path'],
            'entrada' => $entrada,
        ]];
        $vistos = [(string) $entrada['cert_path']];

        foreach ($replicarIds as $id) {
            $id = trim((string) $id);
            if ($id === '' || $id === (string) $entrada['id']) {
                continue;
            }
            $otro = $this->buscarPorId($id);
            $cert = (string) $otro['cert_path'];
            if (in_array($cert, $vistos, true)) {
                continue;
            }
            $vistos[] = $cert;
            $destinos[] = [
                'id' => (string) $otro['id'],
                'etiqueta' => (string) ($otro['etiqueta'] ?? $otro['id']),
                'cert' => $cert,
                'key' => $otro['private_key_path'],
                'entrada' => $otro,
            ];
        }

        return $destinos;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return list<string>
     */
    private function borrarCacheTa(array $entrada): array
    {
        $dir = rtrim((string) ($entrada['ta_storage_dir'] ?? ''), '/');
        if ($dir === '' || ! is_dir($dir)) {
            return [];
        }
        $borrados = [];
        foreach (glob($dir.'/ta_*.xml') ?: [] as $file) {
            if (@unlink($file)) {
                $borrados[] = $file;
            }
        }

        return $borrados;
    }

    private function asegurarDirectorio(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new Exception("No se pudo crear {$dir}");
        }
    }

    private function rutaCorta(string $path): string
    {
        $base = storage_path('app/arca/');
        if (str_starts_with($path, $base)) {
            return 'storage/app/arca/'.substr($path, strlen($base));
        }

        return $path;
    }
}
