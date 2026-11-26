<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Suss_Empresa_Config;
use App\Models\Contable\Suss_Presentacion_Config;
use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
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
     * Solicitud de pago SUSS y régimen 748 → 754, por empresa (columnas = empresas asignadas).
     */
    public function empresas()
    {
        can('listar-suss-config');

        $empresas = $this->empresaRepository->allFiltrado();
        $configs = Suss_Empresa_Config::query()
            ->whereIn('empresa_id', $empresas->pluck('id')->all())
            ->with([
                'proveedor:id,codigo,nombre',
                'sectorSolicitudpago:id,codigo,nombre',
                'conceptoSolicitudpago:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'moneda:id,nombre',
                'centrocosto:id,codigo,nombre',
                'cuentacontableSuss:id,codigo,nombre',
                'cuentacontableBanco:id,codigo,nombre',
            ])
            ->get()
            ->keyBy('empresa_id');

        return view('contable.suss_config.empresas', compact('empresas', 'configs'));
    }

    public function guardarEmpresas(Request $request)
    {
        can('actualizar-suss-config');

        $permitidas = $this->empresaRepository->allFiltrado()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $filas = (array) $request->input('empresas', []);
        $datos = [];
        $errores = [];

        foreach ($filas as $empresaKey => $f) {
            $empresaId = (int) $empresaKey;
            if ($empresaId <= 0 || ! in_array($empresaId, $permitidas, true)) {
                continue;
            }
            $f = (array) $f;
            $ref = 'Empresa #'.$empresaId;

            $resuelto = [
                'sigla' => trim((string) ($f['sigla'] ?? '')) !== '' ? mb_substr(trim((string) $f['sigla']), 0, 10) : null,
                'proveedor_id' => $this->idPorCodigo(Proveedor::class, $f['proveedor_codigo'] ?? '', null, $ref, 'proveedor', $errores),
                'sector_solicitudpago_id' => $this->idPorCodigo(Sector_Solicitudpago::class, $f['sector_codigo'] ?? '', null, $ref, 'sector', $errores),
                'concepto_solicitudpago_id' => $this->idPorCodigo(Concepto_Solicitudpago::class, $f['concepto_codigo'] ?? '', null, $ref, 'concepto', $errores),
                'formapagosol_id' => $this->idPorCodigo(Formapagosol::class, $f['forma_codigo'] ?? '', null, $ref, 'forma de pago', $errores),
                'centrocosto_id' => $this->idPorCodigo(Centrocosto::class, $f['centrocosto_codigo'] ?? '', null, $ref, 'centro de costo', $errores),
                'cuentacontable_suss_id' => $this->idPorCodigo(Cuentacontable::class, $f['cuenta_debe_codigo'] ?? '', $empresaId, $ref, 'cuenta Debe', $errores),
                'cuentacontable_banco_id' => $this->idPorCodigo(Cuentacontable::class, $f['cuenta_haber_codigo'] ?? '', $empresaId, $ref, 'cuenta Haber', $errores),
                'moneda_id' => $this->idMoneda($f['moneda_id'] ?? null, $ref, $errores),
                'exportar_regimen_748_como_754' => ! empty($f['exportar_748']),
                'activo' => true,
            ];

            $datos[$empresaId] = $resuelto;
        }

        if ($errores !== []) {
            return back()->withInput()->with('mensaje', implode(' ', $errores));
        }

        try {
            DB::beginTransaction();
            foreach ($datos as $empresaId => $resuelto) {
                Suss_Empresa_Config::query()->updateOrCreate(['empresa_id' => $empresaId], $resuelto);
            }
            DB::commit();

            return redirect()->route('suss_config_empresas')
                ->with('mensaje', 'Configuración SUSS por empresa guardada con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    /**
     * Resuelve un código de maestro a id (vacío = sin configurar). Carga errores si el código no existe.
     *
     * @param  class-string  $modelo
     * @param  array<int, string>  $errores
     */
    private function idPorCodigo(string $modelo, mixed $codigo, ?int $empresaId, string $ref, string $rotulo, array &$errores): ?int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $query = $modelo::query()->where('codigo', $codigo);
        if ($empresaId !== null) {
            $query->where('empresa_id', $empresaId);
        }
        $id = (int) ($query->value('id') ?? 0);
        if ($id <= 0) {
            $errores[] = $ref.': no existe el '.$rotulo.' con código «'.$codigo.'».';

            return null;
        }

        return $id;
    }

    /**
     * @param  array<int, string>  $errores
     */
    private function idMoneda(mixed $id, string $ref, array &$errores): ?int
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        if (! Moneda::query()->whereKey($id)->exists()) {
            $errores[] = $ref.': no existe la moneda #'.$id.'.';

            return null;
        }

        return $id;
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
