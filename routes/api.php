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
