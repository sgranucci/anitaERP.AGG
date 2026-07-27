<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\Iibb_Presentacion_Config_CuentaRepositoryInterface;
use App\Repositories\Contable\Iibb_Presentacion_ConfigRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IngresosBrutosConfigController extends Controller
{
    public function __construct(
        private readonly Iibb_Presentacion_ConfigRepositoryInterface $configRepository,
        private readonly Iibb_Presentacion_Config_CuentaRepositoryInterface $cuentaRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        can('listar-ingresos-brutos-config');

        $datas = $this->configRepository->all();

        return view('contable.ingresos_brutos_config.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-ingresos-brutos-config');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipo_enum = Iibb_Presentacion_Config::$enumTipo;
        $frecuencia_enum = Iibb_Presentacion_Config::$enumFrecuencia;

        return view('contable.ingresos_brutos_config.crear', compact(
            'empresa_query',
            'tipo_enum',
            'frecuencia_enum',
        ));
    }

    public function guardar(Request $request)
    {
        can('crear-ingresos-brutos-config');

        $data = $this->validar($request);

        try {
            DB::beginTransaction();
            $config = $this->configRepository->create($data);
            $this->cuentaRepository->syncPorConfig((int) $config->id, $this->filasCuentas($request));
            DB::commit();

            return redirect()->route('ingresos_brutos_config')
                ->with('mensaje', 'Configuración Ingresos Brutos creada con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function editar(int $id)
    {
        can('editar-ingresos-brutos-config');

        $data = $this->configRepository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipo_enum = Iibb_Presentacion_Config::$enumTipo;
        $frecuencia_enum = Iibb_Presentacion_Config::$enumFrecuencia;

        return view('contable.ingresos_brutos_config.editar', compact(
            'data',
            'empresa_query',
            'tipo_enum',
            'frecuencia_enum',
        ));
    }

    public function actualizar(Request $request, int $id)
    {
        can('actualizar-ingresos-brutos-config');

        $data = $this->validar($request);

        try {
            DB::beginTransaction();
            $this->configRepository->update($data, $id);
            $this->cuentaRepository->syncPorConfig($id, $this->filasCuentas($request));
            DB::commit();

            return redirect()->route('ingresos_brutos_config')
                ->with('mensaje', 'Configuración Ingresos Brutos actualizada con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function eliminar(Request $request, int $id)
    {
        can('eliminar-ingresos-brutos-config');

        if ($request->ajax()) {
            if ($this->configRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $request->validate([
            'provincia_id' => 'required|integer|min:1',
            'tipo' => ['required', Rule::in(['retenciones', 'percepciones'])],
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:255',
            'codigo_actividad_arba' => ['required', 'integer', Rule::in([6, 7])],
            'frecuencia' => ['required', Rule::in(array_keys(Iibb_Presentacion_Config::$enumFrecuencia))],
            'activo' => 'nullable',
        ]);

        return [
            'provincia_id' => (int) $request->input('provincia_id'),
            'tipo' => (string) $request->input('tipo'),
            'nombre' => (string) $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'codigo_actividad_arba' => (int) $request->input('codigo_actividad_arba'),
            'frecuencia' => (string) $request->input('frecuencia'),
            'activo' => $request->boolean('activo', true),
        ];
    }

    /**
     * @return list<array{empresa_id:int,cuentacontable_id:int}>
     */
    private function filasCuentas(Request $request): array
    {
        $empresaIds = $request->input('empresa_ids', []);
        $cuentaIds = $request->input('cuentacontable_ids', []);
        $filas = [];

        for ($i = 0; $i < count($cuentaIds); $i++) {
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            if ($cuentaId <= 0 || $empresaId <= 0) {
                continue;
            }
            $filas[] = [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
            ];
        }

        return $filas;
    }
}
