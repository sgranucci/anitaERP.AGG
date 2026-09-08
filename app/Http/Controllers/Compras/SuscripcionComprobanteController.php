<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Suscripcion_Comprobante;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\SuscripcionComprobanteService;
use App\Support\Compras\SuscripcionSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuscripcionComprobanteController extends Controller
{
    public function __construct(
        private SuscripcionComprobanteService $comprobanteService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-suscripcion');

        $empresaId = (int) $request->input('empresa_id');
        $ownerId = (int) $request->input('owner_usuario_id');
        $area = trim((string) $request->input('area', ''));

        // El dueño solo ve los suyos salvo que configure/concilie.
        if (! can('configurar-suscripcion', false) && ! can('conciliar-suscripcion', false)) {
            $ownerId = (int) Auth::id();
        }

        $pendientes = $this->comprobanteService->listarPendientes(
            $empresaId > 0 ? $empresaId : null,
            $ownerId > 0 ? $ownerId : null,
            $area !== '' ? $area : null
        );

        return view('compras.suscripcion.comprobante.index', [
            'pendientes' => $pendientes,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresa_id' => $empresaId,
            'owner_usuario_id' => $ownerId,
            'area' => $area,
            'areas' => SuscripcionSupport::areas(),
            'puedeFiltrarOwner' => can('configurar-suscripcion', false) || can('conciliar-suscripcion', false),
        ]);
    }

    public function subir(Request $request, int $id)
    {
        can('listar-suscripcion');

        $comp = Suscripcion_Comprobante::query()->with('ordencompras')->findOrFail($id);
        if (! $this->comprobanteService->puedeGestionar($comp)) {
            abort(403);
        }

        $request->validate([
            'archivo' => 'required|file|max:10240|mimes:pdf,png,jpg,jpeg',
        ]);

        $resultado = $this->comprobanteService->subir(
            $comp,
            $request->file('archivo'),
            (int) Auth::id()
        );

        $redirect = $request->input('retorno') === 'ficha'
            ? redirect()->route('ver_suscripcion', $comp->ordencompra_id)
            : redirect()->route('comprobantes_pendientes_suscripcion', $request->only(['empresa_id', 'owner_usuario_id', 'area']));

        return $resultado['ok']
            ? $redirect->with('mensaje', $resultado['mensaje'])
            : $redirect->with('error', $resultado['mensaje']);
    }

    public function noAplica(Request $request, int $id)
    {
        can('configurar-suscripcion');

        $comp = Suscripcion_Comprobante::query()->findOrFail($id);
        $resultado = $this->comprobanteService->marcarNoAplica(
            $comp,
            (int) Auth::id(),
            $request->input('observacion')
        );

        $redirect = $request->input('retorno') === 'ficha'
            ? redirect()->route('ver_suscripcion', $comp->ordencompra_id)
            : redirect()->route('comprobantes_pendientes_suscripcion', $request->only(['empresa_id', 'owner_usuario_id', 'area']));

        return $resultado['ok']
            ? $redirect->with('mensaje', $resultado['mensaje'])
            : $redirect->with('error', $resultado['mensaje']);
    }

    public function descargar(int $id): BinaryFileResponse
    {
        can('listar-suscripcion');

        $comp = Suscripcion_Comprobante::query()->with('ordencompras')->findOrFail($id);
        if (! $this->comprobanteService->puedeGestionar($comp)
            && ! can('listar-suscripcion', false)) {
            abort(403);
        }

        $ruta = $comp->rutaArchivo();
        if (! $ruta || ! is_file($ruta)) {
            abort(404);
        }

        return response()->download($ruta, $comp->archivo_nombre);
    }
}
