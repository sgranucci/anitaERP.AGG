<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Arca\ArcaFailoverStore;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia as R;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Locks-in la matriz de clasificación de errores y la regla de reintento CAEA.
 *
 * Regla:
 *   - "transporte"     => debe permitir reintento CAEA
 *   - "datos"          => NO reintentar; abortar
 *   - "sistema"        => NO reintentar; abortar
 *   - "sin_clasificar" => NO reintentar (conservador); abortar
 */
class ArcaWsfeEmisionResilienciaTest extends TestCase
{
    public static function provideClasificacion(): array
    {
        return [
            // ===== TRANSPORTE (deben permitir reintento con CAEA) =====
            'timeout sin respuesta' => ['Connection timed out after 60001 milliseconds', R::CLASE_TRANSPORTE],
            'cURL connection refused' => ['cURL error 7: Connection refused', R::CLASE_TRANSPORTE],
            'DNS no resuelve' => ["Could not resolve host: servicios1.afip.gov.ar", R::CLASE_TRANSPORTE],
            'wsdl no cargable (red)' => [
                "SOAP-ERROR: Parsing WSDL: Couldn't load from 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL' : failed to load external entity",
                R::CLASE_TRANSPORTE,
            ],
            'bad gateway' => ['HTTP error 502 Bad Gateway', R::CLASE_TRANSPORTE],
            'gateway timeout' => ['Gateway Timeout 504', R::CLASE_TRANSPORTE],
            'service unavailable' => ['Service Unavailable 503', R::CLASE_TRANSPORTE],
            'respuesta vacía' => ['WSFE: FECAESolicitar sin resultado.', R::CLASE_TRANSPORTE],
            'no hubo respuesta wrapper' => [
                'No hubo respuesta de ARCA al solicitar el CAE del comprobante 1267916.',
                R::CLASE_TRANSPORTE,
            ],
            'broken pipe' => ['Connection broken pipe to AFIP', R::CLASE_TRANSPORTE],
            'tls handshake' => ['SSL handshake failed', R::CLASE_TRANSPORTE],
            'no route' => ['Network is unreachable', R::CLASE_TRANSPORTE],
            'afip error interno 501' => [
                'WSFE — FECompUltimoAutorizado: [501] Error interno de base de datos: - Metodo FECompUltimoAutorizado',
                R::CLASE_TRANSPORTE,
            ],
            'afip error interno 500 fecaesolicitar' => [
                'No pudo asignar CAE. WSFE — FECAESolicitar: [500] Error interno de aplicación',
                R::CLASE_TRANSPORTE,
            ],

            // ===== DATOS (ARCA respondió: NO reintentar) =====
            'falta dato obligatorio' => ['WSFE — FECAESolicitar: 10015 Falta dato obligatorio: DocTipo', R::CLASE_DATOS],
            'comprobante no autorizado' => ['WSFE — comprobante no autorizado. Resultado: R', R::CLASE_DATOS],
            'codigo 10016' => ['Error 10016: importe total inválido', R::CLASE_DATOS],
            'codigo 10017' => ['10017: ImpNeto no coincide', R::CLASE_DATOS],
            'codigo 600 wsaa' => ['Error 600 ValidacionDeToken: token expirado', R::CLASE_DATOS],
            'observado' => ['WSFE: Comprobante observado por ARCA', R::CLASE_DATOS],
            'rechazado' => ['Comprobante rechazado por ARCA', R::CLASE_DATOS],
            'cae denegado' => ['CAE denegado por razones de seguridad', R::CLASE_DATOS],

            // ===== SISTEMA (bug interno / config: NO reintentar) =====
            'soap encoding bug' => [
                "FECAESolicitar: SOAP-ERROR: Encoding: object has no 'Id' property",
                R::CLASE_SISTEMA,
            ],
            'sqlstate violacion' => [
                "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row",
                R::CLASE_SISTEMA,
            ],
            'wrapper con detalle encoding' => [
                "No hubo respuesta de ARCA al solicitar el CAE del comprobante 1267916. Detalle: FECAESolicitar: SOAP-ERROR: Encoding: object has no 'Id' property",
                R::CLASE_SISTEMA,
            ],
            'fopen falla' => [
                "fopen(/var/www/html/anitaERP/storage/logs/20260517213401000000_4685.csv): Failed to open stream: No such file or directory",
                R::CLASE_SISTEMA,
            ],
            'stdclass as array' => ['Cannot use object of type stdClass as array', R::CLASE_SISTEMA],
            'bridge anita caido' => [
                'Error en grabación Anita (consulta comprobante): Bridge HTTP Anita: Could not resolve host: apiERP.php',
                R::CLASE_SISTEMA,
            ],
            'cert expirado' => ['SSL certificate has expired', R::CLASE_SISTEMA],
            'cert no encontrado' => [
                'WSAA: no existe el archivo /storage/app/arca/wsfe/certs/bierzo/cert.crt',
                R::CLASE_SISTEMA,
            ],
            'undefined index' => ['Undefined array key "FECAESolicitarResult"', R::CLASE_SISTEMA],

            // ===== SIN CLASIFICAR =====
            'mensaje vacio' => ['', R::CLASE_SIN_CLASIFICAR],
            'null' => [null, R::CLASE_SIN_CLASIFICAR],
            'mensaje generico' => ['Algo salió mal sin más detalle', R::CLASE_SIN_CLASIFICAR],
        ];
    }

