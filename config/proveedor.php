<?php
switch(config('app.empresa'))
{
    case "AGG":
        return [
            'tipoalta' => 'PROVISORIA',
            'enviamailaprobacion' => 'S',
            'emailapruebaalta' => ['impuestosBSA@grupoagg.com']
            ];
        break;

    default:
        return [
            'tipoalta' => 'DEFINITIVA',
            'enviamailaprobacion' => 'N',
            'emailapruebaalta' => ['']
            ];
        break;
}