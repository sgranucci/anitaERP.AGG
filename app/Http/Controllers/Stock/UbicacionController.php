<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionUbicacion;
use App\Models\Stock\Ubicacion;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class UbicacionController extends Controller
{
    public function index()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-ubicaciones');

        $datas = Ubicacion::orderBy('codigo')->orderBy('nombre')->get();

        return view('stock.ubicacion.index', compact('datas'));
    }

    public function sincronizar()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-ubicaciones');

        $ret = (new Ubicacion)->sincronizarConAnita();
        $mensaje = sprintf(
            'Sync Anita: %d en Anita, %d importados, %d actualizados',
            $ret['en_anita'],
            $ret['importados'],
            $ret['actualizados']
        );
        if ($ret['errores'] !== []) {
            return redirect('stock/ubicacion')->with('mensaje', $mensaje)
                ->with('errores', array_slice($ret['errores'], 0, 10));
        }

        return redirect('stock/ubicacion')->with('mensaje', $mensaje);
    }

    public function crear()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-ubicaciones');

        return view('stock.ubicacion.crear');
    }

    public function guardar(ValidacionUbicacion $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-ubicaciones');

        $data = Ubicacion::create($this->payload($request));
        try {
            $data->guardarAnita();
        } catch (\Throwable $e) {
            report($e);

            return redirect('stock/ubicacion')->with('mensaje', 'Ubicación creada en ERP')
                ->with('errores', ['No se pudo replicar en Anita: '.$e->getMessage()]);
        }

        return redirect('stock/ubicacion')->with('mensaje', 'Ubicación creada con éxito');
    }

    public function editar($id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-ubicaciones');
        $data = Ubicacion::findOrFail($id);

        return view('stock.ubicacion.editar', compact('data'));
    }

    public function actualizar(ValidacionUbicacion $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-ubicaciones');

        $data = Ubicacion::findOrFail($id);
        $codigoAnterior = $data->codigo;
        $data->update($this->payload($request));

        try {
            if ($codigoAnterior !== $data->codigo) {
                $tmp = new Ubicacion(['codigo' => $codigoAnterior]);
                $tmp->eliminarAnita();
                $data->guardarAnita();
            } else {
                $data->actualizarAnita();
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect('stock/ubicacion')->with('mensaje', 'Ubicación actualizada en ERP')
                ->with('errores', ['No se pudo replicar en Anita: '.$e->getMessage()]);
        }

        return redirect('stock/ubicacion')->with('mensaje', 'Ubicación actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-ubicaciones');

        $data = Ubicacion::findOrFail($id);
        try {
            $data->eliminarAnita();
        } catch (\Throwable $e) {
            report($e);
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }

            return redirect('stock/ubicacion')->with('errores', ['No se pudo borrar en Anita: '.$e->getMessage()]);
        }

        $data->delete();
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect('stock/ubicacion')->with('mensaje', 'Ubicación eliminada con éxito');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ValidacionUbicacion $request): array
    {
        $estado = $request->input('estado', Ubicacion::ESTADO_ACTIVA);
        if ($estado === 'A' || $estado === '') {
            $estado = Ubicacion::ESTADO_ACTIVA;
        }

        return [
            'codigo' => strtoupper(trim((string) $request->input('codigo'))),
            'nombre' => trim((string) $request->input('nombre')),
            'zona' => $this->nullable($request->input('zona')),
            'area' => $this->nullable($request->input('area')),
            'nivel' => $this->nullable($request->input('nivel')),
            'estado' => $estado === Ubicacion::ESTADO_INACTIVA
                ? Ubicacion::ESTADO_INACTIVA
                : Ubicacion::ESTADO_ACTIVA,
        ];
    }

    private function nullable($value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v === '' ? null : $v;
    }
}