    #[DataProvider('provideClasificacion')]
    public function test_clasificar_error(?string $mensaje, string $claseEsperada): void
    {
        self::assertSame(
            $claseEsperada,
            R::clasificarError($mensaje),
            "Mensaje: {$mensaje}"
        );
    }

    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    public static function provideReintento(): array
    {
        return [
            'timeout reintenta' => ['Connection timed out', true],
            'afip 501 reintenta' => [
                'WSFE — FECompUltimoAutorizado: [501] Error interno de base de datos: - Metodo FECompUltimoAutorizado',
                true,
            ],
            'afip 500 fecaesolicitar reintenta' => [
                'No pudo asignar CAE. WSFE — FECAESolicitar: [500] Error interno de aplicación',
                true,
            ],
            'no pudo numerar comprobate reintenta' => ['No pudo numerar comprobate', true],
            'wsdl no carga reintenta' => [
                "Couldn't load from 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL'",
                true,
            ],
            'soap encoding NO reintenta' => [
                "SOAP-ERROR: Encoding: object has no 'Id' property",
                false,
            ],
            'wrapper con encoding NO reintenta' => [
                "No hubo respuesta de ARCA al solicitar el CAE del comprobante 1267916. Detalle: SOAP-ERROR: Encoding: object has no 'Id' property",
                false,
            ],
            'falta dato obligatorio NO reintenta' => [
                'WSFE — FECAESolicitar: 10015 Falta dato obligatorio: DocTipo',
                false,
            ],
            'comprobante rechazado NO reintenta' => ['Comprobante rechazado por ARCA', false],
            'sqlstate NO reintenta' => ['SQLSTATE[23000]: foreign key constraint', false],
            'mensaje vacio NO reintenta' => ['', false],
            'mensaje generico NO reintenta' => ['Algo sin detalle', false],
        ];
    }

    #[DataProvider('provideReintento')]
    public function test_debe_reintentar_transaccion_con_caea_aplica_solo_a_transporte(
        ?string $mensaje,
        bool $reintentar,
    ): void {
        // Setup: wsfe con reintentar_caea=true, no forzar_caea
        config()->set('arca_wsfe.emision.forzar_modo_caea', false);
        config()->set('arca_wsfe.emision.reintentar_caea_si_falla_comunicacion', true);

        $resultado = R::debeReintentarTransaccionConCaea($mensaje, yaUsaCaea: false);
        self::assertSame($reintentar, $resultado, "Mensaje: {$mensaje}");
    }

    public function test_no_reintenta_si_ya_usa_caea(): void
    {
        config()->set('arca_wsfe.emision.reintentar_caea_si_falla_comunicacion', true);
        self::assertFalse(R::debeReintentarTransaccionConCaea('Connection timed out', yaUsaCaea: true));
    }

    public function test_no_reintenta_si_flag_reintento_apagado(): void
    {
        config()->set('arca_wsfe.emision.forzar_modo_caea', false);
        config()->set('arca_wsfe.emision.reintentar_caea_si_falla_comunicacion', false);
        self::assertFalse(R::debeReintentarTransaccionConCaea('Connection timed out', yaUsaCaea: false));
    }

