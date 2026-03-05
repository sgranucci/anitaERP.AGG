<?php
// Constantes de facturacion

switch(strtoupper(config('app.empresa')))
{
    case "EL BIERZO":
        return [
            "DIGITOS_SUCURSAL" => "5",
            "DIGITOS_COMPROBANTE" => "8",
            "LIMITE_FCE" => 3958316,
            "PUNTOVENTA_FACTURACION" => 5,
            "PUNTOVENTA_REMITO" => 1,
            "CUENTACONTABLE_PERCEPCION_IVA" => '211290000',
            "CUENTACONTABLE_VENTA" => '301100000',
            "CUENTACONTABLE_LOGISTICA" => '301100000',
            "IMPUESTO_LOGISTICA_ID" => 3, // Asume que logistica es al 21%
            "USA_DETRACCION" => 'N',
            "PUNTOVENTA_DIVISION_ID" => 5,
            "PUNTOVENTA_DIVISION_LOCAL_ID" => 6,
            "DECIMAL_KILO" => 2,
            "DECIMAL_PIEZA" => 2,
            "DECIMAL_CAJA" => 2,
            "TIPO_REMITO" => 'REM',
            "LETRA_REMITO" => 'R',
            "TIPO_REMITO_ID" => 9,
            "DEPOSITO_VENTA_ID" => 1
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
            "CUENTACONTABLE_VENTA" => '',
            'USA_DETRACCION' => 'S',
            "DECIMAL_CANTIDAD" => 0
        ];
        break;
}