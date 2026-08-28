<?php
// Constantes de facturacion

/**
 * Mapa empresa_id => código de listaprecio donde se cargan los coeficientes de
 * impuesto interno (precio = coeficiente, p. ej. 0.7307). Se aplica por insumo
 * con tipoarticulo nombre = "CIGARRILLO" al expandir la fórmula del ítem
 * facturado (incluye opcionales elegidos) o cuando el propio artículo es de
 * ese tipoarticulo. Vale para todos los facturadores (no solo gastronomía).
 *
 * Override por env (JSON):
 *   FACTURACION_IMPUESTO_INTERNO_LISTAS_POR_EMPRESA='{"1":"162","2":"262","3":"362"}'
 *
 * @return array<int, string>
 */
$impuestoInternoListasPorEmpresa = (static function (): array {
    $raw = env('FACTURACION_IMPUESTO_INTERNO_LISTAS_POR_EMPRESA');
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (! is_array($decoded)) {
        return [];
    }
    $map = [];
    foreach ($decoded as $empresaId => $codigo) {
        $cod = trim((string) $codigo);
        if ($cod !== '' && (int) $empresaId > 0) {
            $map[(int) $empresaId] = $cod;
        }
    }

    return $map;
})();

/**
 * Nombre del tipoarticulo cuyos insumos disparan el cálculo del impuesto
 * interno (uppercase, match exacto contra `tipoarticulo.nombre`).
 */
$impuestoInternoTipoArticulo = strtoupper((string) env('FACTURACION_IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE', 'CIGARRILLO'));

