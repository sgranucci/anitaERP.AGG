<?php

$filtroEmpresa = filter_var(env('PROVEEDOR_FILTRO_EMPRESA', false), FILTER_VALIDATE_BOOLEAN);

switch (config('app.empresa')) {
    case 'AGG':
        return [
            'tipoalta' => 'PROVISORIA',
            'enviamailaprobacion' => 'S',
            'emailapruebaalta' => ['impuestosBSA@grupoagg.com'],
            // Default false: AGG no filtra ni exige empresa en el maestro.
            'filtro_empresa' => $filtroEmpresa,
        ];

    default:
        return [
            'tipoalta' => 'DEFINITIVA',
            'enviamailaprobacion' => 'N',
            'emailapruebaalta' => [''],
            // El Bierzo: activar con PROVEEDOR_FILTRO_EMPRESA=true en .env
            'filtro_empresa' => $filtroEmpresa,
        ];
}
