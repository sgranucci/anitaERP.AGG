<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Services\Compras\PagoproveedorEnvioMasivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagoproveedorEnvioMasivoController extends Controller
{
    public function __construct(
        private PagoproveedorEnvioMasivoService $service,
    ) {}

    public function index(Request $request): View
    {
        can('enviar-op-masivo-proveedor');

        return view('compras.pagoproveedor.envio_masivo', $this->service->consultar($request));
    }

    public function enviar(Request $request): RedirectResponse
    {
        can('enviar-op-masivo-proveedor');

        $request->validate([
            'empresa_id' => 'required|integer',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
            'medio' => 'nullable|string|max:20',
            'mensaje' => 'nullable|string|max:4000',
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
        ], [
            'ids.required' => 'Seleccione al menos una orden de pago.',
            'ids.min' => 'Seleccione al menos una orden de pago.',
        ]);

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $resultado = $this->service->enviarSeleccionadas(
            $request,
            array_map('intval', (array) $request->input('ids', [])),
            $request->input('mensaje')
        );

        $query = array_filter([
            'consultar' => 1,
            'empresa_id' => (int) $request->input('empresa_id'),
            'fecha_desde' => (string) $request->input('fecha_desde'),
            'fecha_hasta' => (string) $request->input('fecha_hasta'),
            'medio' => (string) $request->input('medio', 'ambas'),
        ], static fn ($v) => $v !== null && $v !== '');

        $enviadas = (int) ($resultado['enviadas'] ?? 0);
        $errores = $resultado['errores'] ?? [];
        $redirect = redirect()->route('pagoproveedor_envio_masivo', $query)
            ->with('resultado_envio', $resultado['detalle'] ?? []);

        if ($enviadas > 0 && $errores === []) {
            return $redirect->with('mensaje', 'Se enviaron '.$enviadas.' correo(s) a proveedores.');
        }
        if ($enviadas > 0) {
            return $redirect->with('mensaje_aviso', 'Se enviaron '.$enviadas.' correo(s). '.count($errores).' no se pudieron enviar.');
        }

        $primero = $errores[0]['mensaje'] ?? 'No se envió ningún correo.';

        return $redirect->with('mensaje_error', $primero);
    }
}
