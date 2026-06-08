<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionArbolaprobacion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Sala\RequisicionSalaEstado;
use App\Support\Compras\OrdencompraEstados;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_NivelRepositoryInterface;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ArbolaprobacionController extends Controller
{
    private $arbolaprobacionRepository;

    private $arbolaprobacion_nivelRepository;

    private $arbolaprobacion_movimientoRepository;

    private $empresaRepository;

    private $centrocostoRepository;

    private $monedaRepository;

    private $arbolaprobacionService;

    public function __construct(ArbolaprobacionRepositoryInterface $arbolaprobacionrepository,
        Arbolaprobacion_NivelRepositoryInterface $arbolaprobacion_nivelrepository,
        Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository,
        CentrocostoRepositoryInterface $centrocostorepository,
        EmpresaRepositoryInterface $empresarepository,
        MonedaRepositoryInterface $monedarepository,
        ArbolaprobacionService $arbolaprobacionservice)
    {
        $this->arbolaprobacionRepository = $arbolaprobacionrepository;
        $this->arbolaprobacion_nivelRepository = $arbolaprobacion_nivelrepository;
        $this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->empresaRepository = $empresarepository;
        $this->monedaRepository = $monedarepository;
        $this->arbolaprobacionService = $arbolaprobacionservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('lista-arbol-de-aprobacion');

        $datas = $this->arbolaprobacionRepository->all();

        return view('configuracion.arbolaprobacion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crea-arbol-de-aprobacion');
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $tipoarbol_enum = Arbolaprobacion::$enumTipoArbol;
        $recordatorio_enum = Arbolaprobacion::$enumRecordatorio;
        $estado_enum = Arbolaprobacion::$enumEstado;

        $requisicion_estados_arbol_enum = Requisicion_Estado::estadosArbolRequisicionConfigurables();
        $requisicion_sala_estados_arbol_enum = RequisicionSalaEstado::estadosArbolConfigurables();
        $ordencompra_estados_arbol_enum = OrdencompraEstados::estadosArbolConfigurables();

        return view('configuracion.arbolaprobacion.crear', compact('empresa_query', 'centrocosto_query', 'moneda_query',
            'tipoarbol_enum', 'recordatorio_enum', 'estado_enum', 'requisicion_estados_arbol_enum', 'requisicion_sala_estados_arbol_enum', 'ordencompra_estados_arbol_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionArbolaprobacion $request)
    {
        DB::beginTransaction();
        try {
            $arbolaprobacion = $this->arbolaprobacionRepository->create($request->all());

            if ($arbolaprobacion == 'Error') {
                throw new Exception('Error en grabacion');
            }

            // Guarda tablas asociadas
            if ($arbolaprobacion) {
                $arbolaprobacion_nivel = $this->arbolaprobacion_nivelRepository->create($request->all(), $arbolaprobacion->id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }

        return redirect('configuracion/arbolaprobacion')->with('mensaje', 'Arbol de Aprobación creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('edita-arbol-de-aprobacion');

        $data = $this->arbolaprobacionRepository->find($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $tipoarbol_enum = Arbolaprobacion::$enumTipoArbol;
        $recordatorio_enum = Arbolaprobacion::$enumRecordatorio;
        $estado_enum = Arbolaprobacion::$enumEstado;

        $requisicion_estados_arbol_enum = Requisicion_Estado::estadosArbolRequisicionConfigurables();
        $requisicion_sala_estados_arbol_enum = RequisicionSalaEstado::estadosArbolConfigurables();
        $ordencompra_estados_arbol_enum = OrdencompraEstados::estadosArbolConfigurables();

        return view('configuracion.arbolaprobacion.editar', compact('data', 'empresa_query', 'centrocosto_query', 'moneda_query',
            'tipoarbol_enum', 'recordatorio_enum', 'estado_enum', 'requisicion_estados_arbol_enum', 'requisicion_sala_estados_arbol_enum', 'ordencompra_estados_arbol_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionArbolaprobacion $request, $id)
    {
        can('actualiza-arbol-de-aprobacion');

        DB::beginTransaction();
        try {
            $arbolaprobacion = $this->arbolaprobacionRepository->update($request->all(), $id);

            if (! $arbolaprobacion) {
                throw new Exception('Error en grabacion');
            }

            // Guarda tablas asociadas
            if ($arbolaprobacion) {
                $arbolaprobacion_nivel = $this->arbolaprobacion_nivelRepository->update($request->all(), $id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }

        return redirect('configuracion/arbolaprobacion')->with('mensaje', 'Arbol de Aprobación actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borra-arbol-de-aprobacion');

        if ($request->ajax()) {
            $fl_borro = false;
            if ($this->arbolaprobacionRepository->delete($id)) {
                $fl_borro = true;
            }

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    // Aprobar comprobantes

    public function aprobar($tipocomprobante, $comprobante_id, $hash)
    {
        $flEncontro = false;
        $aprobacion_id = null;
        $usuario_id = null;

        // Busca hash de aprobacion en movimientos del arbol
        $arbolaprobacion_movimiento = collect();
        switch ($tipocomprobante) {
            case 'OV':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
                break;
            case 'RE':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($comprobante_id);
                break;
            case 'RS':
                $arbolaprobacion_movimiento = app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)
                    ->findPorRequisicionSala((int) $comprobante_id);
                break;
            case 'OC':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdencompra($comprobante_id);
                break;
        }

        foreach ($arbolaprobacion_movimiento as $movimiento) {
            if ($movimiento->estado == Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre']) {
                if ($hash == $movimiento->hashaprobacion) {
                    $flEncontro = true;
                    $aprobacion_id = $movimiento->id;
                    $usuario_id = $movimiento->destinatariousuario_id;
                    break;
                }
            }
        }

        if ($flEncontro && $tipocomprobante === 'RE') {
            $datos = $this->arbolaprobacionService->portalDatosRequisicionPorHash((int) $comprobante_id, $hash, 'aprobacion');
            if ($datos === null) {
                return $this->portalFinRequisicion(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
            }

            return view('configuracion.arbolaprobacion.requisicion_portal_aprobar', array_merge($datos, [
                'hash_aprobacion' => $hash,
            ]));
        }

        if ($flEncontro && $tipocomprobante === 'RS') {
            $datos = $this->arbolaprobacionService->portalDatosRequisicionSalaPorHash((int) $comprobante_id, $hash, 'aprobacion');
            if ($datos === null) {
                return $this->portalFinRequisicionSala(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
            }

            return view('configuracion.arbolaprobacion.requisicion_sala_portal_aprobar', array_merge($datos, [
                'hash_aprobacion' => $hash,
            ]));
        }

        if ($flEncontro && $tipocomprobante === 'OC') {
            $datos = $this->arbolaprobacionService->portalDatosOrdencompraPorHash((int) $comprobante_id, $hash, 'aprobacion');
            if ($datos === null) {
                return $this->portalFinOrdencompra(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
            }

            return view('configuracion.arbolaprobacion.ordencompra_portal_aprobar', array_merge($datos, [
                'hash_aprobacion' => $hash,
            ]));
        }

        if ($flEncontro) {
            $this->arbolaprobacionService->aprobar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id);

            return redirect()->route('inicio')->with('mensaje', 'Comprobante aprobado con éxito')->send();
        }

        if ($tipocomprobante === 'RE') {
            return $this->portalFinRequisicion(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
        }

        if ($tipocomprobante === 'RS') {
            return $this->portalFinRequisicionSala(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
        }

        if ($tipocomprobante === 'OC') {
            return $this->portalFinOrdencompra(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
        }

        return redirect()->route('inicio')->with('mensaje', 'No tiene aprobación pendiente')->send();
    }

    public function confirmarAprobacionRequisicion(Request $request)
    {
        $request->validate([
            'comprobante_id' => 'required|integer',
            'aprobacion_id' => 'required|integer',
            'usuario_id' => 'required|integer',
            'hash_aprobacion' => 'required|string',
            'observacion' => 'nullable|string|max:4000',
        ]);

        $datos = $this->arbolaprobacionService->portalDatosRequisicionPorHash(
            (int) $request->comprobante_id,
            $request->hash_aprobacion,
            'aprobacion'
        );

        if ($datos === null
            || (int) $datos['movimiento']->id !== (int) $request->aprobacion_id
            || (int) $datos['movimiento']->destinatariousuario_id !== (int) $request->usuario_id
        ) {
            return $this->portalFinRequisicion(false, 'No se pudo confirmar la aprobación: enlace inválido o ya fue procesada por otro usuario.');
        }

        $this->arbolaprobacionService->aprobar(
            'RE',
            (int) $request->comprobante_id,
            (int) $request->aprobacion_id,
            (int) $request->usuario_id,
            $request->input('observacion')
        );

        $movPost = Arbolaprobacion_Movimiento::find((int) $request->aprobacion_id);
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        if ($movPost && $movPost->estado === $nombreAprobado) {
            return $this->portalFinRequisicion(true, 'La requisición fue aprobada en este nivel. Si el flujo continúa, recibirá nuevas notificaciones por correo.');
        }
        if ($movPost && $movPost->estado === $nombrePendiente) {
            return $this->portalFinRequisicion(false, 'No se pudo registrar la aprobación. Intente nuevamente.');
        }

        return $this->portalFinRequisicion(false, 'Este paso ya fue gestionado por otro usuario o el enlace ya no es válido.');
    }

    public function confirmarAprobacionRequisicionSala(Request $request)
    {
        $request->validate([
            'comprobante_id' => 'required|integer',
            'aprobacion_id' => 'required|integer',
            'usuario_id' => 'required|integer',
            'hash_aprobacion' => 'required|string',
            'observacion' => 'nullable|string|max:4000',
        ]);

        $datos = $this->arbolaprobacionService->portalDatosRequisicionSalaPorHash(
            (int) $request->comprobante_id,
            $request->hash_aprobacion,
            'aprobacion'
        );

        if ($datos === null
            || (int) $datos['movimiento']->id !== (int) $request->aprobacion_id
            || (int) $datos['movimiento']->destinatariousuario_id !== (int) $request->usuario_id
        ) {
            return $this->portalFinRequisicionSala(false, 'No se pudo confirmar la aprobación: enlace inválido o ya fue procesada por otro usuario.');
        }

        $this->arbolaprobacionService->aprobar(
            'RS',
            (int) $request->comprobante_id,
            (int) $request->aprobacion_id,
            (int) $request->usuario_id,
            $request->input('observacion')
        );

        $movPost = Arbolaprobacion_Movimiento::find((int) $request->aprobacion_id);
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        if ($movPost && $movPost->estado === $nombreAprobado) {
            return $this->portalFinRequisicionSala(true, 'La requisición de sala fue aprobada en este nivel. Si el flujo continúa, recibirá nuevas notificaciones por correo.');
        }
        if ($movPost && $movPost->estado === $nombrePendiente) {
            return $this->portalFinRequisicionSala(false, 'No se pudo registrar la aprobación. Intente nuevamente.');
        }

        return $this->portalFinRequisicionSala(false, 'Este paso ya fue gestionado por otro usuario o el enlace ya no es válido.');
    }

    public function confirmarAprobacionOrdencompra(Request $request)
    {
        $request->validate([
            'comprobante_id' => 'required|integer',
            'aprobacion_id' => 'required|integer',
            'usuario_id' => 'required|integer',
            'hash_aprobacion' => 'required|string',
            'observacion' => 'nullable|string|max:4000',
        ]);

        $datos = $this->arbolaprobacionService->portalDatosOrdencompraPorHash(
            (int) $request->comprobante_id,
            $request->hash_aprobacion,
            'aprobacion'
        );

        if ($datos === null
            || (int) $datos['movimiento']->id !== (int) $request->aprobacion_id
            || (int) $datos['movimiento']->destinatariousuario_id !== (int) $request->usuario_id
        ) {
            return $this->portalFinOrdencompra(false, 'No se pudo confirmar la aprobación: enlace inválido o ya fue procesada por otro usuario.');
        }

        $this->arbolaprobacionService->aprobar(
            'OC',
            (int) $request->comprobante_id,
            (int) $request->aprobacion_id,
            (int) $request->usuario_id,
            $request->input('observacion')
        );

        $movPost = Arbolaprobacion_Movimiento::find((int) $request->aprobacion_id);
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        if ($movPost && $movPost->estado === $nombreAprobado) {
            return $this->portalFinOrdencompra(true, 'La orden de compra fue aprobada en este nivel. Si el flujo continúa, recibirá nuevas notificaciones por correo.');
        }
        if ($movPost && $movPost->estado === $nombrePendiente) {
            return $this->portalFinOrdencompra(false, 'No se pudo registrar la aprobación. Intente nuevamente.');
        }

        return $this->portalFinOrdencompra(false, 'Este paso ya fue gestionado por otro usuario o el enlace ya no es válido.');
    }

    protected function portalFinRequisicion(bool $ok, string $mensaje)
    {
        return view('configuracion.arbolaprobacion.requisicion_portal_fin', [
            'ok' => $ok,
            'mensaje' => $mensaje,
        ]);
    }

    protected function portalFinOrdencompra(bool $ok, string $mensaje)
    {
        return view('configuracion.arbolaprobacion.requisicion_portal_fin', [
            'ok' => $ok,
            'mensaje' => $mensaje,
        ]);
    }

    protected function portalFinRequisicionSala(bool $ok, string $mensaje)
    {
        return view('configuracion.arbolaprobacion.requisicion_portal_fin', [
            'ok' => $ok,
            'mensaje' => $mensaje,
        ]);
    }

    // Rechazar comprobantes

    public function buscaRechazo($tipocomprobante, $comprobante_id, $hash)
    {
        $flEncontro = false;
        $aprobacion_id = null;
        $usuario_id = null;

        $arbolaprobacion_movimiento = collect();
        switch ($tipocomprobante) {
            case 'OV':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
                break;
            case 'RE':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($comprobante_id);
                break;
            case 'RS':
                $arbolaprobacion_movimiento = app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)
                    ->findPorRequisicionSala((int) $comprobante_id);
                break;
            case 'OC':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdencompra($comprobante_id);
                break;
        }

        foreach ($arbolaprobacion_movimiento as $movimiento) {
            if ($movimiento->estado == Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre']) {
                if ($hash == $movimiento->hashrechazo) {
                    $flEncontro = true;
                    $aprobacion_id = $movimiento->id;
                    $usuario_id = $movimiento->destinatariousuario_id;
                    break;
                }
            }
        }

        if ($flEncontro && $tipocomprobante === 'RE') {
            $datos = $this->arbolaprobacionService->portalDatosRequisicionPorHash((int) $comprobante_id, $hash, 'rechazo');
            if ($datos === null) {
                return $this->portalFinRequisicion(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
            }

            return view('configuracion.arbolaprobacion.requisicion_portal_rechazar', array_merge($datos, [
                'hash_rechazo' => $hash,
                'tipocomprobante' => $tipocomprobante,
                'comprobante_id' => $comprobante_id,
                'aprobacion_id' => $aprobacion_id,
                'usuario_id' => $usuario_id,
            ]));
        }

        if ($flEncontro && $tipocomprobante === 'RS') {
            $datos = $this->arbolaprobacionService->portalDatosRequisicionSalaPorHash((int) $comprobante_id, $hash, 'rechazo');
            if ($datos === null) {
                return $this->portalFinRequisicionSala(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
            }

            return view('configuracion.arbolaprobacion.requisicion_sala_portal_rechazar', array_merge($datos, [
                'hash_rechazo' => $hash,
                'tipocomprobante' => $tipocomprobante,
                'comprobante_id' => $comprobante_id,
                'aprobacion_id' => $aprobacion_id,
                'usuario_id' => $usuario_id,
            ]));
        }

        if ($flEncontro && $tipocomprobante === 'OC') {
            $datos = $this->arbolaprobacionService->portalDatosOrdencompraPorHash((int) $comprobante_id, $hash, 'rechazo');
            if ($datos === null) {
                return $this->portalFinOrdencompra(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
            }

            return view('configuracion.arbolaprobacion.ordencompra_portal_rechazar', array_merge($datos, [
                'hash_rechazo' => $hash,
                'tipocomprobante' => $tipocomprobante,
                'comprobante_id' => $comprobante_id,
                'aprobacion_id' => $aprobacion_id,
                'usuario_id' => $usuario_id,
            ]));
        }

        if ($flEncontro) {
            return view('configuracion.arbolaprobacion.rechazar', compact('tipocomprobante', 'comprobante_id', 'aprobacion_id', 'usuario_id'));
        }

        if ($tipocomprobante === 'RE') {
            return $this->portalFinRequisicion(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
        }

        if ($tipocomprobante === 'RS') {
            return $this->portalFinRequisicionSala(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
        }

        if ($tipocomprobante === 'OC') {
            return $this->portalFinOrdencompra(false, 'No tiene aprobación pendiente o el enlace ya no es válido.');
        }

        return redirect()->route('inicio')->with('mensaje', 'No tiene aprobación pendiente')->send();
    }

    public function rechazar(Request $request)
    {
        $request->validate([
            'tipocomprobante' => 'required|string',
            'comprobante_id' => 'required',
            'aprobacion_id' => 'required',
            'usuario_id' => 'required',
            'observacion' => 'nullable|string|max:4000',
            'hash_rechazo' => 'nullable|string',
        ]);

        $tipocomprobante = $request->tipocomprobante;
        $comprobante_id = $request->comprobante_id;
        $aprobacion_id = $request->aprobacion_id;
        $usuario_id = $request->usuario_id;
        $observacion = $request->observacion;
        $portalPublico = $request->boolean('portal_publico');

        if ($portalPublico && $tipocomprobante === 'RE' && filled($request->hash_rechazo)) {
            $datos = $this->arbolaprobacionService->portalDatosRequisicionPorHash((int) $comprobante_id, $request->hash_rechazo, 'rechazo');
            if ($datos === null
                || (int) $datos['movimiento']->id !== (int) $aprobacion_id
                || (int) $datos['movimiento']->destinatariousuario_id !== (int) $usuario_id
            ) {
                return $this->portalFinRequisicion(false, 'No se pudo confirmar el rechazo: enlace inválido o ya fue procesado.');
            }
        }

        if ($portalPublico && $tipocomprobante === 'RS' && filled($request->hash_rechazo)) {
            $datos = $this->arbolaprobacionService->portalDatosRequisicionSalaPorHash((int) $comprobante_id, $request->hash_rechazo, 'rechazo');
            if ($datos === null
                || (int) $datos['movimiento']->id !== (int) $aprobacion_id
                || (int) $datos['movimiento']->destinatariousuario_id !== (int) $usuario_id
            ) {
                return $this->portalFinRequisicionSala(false, 'No se pudo confirmar el rechazo: enlace inválido o ya fue procesado.');
            }
        }

        if ($portalPublico && $tipocomprobante === 'OC' && filled($request->hash_rechazo)) {
            $datos = $this->arbolaprobacionService->portalDatosOrdencompraPorHash((int) $comprobante_id, $request->hash_rechazo, 'rechazo');
            if ($datos === null
                || (int) $datos['movimiento']->id !== (int) $aprobacion_id
                || (int) $datos['movimiento']->destinatariousuario_id !== (int) $usuario_id
            ) {
                return $this->portalFinOrdencompra(false, 'No se pudo confirmar el rechazo: enlace inválido o ya fue procesado.');
            }
        }

        if ($tipocomprobante === 'RE' && $portalPublico) {
            $request->validate(['observacion' => 'required|string|min:3|max:4000']);
        }

        if ($tipocomprobante === 'RS' && $portalPublico) {
            $request->validate(['observacion' => 'required|string|min:3|max:4000']);
        }

        if ($tipocomprobante === 'OC' && $portalPublico) {
            $request->validate(['observacion' => 'required|string|min:3|max:4000']);
        }

        $this->arbolaprobacionService->rechazar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id, $observacion);

        if ($portalPublico && $tipocomprobante === 'RE') {
            $movPost = Arbolaprobacion_Movimiento::find((int) $aprobacion_id);
            $nombreRechazado = Arbolaprobacion_Movimiento::$enumEstado[array_search('R', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            if ($movPost && $movPost->estado === $nombreRechazado) {
                return $this->portalFinRequisicion(true, 'El rechazo quedó registrado correctamente.');
            }
            if ($movPost && $movPost->estado === $nombrePendiente) {
                return $this->portalFinRequisicion(false, 'No se pudo registrar el rechazo. Intente nuevamente.');
            }

            return $this->portalFinRequisicion(false, 'Este paso ya fue gestionado por otro usuario o el enlace ya no es válido.');
        }

        if ($portalPublico && $tipocomprobante === 'RS') {
            $movPost = Arbolaprobacion_Movimiento::find((int) $aprobacion_id);
            $nombreRechazado = Arbolaprobacion_Movimiento::$enumEstado[array_search('R', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            if ($movPost && $movPost->estado === $nombreRechazado) {
                return $this->portalFinRequisicionSala(true, 'El rechazo quedó registrado correctamente.');
            }
            if ($movPost && $movPost->estado === $nombrePendiente) {
                return $this->portalFinRequisicionSala(false, 'No se pudo registrar el rechazo. Intente nuevamente.');
            }

            return $this->portalFinRequisicionSala(false, 'Este paso ya fue gestionado por otro usuario o el enlace ya no es válido.');
        }

        if ($portalPublico && $tipocomprobante === 'OC') {
            $movPost = Arbolaprobacion_Movimiento::find((int) $aprobacion_id);
            $nombreRechazado = Arbolaprobacion_Movimiento::$enumEstado[array_search('R', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            if ($movPost && $movPost->estado === $nombreRechazado) {
                return $this->portalFinOrdencompra(true, 'El rechazo quedó registrado correctamente; la orden quedó en estado suspendido.');
            }
            if ($movPost && $movPost->estado === $nombrePendiente) {
                return $this->portalFinOrdencompra(false, 'No se pudo registrar el rechazo. Intente nuevamente.');
            }

            return $this->portalFinOrdencompra(false, 'Este paso ya fue gestionado por otro usuario o el enlace ya no es válido.');
        }

        return redirect()->route('inicio')->with('mensaje', 'Rechazo realizado con éxito')->send();
    }

    public function leerMovimientoAprobacion($tipocomprobante, $comprobante_id)
    {
        // Busca hash de aprobacion en movimientos del arbol
        $arbolaprobacion_movimiento = collect();
        switch ($tipocomprobante) {
            case 'OV':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
                break;
            case 'RE':
                return response()->json(
                    $this->arbolaprobacionService->movimientosRequisicionConAvisoGrabacion((int) $comprobante_id)
                );
            case 'OC':
                return response()->json(
                    $this->arbolaprobacionService->movimientosOrdencompraConAvisoGrabacion((int) $comprobante_id)
                );
        }

        return $arbolaprobacion_movimiento;
    }
}
