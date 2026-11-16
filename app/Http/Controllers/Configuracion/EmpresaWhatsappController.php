<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\EmpresaWhatsappCuenta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Configuracion\WhatsappEnvioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpresaWhatsappController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(): View
    {
        can('listar-empresa-whatsapp');

        $empresas = $this->empresaRepository->allFiltrado();
        $cuentas = EmpresaWhatsappCuenta::query()
            ->whereIn('empresa_id', $empresas->pluck('id'))
            ->get()
            ->keyBy('empresa_id');

        return view('configuracion.empresa_whatsapp.index', [
            'empresas' => $empresas,
            'cuentas' => $cuentas,
        ]);
    }

    public function editar(int $empresaId): View|RedirectResponse
    {
        can('editar-empresa-whatsapp');

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return redirect()->route('consultar_empresa_whatsapp')
                ->with('error', 'Empresa no permitida.');
        }

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cuenta = EmpresaWhatsappCuenta::query()->firstOrNew(['empresa_id' => $empresaId]);

        return view('configuracion.empresa_whatsapp.editar', [
            'empresa' => $empresa,
            'cuenta' => $cuenta,
        ]);
    }

    public function actualizar(Request $request, int $empresaId): RedirectResponse
    {
        can('actualizar-empresa-whatsapp');

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return redirect()->route('consultar_empresa_whatsapp')
                ->with('error', 'Empresa no permitida.');
        }

        $datos = $request->validate([
            'nombre' => 'nullable|string|max:120',
            'proveedor' => 'nullable|string|max:40',
            'phone_number_id' => 'nullable|string|max:80',
            'waba_id' => 'nullable|string|max:80',
            'access_token' => 'nullable|string|max:4000',
            'api_version' => 'nullable|string|max:20',
            'activo' => 'nullable|boolean',
        ]);

        $cuenta = EmpresaWhatsappCuenta::query()->firstOrNew(['empresa_id' => $empresaId]);
        $cuenta->nombre = $datos['nombre'] ?? $cuenta->nombre;
        $cuenta->proveedor = $datos['proveedor'] ?? 'meta_cloud';
        $cuenta->phone_number_id = $datos['phone_number_id'] ?? null;
        $cuenta->waba_id = $datos['waba_id'] ?? null;
        $cuenta->api_version = $datos['api_version'] ?? 'v21.0';
        $cuenta->activo = $request->boolean('activo');

        $token = trim((string) ($datos['access_token'] ?? ''));
        if ($token !== '') {
            $cuenta->access_token = $token;
        }

        $cuenta->empresa_id = $empresaId;
        $cuenta->save();

        return redirect()->route('consultar_empresa_whatsapp')
            ->with('mensaje', 'Cuenta WhatsApp actualizada. El canal opera solo si está activa y completa.');
    }

    public function enviarPrueba(Request $request, int $empresaId, WhatsappEnvioService $whatsapp): RedirectResponse
    {
        can('actualizar-empresa-whatsapp');

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return redirect()->route('consultar_empresa_whatsapp')
                ->with('error', 'Empresa no permitida.');
        }

        $usuarioId = (int) ($request->input('usuario_id') ?: auth()->id());
        if ($usuarioId <= 0) {
            return back()->with('error', 'Indicá un usuario destino para la prueba.');
        }

        $resultado = $whatsapp->enviarPrueba($empresaId, $usuarioId);

        return redirect()
            ->route('editar_empresa_whatsapp', $empresaId)
            ->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }
}
