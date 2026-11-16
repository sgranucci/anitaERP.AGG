<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Contable\ConciliacionBancariaExcepcion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ConciliacionBancariaService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaExcepcionSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Auth;
use Illuminate\Http\Request;

class ConciliacionBancariaExcepcionController extends Controller
{
    public function __construct(
        private readonly ConciliacionBancariaService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertPuedeWorklist();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresaQuery->count() === 1 ? (int) $empresaQuery->first()->id : 0);
        $cuentacajaId = (int) $request->input('cuentacaja_id', 0);
        $mes = max(1, min(12, (int) $request->input('mes', (int) date('n'))));
        $anio = max(2000, (int) $request->input('anio', (int) date('Y')));
        $estado = (string) $request->input('estado', 'abierta');
        $consultar = $request->boolean('consultar');

        $cuenta = $cuentacajaId > 0 ? Cuentacaja::query()->find($cuentacajaId) : null;
        $sync = null;
        $filas = collect();

        if ($consultar && $empresaId > 0 && $cuentacajaId > 0 && $this->empresaRepository->empresaIdPermitida($empresaId)) {
            $sync = ConciliacionBancariaExcepcionSupport::sincronizarDesdeAging(
                $this->service,
                $empresaId,
                $cuentacajaId,
                $mes,
                $anio,
            );
            $q = ConciliacionBancariaExcepcion::query()
                ->with(['asignado:id,nombre'])
                ->where('empresa_id', $empresaId)
                ->where('cuentacaja_id', $cuentacajaId)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->orderByDesc('dias')
                ->orderByDesc('importe');
            if ($estado !== '' && $estado !== 'todos') {
                if ($estado === 'abierta') {
                    $q->whereIn('estado', [
                        ConciliacionBancariaExcepcion::ESTADO_ABIERTA,
                        ConciliacionBancariaExcepcion::ESTADO_EN_CURSO,
                    ]);
                } else {
                    $q->where('estado', $estado);
                }
            }
            $filas = $q->paginate(50)->appends($request->query());
        }

        $usuarios = collect();
        if ($empresaId > 0) {
            $uq = UsuarioOperativoSupport::query()->orderBy('nombre');
            UsuarioOperativoSupport::aplicarFiltroEmpresa($uq, $empresaId);
            $usuarios = $uq->get(['id', 'nombre']);
        }

        return view('contable.conciliacion_bancaria.excepciones', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'cuentacaja_id' => $cuentacajaId,
            'cuenta' => $cuenta,
            'mes' => $mes,
            'anio' => $anio,
            'estado' => $estado,
            'consultar' => $consultar,
            'filas' => $filas,
            'sync' => $sync,
            'usuarios' => $usuarios,
            'filtrosQuery' => array_filter([
                'empresa_id' => $empresaId ?: null,
                'cuentacaja_id' => $cuentacajaId ?: null,
                'mes' => $mes,
                'anio' => $anio,
                'estado' => $estado,
                'consultar' => $consultar ? 1 : null,
            ]),
        ]);
    }

    public function asignar(Request $request)
    {
        $this->assertPuedeWorklist();
        $id = (int) $request->input('id');
        $usuarioId = (int) $request->input('usuario_id');
        $exc = ConciliacionBancariaExcepcion::query()->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $exc->empresa_id)) {
            abort(403);
        }
        $exc->asignado_usuario_id = $usuarioId > 0 ? $usuarioId : null;
        if ($usuarioId > 0 && $exc->estado === ConciliacionBancariaExcepcion::ESTADO_ABIERTA) {
            $exc->estado = ConciliacionBancariaExcepcion::ESTADO_EN_CURSO;
        }
        $exc->save();

        return back()->with('mensaje', 'Excepción actualizada.');
    }

    public function resolver(Request $request)
    {
        $this->assertPuedeWorklist();
        $id = (int) $request->input('id');
        $resolucion = (string) $request->input('resolucion', 'manual');
        $exc = ConciliacionBancariaExcepcion::query()->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $exc->empresa_id)) {
            abort(403);
        }
        $exc->estado = $resolucion === 'descartar'
            ? ConciliacionBancariaExcepcion::ESTADO_DESCARTADA
            : ConciliacionBancariaExcepcion::ESTADO_RESUELTA;
        $exc->resolucion = $resolucion;
        $exc->nota = mb_substr((string) $request->input('nota', ''), 0, 500) ?: null;
        $exc->resuelto_por_usuario_id = Auth::id() ? (int) Auth::id() : null;
        $exc->resuelto_at = now();
        $exc->save();

        return back()->with('mensaje', 'Excepción marcada como '.$exc->estado.'.');
    }

    private function assertPuedeWorklist(): void
    {
        if (can('listar-conciliacion-bancaria-excepciones', false)
            || can('listar-conciliacion-bancaria-aging', false)) {
            return;
        }
        can('listar-conciliacion-bancaria-excepciones');
    }
}
