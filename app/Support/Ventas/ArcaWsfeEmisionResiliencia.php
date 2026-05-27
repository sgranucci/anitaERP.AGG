<?php

namespace App\Support\Ventas;

/**
 * Resiliencia de emisión ARCA (CAE en línea vs CAEA) para WSFEv1 y WSMTXCA.
 *
 * Clasifica los errores que se producen al solicitar el CAE en tres categorías:
 *
 * - "transporte": comunicación caída, timeout, DNS, gateway 5xx, WSDL no
 *   accesible, respuesta vacía, latencia excesiva. ARCA pudo no haber
 *   recibido / procesado el comprobante o la respuesta no llegó. ↘ Reintento
 *   con PV CAEA es válido (cuando reintentar_caea_si_falla_comunicacion=true)
 *   porque seguimos dándole comprobante al cliente sin depender de la red.
 *
 * - "datos": ARCA respondió con un error claro de negocio (códigos 10000+,
 *   "Falta dato obligatorio", "Comprobante no autorizado", "Resultado: R",
 *   observaciones críticas). ↘ NO reintentar; abortar y mostrar el detalle.
 *   Reintentar con CAEA solo oculta el problema y produce comprobante con
 *   datos incorrectos.
 *
 * - "sistema": bug interno o falla previa al envío (SOAP encoding error,
 *   SQLSTATE, certificados inválidos, TA mal firmado, errores PHP, archivos
 *   inexistentes). ↘ NO reintentar; abortar para que un técnico revise.
 *
 * - "sin_clasificar": mensaje vacío o sin patrón conocido. Conservador: NO
 *   reintentar; abortar mostrando el mensaje original.
 */
final class ArcaWsfeEmisionResiliencia
{
    public const CLASE_TRANSPORTE = 'transporte';
    public const CLASE_DATOS = 'datos';
    public const CLASE_SISTEMA = 'sistema';
    public const CLASE_SIN_CLASIFICAR = 'sin_clasificar';

    public static function esWsMtxca(?string $webservice): bool
    {
        return ($webservice ?? '') === 'wsmtxca';
    }

    private static function configKey(?string $webservice): string
    {
        return self::esWsMtxca($webservice) ? 'arca_mtxca' : 'arca_wsfe';
    }

