<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\TurnoOperativoLocal;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalTurnoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalTurnoCierreSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FacturacionLocalTurnoController extends Controller
{
    public function __construct(
        private readonly FacturacionLocalTurnoService $turnoService,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-turno-facturacion-local');

        $localId = (int) $request->input('local_id', 0);
        $q = TurnoOperativoLocal::query()
            ->with([
                'localVenta:id,codigo,nombre',
                'turnoLocal:id,codigo,nombre',
                'usuarioApertura:id,nombre',
                'usuarioCierre:id,nombre',
            ])
            ->orderByDesc('id');
        if ($localId > 0) {
            $q->where('local_venta_id', $localId);
        }
        $datas = $q->paginate(20)->appends($request->query());
        $locales = LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);

        return view('ventas.facturacion_local.turno.index', compact('datas', 'locales', 'localId'));
    }

    public function abrir(Request $request)
    {
        $this->assertFerli();
        if (! can('abrir-turno-facturacion-local', false)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => 'Sin permiso para abrir turno.'], 403);
            }
            can('abrir-turno-facturacion-local');
        }
        try {
            $local = LocalVenta::query()->findOrFail((int) $request->input('local_id'));
            $turno = $this->turnoService->abrir($local, [
                'fondo_inicial' => (float) $request->input('fondo_inicial', 0),
                'observacion' => $request->input('observacion'),
                'identificador_pc' => $request->input('identificador_pc'),
                'turno_local_id' => (int) $request->input('turno_local_id', 0),
            ]);
            session(['facturacion_local.local_id' => (int) $local->id]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'turno_id' => (int) $turno->id,
                    'redirect' => route('facturacion_local_pos', ['local_id' => $local->id]),
                ]);
            }

            return redirect()->route('facturacion_local_pos', ['local_id' => $local->id])
                ->with('mensaje', 'Turno #'.$turno->id.' abierto en '.$local->nombre);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function ver(int $id)
    {
        $this->assertFerli();
        $this->assertPuedeVerCierre();

        $turno = $this->turnoParaCierre($id);
        $resumen = FacturacionLocalTurnoCierreSupport::resumen($turno);
        $puedeCerrar = $turno->estaAbierto() && can('cerrar-turno-facturacion-local', false);
        $puedeMoverMedio = $turno->estaAbierto()
            && $turno->cierre_en === null
            && can('cambiar-medio-pago-facturacion-local', false);

        return view('ventas.facturacion_local.turno.ver', compact(
            'turno',
            'resumen',
            'puedeCerrar',
            'puedeMoverMedio',
        ));
    }

    public function facturasMedio(Request $request, int $id)
    {
        $this->assertFerli();
        $this->assertPuedeVerCierre();

        $turno = $this->turnoParaCierre($id);
        $soloNc = (int) $request->input('notas_credito', 0) === 1;
        $cuentacajaId = (int) $request->input('cuentacaja_id', 0);
        $facturas = FacturacionLocalTurnoCierreSupport::comprobantes($turno, $cuentacajaId, $soloNc);

        $titulo = 'Notas de crédito del turno';
        if (! $soloNc) {
            $resumen = FacturacionLocalTurnoCierreSupport::resumen($turno);
            $titulo = 'Facturas';
            foreach ($resumen['por_medio'] as $medio) {
                if ((int) $medio['cuentacaja_id'] === $cuentacajaId) {
                    $nombre = trim((string) $medio['codigo'].' '.(string) $medio['nombre']);
                    $titulo = 'Facturas — '.($nombre !== '' ? $nombre : 'Medio de pago');
                    break;
                }
            }
        }

        return response()->json([
            'ok' => true,
            'titulo' => $titulo,
            'facturas' => $facturas,
        ]);
    }

    public function cerrar(Request $request, int $id)
    {
        $this->assertFerli();
        can('cerrar-turno-facturacion-local');
        try {
            $turno = TurnoOperativoLocal::query()->findOrFail($id);
            $medios = $request->input('medios_contado', []);
            if (! is_array($medios)) {
                $medios = [];
            }
            $turno = $this->turnoService->cerrar(
                $turno,
                $medios,
                $request->input('observacion'),
                $request->filled('sobrante_faltante') ? (float) $request->input('sobrante_faltante') : null
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'turno' => $turno,
                    'pdf_url' => route('facturacion_local_turno_pdf', $turno->id),
                ]);
            }

            return redirect()->route('facturacion_local_turno_ver', $turno->id)
                ->with('mensaje', 'Turno #'.$turno->id.' cerrado');
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function pdf(int $id)
    {
        $this->assertFerli();
        $this->assertPuedeVerCierre();
        $turno = $this->turnoParaCierre($id);
        $resumen = FacturacionLocalTurnoCierreSupport::resumen($turno);

        $pdf = Pdf::loadView('ventas.facturacion_local.turno.comprobante', compact('turno', 'resumen'))
            ->setPaper('a4');

        return $pdf->stream('cierre_turno_local_'.$turno->id.'.pdf');
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }

    private function assertPuedeVerCierre(): void
    {
        if (can('listar-turno-facturacion-local', false) || can('cerrar-turno-facturacion-local', false)) {
            return;
        }

        can('listar-turno-facturacion-local');
    }

    private function turnoParaCierre(int $id): TurnoOperativoLocal
    {
        return TurnoOperativoLocal::query()
            ->with(['localVenta', 'turnoLocal', 'usuarioApertura', 'usuarioCierre'])
            ->findOrFail($id);
    }
}
