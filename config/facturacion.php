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
            "CUENTACONTABLE_PERCEPCION_IVA" => '211290000',
            "CUENTACONTABLE_VENTA" => '301100000',
            "CUENTACONTABLE_LOGISTICA" => '301100000',
            "IMPUESTO_LOGISTICA_ID" => 3, // Asume que logistica es al 21%
            "USA_DETRACCION" => 'N',
            "PUNTOVENTA_DIVISION_ID" => 5,
            "PUNTOVENTA_DIVISION_LOCAL_ID" => 6,
            "COEFICIENTE_EXTRA_REPARTO_101" => 1.10,
            "DECIMAL_KILO" => 2,
            "DECIMAL_CANTIDAD" => 2,
            "DECIMAL_PIEZA" => 2,
            "DECIMAL_CAJA" => 2,
            "TIPO_REMITO" => 'REM',
            "LETRA_REMITO" => 'R',
            "TIPO_REMITO_ID" => 9,
            // Remito en stock: se resuelve por abreviatura TIPO_REMITO en tipotransaccion_stock
            "DEPOSITO_VENTA_ID" => 1,
            "NETEA_DESCUENTO_LINEA" => false, // false deja precio de lista en el renglon siempre y manda el descuento resultante al pie
                                              // true netea el descuento en el precio de cada linea de la factura sin mandar descuento resultante al pie
            "IMPUESTO_INTERNO_LISTAPRECIO_POR_EMPRESA" => $impuestoInternoListasPorEmpresa,
            "IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE" => $impuestoInternoTipoArticulo,
        ];
        break;
    case "AGG":
        return [
            "DIGITOS_SUCURSAL" => "5",
            "DIGITOS_COMPROBANTE" => "8",
            "LIMITE_FCE" => 3958316,
            "PUNTOVENTA_FACTURACION" => [19,2,3], // Por empresa BSA/KSA/RSA
            "PUNTOVENTA_REMITO" => 1,
            "CUENTACONTABLE_PERCEPCION_IVA" => '',
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