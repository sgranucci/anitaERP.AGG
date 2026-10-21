<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Stock\ContaduriaInventarioWorkbookExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\ContaduriaInventarioReporteService;
use App\Services\Ventas\ContaduriaGastronomiaReporteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;

class ContaduriaGastronomiaReporteController extends Controller
{
    private const MENU_URL = 'ventas/gastronomia/reporte-contaduria';

    public function __construct(
        private readonly ContaduriaGastronomiaReporteService $descuentos,
        private readonly ContaduriaInventarioReporteService $inventario,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        [$mes, $mesNum, $anio] = $this->resolverPeriodo($request);

        $fechaCierre = trim((string) $request->input('fecha_cierre', ''));
        if ($fechaCierre === '') {
            $fechaCierre = Carbon::today()->format('Y-m-d');
        }

        $depositos = trim((string) $request->input('depositos', '1, 8'));
        if ($depositos === '') {
            $depositos = '1, 8';
        }

        return view('ventas.gastronomia.reporte_contaduria.index', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'mes' => $mes,
            'mes_num' => $mesNum,
            'anio' => $anio,
            'fecha_cierre' => $fechaCierre,
            'depositos' => $depositos,
            'origen_inventario' => $this->origenInventario($request),
        ]);
    }

    public function descargarDescuentos(Request $request)
    {
        $this->assertAccesoMenu();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        [$mes] = $this->resolverPeriodo($request, false);
        $this->assertAccesoEmpresa($empresaId);

        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return redirect()
                ->route('gastronomia_reporte_contaduria', $this->parametrosReporte($request))
                ->with('errores', ['Seleccione la empresa y el mes.']);
        }

        $empresaNombre = $this->etiquetaEmpresa($empresaId, $empresaQuery);
        $archivos = $this->descuentos->archivos($empresaId, $mes, $empresaNombre);

        $zipPath = tempnam(sys_get_temp_dir(), 'contaduria');
        if ($zipPath === false) {
            abort(500, 'No se pudo armar el archivo.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'No se pudo armar el archivo.');
        }

        foreach ($archivos as $archivo) {
            $zip->addFromString(
                $archivo['nombre'],
                Excel::raw($archivo['export'], ExcelWriter::XLSX),
            );
        }
        $zip->close();

        return response()
            ->download($zipPath, 'reporte_contaduria_descuentos_'.$mes.'.zip')
            ->deleteFileAfterSend(true);
    }

    public function descargarInventario(Request $request)
    {
        $this->assertAccesoMenu();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fecha = trim((string) $request->input('fecha_cierre', ''));
        $depositos = $this->normalizarDepositos((string) $request->input('depositos', '1, 8'));
        $origen = $this->origenInventario($request);
        $this->assertAccesoEmpresa($empresaId);

        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $depositos === '') {
            return redirect()
                ->route('gastronomia_reporte_contaduria', $this->parametrosReporte($request))
                ->with('errores', ['Seleccione la empresa, la fecha de cierre y los depósitos.']);
        }

        $armado = $this->inventario->armar([
            'empresa_id' => $empresaId,
            'fecha_desde' => $fecha,
            'fecha_hasta' => $fecha,
            'depositos_filtro' => $depositos,
            'solo_con_saldo' => true,
            'origen' => $origen,
        ]);

        if (($armado['error'] ?? null) !== null) {
            return redirect()
                ->route('gastronomia_reporte_contaduria', $this->parametrosReporte($request))
                ->with('errores', [$armado['error']]);
        }

        $empresaNombre = $this->etiquetaEmpresa($empresaId, $this->empresaRepository->allFiltrado());
        $codigos = array_map(static fn (array $dep) => $dep['codigo'], $armado['depositos']);
        $titulo = 'Inventario existencias finales';
        $subtitulo = $empresaNombre.' · Cierre '.Carbon::parse($fecha)->format('d/m/Y').' · Depósitos '.implode(', ', $codigos);

        return (new ContaduriaInventarioWorkbookExport(
            $armado,
            $titulo,
            $subtitulo,
            $empresaNombre,
            (string) ($armado['origen_etiqueta'] ?? ''),
        ))->download('reporte_contaduria_inventario_'.$fecha.'.xlsx');
    }

    /**
     * @return array{0:string,1:int,2:int}
     */
    private function resolverPeriodo(Request $request, bool $completarVacio = true): array
    {
        $hoy = Carbon::today();
        $crudo = trim((string) $request->input('mes', ''));
        $anio = (int) $request->input('anio', 0);
        $mesNum = 0;

        if (preg_match('/^(\d{4})-(\d{2})$/', $crudo, $partes)) {
            $anio = (int) $partes[1];
            $mesNum = (int) $partes[2];
        } elseif ($crudo !== '') {
            $mesNum = (int) $crudo;
        }

        $periodoValido = $mesNum >= 1 && $mesNum <= 12 && $anio >= 2000 && $anio <= 2100;
        if (! $periodoValido) {
            if (! $completarVacio) {
                return ['', 0, 0];
            }
            $mesNum = (int) $hoy->month;
            $anio = (int) $hoy->year;
        }

        return [sprintf('%04d-%02d', $anio, $mesNum), $mesNum, $anio];
    }

    /**
     * @return array<string, mixed>
     */
    private function parametrosReporte(Request $request): array
    {
        [$mes, $mesNum, $anio] = $this->resolverPeriodo($request, false);

        return [
            'empresa_id' => $request->input('empresa_id'),
            'mes' => $mesNum > 0 ? $mesNum : $mes,
            'anio' => $anio > 0 ? $anio : $request->input('anio'),
            'fecha_cierre' => $request->input('fecha_cierre'),
            'depositos' => $request->input('depositos'),
            'origen_inventario' => $request->input('origen_inventario'),
        ];
    }

    private function origenInventario(Request $request): string
    {
        return $request->input('origen_inventario') === 'saldo' ? 'saldo' : 'recuento';
    }

    private function normalizarDepositos(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        if (! str_contains($valor, ',') && ! str_contains($valor, ';')) {
            $valor = preg_replace('/\s+/', ',', $valor) ?? $valor;
        }

        $partes = preg_split('/[,;]+/', $valor) ?: [];
        $codigos = [];
        foreach ($partes as $parte) {
            $parte = trim((string) $parte);
            if ($parte !== '' && ! in_array($parte, $codigos, true)) {
                $codigos[] = $parte;
            }
        }

        return implode(',', $codigos);
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    private function assertAccesoMenu(): void
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return;
        }

        $rolId = (int) session()->get('rol_id');
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0 || ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            abort(403, 'No tiene acceso a este reporte.');
        }
    }

    private function etiquetaEmpresa(int $empresaId, $empresaQuery): string
    {
        if ($empresaId <= 0) {
            return '';
        }

        $empresa = $empresaQuery->firstWhere('id', $empresaId);

        return trim((string) ($empresa->nombre ?? ''));
    }
}
