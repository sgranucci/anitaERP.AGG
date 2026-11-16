<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\WhatsappEnvioLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappEnvioLogController extends Controller
{
    public function index(Request $request): View
    {
        can('listar-empresa-whatsapp');

        $estado = trim((string) $request->input('estado', ''));
        $query = WhatsappEnvioLog::query()
            ->with(['usuarios:id,nombre,usuario', 'empresas:id,nombre'])
            ->orderByDesc('id');

        if ($estado !== '') {
            $query->where('estado', $estado);
        }
        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', (int) $request->input('empresa_id'));
        }

        $filas = $query->paginate(30)->appends($request->query());

        return view('configuracion.whatsapp_envio_log.index', [
            'filas' => $filas,
            'estado' => $estado,
            'empresa_id' => (int) $request->input('empresa_id', 0),
        ]);
    }
}
