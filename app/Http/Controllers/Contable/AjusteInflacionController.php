<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\AjusteInflacionConfiguracion;
use App\Models\Contable\AjusteInflacionCorrida;
use App\Models\Contable\AjusteInflacionCuenta;
use App\Models\Contable\AjusteInflacionIndice;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Tipoasiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\AjusteInflacionIndiceService;
use App\Services\Contable\AjusteInflacionService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AjusteInflacionController extends Controller
{
    public function __construct(
        private readonly AjusteInflacionService $ajusteService,
        private readonly AjusteInflacionIndiceService $indiceService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-ajuste-inflacion');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresas->count() === 1) {
            $empresaId = (int) $empresas->first()->id;
        }
        $this->assertEmpresaPermitida($empresaId);

        $configuracion = null;
        $cuentasConfiguradas = collect();
        $corridas = AjusteInflacionCorrida::query()->where('id', '<', 0)->paginate(10);
        if ($empresaId > 0) {
            $configuracion = AjusteInflacionConfiguracion::query()
                ->with(['cuentaRecpam', 'centrocostoRecpam', 'tipoasiento'])
                ->where('empresa_id', $empresaId)
                ->first();
            $cuentasConfiguradas = AjusteInflacionCuenta::query()
                ->with('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->get()
                ->sortBy(fn ($fila) => (string) ($fila->cuentacontable?->codigo ?? ''))
                ->values();
            $corridas = AjusteInflacionCorrida::query()
                ->with(['asiento', 'usuario', 'confirmadoPor', 'indiceCierre'])
                ->where('empresa_id', $empresaId)
                ->orderByDesc('fecha_cierre')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'corridas_page')
                ->appends($request->query());
        }

        $corridaSeleccionada = null;
        $detalles = null;
        $corridaId = (int) $request->input('corrida_id', 0);
        if ($corridaId > 0 && $empresaId > 0) {
            $corridaSeleccionada = AjusteInflacionCorrida::query()
                ->with(['asiento', 'indiceCierre'])
                ->where('empresa_id', $empresaId)
                ->findOrFail($corridaId);
            $detalles = $corridaSeleccionada->detalles()
                ->with(['cuentacontable', 'centrocosto', 'indiceOrigen'])
                ->orderBy('periodo_origen')
                ->orderBy('cuentacontable_id')
                ->paginate(50, ['*'], 'detalle_page')
                ->appends($request->query());
        }

        return view('contable.ajuste_inflacion.index', [
            'empresa_query' => $empresas,
            'empresa_id' => $empresaId,
            'configuracion' => $configuracion,
            'cuentas_configuradas' => $cuentasConfiguradas,
            'indices' => AjusteInflacionIndice::query()->with('usuario')->orderByDesc('periodo')->limit(24)->get(),
            'corridas' => $corridas,
            'corrida_seleccionada' => $corridaSeleccionada,
            'detalles' => $detalles,
            'puede_configurar' => can('configurar-ajuste-inflacion', false),
            'puede_importar_indices' => can('importar-indices-ajuste-inflacion', false),
            'puede_simular' => can('simular-ajuste-inflacion', false),
            'puede_confirmar' => can('confirmar-ajuste-inflacion', false),
        ]);
    }

    public function inicializar(Request $request)
    {
        can('configurar-ajuste-inflacion');
        $request->validate(['empresa_id' => 'required|integer|min:1']);
        $empresaId = (int) $request->input('empresa_id');
        $this->assertEmpresaPermitida($empresaId);

        try {
            $resultado = $this->ajusteService->inicializarConfiguracionDesdeUltimoAj($empresaId);
        } catch (\Throwable $e) {
            return $this->volver($empresaId)->with('error', $e->getMessage());
        }

        return $this->volver($empresaId)->with(
            'mensaje',
            'Configuración inicializada: '.($resultado['cuentas_detectadas'] ?? 0)
            .' cuentas tomadas del último AJ'
            .(isset($resultado['fecha_aj']) ? ' ('.$resultado['fecha_aj'].')' : '')
            .'.'
        );
    }

    public function guardarConfiguracion(Request $request)
    {
        can('configurar-ajuste-inflacion');
        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'cuenta_recpam_codigo' => 'required|string|max:50',
            'centrocosto_recpam_codigo' => 'nullable|string|max:50',
        ]);
        $empresaId = (int) $request->input('empresa_id');
        $this->assertEmpresaPermitida($empresaId);

        $cuenta = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', trim((string) $request->input('cuenta_recpam_codigo')))
            ->where('tipocuenta', '1')
            ->first();
        if (! $cuenta) {
            return $this->volver($empresaId)->with('error', 'No existe la cuenta RECPAM imputable indicada para la empresa.');
        }

        $centrocostoId = null;
        $codigoCentro = trim((string) $request->input('centrocosto_recpam_codigo', ''));
        if ($codigoCentro !== '') {
            $centrocostoId = Centrocosto::query()->where('codigo', $codigoCentro)->value('id');
            if ($centrocostoId === null) {
                return $this->volver($empresaId)->with('error', 'No existe el centro de costo RECPAM indicado.');
            }
        }

        $tipoId = Tipoasiento::query()->where('abreviatura', 'AJ')->value('id');
        if ($tipoId === null) {
            return $this->volver($empresaId)->with('error', 'No existe el tipo de asiento AJ.');
        }

        AjusteInflacionConfiguracion::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'cuentacontable_recpam_id' => (int) $cuenta->id,
                'centrocosto_recpam_id' => $centrocostoId,
                'tipoasiento_id' => (int) $tipoId,
                'activo' => true,
            ]
        );

        return $this->volver($empresaId)->with('mensaje', 'Configuración de RECPAM actualizada.');
    }

    public function agregarCuenta(Request $request)
    {
        can('configurar-ajuste-inflacion');
        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'cuenta_codigo' => 'required|string|max:50',
        ]);
        $empresaId = (int) $request->input('empresa_id');
        $this->assertEmpresaPermitida($empresaId);

        $cuenta = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', trim((string) $request->input('cuenta_codigo')))
            ->where('tipocuenta', '1')
            ->first();
        if (! $cuenta) {
            return $this->volver($empresaId)->with('error', 'No existe una cuenta imputable con ese código.');
        }

        AjusteInflacionCuenta::query()->updateOrCreate(
            ['empresa_id' => $empresaId, 'cuentacontable_id' => (int) $cuenta->id],
            ['activo' => true, 'metodo_anticuacion' => 'movimientos_mensuales']
        );

        return $this->volver($empresaId)->with('mensaje', 'Cuenta incorporada al ajuste por inflación.');
    }

    public function quitarCuenta(Request $request, int $id)
    {
        can('configurar-ajuste-inflacion');
        $fila = AjusteInflacionCuenta::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $fila->empresa_id);
        $fila->activo = false;
        $fila->save();

        return $this->volver((int) $fila->empresa_id)->with('mensaje', 'Cuenta excluida de futuras simulaciones.');
    }

    public function guardarIndice(Request $request)
    {
        can('importar-indices-ajuste-inflacion');
        $request->validate([
            'empresa_id' => 'nullable|integer|min:1',
            'periodo' => 'required|date_format:Y-m',
            'valor' => 'required|numeric|min:0.00000001',
            'fuente' => 'nullable|string|max:120',
            'provisorio' => 'nullable|boolean',
        ]);

        try {
            $this->indiceService->guardar(
                (string) $request->input('periodo'),
                (float) $request->input('valor'),
                (int) auth()->id(),
                (string) $request->input('fuente', 'FACPCE RT 6'),
                $request->boolean('provisorio')
            );
        } catch (\Throwable $e) {
            return $this->volver((int) $request->input('empresa_id', 0))->with('error', $e->getMessage());
        }

        return $this->volver((int) $request->input('empresa_id', 0))->with('mensaje', 'Índice guardado correctamente.');
    }

    public function importarIndices(Request $request)
    {
        can('importar-indices-ajuste-inflacion');
        $request->validate([
            'empresa_id' => 'nullable|integer|min:1',
            'archivo_indices' => 'required|file|max:5120',
        ]);

        try {
            $resultado = $this->indiceService->importarCsv(
                $request->file('archivo_indices'),
                (int) auth()->id()
            );
        } catch (\Throwable $e) {
            return $this->volver((int) $request->input('empresa_id', 0))->with('error', $e->getMessage());
        }

        $mensaje = 'Índices importados: '.$resultado['creados'].' nuevos y '.$resultado['actualizados'].' actualizados.';
        if ($resultado['errores'] !== []) {
            $mensaje .= ' Errores: '.implode(' | ', array_slice($resultado['errores'], 0, 10));
        }

        return $this->volver((int) $request->input('empresa_id', 0))
            ->with($resultado['errores'] === [] ? 'mensaje' : 'error', $mensaje);
    }

    public function simular(Request $request)
    {
        can('simular-ajuste-inflacion');
        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'periodo_desde' => 'required|date_format:Y-m',
            'fecha_cierre' => 'required|date',
            'observacion' => 'nullable|string|max:2000',
        ]);
        $empresaId = (int) $request->input('empresa_id');
        $this->assertEmpresaPermitida($empresaId);

        try {
            $corrida = $this->ajusteService->simular(
                $empresaId,
                (string) $request->input('periodo_desde'),
                (string) $request->input('fecha_cierre'),
                (int) auth()->id(),
                $request->input('observacion')
            );
        } catch (\Throwable $e) {
            return $this->volver($empresaId)->with('error', $e->getMessage());
        }

        return redirect()->route('ajuste_inflacion', ['empresa_id' => $empresaId, 'corrida_id' => $corrida->id])
            ->with('mensaje', 'Simulación generada. Revise el papel de trabajo antes de confirmar.');
    }

    public function confirmar(Request $request, int $id)
    {
        can('confirmar-ajuste-inflacion');
        $corrida = AjusteInflacionCorrida::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $corrida->empresa_id);

        try {
            $corrida = $this->ajusteService->confirmar($id, (int) auth()->id());
        } catch (\Throwable $e) {
            return $this->volver((int) $corrida->empresa_id, $id)->with('error', $e->getMessage());
        }

        return $this->volver((int) $corrida->empresa_id, $id)
            ->with('mensaje', 'Ajuste confirmado y asiento AJ '.$corrida->asiento?->numeroasiento.' generado.');
    }

    public function anular(Request $request, int $id)
    {
        can('simular-ajuste-inflacion');
        $corrida = AjusteInflacionCorrida::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $corrida->empresa_id);

        try {
            $this->ajusteService->anular($id, (int) auth()->id());
        } catch (\Throwable $e) {
            return $this->volver((int) $corrida->empresa_id, $id)->with('error', $e->getMessage());
        }

        return $this->volver((int) $corrida->empresa_id)->with('mensaje', 'Simulación anulada.');
    }

    public function exportarCsv(Request $request, int $id): StreamedResponse
    {
        can('listar-ajuste-inflacion');
        $corrida = $this->corridaParaExportar($id);
        $this->assertEmpresaPermitida((int) $corrida->empresa_id);

        $nombre = 'papel_trabajo_ajuste_inflacion_'.$corrida->empresa_id.'_'.$corrida->fecha_cierre->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($corrida): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Empresa', $corrida->empresa?->nombre], ';');
            fputcsv($out, ['Período desde', $corrida->periodo_desde->format('d/m/Y')], ';');
            fputcsv($out, ['Fecha cierre', $corrida->fecha_cierre->format('d/m/Y')], ';');
            fputcsv($out, ['Índice cierre', $corrida->indiceCierre?->valor], ';');
            fputcsv($out, [], ';');
            fputcsv($out, [
                'Período origen', 'Cuenta', 'Descripción', 'Centro costo',
                'Saldo origen', 'Índice origen', 'Coeficiente', 'Reexpresado', 'Ajuste',
            ], ';');
            foreach ($corrida->detalles as $detalle) {
                fputcsv($out, [
                    $detalle->periodo_origen->format('Y-m'),
                    $detalle->cuentacontable?->codigo,
                    $detalle->cuentacontable?->nombre,
                    $detalle->centrocosto?->codigo,
                    $detalle->saldo_origen,
                    $detalle->indiceOrigen?->valor,
                    $detalle->coeficiente,
                    $detalle->importe_reexpresado,
                    $detalle->ajuste,
                ], ';');
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportarPdf(Request $request, int $id)
    {
        can('listar-ajuste-inflacion');
        $corrida = $this->corridaParaExportar($id);
        $this->assertEmpresaPermitida((int) $corrida->empresa_id);

        $registroLogo = (object) ['nombreempresa' => (string) ($corrida->empresa?->nombre ?? '')];
        $pdf = \PDF::loadView('contable.ajuste_inflacion.listado', [
            'corrida' => $corrida,
            'logos' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([$registroLogo])),
        ])->setPaper('legal', 'landscape');

        return $pdf->download(
            'papel_trabajo_ajuste_inflacion_'.$corrida->empresa_id.'_'.$corrida->fecha_cierre->format('Ymd').'.pdf'
        );
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }

    private function corridaParaExportar(int $id): AjusteInflacionCorrida
    {
        return AjusteInflacionCorrida::query()->with([
            'empresa',
            'indiceCierre',
            'detalles.cuentacontable',
            'detalles.centrocosto',
            'detalles.indiceOrigen',
        ])->findOrFail($id);
    }

    private function volver(int $empresaId, ?int $corridaId = null)
    {
        $parametros = ['empresa_id' => $empresaId > 0 ? $empresaId : null];
        if ($corridaId !== null) {
            $parametros['corrida_id'] = $corridaId;
        }

        return redirect()->route('ajuste_inflacion', $parametros);
    }
}
