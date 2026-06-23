<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

/*
| API precarga comprobantes proveedores (Facturas_scan → ERP).
| Montada con y sin prefijo /api (RouteServiceProvider).
|
| URL canónica prod (.210): http://10.20.30.210/api/comprobantes
| Legacy (sin vhost prod):   http://10.20.30.210/anitaERP/public/api/comprobantes
| Alias sin /api:            http://10.20.30.210/comprobantes
*/

Route::get(
    'empresas/{cuitProveedor}/orden-de-compra/{numeroOC}/tipo-comprobante/{tipo}/conceptos',
    [ApiController::class, 'listaConcepto']
);
Route::post('comprobantes', [ApiController::class, 'recibeComprobante']);
