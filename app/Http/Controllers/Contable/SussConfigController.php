<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Suss_Presentacion_Config;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\Suss_Presentacion_Config_CuentaRepositoryInterface;
use App\Repositories\Contable\Suss_Presentacion_ConfigRepositoryInterface;
use App\Support\Contable\Suss\SussFormatoF2004Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SussConfigController extends Controller
{
    public function __construct(
        private readonly Suss_Presentacion_ConfigRepositoryInterface $configRepository,
        private readonly Suss_Presentacion_Config_CuentaRepositoryInterface $cuentaRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        can('listar-suss-config');

        $datas = $this->configRepository->all();

        return view('contable.suss_config.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-suss-config');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $frecuencia_enum = Suss_Presentacion_Config::$enumFrecuencia;

        return view('contable.suss_config.crear', compact('empresa_query', 'frecuencia_enum'));
    }

    public function guardar(Request $request)
    {
        can('crear-suss-config');

        $data = $this->validar($request);

        try {
            DB::beginTransaction();
            $config = $this->configRepository->create($data);
            $this->cuentaRepository->syncPorConfig((int) $config->id, $this->filasCuentas($request));
            DB::commit();

            return redirect()->route('suss_config')
                ->with('mensaje', 'Configuración SUSS creada con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function editar(int $id)
    {
        can('editar-suss-config');

        $data = $this->configRepository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $frecuencia_enum = Suss_Presentacion_Config::$enumFrecuencia;

        return view('contable.suss_config.editar', compact('data', 'empresa_query', 'frecuencia_enum'));
    }

    public function actualizar(Request $request, int $id)
    {
        can('actualizar-suss-config');

        $data = $this->validar($request);

        try {
            DB::beginTransaction();
            $this->configRepository->update($data, $id);
            $this->cuentaRepository->syncPorConfig($id, $this->filasCuentas($request));
            DB::commit();

            return redirect()->route('suss_config')
                ->with('mensaje', 'Configuración SUSS actualizada con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function eliminar(Request $request, int $id)
    {
        can('eliminar-suss-config');

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
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:255',
            'codigo_impuesto' => 'required|integer|in:'.SussFormatoF2004Support::IMPUESTO,
            'codigo_regimen' => 'nullable|integer|min:1|max:999',
            'frecuencia' => ['required', Rule::in(array_keys(Suss_Presentacion_Config::$enumFrecuencia))],
            'activo' => 'nullable',
        ]);

        return [
            'nombre' => (string) $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'codigo_impuesto' => (int) $request->input('codigo_impuesto', SussFormatoF2004Support::IMPUESTO),
            'codigo_regimen' => $request->filled('codigo_regimen') ? (int) $request->input('codigo_regimen') : null,
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
