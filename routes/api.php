<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
 * Puente MCP HTTP (Bearer AI_MCP_TOKEN). tools/list + tools/call.
 * curl -H "Authorization: Bearer $AI_MCP_TOKEN" -X POST $APP_URL/api/ai/mcp/tools/list
 */
Route::post('ai/mcp/tools/list', 'Api\AiMcpController@listTools');
Route::post('ai/mcp/tools/call', 'Api\AiMcpController@callTool');

require base_path('routes/precarga_proveedor_api.php');

/*
 * API v1 reportes definibles: Sanctum PAT (Bearer) y también sesión si el request
 * es first-party stateful (config/sanctum.php). No duplicar estas URIs en web.php.
 */
Route::get('v1/sueldos/reportes-definibles/openapi.json', 'Sueldos\ReporteSueldosDefinibleApiController@openapi')
    ->name('api_v1_reportes_sueldos_definibles_openapi');

Route::prefix('v1/sueldos/reportes-definibles')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function () {
        Route::get('/', 'Sueldos\ReporteSueldosDefinibleApiController@index')
            ->name('api_v1_reportes_sueldos_definibles');
        Route::get('/{id}', 'Sueldos\ReporteSueldosDefinibleApiController@show')->whereNumber('id');
        Route::get('/{id}/export/{formato}', 'Sueldos\ReporteSueldosDefinibleApiController@exportarApi')->whereNumber('id');
        Route::get('/{id}/datasets/{datasetId}', 'Sueldos\ReporteSueldosDefinibleApiController@datasetFilasApi')->whereNumber('id');
        Route::get('/{id}/webhooks', 'Sueldos\ReporteSueldosDefinibleApiController@listarWebhooksApi')->whereNumber('id');
        Route::post('/{id}/webhooks', 'Sueldos\ReporteSueldosDefinibleApiController@crearWebhookApi')->whereNumber('id');
        Route::delete('/{id}/webhooks/{wid}', 'Sueldos\ReporteSueldosDefinibleApiController@borrarWebhookApi')->whereNumber('id');
        Route::post('/{id}/suscripciones/{sid}/ejecutar', 'Sueldos\ReporteSueldosDefinibleApiController@forceRunSuscripcionApi')->whereNumber('id');
        Route::post('/{id}/variantes', 'Sueldos\ReporteSueldosDefinibleApiController@guardarVarianteApi')->whereNumber('id');
        Route::post('/{id}/ejecuciones', 'Sueldos\ReporteSueldosDefinibleApiController@encolar')->whereNumber('id');
        Route::get('/{id}/publicado', 'Sueldos\ReporteSueldosDefinibleApiController@publicado')->whereNumber('id');
        Route::post('/{id}/pivot', 'Sueldos\ReporteSueldosDefinibleApiController@pivot')->whereNumber('id');
        Route::get('/ejecuciones/{id}', 'Sueldos\ReporteSueldosDefinibleApiController@ejecucion')->whereNumber('id');
        Route::get('/ejecuciones/{id}/resultado', 'Sueldos\ReporteSueldosDefinibleApiController@resultado')->whereNumber('id');
    });
