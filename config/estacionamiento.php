<?php

/**
 * Facturación de estacionamiento (módulo Caja).
 * Por ahora solo desplegado en entorno AGG (BIYEMAS / KANDIKO / REBISCO).
 */
$empresaEntorno = strtoupper(trim((string) env('EMPRESA', 'AGG')));
$defaultHabilitado = $empresaEntorno === 'AGG';

return [
    'habilitado' => filter_var(
        env('ESTACIONAMIENTO_HABILITADO', $defaultHabilitado ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),

    // Exigir jornada abierta antes de facturar estacionamiento (como gastronomía).
    'jornada_obligatoria' => filter_var(
        env('ESTACIONAMIENTO_JORNADA_OBLIGATORIA', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Identificador fijo cuando no se usa IP del cliente.
     * Coincide con configuracion_puntoventa_estacionamiento.identificador_pc en ese modo.
     */
    'identificador_pc' => (static function (): string {
        $valor = trim((string) env('ESTACIONAMIENTO_IDENTIFICADOR_PC', ''));

        return $valor !== '' ? $valor : (string) gethostname();
    })(),

    /**
     * true = el identificador efectivo es la IP del cliente ($request->ip()).
     */
    'identificador_pc_usar_ip_cliente' => filter_var(
        env('ESTACIONAMIENTO_IDENTIFICADOR_USAR_IP_CLIENTE', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * true = habilitación de turno por PC antes de facturar estacionamiento.
     */
    'requiere_habilitacion_turno' => filter_var(
        env('ESTACIONAMIENTO_REQUIERE_HABILITACION_TURNO', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * true = bloquear cierre de turno/jornada si hay ticket_estacionamiento en estado ingreso.
     * Requiere el flujo futuro de emisión de ticket de ingreso de autos. Mientras sea false, no se valida.
     */
    'validar_tickets_ingreso_al_cerrar' => filter_var(
        env('ESTACIONAMIENTO_VALIDAR_TICKETS_INGRESO_AL_CERRAR', 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Uso de cuenta de caja para medios de cobro en el POS (tabla usocuentacaja).
     * Si no se define, se busca por nombre "Estacionamiento".
     */
    'usocuentacaja_id' => env('ESTACIONAMIENTO_USO_CUENTACAJA_ID'),

    /**
     * Código de cliente interno del descuento por defecto (centro de costo / invitación).
     */
    'cliente_descuento_codigo' => env('ESTACIONAMIENTO_CLIENTE_DESCUENTO_CODIGO', '501'),

    /**
     * Cuenta de caja de efectivo por empresa para efectivizar (F5).
     * Clave = empresa_id, valor = cuentacaja_id.
     * Alternativa: env JSON ESTACIONAMIENTO_CUENTACAJA_EFECTIVO_POR_EMPRESA='{"1":42,"2":15}'.
     *
     * @var array<int, int>
     */
    'cuentacaja_efectivo_por_empresa' => (static function (): array {
        $raw = env('ESTACIONAMIENTO_CUENTACAJA_EFECTIVO_POR_EMPRESA');
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }
        $map = [];
        foreach ($decoded as $empresaId => $cuentacajaId) {
            $ccId = (int) $cuentacajaId;
            if ($ccId > 0) {
                $map[(int) $empresaId] = $ccId;
            }
        }

        return $map;
    })(),

    /** Moneda por defecto al emitir factura si no hay líneas de cobranza cargadas. */
    'moneda_factura_id' => env('ESTACIONAMIENTO_MONEDA_FACTURA_ID'),

    /**
     * Respaldo si la configuración del punto de venta estacionamiento no define tipotransaccion_caja_id.
     */
    'tipotransaccion_caja_id' => env('ESTACIONAMIENTO_TIPO_TRANSACCION_CAJA_ID'),

    /**
     * Respaldo si la configuración del punto de venta estacionamiento no define tipotransaccion_id.
     */
    'tipotransaccion_factura_id' => env('ESTACIONAMIENTO_TIPO_TRANSACCION_FACTURA_ID'),

    /**
     * Respaldo si la configuración del punto de venta estacionamiento no define tipotransaccion_nota_credito_id.
     */
    'tipotransaccion_nota_credito_id' => env('ESTACIONAMIENTO_TIPO_TRANSACCION_NOTA_CREDITO_ID'),

    /**
     * Tipo de transacción de caja para devolución de factura (nota de crédito) desde estacionamiento.
     */
    'tipotransaccion_caja_devolucion_id' => (int) env('ESTACIONAMIENTO_TIPO_TRANSACCION_CAJA_DEVOLUCION_ID', 3),

    /**
     * Condición IIBB del maestro para cálculo de impuestos cuando la cuenta factura como consumidor final.
     */
    'consumidor_final_condicioniibb_id' => (int) env('ESTACIONAMIENTO_CONSUMIDOR_FINAL_CONDICIONIIBB_ID', 4),

    /**
     * Impuesto maestro sin IVA para factura de cortesía ($0,01, descuento 100 %).
     */
    'impuesto_exento_id' => (int) env('ESTACIONAMIENTO_IMPUESTO_EXENTO_ID', 1),

    /**
     * Si es false, no se graba asiento contable al facturar desde estacionamiento.
     */
    'genera_contabilidad_al_facturar' => filter_var(
        env('ESTACIONAMIENTO_GENERA_CONTABILIDAD_FACTURA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Si es false, la cobranza registrada desde el POS no genera asiento contable de tesorería.
     */
    'genera_contabilidad_al_cobrar' => filter_var(
        env('ESTACIONAMIENTO_GENERA_CONTABILIDAD_COBRANZA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Replica la venta en Informix legacy vía bridge HTTP al facturar desde estacionamiento.
     */
    'sincronizar_anita_al_facturar' => filter_var(
        env('ESTACIONAMIENTO_SINCRONIZAR_ANITA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Tras commit de la emisión estacionamiento: replica venta en Informix sin bloquear cobranza/locks MySQL.
     */
    'anita_tras_commit_al_facturar' => filter_var(
        env('ESTACIONAMIENTO_ANITA_TRAS_COMMIT_AL_FACTURAR', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Anita (venta cabecera + renglones) después de responder al POS estacionamiento.
     */
    'anita_tras_respuesta' => filter_var(
        env('ESTACIONAMIENTO_ANITA_TRAS_RESPUESTA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Segundos máximos que una emisión retiene el candado del PV.
     */
    'emision_lock_segundos' => (int) env('ESTACIONAMIENTO_EMISION_LOCK_SEGUNDOS', 180),

    /**
     * Tras emitir en el POS: imprimir ticket térmico vía salida_factura (comando con %s).
     */
    'ticket_impresion_automatica' => filter_var(
        env('ESTACIONAMIENTO_TICKET_IMPRESION_AUTOMATICA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Imprimir el ticket térmico DESPUÉS de responder al POS (Laravel defer()).
     */
    'ticket_impresion_async' => filter_var(
        env('ESTACIONAMIENTO_TICKET_IMPRESION_ASYNC', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Ancho en caracteres del papel (80 mm ≈ 42). */
    'ticket_ancho_caracteres' => max(32, (int) env('ESTACIONAMIENTO_TICKET_ANCHO', 42)),

    /** Codificación de caracteres para ESC/POS. */
    'ticket_codificacion' => env('ESTACIONAMIENTO_TICKET_CODIFICACION', 'ISO-8859-1'),

    /** Tamaño del QR en impresora Epson (1–8). */
    'ticket_qr_size' => max(1, min(8, (int) env('ESTACIONAMIENTO_TICKET_QR_SIZE', 6))),

    /** Timeout del comando de salida (segundos). */
    'ticket_comando_timeout_segundos' => max(5, (int) env('ESTACIONAMIENTO_TICKET_COMANDO_TIMEOUT', 30)),

    /**
     * Guarda copia legible (.txt) en storage/app/estacionamiento/tickets/preview/.
     */
    'ticket_guardar_preview' => filter_var(
        env('ESTACIONAMIENTO_TICKET_GUARDAR_PREVIEW', env('APP_ENV', 'production') === 'local'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Lista de precios ERP (cabecera venta) si el PV no define otra. */
    'listaprecio_id' => (int) env('ESTACIONAMIENTO_LISTAPRECIO_ID', 1),
];
