<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Http\Controllers\Controller;
use App\Models\Caja\Bingo\CierreParcialTurnoBingo;
use App\Models\Caja\Bingo\TurnoOperativoBingo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Bingo\BingoCierreTurnoReporteSupport;
use App\Support\Caja\Bingo\BingoIdentificadorPc;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CierreTurnoBingoController extends Controller
{
    public function __construct(
        private readonly BingoCierreTurnoReporteSupport $reporteSupport,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-cierres-turno-bingo');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        $fechaDesde = (string) $request->input('fecha_desde', Carbon::today()->subDays(7)->format('Y-m-d'));
        $fechaHasta = (string) $request->input('fecha_hasta', Carbon::today()->format('Y-m-d'));
        $identificadorPc = (string) $request->input('identificador_pc', BingoIdentificadorPc::resolver($request));

        $query = TurnoOperativoBingo::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado', 'usuarioCierre', 'empresa'])
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->whereNotNull('cierre_en');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }
        if ($identificadorPc !== '') {
            $query->where('identificador_pc', $identificadorPc);
        }
        if ($fechaDesde !== '') {
            $query->whereDate('cierre_en', '>=', $fechaDesde);
        }
        if ($fechaHasta !== '') {
            $query->whereDate('cierre_en', '<=', $fechaHasta);
        }

        return view('caja.bingo.cierres_turno.index', [
            'datas' => $query->orderByDesc('cierre_en')->paginate(15)->appends($request->query()),
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'identificador_pc' => $identificadorPc,
        ]);
    }

    public function comprobanteCierre(int $id)
    {
        can('ver-comprobante-cierre-turno-bingo');
        $turno = TurnoOperativoBingo::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado', 'usuarioCierre', 'empresa', 'cierresParciales'])
            ->findOrFail($id);
        $this->assertEmpresaPermitida((int) $turno->empresa_id);

        $d = $this->reporteSupport->datosComprobanteCierre($turno);
        $view = view('caja.bingo.cierres_turno.comprobante', ['d' => $d])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'portrait');
        $pdf->loadHTML($view);

        return $pdf->stream('cierre_turno_bingo_'.$turno->id.'.pdf');
    }

    public function comprobanteParcial(int $id)
    {
        can('ver-comprobante-cierre-turno-bingo');
        $parcial = CierreParcialTurnoBingo::query()
            ->with(['turnoOperativo.turno', 'turnoOperativo.jornada', 'turnoOperativo.empresa'])
            ->findOrFail($id);
        $turno = $parcial->turnoOperativo;
        if ($turno !== null) {
            $this->assertEmpresaPermitida((int) $turno->empresa_id);
        }

        $d = $this->reporteSupport->datosComprobanteParcial($parcial);
        $view = view('caja.bingo.cierres_turno.comprobante_parcial', ['d' => $d])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'portrait');
        $pdf->loadHTML($view);

        return $pdf->stream('cierre_parcial_bingo_'.$parcial->id.'.pdf');
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
