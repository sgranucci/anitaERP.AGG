<?php
// Constantes de arbol de aprobacion / maestro artículo

$filtroEmpresa = filter_var(env('ARTICULO_FILTRO_EMPRESA', false), FILTER_VALIDATE_BOOLEAN);

return [
    'ENVIA_MAIL_ALTA_ARTICULO' => 'SI',
    'DESTINATARIO_ALTA_ARTICULO' => [
        [
            'uso' => '1', // Gastronomia
            'destinatarios' => [
                'ddominguez@grupoagg.com',
                'ablanco@grupoagg.com',
                'egalarza@grupoagg.com',
            ],
        ],
    ],
    // Default false = AGG/Bierzo sin cambio de UI/filtros. Activar solo donde se filtre por empresa_id.
    'filtro_empresa' => $filtroEmpresa,
];
