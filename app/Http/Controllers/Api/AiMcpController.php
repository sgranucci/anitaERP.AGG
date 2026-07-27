<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Ai\AiMcpBridgeSupport;
use Illuminate\Http\Request;

/**
 * Endpoints HTTP MCP-like: /api/ai/mcp/tools/list|call
 */
class AiMcpController extends Controller
{
    public function __construct(
        private AiMcpBridgeSupport $bridge,
    ) {}

    public function listTools(Request $request)
    {
        if ($deny = $this->autorizar($request)) {
            return $deny;
        }

        return response()->json($this->bridge->listarTools());
    }

    public function callTool(Request $request)
    {
        if ($deny = $this->autorizar($request)) {
            return $deny;
        }

        $name = (string) $request->input('name', $request->input('tool', ''));
        $arguments = $request->input('arguments', $request->input('params', []));
        if (! is_array($arguments)) {
            $arguments = [];
        }

        $resultado = $this->bridge->llamarTool($name, $arguments);
        $status = ($resultado['ok'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    private function autorizar(Request $request)
    {
        if (! $this->bridge->habilitado()) {
            return response()->json([
                'ok' => false,
                'error' => 'MCP deshabilitado (AI_MCP_HABILITADO / AI_MCP_TOKEN / kill-switch).',
            ], 503);
        }

        $header = (string) $request->header('Authorization', '');
        $token = null;
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $m)) {
            $token = trim($m[1]);
        } elseif ($request->filled('token')) {
            $token = (string) $request->input('token');
        }

        if (! $this->bridge->tokenValido($token)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        return null;
    }
}