    /**
     * true: todas las emisiones usan el PV CAEA configurado y no llaman al WS ARCA en línea.
     */
    public static function forzarModoCaea(?string $webservice = null): bool
    {
        return filter_var(config(self::configKey($webservice).'.emision.forzar_modo_caea'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * true: si falla la comunicación con ARCA en una transacción CAE, reintenta una vez con PV CAEA.
     */
    public static function reintentarCaeaSiFallaComunicacion(?string $webservice = null): bool
    {
        return filter_var(
            config(self::configKey($webservice).'.emision.reintentar_caea_si_falla_comunicacion'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Patrones de errores de bug interno / sistema. Tienen MÁXIMA prioridad:
     * si el mensaje matchea cualquiera, se aborta sin reintentar.
     *
     * @return list<string>
     */
    private static function patronesSistema(): array
    {
        return [
            // PHP SOAP encoder (estructura mal armada en código)
            'soap-error: encoding',
            "object has no '",
            "has no '",
            // Errores PHP / Laravel
            'sqlstate',
            'integrity constraint',
            'foreign key constraint',
            'class not found',
            'class "',
            'undefined method',
            'undefined function',
            'undefined property',
            'undefined index',
            'undefined offset',
            'undefined variable',
            'undefined array key',
            'cannot use object of type',
            'maximum execution time',
            'allowed memory size',
            'argument #',
            'must be of type',
            'must implement interface',
            'call to undefined',
            'cannot redeclare',
            'syntax error, unexpected',
            // WSAA / certificados (no es transporte sino configuración del sistema)
            'wsaa-error',
            'no se pudo cargar',
            'no existe el archivo',
            'no such file or directory',
            'permission denied',
            'pkcs12',
            'openssl_',
            'private key',
            'invalid private key',
            'certificate has expired',
            'certificate has not yet',
            'verify error:num',
            'unable to load certificate',
            'unable to load private key',
            // Bridge Anita (es un sistema externo nuestro, no ARCA)
            'bridge http anita',
            'bridge anita',
            'error en grabación anita',
            // Bug típico de "Cannot use object of type stdClass as array"
            'cannot use object of type stdclass',
        ];
    }

    /**
     * Patrones de errores de negocio / datos devueltos por ARCA. Abortar sin reintentar.
     *
     * @return list<string>
     */
    private static function patronesDatosArca(): array
    {
        return [
            'falta dato obligatorio',
            'no pudo asignar cae',
            'comprobante no autorizado',
            'numerador inval',
            'numerador inv',
            'observad',
            'rechazad',
            'el cuit informado',
            'el cuit no se encuentra autorizado',
            'el cuit del emisor',
            'doctipo',
            'no se encuentra en el padr',
            'condicioniva',
            'la condicion iva',
            'condicion frente al iva',
            'importe total inv',
            'importe neto',
            'importe iva',
            'el comprobante ya fue informado',
            'el comprobante esta vinculado',
            'rg 4540',
            'rg 4291',
            'wsfe — comprobante no autorizado',
            'wsfe — fecaesolicitar:',
            'cae denegado',
            'resultado: r',
        ];
    }

    /**
     * Patrones de errores de transporte / red / latencia. Apropiados para reintento con CAEA.
     *
     * @return list<string>
     */
    private static function patronesTransporte(): array
    {
        return [
            // Timeouts
            'timeout',
            'timed out',
            'operation timed out',
            'connection timed out',
            // Conexión rota
            'could not connect',
            'connection refused',
            'connection reset',
            'connection closed',
            'failed to connect',
            'broken pipe',
            'connection aborted',
            // DNS / red
            'could not resolve host',
            'name or service not known',
            'no route to host',
            'network is unreachable',
            'host unreachable',
            'name resolution',
            // HTTP gateway
            'bad gateway',
            'gateway timeout',
            'service unavailable',
            'http error 502',
            'http error 503',
            'http error 504',
            ' 502 ',
            ' 503 ',
            ' 504 ',
            // cURL transporte
            'curl error',
            'cur error',
            // SOAP transporte (WSDL / parseo / fault sin respuesta)
            'parsing wsdl',
            "couldn't load from",
            'failed to load external entity',
            'looks like we got no xml document',
            'http request failed',
            'error fetching http headers',
            // TLS / SSL (handshake / canal)
            'ssl handshake',
            'tls handshake',
            'sslv3 alert',
            'ssl routines',
            'ssl_error',
            // Respuesta vacía o ambigua
            'sin resultado',
            'empty response',
            'no response',
            'respuesta vac',
            'respuesta vacía del bridge',
            'no hubo respuesta',
        ];
    }

    /**
     * Clasifica un mensaje de error en una de las constantes CLASE_*.
     * Prioridad: sistema > datos > transporte > sin_clasificar.
     */
    public static function clasificarError(?string $mensaje): string
    {
        if ($mensaje === null || trim($mensaje) === '') {
            return self::CLASE_SIN_CLASIFICAR;
        }

        $m = strtolower($mensaje);

        foreach (self::patronesSistema() as $needle) {
            if (str_contains($m, $needle)) {
                return self::CLASE_SISTEMA;
            }
        }

        foreach (self::patronesDatosArca() as $needle) {
            if (str_contains($m, $needle)) {
                return self::CLASE_DATOS;
            }
        }

        // Códigos de error explícitos de ARCA: 600/700 (WSAA) son sistema; 10xxx (WSFE) son datos.
        if (preg_match('/(?<![0-9])1[0-9]{4}(?![0-9])/', $m)) {
            return self::CLASE_DATOS;
        }
        if (preg_match('/(?<![0-9])(?:6\d{2}|7\d{2})(?![0-9])/', $m)) {
            // 600 ValidacionDeToken, 601 Cuit no autorizado, etc. ARCA dio respuesta clara → datos.
            return self::CLASE_DATOS;
        }

        foreach (self::patronesTransporte() as $needle) {
            if (str_contains($m, $needle)) {
                return self::CLASE_TRANSPORTE;
            }
        }

        return self::CLASE_SIN_CLASIFICAR;
    }

    /**
     * @deprecated Mantener para compat; preferir clasificarError() o esErrorTransporte().
     */
    public static function debeReintentarPorMensaje(?string $mensaje, ?string $webservice = null): bool
    {
        return self::esErrorTransporte($mensaje);
    }

    public static function esErrorTransporte(?string $mensaje): bool
    {
        return self::clasificarError($mensaje) === self::CLASE_TRANSPORTE;
    }

    public static function esErrorDatos(?string $mensaje): bool
    {
        return self::clasificarError($mensaje) === self::CLASE_DATOS;
    }

    public static function esErrorSistema(?string $mensaje): bool
    {
        return self::clasificarError($mensaje) === self::CLASE_SISTEMA;
    }

    /**
     * Falla al solicitar CAE donde no hubo respuesta clara de ARCA (timeout, red, SOAP sin resultado).
     *
     * Se usa para decidir si vale la pena consultar FECompConsultar como verificación posterior.
     * Solo true para errores de transporte (o mensaje vacío). En errores de datos/sistema la
     * respuesta de ARCA (o el bug) ya es clara y la consulta extra no aporta.
     */
    public static function esFallaComunicacionSinRespuestaClara(?string $mensaje, ?string $webservice = null): bool
    {
        if ($mensaje === null || trim($mensaje) === '') {
            return true;
        }

        return self::clasificarError($mensaje) === self::CLASE_TRANSPORTE;
    }

    /**
     * @return array{puntoventa_id:int, usa_caea:bool}
     */
    public static function resolverPuntoventaEmision(
        int $puntoventaCaeId,
        int $puntoventaCaeaId,
        bool $forzarCaeaTransaccion = false,
        ?string $webservice = null,
    ): array {
        $usaCaea = self::forzarModoCaea($webservice) || $forzarCaeaTransaccion;

        return [
            'puntoventa_id' => $usaCaea ? $puntoventaCaeaId : $puntoventaCaeId,
            'usa_caea' => $usaCaea,
        ];
    }

    /**
     * true: corresponde reintentar la transacción usando PV CAEA porque el primer intento (CAE)
     * cayó por un problema de COMUNICACIÓN. Nunca reintentar cuando el error es de datos o sistema.
     */
    public static function debeReintentarTransaccionConCaea(?string $mensaje, bool $yaUsaCaea, ?string $webservice = null): bool
    {
        if ($yaUsaCaea || self::forzarModoCaea($webservice)) {
            return false;
        }

        if (! self::reintentarCaeaSiFallaComunicacion($webservice)) {
            return false;
        }

        return self::esErrorTransporte($mensaje);
    }

    public static function mensajeAvisoModoCaeaForzado(?string $webservice = null): ?string
    {
        if (! self::forzarModoCaea($webservice)) {
            return null;
        }

        $env = self::esWsMtxca($webservice) ? 'ARCA_MTXCA_FORZAR_MODO_CAEA' : 'ARCA_WSFE_FORZAR_MODO_CAEA';

        return "Modo CAEA forzado ({$env}): no se consultó el web service en línea.";
    }

    /**
     * Etiqueta humana de la clase para logs / mensajes de aviso al operador.
     */
    public static function etiquetaClaseError(string $clase): string
    {
        return match ($clase) {
            self::CLASE_TRANSPORTE => 'comunicación con ARCA',
            self::CLASE_DATOS => 'datos del comprobante (ARCA)',
            self::CLASE_SISTEMA => 'sistema interno',
            default => 'no clasificado',
        };
    }

    /**
     * Arma un mensaje claro para el operador del POS según la clase del error.
     *
     * El frontend de gastronomía (`proceso_facturacion.js → formatearTextoAviso`)
     * divide en párrafos cada vez que un punto va seguido de mayúscula, por eso
     * usamos oraciones cortas separadas por punto + espacio + mayúscula.
     *
     * @param  string       $mensajeOriginal  detalle técnico (mensaje de la Exception)
     * @param  string|null  $clase            opcional; si es null se calcula con clasificarError().
     * @param  array{intento_caea?:bool,reintento_caea_habilitado?:bool}|null  $contexto
     *        - intento_caea: true si el mensaje viene del reintento con PV CAEA (ya falló CAE antes).
     *        - reintento_caea_habilitado: true si el sistema podría haber reintentado con CAEA
     *          pero el error no clasificó como transporte.
     */
    public static function formatearMensajeOperador(
        string $mensajeOriginal,
        ?string $clase = null,
        ?array $contexto = null,
    ): string {
        $detalle = trim($mensajeOriginal);
        $clase ??= self::clasificarError($detalle);
        $intentoCaea = (bool) ($contexto['intento_caea'] ?? false);
        $reintentoHabilitado = (bool) ($contexto['reintento_caea_habilitado'] ?? false);

        $detalleVisible = $detalle !== '' ? $detalle : '(sin detalle adicional)';

        return match ($clase) {
            self::CLASE_TRANSPORTE => self::mensajeTransporte($detalleVisible, $intentoCaea, $reintentoHabilitado),
            self::CLASE_DATOS => self::mensajeDatos($detalleVisible, $intentoCaea),
            self::CLASE_SISTEMA => self::mensajeSistema($detalleVisible, $intentoCaea),
            default => self::mensajeSinClasificar($detalleVisible, $intentoCaea),
        };
    }

    private static function mensajeTransporte(string $detalle, bool $intentoCaea, bool $reintentoHabilitado): string
    {
        if ($intentoCaea) {
            return 'No se pudo emitir la factura: tampoco hubo respuesta clara al reintento con CAEA. '
                .'Detalle técnico: '.$detalle.'. '
                .'Reintente en unos minutos. '
                .'Si el problema persiste, verifique la conexión a Internet y el estado del servicio AFIP. '
                .'Mientras tanto puede dejar el sistema en modo CAEA forzado '
                .'(ARCA_WSFE_FORZAR_MODO_CAEA=true).';
        }

        $sufijoReintento = $reintentoHabilitado
            ? 'El sistema reintentó automáticamente con CAEA y también falló. '
            : 'El reintento automático con CAEA está deshabilitado '
                .'(ARCA_WSFE_REINTENTAR_CAEA_SI_FALLA_COMUNICACION=false). ';

        return 'No se pudo emitir la factura: no hubo respuesta clara de ARCA (comunicación caída o latencia excesiva). '
            .'Detalle técnico: '.$detalle.'. '
            .$sufijoReintento
            .'Reintente en unos minutos. '
            .'Si el problema persiste, verifique la conexión a Internet y el estado del servicio AFIP. '
            .'Para seguir operando sin red, considere activar el modo CAEA forzado '
            .'(ARCA_WSFE_FORZAR_MODO_CAEA=true).';
    }

    private static function mensajeDatos(string $detalle, bool $intentoCaea): string
    {
        $prefijo = $intentoCaea
            ? 'ARCA rechazó el comprobante CAEA por datos inválidos. '
            : 'ARCA rechazó el comprobante por datos inválidos. ';

        return $prefijo
            .'Detalle: '.$detalle.'. '
            .'Revise los datos del comprobante (receptor, CUIT/DNI, condición frente al IVA, '
            .'importes, alícuotas y tipo de documento) antes de reintentar. '
            .'Reintentar con CAEA no corrige este error.';
    }

    private static function mensajeSistema(string $detalle, bool $intentoCaea): string
    {
        $prefijo = $intentoCaea
            ? 'Error interno del sistema al generar el comprobante CAEA. '
            : 'Error interno del sistema al generar el comprobante. ';

        return $prefijo
            .'No es un problema de ARCA ni de la red. '
            .'Detalle técnico: '.$detalle.'. '
            .'Avise a soporte técnico antes de seguir facturando. '
            .'No reintente la emisión con los mismos datos hasta resolver el error. '
            .'Si necesita facturar en este momento, puede activar temporalmente el modo CAEA '
            .'(ARCA_WSFE_FORZAR_MODO_CAEA=true).';
    }

    private static function mensajeSinClasificar(string $detalle, bool $intentoCaea): string
    {
        $prefijo = $intentoCaea
            ? 'No se pudo completar la facturación (reintento CAEA). '
            : 'No se pudo completar la facturación. ';

        return $prefijo
            .'Detalle: '.$detalle.'. '
            .'Reintente la operación. '
            .'Si el problema persiste, contacte a soporte técnico con el detalle anterior.';
    }
}
