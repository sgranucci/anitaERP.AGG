<?php

namespace App\Http\Controllers\Seguridad;

use App\Exports\Seguridad\IngresoProveedorListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionIngresoProveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Seguridad\IngresoProveedor;
use App\Models\Seguridad\IngresoProveedorArchivo;
use App\Models\Seguridad\IngresoProveedorArea;
use App\Models\Seguridad\IngresoProveedorMotivo;
use App\Models\Seguridad\IngresoProveedorPunto;
use App\Models\Seguridad\IngresoProveedorSector;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Seguridad\IngresoProveedorRepositoryInterface;
use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Seguridad\IngresoProveedorAutorizacionSupport;
use App\Support\Seguridad\IngresoProveedorEnlacePublicoSupport;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\IngresoProveedorListadoFiltros;
use App\Support\Seguridad\IngresoProveedorVinculoSupport;
use App\Support\Seguridad\IngresoProveedorVisibilidadSupport;
use App\Traits\Compras\ProveedorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IngresoProveedorController extends Controller
{
    public function __construct(
        private readonly IngresoProveedorRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-ingreso-proveedor');
        $filtros = $this->resolverFiltros($request);
        $datas = $this->repository->leeIngresoProveedor($filtros, true);

        return view('seguridad.ingreso_proveedor.index', array_merge(
            $this->catalogosIndex(),
            [
                'datas' => $datas,
                'filtros' => $filtros,
                'filtrosQuery' => IngresoProveedorListadoFiltros::paraQueryString($filtros),
                'camposFiltro' => IngresoProveedorListadoFiltros::CAMPOS,
                'empresa_query' => $this->empresaRepository->allFiltrado(),
                'bandejaPendientes' => false,
                'alcanceListado' => IngresoProveedorVisibilidadSupport::etiquetaAlcanceActivo(),
            ]
        ));
    }

    public function pendientes(Request $request)
    {
        if (! can('autorizar-ingreso-proveedor', false)) {
            can('listar-ingreso-proveedor');
        }

        return redirect()->to(url('mis-aprobaciones').'?fuente=ingreso_proveedor');
    }

    public function autorizar(Request $request, int $id)
    {
        can('autorizar-ingreso-proveedor');
        try {
            $ticket = IngresoProveedorAutorizacionSupport::autorizar($id);
        } catch (RuntimeException $e) {
            return $this->respuestaEstado($request, false, $e->getMessage(), $id);
        }

        return $this->respuestaEstado($request, true, 'Ticket #'.$ticket->id.' autorizado.', $id);
    }

    public function rechazar(Request $request, int $id)
    {
        can('autorizar-ingreso-proveedor');
        $motivo = trim((string) $request->input('motivo_rechazo', $request->input('comentario', '')));
        try {
            $ticket = IngresoProveedorAutorizacionSupport::rechazar($id, $motivo);
        } catch (RuntimeException $e) {
            return $this->respuestaEstado($request, false, $e->getMessage(), $id);
        }

        return $this->respuestaEstado($request, true, 'Ticket #'.$ticket->id.' rechazado.', $id);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ingreso-proveedor');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltros($request, $busqueda);
        $filtrosQuery = IngresoProveedorListadoFiltros::paraQueryString($filtros);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeIngresoProveedor($filtros, false);
                $view = \View::make('seguridad.ingreso_proveedor.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf = \PDF::loadHTML($view)->setPaper('legal', 'landscape');
                $pdf->save($path.'/listado_ingreso_proveedor.pdf');

                return $pdf->download('listado_ingreso_proveedor.pdf');
            case 'EXCEL':
                return Excel::download(
                    (new IngresoProveedorListadoExport($this->repository))->parametros($filtros),
                    'listado_ingreso_proveedor.xlsx'
                );
            case 'CSV':
                return Excel::download(
                    (new IngresoProveedorListadoExport($this->repository))->parametros($filtros),
                    'listado_ingreso_proveedor.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            default:
                return redirect()->route('ingreso_proveedor', $filtrosQuery);
        }
    }

    public function crear(Request $request)
    {
        can('crear-ingreso-proveedor');

        return view('seguridad.ingreso_proveedor.crear', $this->datosFormulario(null, $request));
    }

    public function guardar(ValidacionIngresoProveedor $request)
    {
        can('crear-ingreso-proveedor');
        $ticket = $this->repository->create($request->all());

        if ($request->wantsJson()) {
            return $this->respuestaVinculoJson($request, $ticket, 'Ticket de ingreso creado con éxito');
        }

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()->route('editar_ingreso_proveedor', [
                'id' => $ticket->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Ticket de ingreso creado con éxito');
        }

        return redirect()->route('ingreso_proveedor')->with('mensaje', 'Ticket de ingreso creado con éxito');
    }

    /**
     * Consulta pública desde el mail (hash, sin login). Mismo criterio que el árbol.
     */
    public function visualizar($id, $hash)
    {
        $ticket = IngresoProveedor::query()
            ->with([
                'personas.usuarioIngreso:id,nombre',
                'personas.usuarioEgreso:id,nombre',
                'archivos',
                'proveedores' => static fn ($q) => $q->withTrashed()->select('id', 'codigo', 'nombre'),
                'ordencompras:id,numeroordencompra,fecha,estadoordencompra,es_contrato',
                'motivos:id,nombre,codigo',
                'puntos:id,nombre',
                'sectores:id,nombre',
                'areas:id,nombre',
                'empresas:id,nombre',
                'usuarios:id,nombre,usuario',
                'usuarioAutorizo:id,nombre,usuario',
            ])
            ->find((int) $id);
        if (! $ticket || ! IngresoProveedorEnlacePublicoSupport::hashValido($ticket, (string) $hash)) {
            return redirect()->route('login')->with(
                'mensaje',
                'El enlace del ticket no es válido o ya no está disponible.'
            );
        }

        request()->merge([
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);

        return view('seguridad.ingreso_proveedor.visualizar', [
            'data' => $ticket,
            'hashPublico' => (string) $ticket->hashvisualizar,
        ]);
    }

    /**
     * Descarga pública de un adjunto (mismo hash del mail, sin login).
     */
    public function visualizarArchivo($id, $hash, $archivo): BinaryFileResponse
    {
        $ticket = IngresoProveedor::query()->with('archivos')->find((int) $id);
        if (! $ticket || ! IngresoProveedorEnlacePublicoSupport::hashValido($ticket, (string) $hash)) {
            abort(404);
        }

        $arch = $ticket->archivos->firstWhere('id', (int) $archivo);
        if (! $arch instanceof IngresoProveedorArchivo) {
            abort(404);
        }

        $disk = Storage::disk(IngresoProveedorArchivo::DISCO);
        $relativa = $arch->rutaRelativa();
        if (! $disk->exists($relativa)) {
            abort(404);
        }

        $path = $disk->path($relativa);
        $nombre = $arch->nombre_original ?: basename((string) $arch->nombre_archivo);
        $headers = [];
        if ($arch->mime) {
            $headers['Content-Type'] = $arch->mime;
        }

        if (request()->boolean('inline')) {
            $response = response()->file($path, $headers);
            ArchivoAdjuntoCacheSupport::aplicarAntiCacheNavegador($response);

            return $response;
        }

        return response()->download($path, $nombre, $headers);
    }

    public function consultar(Request $request, $id)
    {
        $request->merge([
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);

        return $this->editar($request, $id);
    }

    public function editar(Request $request, $id)
    {
        $esConsulta = IngresoProveedorVisibilidadSupport::requestEsConsulta($request);
        if (! $esConsulta && IngresoProveedorVisibilidadSupport::esDuenio((int) $id)
            && ! can('editar-ingreso-proveedor', false)) {
            $request->merge([
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
            $esConsulta = true;
        }
        if ($esConsulta) {
            IngresoProveedorVisibilidadSupport::abortSiNoPuedeAbrirEnConsulta((int) $id);
        } else {
            can('editar-ingreso-proveedor');
            IngresoProveedorVisibilidadSupport::abortSiNoAccesible((int) $id);
        }
        $data = $this->repository->findOrFail((int) $id);

        return view('seguridad.ingreso_proveedor.editar', array_merge(
            $this->datosFormulario($data, $request),
            [
                'data' => $data,
                'puedeActualizar' => can('actualizar-ingreso-proveedor', false),
            ]
        ));
    }

    public function actualizar(ValidacionIngresoProveedor $request, $id)
    {
        can('actualizar-ingreso-proveedor');
        IngresoProveedorVisibilidadSupport::abortSiNoAccesible((int) $id);
        $ticket = $this->repository->update($request->all(), (int) $id);

        if ($request->wantsJson()) {
            return $this->respuestaVinculoJson($request, $ticket, 'Ticket de ingreso actualizado con éxito');
        }

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()->route('editar_ingreso_proveedor', [
                'id' => (int) $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Ticket de ingreso actualizado con éxito');
        }

        return redirect()->route('ingreso_proveedor')->with('mensaje', 'Ticket de ingreso actualizado con éxito');
    }

    public function formularioModal(Request $request)
    {
        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            IngresoProveedorVisibilidadSupport::abortSiNoAccesible($id);
        }
        $data = $id > 0 ? $this->repository->findOrFail($id) : null;
        if ($data) {
            can('editar-ingreso-proveedor');
        } else {
            can('crear-ingreso-proveedor');
        }

        return view('seguridad.ingreso_proveedor.partials.form_modal', array_merge(
            $this->datosFormulario($data, $request),
            [
                'data' => $data,
                'enModal' => true,
                'proveedorBloqueado' => (int) $request->input('proveedor_id', $data->proveedor_id ?? 0) > 0,
            ]
        ));
    }

    public function grillaVinculada(Request $request)
    {
        if (! IngresoProveedorVinculoSupport::usuarioPuedeVerSolapa()) {
            abort(403);
        }

        $tickets = $this->ticketsParaVinculo($request);

        return view('seguridad.ingreso_proveedor.partials.solapa_vinculada_grilla', [
            'tickets' => $tickets,
        ]);
    }

    public function eliminar($id)
    {
        can('borrar-ingreso-proveedor');
        IngresoProveedorVisibilidadSupport::abortSiNoAccesible((int) $id);
        $this->repository->delete((int) $id);

        return redirect()->route('ingreso_proveedor')->with('mensaje', 'Ticket de ingreso eliminado');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(?IngresoProveedor $data = null, ?Request $request = null): array
    {
        $request = $request ?? request();
        $filtros = $this->resolverFiltros($request);
        $prefill = $this->prefillDesdeOrigen($request, $data);

        $soloConsulta = IngresoProveedorVisibilidadSupport::requestEsConsulta($request);

        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'motivos' => IngresoProveedorMotivo::query()->where('activo', true)->orderBy('nombre')->get(),
            'puntos' => IngresoProveedorPunto::query()->where('activo', true)->orderBy('nombre')->get(),
            'areas' => IngresoProveedorArea::query()->where('activo', true)->orderBy('nombre')->get(),
            'sectores' => IngresoProveedorSector::query()->where('activo', true)->orderBy('nombre')->get(),
            'estados' => IngresoProveedorEstados::META,
            'filtrosQuery' => QueryRetornoListado::retornoLinksDesdeFiltrosQuery(
                IngresoProveedorListadoFiltros::paraQueryString($filtros)
            ),
            'prefill' => $prefill,
            'soloConsulta' => $soloConsulta,
            'puedeAutorizarSeguridad' => can('autorizar-ingreso-proveedor', false),
        ];
    }

    private function respuestaEstado(Request $request, bool $ok, string $mensaje, int $ticketId)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => $ok,
                'mensaje' => $mensaje,
                'ticket_id' => $ticketId,
            ], $ok ? 200 : 422);
        }

        if (! $ok) {
            return back()->with('mensaje', $mensaje);
        }

        $params = ['id' => $ticketId];
        if ($request->input('origen') === 'modal_consulta' || $request->query('origen') === 'modal_consulta') {
            $params['origen'] = 'modal_consulta';
            $params['vista'] = 'consulta';
        }

        return redirect()->route('editar_ingreso_proveedor', $params)->with('mensaje', $mensaje);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return IngresoProveedorListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }

    /**
     * @return array{empresa_id:?int,proveedor_id:?int,ordencompra_id:?int,proveedor:?object}
     */
    private function prefillDesdeOrigen(Request $request, ?IngresoProveedor $data): array
    {
        if ($data) {
            $proveedorId = (int) ($data->proveedor_id ?: $request->input('proveedor_id', 0));
            $ocId = (int) ($data->ordencompra_id ?: $request->input('ordencompra_id', 0));

            return [
                'empresa_id' => $data->empresa_id ?: ((int) $request->input('empresa_id', 0) ?: null),
                'proveedor_id' => $proveedorId ?: null,
                'ordencompra_id' => $ocId ?: null,
                'proveedor' => $data->proveedores ?: $this->resolverProveedor($proveedorId),
                'ordencompra' => $data->ordencompras ?: $this->resolverOrdencompra($ocId),
            ];
        }

        $proveedorId = (int) $request->input('proveedor_id', 0);
        $ocId = (int) $request->input('ordencompra_id', 0);

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0) ?: null,
            'proveedor_id' => $proveedorId ?: null,
            'ordencompra_id' => $ocId ?: null,
            'proveedor' => $this->resolverProveedor($proveedorId),
            'ordencompra' => $this->resolverOrdencompra($ocId),
        ];
    }

    private function resolverProveedor(int $proveedorId): ?Proveedor
    {
        if ($proveedorId <= 0) {
            return null;
        }

        return Proveedor::withTrashed()->find($proveedorId);
    }

    private function resolverOrdencompra(int $ordencompraId): ?Ordencompra
    {
        if ($ordencompraId <= 0) {
            return null;
        }

        return Ordencompra::query()
            ->select([
                'id', 'numeroordencompra', 'fecha', 'estadoordencompra', 'es_contrato',
                'contrato_exige_ingresos', 'contrato_vigencia_desde', 'contrato_vigencia_hasta',
                'proveedor_id', 'empresa_id',
            ])
            ->find($ordencompraId);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogosIndex(): array
    {
        return [
            'sectores' => IngresoProveedorSector::query()->where('activo', true)->orderBy('nombre')->get(),
            'areas' => IngresoProveedorArea::query()->where('activo', true)->orderBy('nombre')->get(),
            'estados' => IngresoProveedorEstados::META,
        ];
    }

    public function buscarProveedorRapido(Request $request)
    {
        if (! can('crear-ingreso-proveedor', false) && ! can('editar-ingreso-proveedor', false)) {
            can('crear-ingreso-proveedor');
        }

        $nombre = trim((string) $request->input('nombre', ''));
        $cuit = preg_replace('/\D+/', '', (string) $request->input('cuit', ''));
        if ($nombre === '' && $cuit === '') {
            return response()->json(['ok' => true, 'items' => []]);
        }

        $query = Proveedor::query()
            ->select(['id', 'codigo', 'nombre', 'nroinscripcion'])
            ->whereIn('estado', ProveedorTrait::$estadosHabilitadosOperacion);

        $empresaId = (int) $request->input('empresa_id', 0);
        if (config('proveedor.filtro_empresa') && $empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        $query->where(function ($q) use ($nombre, $cuit) {
            if ($nombre !== '') {
                $q->where('nombre', 'like', '%'.$nombre.'%')
                    ->orWhere('codigo', 'like', '%'.$nombre.'%')
                    ->orWhere('nroinscripcion', 'like', '%'.$nombre.'%');
            }
            if ($cuit !== '') {
                $q->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), ' ', ''), '.', '') LIKE ?",
                    ['%'.$cuit.'%']
                );
            }
        });

        $items = $query->orderBy('nombre')->limit(20)->get()->map(static function (Proveedor $p) {
            return [
                'id' => (int) $p->id,
                'codigo' => (string) $p->codigo,
                'nombre' => (string) $p->nombre,
                'cuit' => (string) ($p->nroinscripcion ?? ''),
            ];
        })->values();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function consultaContrato(Request $request)
    {
        if (! can('crear-ingreso-proveedor', false) && ! can('editar-ingreso-proveedor', false)) {
            can('crear-ingreso-proveedor');
        }

        $texto = trim((string) $request->input('busqueda', $request->input('texto', '')));
        $proveedorId = (int) $request->input('proveedor_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $query = IngresoProveedorVinculoSupport::queryContratosActivos(
            $proveedorId > 0 ? $proveedorId : null,
            $empresaId > 0 ? $empresaId : null
        )->with(['proveedores:id,codigo,nombre', 'empresas:id,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(40);

        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('numeroordencompra', 'like', '%'.$texto.'%')
                    ->orWhereHas('proveedores', function ($p) use ($texto) {
                        $p->where('nombre', 'like', '%'.$texto.'%')
                            ->orWhere('codigo', 'like', '%'.$texto.'%');
                    });
            });
        }

        $contratos = $query->get();

        return view('seguridad.ingreso_proveedor.partials.tabla_consulta_contrato', [
            'contratos' => $contratos,
        ]);
    }

    public function resolverContrato(Request $request)
    {
        if (! can('crear-ingreso-proveedor', false) && ! can('editar-ingreso-proveedor', false)) {
            can('crear-ingreso-proveedor');
        }

        $id = (int) $request->input('id', 0);
        $numero = trim((string) $request->input('numero', $request->input('codigo', '')));
        $proveedorId = (int) $request->input('proveedor_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $query = IngresoProveedorVinculoSupport::queryContratosActivos(
            $proveedorId > 0 ? $proveedorId : null,
            $empresaId > 0 ? $empresaId : null
        )->with(['proveedores:id,codigo,nombre']);

        $oc = $id > 0
            ? $query->whereKey($id)->first()
            : ($numero !== '' ? (clone $query)->where('numeroordencompra', $numero)->first() : null);

        if (! $oc) {
            return response()->json(['ok' => false, 'mensaje' => 'No hay un contrato activo con ese número.'], 404);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $oc->id,
            'numero' => (string) $oc->numeroordencompra,
            'proveedor_id' => (int) ($oc->proveedor_id ?? 0),
            'proveedor_codigo' => (string) ($oc->proveedores->codigo ?? ''),
            'proveedor_nombre' => (string) ($oc->proveedores->nombre ?? ''),
            'estado' => (string) $oc->estadoordencompra,
            'exige_ingresos' => (bool) $oc->contrato_exige_ingresos,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, IngresoProveedor>
     */
    private function ticketsParaVinculo(Request $request)
    {
        $ocId = (int) $request->input('ordencompra_id', 0);
        if ($ocId > 0) {
            return IngresoProveedorVinculoSupport::ticketsDeOc($ocId);
        }
        $proveedorId = (int) $request->input('proveedor_id', 0);
        if ($proveedorId > 0) {
            return IngresoProveedorVinculoSupport::ticketsDeProveedor($proveedorId);
        }

        return collect();
    }

    private function respuestaVinculoJson(Request $request, IngresoProveedor $ticket, string $mensaje)
    {
        $tickets = $this->ticketsParaVinculo($request);

        return response()->json([
            'ok' => true,
            'mensaje' => $mensaje,
            'ticket_id' => (int) $ticket->id,
            'cantidad' => $tickets->count(),
            'html' => view('seguridad.ingreso_proveedor.partials.solapa_vinculada_grilla', [
                'tickets' => $tickets,
            ])->render(),
        ]);
    }
}