switch(strtoupper(config('app.empresa')))
{
    case "EL BIERZO":
        return [
            "LAYOUT_ITEMS_PEDIDO" => true,
            "DIGITOS_SUCURSAL" => "5",
            "DIGITOS_COMPROBANTE" => "8",
            "LIMITE_FCE" => 5549862,
            "PUNTOVENTA_FACTURACION" => 5,
            "PUNTOVENTA_REMITO" => 1,
            // Preferencia por usuario (códigos ARCA, no ids). clarisad = producción; resto = prueba.
            "TIPO_FACTURA_ABREVIATURA" => "FAC",
            "PUNTOVENTA_FACTURACION_CODIGO" => "00008",
            "PUNTOVENTA_REMITO_CODIGO" => "00099",
            "PREFERENCIA_FACTURACION_USUARIOS_PRODUCCION" => ["clarisad", "cdacurso"],
            "PUNTOVENTA_FACTURACION_PRODUCCION_CODIGO" => "00010",
            "PUNTOVENTA_REMITO_PRODUCCION_CODIGO" => "00001",
            "CUENTACONTABLE_PERCEPCION_IVA" => '211290000',
            // RG 2126 / Anita impcont 46: Perc. no categorizado. El ABM impuesto PNC manda; esto es fallback.
            "CUENTACONTABLE_PERCEPCION_NO_CATEGORIZADO" => env('FACTURACION_CUENTA_PERCEPCION_NO_CATEGORIZADO', '211172000'),
            "CUENTACONTABLE_IVA" => '211170000',
            "CUENTACONTABLE_VENTA" => '301100000',
            "CUENTACONTABLE_LOGISTICA" => '301100000',
            "IMPUESTO_LOGISTICA_ID" => 3, // Asume que logistica es al 21%
            "USA_DETRACCION" => 'N',
            "PUNTOVENTA_DIVISION_ID" => 5,
            "PUNTOVENTA_DIVISION_LOCAL_ID" => 6,
            // Reparto 101: correlativo Anita Villafranca FAC A sucursal 1; se emite en PV 15.
            "VILLAFRANCA_NUMERADOR_SUCURSAL" => "1",
            "COEFICIENTE_EXTRA_REPARTO_101" => 1.10,
            "DECIMAL_KILO" => 2,
            "DECIMAL_CANTIDAD" => 2,
            "DECIMAL_PIEZA" => 2,
            "DECIMAL_CAJA" => 2,
            "TIPO_REMITO" => 'REM',
            "LETRA_REMITO" => 'R',
            "TIPO_REMITO_ID" => 9,
            // % a descontar del neto del remito para valor asegurado (Anita tot_seguro).
            "PORCENTAJE_VALOR_ASEGURADO" => (float) env('REMITO_PORCENTAJE_VALOR_ASEGURADO', 15),
            // Unidad enviada a Anita cuando el articulo no tiene unidadmedida_id en el ERP
            "UNIDADMEDIDA_DEFAULT" => 'Kg',
            // Remito en stock: se resuelve por abreviatura TIPO_REMITO en tipotransaccion_stock
            "DEPOSITO_VENTA_ID" => 1,
            "NETEA_DESCUENTO_LINEA" => false, // false deja precio de lista en el renglon siempre y manda el descuento resultante al pie
                                              // true netea el descuento en el precio de cada linea de la factura sin mandar descuento resultante al pie
            "IMPUESTO_INTERNO_LISTAPRECIO_POR_EMPRESA" => $impuestoInternoListasPorEmpresa,
            "IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE" => $impuestoInternoTipoArticulo,
            // Candado anti-doble factura del mismo pedido (segundos).
            "pedido_facturacion_lock_segundos" => 180,
            // Salto CAE→CAEA en mostrador / pedido / remito (solo este case EL BIERZO).
            "SALTO_CAEA_ADMINISTRATIVA" => filter_var(env('FACTURACION_SALTO_CAEA_ADMINISTRATIVA', true), FILTER_VALIDATE_BOOLEAN),
            "SALTO_CAEA_MAPEO_CODIGOS" => [
                '00010' => (string) env('FACTURACION_SALTO_CAEA_PARA_00010', '00005'),
                '00009' => (string) env('FACTURACION_SALTO_CAEA_PARA_00009', '00005'),
            ],
            // Profiler paso a paso factura pedido (laravel.log: facturacion.pedido.paso / .profile).
            "pedido_emision_profile" => filter_var(env('FACTURACION_PEDIDO_PROFILE', true), FILTER_VALIDATE_BOOLEAN),
            "pedido_emision_profile_live" => filter_var(env('FACTURACION_PEDIDO_PROFILE_LIVE', true), FILTER_VALIDATE_BOOLEAN),
            "pedido_emision_profile_en_respuesta" => filter_var(env('FACTURACION_PEDIDO_PROFILE_EN_RESPUESTA', false), FILTER_VALIDATE_BOOLEAN),
            "pedido_emision_umbral_advertencia_ms" => max(0, (int) env('FACTURACION_PEDIDO_UMBRAL_ADVERTENCIA_MS', 5000)),
            // Anita de factura pedido / remito / mostrador: después de responder. ARCA (número + CAE) sigue síncrono.
            "ANITA_TRAS_RESPUESTA_PEDIDO" => filter_var(env('BIERZO_PEDIDO_ANITA_TRAS_RESPUESTA', true), FILTER_VALIDATE_BOOLEAN),
            // Cola Laravel (no terminating/Apache). Requiere QUEUE_CONNECTION=database|redis y worker.
            "ANITA_PEDIDO_EN_COLA" => filter_var(env('BIERZO_PEDIDO_ANITA_EN_COLA', true), FILTER_VALIDATE_BOOLEAN),
            "ANITA_PEDIDO_COLA" => env('BIERZO_PEDIDO_ANITA_COLA', 'default'),
            "ANITA_PEDIDO_JOB_TRIES" => max(1, (int) env('BIERZO_PEDIDO_ANITA_JOB_TRIES', 3)),
            "ANITA_PEDIDO_JOB_BACKOFF_SEGUNDOS" => [60, 300, 900],
            "ANITA_PEDIDO_JOB_TIMEOUT" => max(60, (int) env('BIERZO_PEDIDO_ANITA_JOB_TIMEOUT', 300)),
            "ANITA_PEDIDO_REGRABAR_HABILITADO" => filter_var(env('BIERZO_PEDIDO_ANITA_REGRABAR', true), FILTER_VALIDATE_BOOLEAN),
            "ANITA_PEDIDO_REGRABAR_MAX_INTENTOS" => max(1, (int) env('BIERZO_PEDIDO_ANITA_REGRABAR_MAX_INTENTOS', 20)),
            "ANITA_PEDIDO_REGRABAR_LIMITE" => max(1, (int) env('BIERZO_PEDIDO_ANITA_REGRABAR_LIMITE', 20)),
            // Último número CAEA forzado por PV (próximo = piso+1). PV 8 no tiene compemis Anita.
            "CAEA_PISO_NUMERO_POR_CODIGO" => [
                '00008' => (int) env('FACTURACION_CAEA_PISO_PV_00008', 43),
            ],
        ];
        break;
    case "AGG":
        return [
            "DIGITOS_SUCURSAL" => "5",
            "DIGITOS_COMPROBANTE" => "8",
            "LIMITE_FCE" => 3958316,
            "PUNTOVENTA_FACTURACION" => [19,2,3], // Por empresa BSA/KSA/RSA
            "PUNTOVENTA_REMITO" => 1,
            // Sin precarga: valor asegurado = neto salvo override .env.
            "PORCENTAJE_VALOR_ASEGURADO" => (float) env('REMITO_PORCENTAJE_VALOR_ASEGURADO', 0),
            "CUENTACONTABLE_PERCEPCION_IVA" => '',
            "CUENTACONTABLE_PERCEPCION_NO_CATEGORIZADO" => env('FACTURACION_CUENTA_PERCEPCION_NO_CATEGORIZADO', ''),
            "CUENTACONTABLE_VENTA" => '415010002',
            'USA_DETRACCION' => 'S',
            "DECIMAL_CANTIDAD" => 0,
            "NETEA_DESCUENTO_LINEA" => false,
            // Default AGG: BIYEMAS=162, KANDIKO=262, REBISCO=362. Override por env si cambian códigos.
            "IMPUESTO_INTERNO_LISTAPRECIO_POR_EMPRESA" => $impuestoInternoListasPorEmpresa !== []
                ? $impuestoInternoListasPorEmpresa
                : [1 => '162', 2 => '262', 3 => '362'],
            "IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE" => $impuestoInternoTipoArticulo,
        ];
        break;
}