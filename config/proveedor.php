<?php
// Constantes de reporte total de pares
switch(config('app.empresa'))
{
    case "AGG":
        return [
            'tipoalta' => 'PROVISORIA',
            'enviamailaprobacion' => 'S',
            'emailapruebaalta' => ['impuestosBSA@grupoagg.com']
            ];
        break;
}