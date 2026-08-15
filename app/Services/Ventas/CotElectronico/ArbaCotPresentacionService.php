<?php

namespace App\Services\Ventas\CotElectronico;

use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class ArbaCotPresentacionService
{
    /** Códigos ARBA de fallo de autenticación (usuario/clave). */
    private const CODIGOS_ERROR_AUTENTICACION = [2, 3, 7, 10, 11];

    /**
     * Códigos ARBA que indican que la autenticación pasó pero falta/ falla el archivo
     * (respuesta esperada al probar conexión sin adjuntar remitos).
     *
     * @var list<int>
     */
    private const CODIGOS_PRUEBA_CONEXION_OK = [12, 13, 14, 15, 16, 17, 18, 44, 45, 46, 47, 74, 75, 76];

    /**
     * Prueba conectividad y credenciales CIT sin enviar remitos.
     *
     * @return array{
     *   ok: bool,
     *   mensaje: string,
     *   url: string,
     *   ambiente: string,
     *   http_status: ?int,
     *   codigo_error: ?int,
     *   xml: ?string
     * }
     */
    public function probarConexion(): array
    {
        $config = $this->resolverConfiguracion();
        if ($config['error'] !== null) {
            return [
                'ok' => false,
                'mensaje' => $config['error'],
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => null,
                'codigo_error' => null,
                'xml' => null,
            ];
        }

        try {
            $response = Http::timeout(min(30, (int) config('arba_cot.timeout_segundos', 120)))
                ->asMultipart()
                ->post($config['url'], [
                    'user' => $config['usuario'],
                    'password' => $config['password'],
                ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo conectar con ARBA: '.$e->getMessage(),
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => null,
                'codigo_error' => null,
                'xml' => null,
            ];
        }

        $xmlBody = $response->body();
        $httpStatus = $response->status();

        if (trim($xmlBody) === '') {
            return [
                'ok' => false,
                'mensaje' => 'ARBA respondió vacío (HTTP '.$httpStatus.').',
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => $httpStatus,
                'codigo_error' => null,
                'xml' => $xmlBody,
            ];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody);
        if ($xml === false) {
            return [
                'ok' => false,
                'mensaje' => 'Respuesta no XML de ARBA (HTTP '.$httpStatus.').',
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => $httpStatus,
                'codigo_error' => null,
                'xml' => $xmlBody,
            ];
        }

        if ($xml->getName() === 'TBCOMPROBANTE') {
            return [
                'ok' => true,
                'mensaje' => 'Conexión OK. ARBA aceptó usuario/clave (respuesta inesperada de comprobante sin archivo).',
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => $httpStatus,
                'codigo_error' => null,
                'xml' => $xmlBody,
            ];
        }

        if ($xml->getName() !== 'TBError') {
            return [
                'ok' => false,
                'mensaje' => 'Respuesta ARBA no reconocida (HTTP '.$httpStatus.').',
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => $httpStatus,
                'codigo_error' => null,
                'xml' => $xmlBody,
            ];
        }

        $codigo = $this->extraerCodigoError($xml);
        $mensajeArba = trim((string) ($xml->mensajeError ?? $xml->tipoError ?? 'Error ARBA'));

        if ($codigo !== null && in_array($codigo, self::CODIGOS_ERROR_AUTENTICACION, true)) {
            return [
                'ok' => false,
                'conectividad_ok' => true,
                'autenticacion_ok' => false,
                'mensaje' => $this->mensajeAutenticacion($codigo, $mensajeArba),
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => $httpStatus,
                'codigo_error' => $codigo,
                'xml' => $xmlBody,
            ];
        }

        if ($codigo !== null && in_array($codigo, self::CODIGOS_PRUEBA_CONEXION_OK, true)) {
            return [
                'ok' => true,
                'conectividad_ok' => true,
                'autenticacion_ok' => true,
                'mensaje' => 'Conexión OK. Usuario y clave aceptados por ARBA (código '.$codigo.': '.$mensajeArba.').',
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => $httpStatus,
                'codigo_error' => $codigo,
                'xml' => $xmlBody,
            ];
        }

        if ($this->esErrorEsperadoSinArchivo($mensajeArba)) {
            return [
                'ok' => true,
                'conectividad_ok' => true,
                'autenticacion_ok' => true,
                'mensaje' => 'Conexión OK. Usuario y clave aceptados por ARBA ('.$mensajeArba.').',
                'url' => $config['url'],
                'ambiente' => $config['ambiente'],
                'http_status' => $httpStatus,
                'codigo_error' => $codigo,
                'xml' => $xmlBody,
            ];
        }

        return [
            'ok' => false,
            'mensaje' => ($codigo !== null ? 'Código '.$codigo.': ' : '').$mensajeArba,
            'url' => $config['url'],
            'ambiente' => $config['ambiente'],
            'http_status' => $httpStatus,
            'codigo_error' => $codigo,
            'xml' => $xmlBody,
        ];
    }

    /**
     * @return array{
     *   ok:bool,
     *   xml:?string,
     *   error_general:?string,
     *   cuit_empresa:?string,
     *   numero_comprobante:?string,
     *   nombre_archivo:?string,
     *   codigo_integridad:?string,
     *   remitos:list<array<string, mixed>>
     * }
     */
    public function presentarRemitos(string $rutaArchivo, string $nombreArchivo): array
    {
        $config = $this->resolverConfiguracion();
        if ($config['error'] !== null) {
            return [
                'ok' => false,
                'xml' => null,
                'error_general' => $config['error'],
                'cuit_empresa' => null,
                'numero_comprobante' => null,
                'nombre_archivo' => null,
                'codigo_integridad' => null,
                'remitos' => [],
            ];
        }

        if (! is_readable($rutaArchivo)) {
            return [
                'ok' => false,
                'xml' => null,
                'error_general' => 'No se pudo leer el archivo generado para ARBA.',
                'cuit_empresa' => null,
                'numero_comprobante' => null,
                'nombre_archivo' => null,
                'codigo_integridad' => null,
                'remitos' => [],
            ];
        }

        $enviado = $this->postArchivoMultipart(
            $config['url'],
            $config['usuario'],
            $config['password'],
            $rutaArchivo,
            $nombreArchivo,
        );

        if ($enviado['error'] !== null) {
            return [
                'ok' => false,
                'xml' => $enviado['body'],
                'error_general' => $enviado['error'],
                'cuit_empresa' => null,
                'numero_comprobante' => null,
                'nombre_archivo' => null,
                'codigo_integridad' => null,
                'remitos' => [],
            ];
        }

        return $this->parsearRespuestaXml((string) $enviado['body']);
    }

    /**
     * POST multipart con CURLFile. El Http client de Laravel manda Expect: 100-continue
     * y el servlet de ARBA a veces recibe el archivo vacío → "No hay registro 01= HEADER".
     *
     * @return array{body:?string,error:?string}
     */
    private function postArchivoMultipart(
        string $url,
        string $usuario,
        string $password,
        string $rutaArchivo,
        string $nombreArchivo,
    ): array {
        $timeout = (int) config('arba_cot.timeout_segundos', 120);
        $archivo = new \CURLFile($rutaArchivo, 'text/plain', $nombreArchivo);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'user' => $usuario,
                'password' => $password,
                'file' => $archivo,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POSTREDIR => 3,
            CURLOPT_HTTPHEADER => ['Expect:'],
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $errorCurl = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'body' => is_string($body) ? $body : null,
                'error' => 'No se pudo conectar con ARBA: '.$errorCurl,
            ];
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $xmlBody = is_string($body) ? $body : '';

            return [
                'body' => $xmlBody,
                'error' => 'HTTP '.$httpStatus.': '.$this->extraerMensajeError($xmlBody),
            ];
        }

        return [
            'body' => is_string($body) ? $body : '',
            'error' => null,
        ];
    }

    /**
     * @return array{
     *   url: string,
     *   usuario: string,
     *   password: string,
     *   ambiente: string,
     *   error: ?string
     * }
     */
    private function resolverConfiguracion(): array
    {
        $url = $this->resolverUrl();
        $usuario = (string) config('arba_cot.usuario', '');
        $password = (string) config('arba_cot.password', '');
        $ambiente = (string) config('arba_cot.ambiente', 'test');

        $error = null;
        if ($usuario === '' || $password === '') {
            $error = 'Configure ARBA_COT_USER y ARBA_COT_PASSWORD en .env (clave CIT de ARBA).';
        }

        return [
            'url' => $url,
            'usuario' => $usuario,
            'password' => $password,
            'ambiente' => $ambiente,
            'error' => $error,
        ];
    }

    private function resolverUrl(): string
    {
        $url = trim((string) config('arba_cot.url', ''));
        if ($url !== '') {
            return $url;
        }

        return config('arba_cot.ambiente') === 'prod'
            ? 'https://cot.arba.gov.ar/TransporteBienes/SeguridadCliente/presentarRemitos.do'
            : 'http://cot.test.arba.gov.ar/TransporteBienes/SeguridadCliente/presentarRemitos.do';
    }

    private function extraerCodigoError(SimpleXMLElement $xml): ?int
    {
        $raw = trim((string) ($xml->codigoError ?? ''));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/\d+/', $raw, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    private function mensajeAutenticacion(int $codigo, string $mensajeArba): string
    {
        return match ($codigo) {
            2 => 'Usuario o clave inválidos. Verifique ARBA_COT_USER y ARBA_COT_PASSWORD.',
            3 => 'Usuario no habilitado en ARBA.',
            7 => 'Usuario bloqueado en ARBA.',
            10 => 'Falta el parámetro user (ARBA_COT_USER vacío).',
            11 => 'Falta el parámetro password (ARBA_COT_PASSWORD vacío).',
            default => 'Error de autenticación ARBA ('.$codigo.'): '.$mensajeArba,
        };
    }

    private function esErrorEsperadoSinArchivo(string $mensajeArba): bool
    {
        $mensaje = strtoupper($mensajeArba);

        return str_contains($mensaje, 'FILE')
            || str_contains($mensaje, 'ARCHIVO')
            || str_contains($mensaje, 'FORMULARIO MULTIPART');
    }

    /**
     * @return array{
     *   ok:bool,
     *   xml:?string,
     *   error_general:?string,
     *   cuit_empresa:?string,
     *   numero_comprobante:?string,
     *   nombre_archivo:?string,
     *   codigo_integridad:?string,
     *   remitos:list<array<string, mixed>>
     * }
     */
    private function parsearRespuestaXml(string $xmlBody): array
    {
        $base = [
            'ok' => false,
            'xml' => $xmlBody,
            'error_general' => null,
            'cuit_empresa' => null,
            'numero_comprobante' => null,
            'nombre_archivo' => null,
            'codigo_integridad' => null,
            'remitos' => [],
        ];

        if (trim($xmlBody) === '') {
            $base['error_general'] = 'ARBA devolvió una respuesta vacía.';

            return $base;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody);
        if ($xml === false) {
            $base['error_general'] = 'No se pudo interpretar la respuesta XML de ARBA.';

            return $base;
        }

        if ($xml->getName() === 'TBError') {
            $base['error_general'] = trim((string) ($xml->mensajeError ?? $xml->tipoError ?? 'Error ARBA'));

            return $base;
        }

        if ($xml->getName() !== 'TBCOMPROBANTE') {
            $base['error_general'] = 'Respuesta ARBA no reconocida.';

            return $base;
        }

        $base['ok'] = true;
        $base['cuit_empresa'] = trim((string) ($xml->cuitEmpresa ?? ''));
        $base['numero_comprobante'] = trim((string) ($xml->numeroComprobante ?? ''));
        $base['nombre_archivo'] = trim((string) ($xml->nombreArchivo ?? ''));
        $base['codigo_integridad'] = trim((string) ($xml->codigoIntegridad ?? ''));
        $base['remitos'] = $this->parsearValidacionesRemitos($xml);

        return $base;
    }

    /** @return list<array<string, mixed>> */
    private function parsearValidacionesRemitos(SimpleXMLElement $xml): array
    {
        $remitos = [];
        $validaciones = $xml->validacionesRemitos ?? null;
        if ($validaciones === null) {
            return $remitos;
        }

        foreach ($validaciones->remito as $remitoXml) {
            $errores = [];
            if (isset($remitoXml->errores)) {
                foreach ($remitoXml->errores->error as $errorXml) {
                    $errores[] = trim((string) ($errorXml->codigo ?? '')).' - '.trim((string) ($errorXml->descripcion ?? ''));
                }
            }

            $remitos[] = [
                'numero_unico' => trim((string) ($remitoXml->numeroUnico ?? '')),
                'procesado' => strtoupper(trim((string) ($remitoXml->procesado ?? ''))),
                'cot' => trim((string) ($remitoXml->cot ?? '')),
                'errores' => $errores,
            ];
        }

        return $remitos;
    }

    private function extraerMensajeError(string $xmlBody): string
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody);
        if ($xml !== false && $xml->getName() === 'TBError') {
            return trim((string) ($xml->mensajeError ?? 'Error ARBA'));
        }

        return substr(trim(strip_tags($xmlBody)), 0, 250);
    }
}
