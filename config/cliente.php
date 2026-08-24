<?php
// Constantes de clientes / reporte total de pares

// false = no se puede repetir CUIT/documento entre clientes (default).
// true  = permite alta/edición con CUIT ya usado (aviso en pantalla, sin bloquear).
$permitirCuitDuplicado = filter_var(env('CLIENTE_PERMITIR_CUIT_DUPLICADO', false), FILTER_VALIDATE_BOOLEAN);

switch(config('app.empresa'))
{
    case "INTERFORMING":
        return [
            "tipoalta" => [
                        'DEFINITIVO' => ['D'], 
                        'PROVISORIO' => ['P']
                ],
            "tiposuspension" => [
                        'MOROSO' => '1',
                        'PROFORMA' => '2',
                        'MOROSOS' => '3',
                        'NO_FACTURAR' => '4'
                ],
            'CLIENTE_STOCK_ID' => '620',
            'CLIENTE_DESPACHO_ID' => (int) env('CLIENTE_DESPACHO_ID', 0),
            'MAIL_CLIENTE_PROVISORIO' => 'fherber@interforming.com.ar',
            'TOPE_DESCUENTO' => 20,
            'CATEGORIA_SECOS_ID' => 10,
            'SUBCATEGORIA_MAQUINA_ID' => 1,
            'SUBCATEGORIA_TIRA_ID' => 2,
            'DEUDORES_POR_VENTAS' => 112101000,
            'ANTICIPO_DE_CLIENTES' => 112101000,
            'EMPRESA_DEFAULT_ID' => 1,
            'ENVIA_MAIL_ALTA_CLIENTE_DEFINITIVO' => 'NO',
            'DESTINATARIO_ALTA_CLIENTE_DEFINITIVO' => ['fherber@interforming.com.ar'],
            'SINCRONIZA_CLIMA_ANITA' => false,
            'permitir_cuit_duplicado' => $permitirCuitDuplicado,
            ];
        break;

    case "EL BIERZO":
        return [
            "tipoalta" => [
                        'DEFINITIVO' => ['D'], 
                        'PROVISORIO' => ['P']
                ],
            "tiposuspension" => [
                        'MOROSO' => '1',
                        'PROFORMA' => '2',
                        'MOROSOS' => '3',
                        'NO_FACTURAR' => '4'
                ],
            'CLIENTE_STOCK_ID' => '620',
            'CLIENTE_DESPACHO_ID' => (int) env('CLIENTE_DESPACHO_ID', 0),
            'MAIL_CLIENTE_PROVISORIO' => 'info@elbierzo.com.ar',
            'TOPE_DESCUENTO' => 20,
            'CATEGORIA_SECOS_ID' => 10,
            'SUBCATEGORIA_MAQUINA_ID' => 1,
            'SUBCATEGORIA_TIRA_ID' => 2,
            'DEUDORES_POR_VENTAS' => 113100000,
            'ANTICIPO_DE_CLIENTES' => 113100000,
            'EMPRESA_DEFAULT_ID' => 1,
            'ENVIA_MAIL_ALTA_CLIENTE_DEFINITIVO' => 'SI',
            'DESTINATARIO_ALTA_CLIENTE_DEFINITIVO' => ['luisav@elbierzo.com.ar', 'claudiam@elbierzo.com.ar', 'carolinal@elbierzo.com.ar'],
            'SINCRONIZA_CLIMA_ANITA' => true,
            // EL BIERZO: permite CUIT duplicados por defecto (override con CLIENTE_PERMITIR_CUIT_DUPLICADO=false)
            'permitir_cuit_duplicado' => filter_var(env('CLIENTE_PERMITIR_CUIT_DUPLICADO', true), FILTER_VALIDATE_BOOLEAN),
            // Coeficiente extra (clim_coef_extra). Solo lectura en ABM; se asigna en altas.
            'COEFICIENTE_EXTRA' => (float) env('CLIENTE_COEFICIENTE_EXTRA', 1.05),
            ];
        break;

    case "AGG":
        return [
            "tipoalta" => [
                        'DEFINITIVO' => ['D'], 
                        'PROVISORIO' => ['P']
                ],
            "tiposuspension" => [
                        'MOROSO' => '1',
                        'PROFORMA' => '2',
                        'MOROSOS' => '3',
                        'NO_FACTURAR' => '4'
                ],
            'CLIENTE_STOCK_ID' => '620',
            'CLIENTE_DESPACHO_ID' => (int) env('CLIENTE_DESPACHO_ID', 0),
            'MAIL_CLIENTE_PROVISORIO' => 'impuestosBSA@grupoagg.com',
            'TOPE_DESCUENTO' => 20,
            'CATEGORIA_SECOS_ID' => 10,
            'SUBCATEGORIA_MAQUINA_ID' => 1,
            'SUBCATEGORIA_TIRA_ID' => 2,
            'EMPRESA_DEFAULT_ID' => 1,
            'DEUDORES_POR_VENTAS' => 114020008,
            'ANTICIPO_DE_CLIENTES' => 114020008,
            'ENVIA_MAIL_ALTA_CLIENTE_DEFINITIVO' => 'SI',
            'DESTINATARIO_ALTA_CLIENTE_DEFINITIVO' => ['impuestosBSA@grupoagg.com'],
            'SINCRONIZA_CLIMA_ANITA' => true,
            'permitir_cuit_duplicado' => $permitirCuitDuplicado,
            ];        
    break;
}