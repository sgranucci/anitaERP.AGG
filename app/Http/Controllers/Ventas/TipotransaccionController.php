<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipotransaccion;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Services\Arca\ArcaTiposComprobanteCatalogoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class TipotransaccionController extends Controller
{
    public function __construct(
        private TipotransaccionRepositoryInterface $repository,
        private ArcaTiposComprobanteCatalogoService $arcaTiposComprobanteCatalogo,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        can('listar-tipos-transacciones');
        $datas = $this->repository->all(['V', 'U', 'C']);
        $operacionEnum = Tipotransaccion::$enumOperacion;
        $operacionStockEnum = Tipotransaccion::$enumOperacionStock;
        $signoEnum = Tipotransaccion::$enumSigno;
        $estadoEnum = Tipotransaccion::$enumEstado;

        return view('ventas.tipotransaccion.index', compact('datas', 'operacionEnum', 'operacionStockEnum', 'signoEnum', 'estadoEnum'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function crear()
    {
        can('crear-tipos-transacciones');

        return view('ventas.tipotransaccion.crear', $this->datosFormulario());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function guardar(ValidacionTipotransaccion $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/tipotransaccion')->with('mensaje', 'Tipo de transacción creada con exito');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('listar-tipos-transacciones', false) && ! can('editar-tipos-transacciones', false)) {
                abort(403, 'No tiene permiso para consultar el tipo de comprobante.');
            }
        } else {
            can('editar-tipos-transacciones');
        }

        $data = $this->repository->findOrFail($id);
        $puedeActualizar = can('actualizar-tipos-transacciones', false);

        return view('ventas.tipotransaccion.editar', array_merge(
            [
                'data' => $data,
                'solo_consulta' => $soloConsulta,
                'puede_actualizar' => $puedeActualizar,
            ],
            $this->datosFormulario($data)
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function actualizar(ValidacionTipotransaccion $request, $id)
    {
        can('actualizar-tipos-transacciones');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/tipotransaccion')->with('mensaje', 'Tipo de transacción actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipos-transacciones');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Tipos de comprobante AFIP (WSMTXCA o WSFE) para el ABM.
     */
    public function tiposCbteArca(Request $request): JsonResponse
    {
        if (! can('crear-tipos-transacciones', false) && ! can('editar-tipos-transacciones', false)) {
            abort(403, 'No tiene permiso');
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'refresh' => ['sometimes', 'boolean'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $webservice = $this->arcaTiposComprobanteCatalogo->webserviceParaEmpresa($empresaId);
        $diagnostico = $this->arcaTiposComprobanteCatalogo->diagnosticoCertificado($empresaId, $webservice);

        try {
            $this->arcaTiposComprobanteCatalogo->assertEmpresaConfigurada($empresaId, $webservice);
        } catch (Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'webservice' => $webservice,
                'diagnostico' => $diagnostico,
            ], 422);
        }

        $refresh = $request->boolean('refresh');

        try {
            $resultado = $this->arcaTiposComprobanteCatalogo->obtenerTiposComprobante($empresaId, $refresh);

            return response()->json([
                'ok' => true,
                'empresa_id' => $empresaId,
                'webservice' => $webservice,
                'webservice_etiqueta' => $this->arcaTiposComprobanteCatalogo->etiquetaWebservice($webservice),
                'diagnostico' => $diagnostico,
                'origen' => $resultado['origen'],
                'sincronizado_at' => $resultado['sincronizado_at'],
                'persistido' => (bool) ($resultado['persistido'] ?? false),
                'registros_guardados' => (int) ($resultado['registros_guardados'] ?? 0),
                'tipos' => $resultado['tipos'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $this->mensajeErrorArcaTiposCbte($e, $webservice, $diagnostico),
                'webservice' => $webservice,
                'diagnostico' => $diagnostico,
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(?Tipotransaccion $data = null): array
    {
        $operacionEnum = Tipotransaccion::$enumOperacion;
        $operacionStockEnum = Tipotransaccion::$enumOperacionStock;
        $signoEnum = Tipotransaccion::$enumSigno;
        $estadoEnum = Tipotransaccion::$enumEstado;
        $empresa_query = $this->empresasArcaQuery();
        $empresaArcaId = (int) old('empresa_arca_id', $this->empresaArcaDefaultId($empresa_query));
        $webserviceArca = $empresaArcaId > 0
            ? $this->arcaTiposComprobanteCatalogo->webserviceParaEmpresa($empresaArcaId)
            : '';
        $webserviceArcaEtiqueta = $webserviceArca !== ''
            ? $this->arcaTiposComprobanteCatalogo->etiquetaWebservice($webserviceArca)
            : '';
        $tiposCbteArca = [];
        $sincronizadoArcaTexto = null;

        if ($empresaArcaId > 0 && $webserviceArca !== '') {
            if ($this->arcaTiposComprobanteCatalogo->tieneCatalogoEnBd($empresaArcaId, $webserviceArca)) {
                $tiposCbteArca = $this->arcaTiposComprobanteCatalogo->listarDesdeBd($empresaArcaId, $webserviceArca);
                $ultima = $this->arcaTiposComprobanteCatalogo->ultimaSincronizacion($empresaArcaId, $webserviceArca);
                $sincronizadoArcaTexto = $ultima?->format('d/m/Y H:i');
            }
        }

        return compact(
            'operacionEnum',
            'operacionStockEnum',
            'signoEnum',
            'estadoEnum',
            'empresa_query',
            'empresaArcaId',
            'webserviceArca',
            'webserviceArcaEtiqueta',
            'tiposCbteArca',
            'sincronizadoArcaTexto'
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Empresa>
     */
    private function empresasArcaQuery()
    {
        $ids = $this->arcaTiposComprobanteCatalogo->empresasConCertificadoArca();
        if ($ids === []) {
            return collect();
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $diagnostico
     */
    private function mensajeErrorArcaTiposCbte(Exception $e, string $webservice, array $diagnostico = []): string
    {
        $msg = $e->getMessage();
        $wsaa = (string) ($diagnostico['wsaa_service'] ?? '');
        $certPath = (string) ($diagnostico['cert_path'] ?? '');
        $cuitCert = (string) ($diagnostico['cuit_certificado'] ?? '');
        $cuitEmp = (string) ($diagnostico['cuit_empresa'] ?? '');

        if (stripos($msg, 'Computador no autorizado') !== false) {
            $hint = 'El módulo afip.php usa WSAA «wsfe» y el CUIT del XML; este ABM usa «'.$wsaa.'» y empresa.nroinscripcion.';
            if ($cuitCert !== '' && $cuitEmp !== '' && $cuitCert !== $cuitEmp) {
                $hint .= " Certificado={$cuitCert}, empresa={$cuitEmp}.";
            }

            return $msg.' — '.$hint
                .' Si afip.php funciona, probá ARCA_TIPOS_CBTE_WEBSERVICE=wsfev1 en .env o habilitá el servicio «wsmtxca» en AFIP para el CUIT del certificado.';
        }

        if (stripos($msg, 'lista de relaciones') !== false || stripos($msg, 'ValidacionDeToken') !== false) {
            $etiqueta = $this->arcaTiposComprobanteCatalogo->etiquetaWebservice($webservice);
            $extra = $certPath !== '' ? " Cert: {$certPath}." : '';

            return $msg.' — '.$etiqueta.' (WSAA «'.$wsaa.'»). CUIT certificado='.$cuitCert.', CUIT empresa='.$cuitEmp.'.'
                .$extra
                .' afip.php envía el CUIT en el XML; ARCA SOAP usa empresa.nroinscripcion. Deben coincidir con el certificado.';
        }

        if (
            stripos($msg, 'Parsing WSDL') !== false
            || stripos($msg, 'failed to load external entity') !== false
            || stripos($msg, 'Couldn\'t load from') !== false
        ) {
            $env = (string) config('arca.env', 'homo');
            $subdir = $webservice === ArcaTiposComprobanteCatalogoService::WS_MTXCA ? 'mtxca' : 'wsfe';
            $archivo = $webservice === ArcaTiposComprobanteCatalogoService::WS_MTXCA
                ? 'MTXCAService.wsdl'
                : 'service.wsdl';
            $local = storage_path("app/arca/{$subdir}/wsdl/{$env}/{$archivo}");

            return $msg.' — Copie el WSDL en '.$local.' o defina ARCA_'.strtoupper($subdir).'_WSDL_LOCAL en .env.';
        }

        return $msg;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Empresa>  $empresa_query
     */
    private function empresaArcaDefaultId($empresa_query): int
    {
        if ($empresa_query->isEmpty()) {
            return 0;
        }

        $preferido = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);
        if ($empresa_query->contains('id', $preferido)) {
            return $preferido;
        }

        return (int) $empresa_query->first()->id;
    }
}
