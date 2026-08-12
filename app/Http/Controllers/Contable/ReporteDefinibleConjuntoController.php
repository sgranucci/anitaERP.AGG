<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\ReporteContableConjunto;
use App\Models\Contable\ReporteContableConjuntoCuenta;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReporteDefinibleConjuntoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        can('editar-reporte-definible');
        $coleccion = ReporteContableConjunto::query()
            ->withCount('cuentas')
            ->orderBy('codigo')
            ->paginate(20);

        return view('contable.reporte_definible_conjunto.index', [
            'coleccion' => $coleccion,
            'puede_actualizar' => can('actualizar-reporte-definible', false),
        ]);
    }

    public function crear()
    {
        can('crear-reporte-definible');

        return view('contable.reporte_definible_conjunto.crear', [
            'data' => new ReporteContableConjunto(['activo' => true]),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-reporte-definible');
        $data = $this->validarCabecera($request);
        $conjunto = ReporteContableConjunto::query()->create($data);

        return redirect()
            ->route('editar_reporte_definible_conjunto', $conjunto->id)
            ->with('mensaje', 'Set de cuentas creado.');
    }

    public function editar($id)
    {
        can('editar-reporte-definible');
        $data = ReporteContableConjunto::query()
            ->with(['cuentas.cuentacontable'])
            ->findOrFail((int) $id);

        return view('contable.reporte_definible_conjunto.editar', [
            'data' => $data,
            'puede_actualizar' => can('actualizar-reporte-definible', false),
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $conjunto = ReporteContableConjunto::query()->findOrFail((int) $id);
        $data = $this->validarCabecera($request, (int) $id);
        $conjunto->fill($data)->save();

        return redirect()
            ->route('editar_reporte_definible_conjunto', $id)
            ->with('mensaje', 'Set actualizado.');
    }

    public function eliminar($id)
    {
        can('eliminar-reporte-definible');
        $conjunto = ReporteContableConjunto::query()->findOrFail((int) $id);
        $conjunto->delete();

        return redirect()
            ->route('reporte_definible_conjunto')
            ->with('mensaje', 'Set eliminado.');
    }

    public function guardarCuenta(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $conjunto = ReporteContableConjunto::query()->findOrFail((int) $id);
        $data = $request->validate([
            'codigo_cuenta' => 'required|integer|min:1',
            'codigo_hasta' => 'nullable|integer|min:1',
            'origen' => 'required|in:R,P',
            'signo' => 'nullable|integer',
            'carga_ccosto' => 'nullable|in:S,R,P',
        ]);
        $codigo = (int) $data['codigo_cuenta'];
        $codigoHasta = isset($data['codigo_hasta']) && (int) $data['codigo_hasta'] > 0
            ? (int) $data['codigo_hasta']
            : null;
        if ($codigoHasta !== null && $codigoHasta < $codigo) {
            return back()->with('mensaje_error', 'Código hasta debe ser ≥ desde.')->withInput();
        }
        $cta = Cuentacontable::query()->where('codigo', (string) $codigo)->first();
        $orden = (int) $conjunto->cuentas()->max('orden') + 1;
        $conjunto->cuentas()->create([
            'cuentacontable_id' => $cta?->id,
            'codigo_cuenta' => $codigo,
            'codigo_hasta' => $codigoHasta,
            'origen' => $data['origen'],
            'signo' => ((int) ($data['signo'] ?? 1)) < 0 ? -1 : 1,
            'carga_ccosto' => ReporteDefinibleSupport::normalizarCargaCcosto((string) ($data['carga_ccosto'] ?? 'S')),
            'orden' => $orden,
        ]);

        return redirect()
            ->route('editar_reporte_definible_conjunto', $id)
            ->with('mensaje', 'Cuenta agregada al set.');
    }

    public function eliminarCuenta($id, $cuentaId)
    {
        can('actualizar-reporte-definible');
        $cta = ReporteContableConjuntoCuenta::query()
            ->where('reporte_contable_conjunto_id', (int) $id)
            ->where('id', (int) $cuentaId)
            ->firstOrFail();
        $cta->delete();

        return redirect()
            ->route('editar_reporte_definible_conjunto', $id)
            ->with('mensaje', 'Cuenta quitada del set.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validarCabecera(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('reporte_contable_conjunto', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:80',
            'observaciones' => 'nullable|string|max:2000',
            'activo' => 'nullable|boolean',
        ]);
        $data['activo'] = $request->boolean('activo', true);

        return $data;
    }
}
