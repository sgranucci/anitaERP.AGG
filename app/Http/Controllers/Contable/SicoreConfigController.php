<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Sicore_Config;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\Sicore_ConfigRepositoryInterface;
use App\Repositories\Contable\Sicore_Config_CuentaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SicoreConfigController extends Controller
{
    public function __construct(
        private readonly Sicore_ConfigRepositoryInterface $configRepository,
        private readonly Sicore_Config_CuentaRepositoryInterface $cuentaRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        can('listar-sicore-config');

        $datas = $this->configRepository->all();

        return view('contable.sicore_config.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-sicore-config');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $criterio_enum = Sicore_Config::$enumCriterio;
        $concilia_enum = Sicore_Config::$enumConciliaCon;
        $frecuencia_enum = Sicore_Config::$enumFrecuencia;

        return view('contable.sicore_config.crear', compact(
            'empresa_query',
            'criterio_enum',
            'concilia_enum',
            'frecuencia_enum',
        ));
    }

    public function guardar(Request $request)
    {
        can('crear-sicore-config');

        $data = $this->validar($request);

        try {
            DB::beginTransaction();
            $config = $this->configRepository->create($data);
            $this->guardarCuentas($request, (int) $config->id);
            DB::commit();

            return redirect()->route('sicore_config')->with('mensaje', 'Configuración SICORE creada con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function editar(int $id)
    {
        can('editar-sicore-config');

        $data = $this->configRepository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $criterio_enum = Sicore_Config::$enumCriterio;
        $concilia_enum = Sicore_Config::$enumConciliaCon;
        $frecuencia_enum = Sicore_Config::$enumFrecuencia;

        return view('contable.sicore_config.editar', compact(
            'data',
            'empresa_query',
            'criterio_enum',
            'concilia_enum',
            'frecuencia_enum',
        ));
    }

    public function actualizar(Request $request, int $id)
    {
        can('actualizar-sicore-config');

        $data = $this->validar($request);

        try {
            DB::beginTransaction();
            $this->configRepository->update($data, $id);
            $this->cuentaRepository->deletePorConfig($id);
            $this->guardarCuentas($request, $id);
            DB::commit();

            return redirect()->route('sicore_config')->with('mensaje', 'Configuración SICORE actualizada con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function eliminar(Request $request, int $id)
    {
        can('eliminar-sicore-config');

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
        $validated = $request->validate([
            'codigo_impuesto' => 'required|integer|min:1|max:9999',
            'nombre' => 'required|string|max:120',
            'criterio' => 'required|string|max:30',
            'codigo_operacion' => 'required|integer|in:1,2',
            'concilia_con' => 'required|string|max:30',
            'frecuencia' => 'required|string|max:20',
            'concepto_retencion_sueldos' => 'nullable|integer|min:1',
            'concepto_devolucion_sueldos' => 'nullable|integer|min:0',
        ]);

        if (($validated['criterio'] ?? '') === 'sueldos' && empty($validated['concepto_retencion_sueldos'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'concepto_retencion_sueldos' => 'Indique el código de haberes de retención para sueldos (787).',
            ]);
        }

        return [
            'codigo_impuesto' => (int) $request->input('codigo_impuesto'),
            'codigo_regimen' => $request->filled('codigo_regimen') ? (int) $request->input('codigo_regimen') : null,
            'nombre' => (string) $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'criterio' => (string) $request->input('criterio'),
            'codigo_operacion' => (int) $request->input('codigo_operacion'),
            'concilia_con' => (string) $request->input('concilia_con'),
            'frecuencia' => (string) $request->input('frecuencia'),
            'quincena_1_desde' => $request->input('quincena_1_desde'),
            'quincena_1_hasta' => $request->input('quincena_1_hasta'),
            'quincena_2_desde' => $request->input('quincena_2_desde'),
            'quincena_2_hasta' => $request->input('quincena_2_hasta'),
            'concepto_retencion_sueldos' => $request->filled('concepto_retencion_sueldos')
                ? (int) $request->input('concepto_retencion_sueldos') : null,
            'concepto_devolucion_sueldos' => $request->filled('concepto_devolucion_sueldos')
                ? (int) $request->input('concepto_devolucion_sueldos') : null,
            'activo' => $request->boolean('activo', true),
        ];
    }

    private function guardarCuentas(Request $request, int $configId): void
    {
        $empresaIds = $request->input('empresa_ids', []);
        $cuentaIds = $request->input('cuentacontable_ids', []);

        for ($i = 0; $i < count($cuentaIds); $i++) {
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            if ($cuentaId <= 0 || $empresaId <= 0) {
                continue;
            }

            $this->cuentaRepository->create([
                'sicore_config_id' => $configId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
            ]);
        }
    }
}
