<?php
// Constantes de reporte total de pares
switch(config('app.empresa'))
{
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
            'MAIL_CLIENTE_PROVISORIO' => 'info@elbierzo.com.ar',
            'TOPE_DESCUENTO' => 20,
            'CATEGORIA_SECOS_ID' => 10,
            'SUBCATEGORIA_MAQUINA_ID' => 1,
            'SUBCATEGORIA_TIRA_ID' => 2,
            'DEUDORES_POR_VENTAS' => 113100000,
            'ANTICIPO_DE_CLIENTES' => 113100000,
            'EMPRESA_DEFAULT_ID' => 1,
            'ENVIA_MAIL_ALTA_CLIENTE_DEFINITIVO' => 'SI',
            'DESTINATARIO_ALTA_CLIENTE_DEFINITIVO' => ['luisav@elbierzo.com.ar', 'claudiam@elbierzo.com.ar', 'carolinal@elbierzo.com.ar']
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
            'MAIL_CLIENTE_PROVISORIO' => 'impuestosBSA@grupoagg.com',
            'TOPE_DESCUENTO' => 20,
            'CATEGORIA_SECOS_ID' => 10,
            'SUBCATEGORIA_MAQUINA_ID' => 1,
            'SUBCATEGORIA_TIRA_ID' => 2,
            'EMPRESA_DEFAULT_ID' => 1,
            'DEUDORES_POR_VENTAS' => 114020008,
            'ANTICIPO_DE_CLIENTES' => 114020008,
            'ENVIA_MAIL_ALTA_CLIENTE_DEFINITIVO' => 'SI',
            'DESTINATARIO_ALTA_CLIENTE_DEFINITIVO' => ['impuestosBSA@grupoagg.com']
            ];        
    break;
}