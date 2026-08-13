<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\CertificadoSenasaSurmarListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\CertificadoSenasaSurmar;
use App\Models\Ventas\Camion;
use App\Models\Ventas\Transporte;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\CertificadoSenasaSurmarService;
use App\Support\Pdf\DompdfPaperSupport;
use App\Support\Stock\CertificadoSenasaSurmarListadoFiltros;
use App\Support\Stock\SurmarSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CertificadoSenasaSurmarController extends Controller
{
    public function __construct(
        private readonly CertificadoSenasaSurmarService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    private function assertSurmar(): void
    {
    }

    public function index(Request $request)
    {
        can('listar-certificado-senasa-surmar');
        $this->assertSurmar();
        $filtros = CertificadoSenasaSurmarListadoFiltros::resolverDesdeRequest($request);
        $coleccion = $this->service->listar($filtros, true);
        $filtrosQuery = CertificadoSenasaSurmarListadoFiltros::paraQueryString($filtros);

        return view('stock.certificado_senasa_surmar.index', [
            'coleccion' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => CertificadoSenasaSurmarListadoFiltros::camposFiltro(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-certificado-senasa-surmar');
        $this->assertSurmar();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CertificadoSenasaSurmarListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->service->listar($filtros, false);
                $view = \View::make('stock.certificado_senasa_surmar.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_certificado_senasa_surmar';
                $pdf = \App::make('dompdf.wrapper');
                DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_LISTADO);
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new CertificadoSenasaSurmarListadoExport($this->service))
                    ->parametros($filtros)
                    ->download('certificado_senasa_surmar.xlsx');

            case 'CSV':
                return (new CertificadoSenasaSurmarListadoExport($this->service))
                    ->parametros($filtros)
                    ->download('certificado_senasa_surmar.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('certificado_senasa_surmar', CertificadoSenasaSurmarListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-certificado-senasa-surmar');
        $this->assertSurmar();
        return view('stock.certificado_senasa_surmar.crear', [
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'camiones' => Camion::query()->orderBy('codigo')->get(['id', 'codigo', 'dominio', 'cantidad_precinto']),
            'transporteSeleccionado' => $this->resolverTransporteDesdeRequest($request),
            'punto_emision' => (int) config('arca_wsremcarne.defaults.punto_emision', 1),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-certificado-senasa-surmar');

        $this->assertSurmar();
        $data = $request->validate([
            'fecha' => 'required|date',
            'camion_id' => 'required|integer|min:1',
            'transporte_id' => 'nullable|integer|min:1',
            'cliente_id' => 'required|integer|min:1',
            'precinto' => 'nullable|string|max:15',
            'cantidad_precinto' => 'nullable|integer|min:0',
            'temperatura' => 'nullable|numeric',
            'establecimiento_destino' => 'nullable|integer|min:0',
            'punto_emision' => 'nullable|integer|min:1',
            'observacion' => 'nullable|string|max:2000',
            'genera_web' => 'nullable|boolean',
            'genera_remito' => 'nullable|boolean',
        ]);
        $data['genera_web'] = $request->boolean('genera_web');
        $data['genera_remito'] = $request->boolean('genera_remito');
        $transporte = $this->resolverTransporteDesdeRequest($request);
        $data['transporte_id'] = $transporte?->id;

        try {
            $cert = $this->service->iniciar($data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('cargar_certificado_senasa_surmar', $cert->id)
            ->with('mensaje', 'Certificado provisorio '.$cert->etiqueta.'. Picá ítems con etiquetas; cada línea se graba al confirmar.');
    }

    public function cargar(int $id)
    {
        can('editar-certificado-senasa-surmar');
        $this->assertSurmar();
        $cert = $this->service->buscar($id);

        $lineas = $cert->articulos
            ->map(fn ($l) => $this->service->lineaPayload($l))
            ->values()
            ->all();

        return view('stock.certificado_senasa_surmar.cargar', [
            'cert' => $cert,
            'lineas' => $lineas,
            'editable' => $cert->esEditable(),
            'empresa_id' => SurmarSupport::EMPRESA_ID,
        ]);
    }

    public function apiGuardarLinea(Request $request, int $id)
    {
        can('editar-certificado-senasa-surmar');

        $this->assertSurmar();
        $data = $request->validate([
            'linea_id' => 'nullable|integer|min:1',
            'articulo_id' => 'required|integer|min:1',
            'etiqueta_ids' => 'required|array|min:1',
            'etiqueta_ids.*' => 'integer|min:1',
            'kilos' => 'nullable|numeric|min:0.0001',
            'cajas' => 'nullable|numeric|min:0',
            'piezas' => 'nullable|numeric|min:0',
            'tropa' => 'nullable|integer|min:0',
            'cert_tercero' => 'nullable|string|max:20',
            'partida' => 'nullable|integer|min:0',
        ]);

        try {
            $result = $this->service->guardarLineaProvisoria($id, $data);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true] + $result);
    }

    public function apiEliminarLinea(int $id, int $lineaId)
    {
        can('editar-certificado-senasa-surmar');

        $this->assertSurmar();
        try {
            $this->service->eliminarLinea($id, $lineaId);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function apiResolverEtiqueta(Request $request)
    {
        can('editar-certificado-senasa-surmar');

        $this->assertSurmar();
        $codigo = trim((string) $request->input('codigo', $request->input('etiqueta_id', '')));
        if ($codigo === '') {
            return response()->json(['ok' => false, 'errors' => ['etiqueta' => ['Indique ID o código Anita.']]], 422);
        }

        try {
            $payload = $this->service->resolverEtiqueta($codigo);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'etiqueta' => $payload]);
    }

    public function confirmar(int $id)
    {
        can('confirmar-certificado-senasa-surmar');

        $this->assertSurmar();
        try {
            $cert = $this->service->confirmar($id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $msg = 'Certificado '.$cert->etiqueta.' confirmado.';
        if ($cert->cod_remito) {
            $msg .= ' Remito AFIP '.$cert->cod_remito.'.';
        }

        return redirect()
            ->route('cargar_certificado_senasa_surmar', $cert->id)
            ->with('mensaje', $msg);
    }

    public function anular(int $id)
    {
        can('anular-certificado-senasa-surmar');

        $this->assertSurmar();
        try {
            $cert = $this->service->anular($id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('certificado_senasa_surmar')
            ->with('mensaje', 'Certificado '.$cert->etiqueta.' anulado.');
    }

    public function descargarXml(int $id)
    {
        can('listar-certificado-senasa-surmar');
        $this->assertSurmar();
        $cert = $this->service->buscar($id);
        if (! $cert->xml_path || ! Storage::disk('local')->exists($cert->xml_path)) {
            return back()->with('error', 'No hay XML SENASA generado para este certificado.');
        }

        return Storage::disk('local')->download(
            $cert->xml_path,
            basename($cert->xml_path),
            ['Content-Type' => 'application/xml']
        );
    }

    private function resolverTransporteDesdeRequest(Request $request): ?Transporte
    {
        $id = (int) $request->input('transporte_id', old('transporte_id', 0));
        if ($id > 0) {
            $porId = Transporte::query()->find($id);
            if ($porId) {
                return $porId;
            }
        }

        $codigo = trim((string) $request->input('codigotransporte', old('codigotransporte', '')));
        if ($codigo === '') {
            return null;
        }

        return Transporte::query()->where('codigo', $codigo)->first();
    }
}
