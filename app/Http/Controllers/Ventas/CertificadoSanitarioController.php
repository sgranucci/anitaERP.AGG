<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Camion;
use App\Models\Ventas\CertificadoSanitario;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Zonavta;
use App\Services\Ventas\CertificadoSanitarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CertificadoSanitarioController extends Controller
{
    public function __construct(
        private CertificadoSanitarioService $service,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-certificado-sanitario');

        $datas = CertificadoSanitario::query()
            ->with(['camion', 'transporte'])
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->limit(200)
            ->get();

        return view('ventas.certificado_sanitario.index', compact('datas'));
    }

    public function crear(Request $request)
    {
        can('crear-certificado-sanitario');

        $camiones = Camion::query()->orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
        if ($camiones->isEmpty()) {
            app(\App\Repositories\Ventas\CamionRepositoryInterface::class)->all();
            $camiones = Camion::query()->orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
        }

        $transportes = Transporte::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
        $zonas = Zonavta::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']);

        $preview = null;
        $filtros = [
            'fecha' => $request->get('fecha', now()->toDateString()),
            'transporte_id' => $request->get('transporte_id'),
            'zonavta_id' => $request->get('zonavta_id'),
            'cliente_id' => $request->get('cliente_id'),
            'transporte_desde' => $request->get('transporte_desde'),
            'transporte_hasta' => $request->get('transporte_hasta'),
            'fallback_anita' => $request->boolean('fallback_anita', (bool) config('senasa.fallback_anita_pedido')),
        ];

        if ($request->boolean('consultar')) {
            $preview = $this->service->previewLineas($filtros);
        }

        return view('ventas.certificado_sanitario.crear', compact(
            'camiones',
            'transportes',
            'zonas',
            'preview',
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

        return view('ventas.certificado_sanitario.ver', compact('data'));
    }

    public function descargarXml($id, string $tipo)
    {
        can('listar-certificado-sanitario');
        $data = CertificadoSanitario::query()->findOrFail($id);
        $tipo = strtoupper($tipo) === 'S' ? 'S' : 'N';
        $path = $tipo === 'S' ? $data->xml_frio : $data->xml_sin_frio;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return back()->with('error', 'XML no disponible para este certificado.');
        }

        return Storage::disk('local')->download($path, basename($path), [
            'Content-Type' => 'application/xml',
        ]);
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
}
