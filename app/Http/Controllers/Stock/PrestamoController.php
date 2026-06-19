<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPrestamo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Prestamo;
use App\Models\Stock\Prestamo_Token;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Services\Stock\PrestamoService;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestamoController extends Controller
{
    public function __construct(
        private readonly PrestamoService $service,
        private readonly Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('listar-prestamo');
        $prestamos = $this->service->listar();

        return view('stock.prestamo.index', compact('prestamos'));
    }

    public function crear()
    {
        can('crear-prestamo');
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old('empresa_id', $empresa_query->first()->id ?? null);

        return view('stock.prestamo.crear', compact('empresa_query', 'empresa_id'));
    }

    public function guardar(ValidacionPrestamo $request)
    {
        can('crear-prestamo');

        try {
            $prestamo = $this->service->guardar($request->validated());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error al crear el préstamo: '.$e->getMessage());
        }

        return redirect('stock/prestamo/'.$prestamo->id.'/editar')
            ->with('mensaje', 'Préstamo creado en estado BORRADOR. Verificá los datos y luego confirmá el envío.');
    }

    public function editar(int $id)
    {
        can('editar-prestamo');
        $prestamo = $this->service->buscar($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old(
            'empresa_id',
            (int) (Depmae::query()->whereKey((int) $prestamo->deposito_origen_id)->value('empresa_id')
                ?? $empresa_query->first()->id)
        );
        $saldosOrigen = $this->saldosArticulosDelPrestamo($prestamo, (int) $prestamo->deposito_origen_id);
        $saldosDestino = $this->saldosArticulosDelPrestamo($prestamo, (int) $prestamo->deposito_destino_id);

        return view('stock.prestamo.editar', compact(
            'prestamo', 'saldosOrigen', 'saldosDestino', 'empresa_query', 'empresa_id'
        ));
    }

    public function actualizar(ValidacionPrestamo $request, int $id)
    {
        can('actualizar-prestamo');

        try {
            $this->service->actualizar($id, $request->validated());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error al actualizar: '.$e->getMessage());
        }

        return redirect('stock/prestamo/'.$id.'/editar')
            ->with('mensaje', 'Préstamo actualizado.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-prestamo');
        try {
            $this->service->eliminar($id);
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
            }

            return back()->with('mensaje', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect('stock/prestamo')->with('mensaje', 'Préstamo eliminado.');
    }

    public function ver(int $id)
    {
        can('listar-prestamo');
        $prestamo = $this->service->buscar($id);

        return view('stock.prestamo.ver', compact('prestamo'));
    }

    public function confirmarEnvio(int $id)
    {
        can('confirmar-envio-prestamo');
        try {
            $this->service->confirmarEnvio($id);
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo confirmar el envío: '.$e->getMessage());
        }

        return redirect('stock/prestamo/'.$id.'/ver')
            ->with('mensaje', 'Préstamo enviado. Se notificó al destinatario para que apruebe la recepción.');
    }

    public function aprobar(Request $request, int $id)
    {
        can('aprobar-recepcion-prestamo');
        try {
            $this->service->aprobarRecepcion($id, Auth::id(), $request->input('observaciones'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo aprobar: '.$e->getMessage());
        }

        return redirect('stock/prestamo/'.$id.'/ver')
            ->with('mensaje', 'Préstamo aprobado y stock actualizado en el depósito destino.');
    }

    public function rechazar(Request $request, int $id)
    {
        can('rechazar-recepcion-prestamo');
        try {
            $this->service->rechazarRecepcion($id, Auth::id(), $request->input('motivo_rechazo'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo rechazar: '.$e->getMessage());
        }

        return redirect('stock/prestamo/'.$id.'/ver')
            ->with('mensaje', 'Préstamo rechazado, se reversó la salida del depósito origen.');
    }

    public function devolver(Request $request, int $id)
    {
        can('devolver-prestamo');
        $devoluciones = $request->input('devoluciones', []);
        try {
            $this->service->registrarDevolucion(
                $id,
                is_array($devoluciones) ? $devoluciones : [],
                $request->input('observaciones'),
            );
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo registrar la devolución: '.$e->getMessage());
        }

        return redirect('stock/prestamo/'.$id.'/ver')
            ->with('mensaje', 'Devolución registrada.');
    }

    public function cancelar(Request $request, int $id)
    {
        can('cancelar-prestamo');
        try {
            $this->service->cancelar($id, $request->input('motivo'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo cancelar: '.$e->getMessage());
        }

        return redirect('stock/prestamo/'.$id.'/ver')->with('mensaje', 'Préstamo cancelado.');
    }

    public function reenviarCorreo(int $id)
    {
        can('reenviar-correo-prestamo');
        try {
            $this->service->reenviarCorreoAprobacion($id);
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo reenviar el correo: '.$e->getMessage());
        }

        return back()->with('mensaje', 'Correo de aprobación reenviado.');
    }

    public function saldoArticulo(Request $request): JsonResponse
    {
        can('listar-prestamo');
        $articuloId = (int) $request->input('articulo_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $depositoIds = array_filter(array_map('intval', (array) $request->input('depositos', [])));
        if ($articuloId <= 0 || empty($depositoIds)) {
            return response()->json(['saldos' => []]);
        }

        if ($empresaId > 0) {
            $depositoIds = array_values(array_filter(
                $depositoIds,
                fn (int $depId) => Depmae::existeParaEmpresa($depId, $empresaId)
            ));
        }

        $saldos = $this->saldoRepository->saldosArticuloPorDeposito($articuloId, $depositoIds);

        return response()->json(['saldos' => $saldos]);
    }

    /* ---------------------- Endpoints públicos por token ---------------------- */

    public function aprobarPublico(string $token)
    {
        return $this->procesarAccionPublica($token, Prestamo_Token::ACCION_APROBAR);
    }

    public function rechazarPublico(Request $request, string $token)
    {
        return $this->procesarAccionPublica($token, Prestamo_Token::ACCION_RECHAZAR, $request->input('motivo'));
    }

    public function verPublico(string $token)
    {
        $row = Prestamo_Token::where('token', $token)->first();
        if (! $row || ! $row->estaActivo()) {
            return response()->view('stock.prestamo.publico_resultado', [
                'titulo' => 'Enlace no válido',
                'detalle' => 'Este enlace ya fue utilizado, fue invalidado o expiró.',
                'tipo' => 'error',
            ], 410);
        }

        $prestamo = $this->service->buscar((int) $row->prestamo_id);

        return view('stock.prestamo.publico_ver', compact('prestamo'));
    }

    private function procesarAccionPublica(string $token, string $accion, ?string $motivo = null)
    {
        try {
            $row = $this->service->consumirToken($token, $accion);
        } catch (\Throwable $e) {
            return response()->view('stock.prestamo.publico_resultado', [
                'titulo' => 'Acción no procesada',
                'detalle' => $e->getMessage(),
                'tipo' => 'error',
            ], 410);
        }

        try {
            if ($accion === Prestamo_Token::ACCION_APROBAR) {
                $this->service->aprobarRecepcion(
                    (int) $row->prestamo_id,
                    (int) $row->usuario_destino_id,
                    'Aprobado por enlace de correo'
                );
                $titulo = 'Préstamo aprobado';
                $detalle = 'Se generó el ingreso al depósito destino y se notificó al solicitante.';
                $tipo = 'ok';
            } else {
                $this->service->rechazarRecepcion(
                    (int) $row->prestamo_id,
                    (int) $row->usuario_destino_id,
                    $motivo
                );
                $titulo = 'Préstamo rechazado';
                $detalle = 'Se reversó la salida en el depósito origen y se notificó al solicitante.';
                $tipo = 'ok';
            }
        } catch (\Throwable $e) {
            return response()->view('stock.prestamo.publico_resultado', [
                'titulo' => 'Acción no procesada',
                'detalle' => $e->getMessage(),
                'tipo' => 'error',
            ], 422);
        }

        return view('stock.prestamo.publico_resultado', compact('titulo', 'detalle', 'tipo'));
    }

    /**
     * Devuelve los artículos del préstamo (cargados como relación) ya
     * resueltos como mapa articulo_id => cantidad para el depósito dado.
     */
    private function saldosArticulosDelPrestamo(Prestamo $prestamo, int $depositoId): array
    {
        $ids = $prestamo->items->pluck('articulo_id')->unique()->all();
        if (empty($ids)) {
            return [];
        }

        $resultado = [];
        foreach ($ids as $articuloId) {
            $resultado[(int) $articuloId] = $this->saldoRepository->saldo((int) $articuloId, $depositoId);
        }

        return $resultado;
    }

}
