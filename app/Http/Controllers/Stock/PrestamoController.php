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
use PDF;

class PrestamoController extends Controller
{
    public function __construct(
        private readonly PrestamoService $service,
        private readonly Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('listar-salida-bienes');
        $prestamos = $this->service->listar();
        $kpis = $this->service->resumenKpis();

        return view('stock.salida_bienes.index', compact('prestamos', 'kpis'));
    }

    public function crear()
    {
        can('crear-salida-bienes');
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old('empresa_id', $empresa_query->first()->id ?? null);

        return view('stock.salida_bienes.crear', compact('empresa_query', 'empresa_id'));
    }

    public function guardar(ValidacionPrestamo $request)
    {
        can('crear-salida-bienes');

        try {
            $prestamo = $this->service->guardar($request->validated());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error al crear la salida: '.$e->getMessage());
        }

        return redirect()->route('editar_salida_bienes', ['id' => $prestamo->id])
            ->with('mensaje', 'Salida creada en BORRADOR. Verificá los datos y confirmá el envío.');
    }

    public function editar(int $id)
    {
        can('editar-salida-bienes');
        $prestamo = $this->service->buscar($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old(
            'empresa_id',
            (int) (Depmae::query()->whereKey((int) $prestamo->deposito_origen_id)->value('empresa_id')
                ?? $empresa_query->first()->id)
        );
        $saldosOrigen = $this->saldosArticulosDelPrestamo($prestamo, (int) $prestamo->deposito_origen_id);
        $saldosDestino = $prestamo->deposito_destino_id
            ? $this->saldosArticulosDelPrestamo($prestamo, (int) $prestamo->deposito_destino_id)
            : [];

        return view('stock.salida_bienes.editar', compact(
            'prestamo', 'saldosOrigen', 'saldosDestino', 'empresa_query', 'empresa_id'
        ));
    }

    public function actualizar(ValidacionPrestamo $request, int $id)
    {
        can('actualizar-salida-bienes');

        try {
            $this->service->actualizar($id, $request->validated());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error al actualizar: '.$e->getMessage());
        }

        return redirect()->route('editar_salida_bienes', ['id' => $id])
            ->with('mensaje', 'Salida actualizada.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-salida-bienes');
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

        return redirect()->route('salida_bienes')->with('mensaje', 'Salida eliminada.');
    }

    public function ver(int $id)
    {
        can('listar-salida-bienes');
        $prestamo = $this->service->buscar($id);

        return view('stock.salida_bienes.ver', compact('prestamo'));
    }

    public function pdf(int $id)
    {
        can('listar-salida-bienes');
        $prestamo = $this->service->buscar($id);
        $pdf = PDF::loadView('stock.salida_bienes.pdf', compact('prestamo'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('salida_bienes_'.$prestamo->codigo.'.pdf');
    }

    public function confirmarEnvio(int $id)
    {
        can('confirmar-envio-salida-bienes');
        try {
            $this->service->confirmarEnvio($id);
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo confirmar el envío: '.$e->getMessage());
        }

        return redirect()->route('ver_salida_bienes', ['id' => $id])
            ->with('mensaje', 'Envío confirmado.');
    }

    public function aprobar(Request $request, int $id)
    {
        can('aprobar-recepcion-salida-bienes');
        try {
            $this->service->aprobarRecepcion($id, Auth::id(), $request->input('observaciones'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo aprobar: '.$e->getMessage());
        }

        return redirect()->route('ver_salida_bienes', ['id' => $id])
            ->with('mensaje', 'Recepción aprobada.');
    }

    public function rechazar(Request $request, int $id)
    {
        can('rechazar-recepcion-salida-bienes');
        try {
            $this->service->rechazarRecepcion($id, Auth::id(), $request->input('motivo_rechazo'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo rechazar: '.$e->getMessage());
        }

        return redirect()->route('ver_salida_bienes', ['id' => $id])
            ->with('mensaje', 'Salida rechazada; se reversó el stock de artículos si correspondía.');
    }

    public function devolver(Request $request, int $id)
    {
        can('devolver-salida-bienes');
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

        return redirect()->route('ver_salida_bienes', ['id' => $id])
            ->with('mensaje', 'Devolución registrada.');
    }

    public function cerrar(Request $request, int $id)
    {
        can('cerrar-salida-bienes');
        try {
            $this->service->cerrarSinDevolucion($id, $request->input('motivo'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo cerrar: '.$e->getMessage());
        }

        return redirect()->route('ver_salida_bienes', ['id' => $id])
            ->with('mensaje', 'Salida cerrada sin devolución.');
    }

    public function cancelar(Request $request, int $id)
    {
        can('cancelar-salida-bienes');
        try {
            $this->service->cancelar($id, $request->input('motivo'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo cancelar: '.$e->getMessage());
        }

        return redirect()->route('ver_salida_bienes', ['id' => $id])->with('mensaje', 'Salida cancelada.');
    }

    public function reenviarCorreo(int $id)
    {
        can('reenviar-correo-salida-bienes');
        try {
            $this->service->reenviarCorreoAprobacion($id);
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo reenviar el correo: '.$e->getMessage());
        }

        return back()->with('mensaje', 'Correo de aprobación reenviado.');
    }

    public function saldoArticulo(Request $request): JsonResponse
    {
        can('listar-salida-bienes');
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
            return response()->view('stock.salida_bienes.publico_resultado', [
                'titulo' => 'Enlace no válido',
                'detalle' => 'Este enlace ya fue utilizado, fue invalidado o expiró.',
                'tipo' => 'error',
            ], 410);
        }

        $prestamo = $this->service->buscar((int) $row->prestamo_id);

        return view('stock.salida_bienes.publico_ver', compact('prestamo'));
    }

    private function procesarAccionPublica(string $token, string $accion, ?string $motivo = null)
    {
        try {
            $row = $this->service->consumirToken($token, $accion);
        } catch (\Throwable $e) {
            return response()->view('stock.salida_bienes.publico_resultado', [
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
                $titulo = 'Salida aprobada';
                $detalle = 'Se registró la recepción y se notificó al solicitante.';
                $tipo = 'ok';
            } else {
                $this->service->rechazarRecepcion(
                    (int) $row->prestamo_id,
                    (int) $row->usuario_destino_id,
                    $motivo
                );
                $titulo = 'Salida rechazada';
                $detalle = 'Se reversó el stock en origen (si había artículos) y se notificó al solicitante.';
                $tipo = 'ok';
            }
        } catch (\Throwable $e) {
            return response()->view('stock.salida_bienes.publico_resultado', [
                'titulo' => 'Acción no procesada',
                'detalle' => $e->getMessage(),
                'tipo' => 'error',
            ], 422);
        }

        return view('stock.salida_bienes.publico_resultado', compact('titulo', 'detalle', 'tipo'));
    }

    private function saldosArticulosDelPrestamo(Prestamo $prestamo, int $depositoId): array
    {
        $ids = $prestamo->items->pluck('articulo_id')->filter()->unique()->all();
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
