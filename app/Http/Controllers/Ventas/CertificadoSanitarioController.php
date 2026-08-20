<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\CertificadoSanitarioListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Camion;
use App\Models\Ventas\CertificadoSanitario;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Zonavta;
use App\Repositories\Ventas\CamionRepositoryInterface;
use App\Services\Ventas\CertificadoSanitarioPdfService;
use App\Services\Ventas\CertificadoSanitarioService;
use App\Support\Pdf\DompdfPaperSupport;
use App\Support\Ventas\CertificadoSanitarioListadoFiltros;
use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioPreviewAplanado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CertificadoSanitarioController extends Controller
{
    public function __construct(
        private CertificadoSanitarioService $service,
        private CertificadoSanitarioPdfService $pdfService,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-certificado-sanitario');

        $filtros = CertificadoSanitarioListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->service->listar($filtros, true);
        $totalesListado = $this->service->totalesListado($filtros);
        $filtrosQuery = CertificadoSanitarioListadoFiltros::paraQueryString($filtros);

        return view('ventas.certificado_sanitario.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => CertificadoSanitarioListadoFiltros::CAMPOS,
            'totalesListado' => $totalesListado,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-certificado-sanitario');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CertificadoSanitarioListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->service->listar($filtros, false);
                $totalesListado = $this->service->totalesListado($filtros);
                $view = \View::make('ventas.certificado_sanitario.listado', compact('datas', 'totalesListado'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_certificado_sanitario';
                $pdf = \App::make('dompdf.wrapper');
                DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_LISTADO);
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new CertificadoSanitarioListadoExport($this->service))
                    ->parametros($filtros)
                    ->download('certificado_sanitario.xlsx');

            case 'CSV':
                return (new CertificadoSanitarioListadoExport($this->service))
                    ->parametros($filtros)
                    ->download('certificado_sanitario.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_certificado_sanitario', CertificadoSanitarioListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-certificado-sanitario');

        if (! Camion::query()->exists()) {
            app(CamionRepositoryInterface::class)->all();
        }

        $zonas = collect();
        $transporteSeleccionado = $this->resolverTransporteDesdeRequest($request);
        $zonaSeleccionada = $this->resolverZonavtaDesdeRequest($request);
        $clienteSeleccionado = $this->resolverClienteDesdeRequest($request);
        $camionSeleccionado = $this->resolverCamionDesdeRequest($request);

        $preview = null;
        $previewFilas = collect();
        $previewTotales = ['kilos' => 0.0, 'cajas' => 0.0, 'lineas' => 0, 'pedidos' => 0];
        $omitidosSinSenasa = collect();
        $filtros = [
            'fecha' => $request->get('fecha', now()->toDateString()),
            'transporte_id' => $transporteSeleccionado?->id,
            'zonavta_id' => $zonaSeleccionada?->id,
            'cliente_id' => $clienteSeleccionado?->id,
            'transporte_desde' => $request->get('transporte_desde'),
            'transporte_hasta' => $request->get('transporte_hasta'),
            'fallback_anita' => $request->boolean('fallback_anita', (bool) config('senasa.fallback_anita_pedido')),
        ];

        if ($request->boolean('consultar')) {
            $listado = $this->service->previewConsulta($filtros);
            $preview = $listado->lineas;
            $omitidosSinSenasa = $listado->omitidosSinSenasa;
            $previewFilas = CertificadoSanitarioPreviewAplanado::aplanar($preview);
            $previewTotales = CertificadoSanitarioPreviewAplanado::totales($preview);
        }

        return view('ventas.certificado_sanitario.crear', compact(
            'camionSeleccionado',
            'transporteSeleccionado',
            'zonaSeleccionada',
            'clienteSeleccionado',
            'zonas',
            'preview',
            'previewFilas',
            'previewTotales',
            'omitidosSinSenasa',
            'filtros'
        ));
    }

    public function guardar(Request $request)
    {
        can('crear-certificado-sanitario');

        $data = $request->validate([
            'fecha' => 'required|date',
            'camion_id' => 'required|exists:camion,id',
            'temperatura' => 'nullable|numeric',
            'nro_remito' => 'nullable|integer|min:0',
            'precinto' => 'nullable|max:15',
            'cantidad_precinto' => 'nullable|integer|min:0|max:99',
            'establecimiento_destino' => 'nullable|integer|min:0|max:9999',
            'abre_por_localidad' => 'nullable|boolean',
            'genera_web' => 'nullable|boolean',
            'transporte_id' => 'nullable|exists:transporte,id',
            'zonavta_id' => 'nullable|exists:zonavta,id',
            'cliente_id' => 'nullable|exists:cliente,id',
            'transporte_desde' => 'nullable|integer|min:0',
            'transporte_hasta' => 'nullable|integer|min:0',
            'fallback_anita' => 'nullable|boolean',
        ]);

        $data['abre_por_localidad'] = $request->boolean('abre_por_localidad');
        $data['genera_web'] = $request->boolean('genera_web', true);
        $data['fallback_anita'] = $request->boolean('fallback_anita', true);
        $transporte = $this->resolverTransporteDesdeRequest($request);
        $data['transporte_id'] = $transporte?->id;
        $zona = $this->resolverZonavtaDesdeRequest($request);
        $data['zonavta_id'] = $zona?->id;
        $cliente = $this->resolverClienteDesdeRequest($request);
        $data['cliente_id'] = $cliente?->id;

        try {
            $creados = $this->service->generar($data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = count($creados) === 1
            ? 'Certificado '.$creados[0]->etiqueta.' generado.'
            : count($creados).' certificados sanitarios generados.';

        return redirect()->route('consultar_certificado_sanitario')->with('mensaje', $msg);
    }

    public function ver($id)
    {
        can('listar-certificado-sanitario');
        $data = CertificadoSanitario::query()
            ->with(['camion', 'transporte', 'articulos.articulo', 'clientes.cliente', 'destinos'])
            ->findOrFail($id);
        $data = $this->service->regenerarXmlsSiFaltan($data);
        $xmls = $this->xmlsParaVista($data);

        return view('ventas.certificado_sanitario.ver', compact('data', 'xmls'));
    }

    public function descargarXml(Request $request, $id, string $tipo)
    {
        can('listar-certificado-sanitario');
        $data = CertificadoSanitario::query()->findOrFail($id);
        $data = $this->service->regenerarXmlsSiFaltan($data);
        $tipo = strtoupper($tipo) === 'S' ? 'S' : 'N';
        $path = $tipo === 'S' ? $data->xml_frio : $data->xml_sin_frio;
        if (! $this->service->xmlLegible($path)) {
            return redirect()
                ->route('consultar_certificado_sanitario')
                ->with('error', 'XML no disponible para este certificado.');
        }

        $nombre = basename($path);
        $contenido = Storage::disk('local')->get($path);

        if ($request->boolean('ver')) {
            return view('ventas.certificado_sanitario.xml', [
                'data' => $data,
                'tipo' => $tipo,
                'nombre' => $nombre,
                'contenido' => $contenido,
            ]);
        }

        try {
            return $this->service->descargarXmlZip($path);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('consultar_certificado_sanitario')
                ->with('error', $e->getMessage());
        }
    }

    public function pdfSolicitud(Request $request, $id)
    {
        can('listar-certificado-sanitario');

        return $this->pdfService->descargarSolicitud((int) $id, ! $request->boolean('descargar'));
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-certificado-sanitario');

        if (! $request->ajax()) {
            abort(404);
        }

        $data = CertificadoSanitario::query()->findOrFail($id);
        foreach ([$data->xml_frio, $data->xml_sin_frio] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
        $data->delete();

        return response()->json(['mensaje' => 'ok']);
    }

    private function resolverCamionDesdeRequest(Request $request): ?Camion
    {
        $id = (int) $request->input('camion_id', old('camion_id', 0));
        if ($id > 0) {
            $porId = Camion::query()->find($id);
            if ($porId) {
                return $porId;
            }
        }

        $codigo = trim((string) $request->input('camion_codigo', old('camion_codigo', '')));
        if ($codigo === '') {
            return null;
        }

        return app(CamionRepositoryInterface::class)->findPorCodigo($codigo);
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

    private function resolverClienteDesdeRequest(Request $request): ?Cliente
    {
        $id = (int) $request->input('cliente_id', old('cliente_id', 0));
        if ($id > 0) {
            $porId = Cliente::query()->find($id);
            if ($porId) {
                return $porId;
            }
        }

        $codigo = trim((string) $request->input('codigocliente', old('codigocliente', '')));
        if ($codigo === '') {
            return null;
        }

        return Cliente::query()
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo);
                $alt = ltrim($codigo, '0');
                if ($alt !== '' && $alt !== $codigo) {
                    $q->orWhere('codigo', $alt);
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function resolverZonavtaDesdeRequest(Request $request): ?Zonavta
    {
        $id = (int) $request->input('zonavta_id', old('zonavta_id', 0));
        if ($id > 0) {
            $porId = Zonavta::query()->find($id);
            if ($porId) {
                return $porId;
            }
        }

        $codigo = trim((string) $request->input('codigozonavta', old('codigozonavta', '')));
        if ($codigo === '') {
            return null;
        }

        return Zonavta::query()
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo);
                if ((string) (int) $codigo !== $codigo) {
                    $q->orWhere('codigo', (string) (int) $codigo);
                }
            })
            ->first();
    }

    /**
     * @return array{frio: ?string, sin_frio: ?string}
     */
    private function xmlsParaVista(CertificadoSanitario $data): array
    {
        $leer = static function (?string $path): ?string {
            if (! $path || ! Storage::disk('local')->exists($path)) {
                return null;
            }

            return Storage::disk('local')->get($path);
        };

        return [
            'frio' => $leer($data->xml_frio),
            'sin_frio' => $leer($data->xml_sin_frio),
        ];
    }
}
