<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionChequera;
use App\Models\Caja\Chequera;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Support\Caja\ChequeConsultaChequeraSupport;
use App\Support\Caja\ChequePropioAnitaNumeracionSupport;

class ChequeraController extends Controller
{
	private $repository;

    public function __construct(ChequeraRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-chequera');
		$datas = $this->repository->all();
        $tipochequera_enum = Chequera::$enumTipochequera;
        $tipocheque_enum = Chequera::$enumTipocheque;
        $estado_enum = Chequera::$enumEstado;

        return view('caja.chequera.index', compact('datas', 'tipochequera_enum', 'tipocheque_enum',
                                                'estado_enum'));
    }

    public function sincronizarDesdeAnita()
    {
        can('listar-chequera');
        try {
            $ret = $this->repository->sincronizarConAnita();
        } catch (\Throwable $e) {
            return redirect('caja/chequera')->with('mensaje_error', 'No se pudo sincronizar chequeras desde Anita: '.$e->getMessage());
        }

        $msg = sprintf(
            'Chequeras sincronizadas desde Anita: %d en Anita, %d creadas, %d actualizadas, %d omitidas.',
            (int) ($ret['en_anita'] ?? 0),
            (int) ($ret['creadas'] ?? 0),
            (int) ($ret['actualizadas'] ?? 0),
            (int) ($ret['omitidas'] ?? 0)
        );

        return redirect('caja/chequera')->with('mensaje', $msg);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-chequera');

        $tipochequera_enum = Chequera::$enumTipochequera;
        $tipocheque_enum = Chequera::$enumTipocheque;
        $estado_enum = Chequera::$enumEstado;
        $codigoSugerido = $this->repository->proximoCodigo();

        return view('caja.chequera.crear', compact(
            'tipochequera_enum',
            'tipocheque_enum',
            'estado_enum',
            'codigoSugerido'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionChequera $request)
    {
        can('crear-chequera');

        try {
            $chequera = $this->repository->create($request->validated());
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('mensaje_error', $e->getMessage());
        }

        $codigo = trim((string) ($chequera->codigo ?? ''));

        return redirect('caja/chequera')->with(
            'mensaje',
            $codigo !== '' ? 'Chequera '.$codigo.' creada con éxito' : 'Chequera creada con éxito'
        );
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-chequera');
        $data = $this->repository->findOrFail($id);

        $tipochequera_enum = Chequera::$enumTipochequera;
        $tipocheque_enum = Chequera::$enumTipocheque;
        $estado_enum = Chequera::$enumEstado;

        return view('caja.chequera.editar', compact(
            'data',
            'tipochequera_enum',
            'tipocheque_enum',
            'estado_enum'
        ));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionChequera $request, $id)
    {
        can('actualizar-chequera');

        try {
            $this->repository->update($request->validated(), $id);
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect('caja/chequera')->with('mensaje', 'Chequera actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-chequera');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaChequera(Request $request)
    {
        $cuentacajaId = (int) $request->input('cuentacaja_id');
        $fechaPago = (string) $request->input('fecha_pago', '');
        $fechaEmision = (string) $request->input('fecha_emision', '');
        $preferirDiferido = ChequePropioAnitaNumeracionSupport::esFechaDiferida($fechaEmision, $fechaPago);
        $difRaw = $request->input('diferido');
        if ($difRaw !== null && $difRaw !== '') {
            $preferirDiferido = filter_var($difRaw, FILTER_VALIDATE_BOOLEAN);
        }

        $filas = ChequeConsultaChequeraSupport::consultar([
            'cuentacaja_id' => $cuentacajaId,
            'consulta' => (string) $request->input('consulta', ''),
            'preferir_diferido' => $preferirDiferido,
            'incluir_terminadas' => filter_var($request->input('incluir_terminadas'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $puedeConsultar = can('editar-chequera', false) || can('listar-chequera', false);
        foreach ($filas as &$fila) {
            $fila['url_abm'] = $puedeConsultar
                ? route('editar_chequera', ['id' => (int) $fila['id']])
                : null;
        }
        unset($fila);

        return response()->json([
            'data' => $filas,
            'preferir_diferido' => $preferirDiferido,
            'cuenta' => ChequeConsultaChequeraSupport::cuentaResumen($cuentacajaId),
        ]);
    }
}