    public function test_no_reintenta_si_ya_emitio_con_pv_caea(): void
    {
        config()->set('arca_wsfe.emision.forzar_modo_caea', true);
        config()->set('arca_wsfe.emision.reintentar_caea_si_falla_comunicacion', true);
        self::assertFalse(R::debeReintentarTransaccionConCaea('Connection timed out', yaUsaCaea: true));
    }

    public function test_reintenta_si_failover_se_activo_durante_emision_cae(): void
    {
        config()->set('arca_wsfe.emision.forzar_modo_caea', false);
        config()->set('arca_wsfe.emision.reintentar_caea_si_falla_comunicacion', true);
        config()->set('arca.monitor_conectividad.fallos_para_activar', 1);

        try {
            ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
            ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, false, 'Service Unavailable');
            self::assertTrue(R::forzarModoCaea());
            self::assertTrue(
                R::debeReintentarTransaccionConCaea('Service Unavailable 503', yaUsaCaea: false),
                'La transacción sigue en PV CAE aunque el failover global ya esté activo'
            );
        } finally {
            ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
        }
    }

    public function test_notificar_falla_transporte_incrementa_failover(): void
    {
        config()->set('arca_wsfe.emision.forzar_modo_caea', false);
        config()->set('arca.monitor_conectividad.fallos_para_activar', 1);

        try {
            ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
            R::notificarFallaTransporteEmision('Connection timed out', 'wsfev1', ['probe' => 'test']);
            self::assertTrue(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));
        } finally {
            ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
        }
    }

    public function test_notificar_falla_transporte_ignora_errores_de_datos(): void
    {
        try {
            ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
            R::notificarFallaTransporteEmision('10015 Falta dato obligatorio', 'wsfev1');
            self::assertFalse(ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE));
        } finally {
            ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
        }
    }

    public function test_soap_timeout_pos_para_opciones_gastronomia(): void
    {
        config()->set('arca_wsfe.soap_timeout_pos', 18);
        config()->set('arca_mtxca.soap_timeout_pos', 18);
        self::assertSame(
            18,
            R::soapTimeoutPosParaOpciones(['emision_pos_arca' => true], 'wsfev1'),
        );
        self::assertSame(
            18,
            R::soapTimeoutPosParaOpciones(['aplicar_timeout_pos_arca' => true], 'wsmtxca'),
        );
        self::assertNull(R::soapTimeoutPosParaOpciones([], 'wsfev1'));
    }

    public function test_forzar_modo_caea_por_failover_automatico(): void
    {
        config()->set('arca_wsfe.emision.forzar_modo_caea', false);
        config()->set('arca.monitor_conectividad.fallos_para_activar', 1);

        try {
            ArcaFailoverStore::registrarChequeo(ArcaFailoverStore::WS_WSFE, false, 'timeout');
            self::assertTrue(R::forzarModoCaea());
            self::assertTrue(R::failoverAutomaticoActivo());
            self::assertStringContainsString('monitor de conectividad', (string) R::mensajeAvisoModoCaeaForzado());
        } finally {
            ArcaFailoverStore::reset(ArcaFailoverStore::WS_WSFE);
        }
    }

    public function test_es_falla_comunicacion_sin_respuesta_clara_descarta_bugs(): void
    {
        // Bug interno NO se considera "sin respuesta clara"
        self::assertFalse(R::esFallaComunicacionSinRespuestaClara(
            "SOAP-ERROR: Encoding: object has no 'Id' property"
        ));
        self::assertFalse(R::esFallaComunicacionSinRespuestaClara(
            'SQLSTATE[23000]: violation'
        ));
        // Datos de ARCA tampoco (ARCA respondió claro)
        self::assertFalse(R::esFallaComunicacionSinRespuestaClara(
            '10015 Falta dato obligatorio'
        ));
        // Transporte sí
        self::assertTrue(R::esFallaComunicacionSinRespuestaClara('Connection timed out'));
        self::assertTrue(R::esFallaComunicacionSinRespuestaClara('Could not connect to host'));
        // Mensaje vacío: conservador, sí (consultar FECompConsultar como verificación)
        self::assertTrue(R::esFallaComunicacionSinRespuestaClara(''));
        self::assertTrue(R::esFallaComunicacionSinRespuestaClara(null));
    }

    public function test_etiqueta_clase_error(): void
    {
        self::assertSame('comunicación con ARCA', R::etiquetaClaseError(R::CLASE_TRANSPORTE));
        self::assertSame('datos del comprobante (ARCA)', R::etiquetaClaseError(R::CLASE_DATOS));
        self::assertSame('sistema interno', R::etiquetaClaseError(R::CLASE_SISTEMA));
        self::assertSame('no clasificado', R::etiquetaClaseError(R::CLASE_SIN_CLASIFICAR));
        self::assertSame('no clasificado', R::etiquetaClaseError('zzz'));
    }

    public function test_formatear_mensaje_transporte_con_reintento_caea_habilitado(): void
    {
        $msg = R::formatearMensajeOperador(
            'Connection timed out after 60001 milliseconds',
            null,
            ['intento_caea' => false, 'reintento_caea_habilitado' => true],
        );

        self::assertStringContainsString('no hubo respuesta clara de ARCA', $msg);
        self::assertStringContainsString('comunicación caída o latencia excesiva', $msg);
        self::assertStringContainsString('Connection timed out', $msg);
        self::assertStringContainsString('reintentó automáticamente con CAEA', $msg);
        self::assertStringContainsString('verifique la conexión', $msg);
        self::assertStringContainsString('ARCA_WSFE_FORZAR_MODO_CAEA', $msg);
    }

    public function test_formatear_mensaje_transporte_con_reintento_deshabilitado(): void
    {
        $msg = R::formatearMensajeOperador(
            'Could not connect to host',
            null,
            ['intento_caea' => false, 'reintento_caea_habilitado' => false],
        );

        self::assertStringContainsString('reintento automático con CAEA está deshabilitado', $msg);
        self::assertStringContainsString('ARCA_WSFE_REINTENTAR_CAEA_SI_FALLA_COMUNICACION=false', $msg);
    }

    public function test_formatear_mensaje_transporte_desde_intento_caea(): void
    {
        $msg = R::formatearMensajeOperador(
            'Connection timed out',
            null,
            ['intento_caea' => true],
        );

        self::assertStringContainsString('tampoco hubo respuesta clara al reintento con CAEA', $msg);
        self::assertStringContainsString('Connection timed out', $msg);
    }

    public function test_formatear_mensaje_datos(): void
    {
        $msg = R::formatearMensajeOperador('WSFE — FECAESolicitar: 10015 Falta dato obligatorio: DocTipo');

        self::assertStringContainsString('ARCA rechazó el comprobante por datos inválidos', $msg);
        self::assertStringContainsString('10015', $msg);
        self::assertStringContainsString('receptor, CUIT/DNI', $msg);
        self::assertStringContainsString('Reintentar con CAEA no corrige', $msg);
    }

    public function test_formatear_mensaje_sistema_oculta_jerga_pero_muestra_detalle(): void
    {
        $msg = R::formatearMensajeOperador(
            "No hubo respuesta de ARCA al solicitar el CAE del comprobante 1267916. Detalle: FECAESolicitar: SOAP-ERROR: Encoding: object has no 'Id' property"
        );

        self::assertStringContainsString('Error interno del sistema', $msg);
        self::assertStringContainsString('No es un problema de ARCA ni de la red', $msg);
        self::assertStringContainsString('SOAP-ERROR: Encoding', $msg);
        self::assertStringContainsString('Avise a soporte técnico', $msg);
        self::assertStringContainsString('No reintente la emisión con los mismos datos', $msg);
    }

    public function test_formatear_mensaje_sin_clasificar(): void
    {
        $msg = R::formatearMensajeOperador('Algo salió mal sin detalle reconocible');

        self::assertStringContainsString('No se pudo completar la facturación', $msg);
        self::assertStringContainsString('Algo salió mal sin detalle reconocible', $msg);
        self::assertStringContainsString('contacte a soporte técnico', $msg);
    }

    public function test_formatear_mensaje_vacio_muestra_sin_detalle(): void
    {
        $msg = R::formatearMensajeOperador('');

        self::assertStringContainsString('(sin detalle adicional)', $msg);
    }

    public function test_formatear_mensaje_acepta_clase_explicita_sin_re_clasificar(): void
    {
        // Pasamos clase explícita; el formateador no debería re-clasificar el mensaje.
        $msg = R::formatearMensajeOperador('cualquier cosa', R::CLASE_TRANSPORTE);
        self::assertStringContainsString('comunicación caída o latencia excesiva', $msg);

        $msg = R::formatearMensajeOperador('cualquier cosa', R::CLASE_DATOS);
        self::assertStringContainsString('ARCA rechazó el comprobante por datos inválidos', $msg);
    }
}
