<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Admin\Rol;
use App\Models\Configuracion\AvisoRegla;
use App\Models\Configuracion\AvisoReglaConfig;
use App\Models\Configuracion\AvisoReglaLog;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\AvisoReglaMotorService;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AvisoReglaController extends Controller
{
    public function __construct(
        private AvisoReglaMotorService $motor,
    ) {}

    public function index(): View
    {
        can('listar-modulo-aviso');

        $config = AvisoReglaConfig::actual();
        $reglas = AvisoRegla::query()->orderBy('prioridad')->orderBy('nombre')->get();
        $logs = AvisoReglaLog::query()
            ->with('regla:id,codigo,nombre')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return view('configuracion.aviso_regla.index', [
            'config' => $config,
            'motor_entorno' => (bool) config('aviso_reglas.habilitado_entorno', true),
            'motor_vivo' => $this->motor->motorHabilitado(),
            'reglas' => $reglas,
            'logs' => $logs,
            'catalogo' => config('modulo_aviso_eventos.catalogo', []),
        ]);
    }

    public function crear(): View
    {
        can('actualizar-modulo-aviso');

        return $this->formView(new AvisoRegla([
            'activo' => false,
            'prioridad' => 100,
            'condiciones' => ['logic' => 'and', 'rules' => []],
            'acciones' => [],
        ]));
    }

    public function editar(int $id): View
    {
        can('listar-modulo-aviso');

        return $this->formView(AvisoRegla::query()->findOrFail($id));
    }

    public function guardar(Request $request): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $regla = AvisoRegla::query()->create($this->validar($request));

        return redirect()->route('editar_aviso_regla', $regla->id)
            ->with('mensaje', 'Regla creada. Activá el motor global y la regla para que opere.');
    }

    public function actualizar(Request $request, int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $regla = AvisoRegla::query()->findOrFail($id);
        $regla->update($this->validar($request, $regla->id));

        return redirect()->route('editar_aviso_regla', $regla->id)
            ->with('mensaje', 'Regla actualizada.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        EloquentAuditDeleteSupport::each(
            AvisoRegla::query()->whereKey($id)
        );

        return redirect()->route('consultar_aviso_regla')
            ->with('mensaje', 'Regla eliminada.');
    }

    public function actualizarMotor(Request $request): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $cfg = AvisoReglaConfig::actual();
        $cfg->motor_habilitado = $request->boolean('motor_habilitado');
        $cfg->save();

        return redirect()->route('consultar_aviso_regla')
            ->with('mensaje', $cfg->motor_habilitado
                ? 'Motor de reglas ACTIVADO. Solo corren reglas con Activo = sí.'
                : 'Motor de reglas desactivado. Las reglas se conservan pero no se evalúan.');
    }

    public function probar(Request $request, int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $regla = AvisoRegla::query()->findOrFail($id);
        $raw = trim((string) $request->input('contexto_json', '{}'));
        $contexto = json_decode($raw, true);
        if (! is_array($contexto)) {
            return back()->withInput()->with('error', 'Contexto JSON inválido.');
        }

        $resultado = $this->motor->simular($regla, $contexto);

        return back()->withInput()->with(
            $resultado['cumple'] ? 'mensaje' : 'error',
            $resultado['detalle']
        );
    }

    private function formView(AvisoRegla $regla): View
    {
        return view('configuracion.aviso_regla.form', [
            'regla' => $regla,
            'catalogo' => config('modulo_aviso_eventos.catalogo', []),
            'campos' => config('aviso_reglas.campos', []),
            'operadores' => config('aviso_reglas.operadores', []),
            'tipos_accion' => config('aviso_reglas.acciones', []),
            'tipos_aviso' => ModuloAvisoTipo::query()
                ->where('activo', true)
                ->orderBy('modulo')
                ->orderBy('codigo')
                ->get(['id', 'modulo', 'codigo', 'nombre'])
                ->map(fn ($t) => [
                    'modulo' => $t->modulo,
                    'codigo' => $t->codigo,
                    'nombre' => $t->nombre,
                ])
                ->values()
                ->all(),
            'roles' => Rol::query()->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($r) => ['id' => $r->id, 'nombre' => $r->nombre])
                ->values()
                ->all(),
            'motor_vivo' => $this->motor->motorHabilitado(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $ignoreId = null): array
    {
        $datos = $request->validate([
            'codigo' => [
                'required', 'string', 'max:80',
                Rule::unique('aviso_regla', 'codigo')->ignore($ignoreId),
            ],
            'nombre' => 'required|string|max:160',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'nullable|boolean',
            'prioridad' => 'nullable|integer|min:1|max:9999',
            'evento' => 'required|string|max:120',
            'condiciones_json' => 'nullable|string|max:20000',
            'acciones_json' => 'nullable|string|max:20000',
        ]);

        $condiciones = $this->decodeJsonField($datos['condiciones_json'] ?? '', ['logic' => 'and', 'rules' => []], 'condiciones');
        $acciones = $this->decodeJsonField($datos['acciones_json'] ?? '', [], 'acciones');
        if (! array_is_list($acciones) && $acciones !== []) {
            $acciones = array_values($acciones);
        }

        return [
            'codigo' => trim($datos['codigo']),
            'nombre' => trim($datos['nombre']),
            'descripcion' => isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : null,
            'activo' => $request->boolean('activo'),
            'prioridad' => max(1, (int) ($datos['prioridad'] ?? 100)),
            'evento' => trim($datos['evento']),
            'condiciones' => $condiciones,
            'acciones' => $acciones,
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $default
     * @return array<string, mixed>|list<mixed>
     */
    private function decodeJsonField(string $raw, array $default, string $campo): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $campo.'_json' => 'JSON inválido en '.$campo.'.',
            ]);
        }

        return $decoded;
    }
}
