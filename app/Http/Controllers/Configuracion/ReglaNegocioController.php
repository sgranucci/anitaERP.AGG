<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ReglaNegocio;
use App\Models\Configuracion\ReglaNegocioConfig;
use App\Models\Configuracion\ReglaNegocioLog;
use App\Models\Configuracion\ReglaNegocioVersion;
use App\Services\Configuracion\ReglaNegocioMotorService;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\ReglaNegocio\ReglaNegocioAvisoBridgeSupport;
use App\Support\ReglaNegocio\ReglaNegocioCoberturaSupport;
use App\Support\ReglaNegocio\ReglaNegocioVersionSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReglaNegocioController extends Controller
{
    public function __construct(
        private ReglaNegocioMotorService $motor,
    ) {}

    public function index(): View
    {
        can('listar-regla-negocio');

        $config = ReglaNegocioConfig::actual();
        $reglas = ReglaNegocio::query()->orderBy('prioridad')->orderBy('nombre')->get();
        $logs = ReglaNegocioLog::query()
            ->with('regla:id,codigo,nombre')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return view('configuracion.regla_negocio.index', [
            'config' => $config,
            'motor_entorno' => (bool) config('regla_negocio.habilitado_entorno', true),
            'motor_vivo' => $this->motor->motorHabilitado(),
            'reglas' => $reglas,
            'logs' => $logs,
            'puntos' => config('regla_negocio_puntos.catalogo', []),
            'cobertura' => ReglaNegocioCoberturaSupport::resumen(),
            'puente_avisos' => ReglaNegocioAvisoBridgeSupport::estado(),
            'retencion_logs_dias' => (int) config('regla_negocio.retencion_logs_dias', 90),
            'puede_disenar' => $this->puedeDisenar(),
            'puede_activar' => $this->puedeActivar(),
        ]);
    }

    public function crear(): View
    {
        $this->exigirDisenar();

        return $this->formView(new ReglaNegocio([
            'activo' => false,
            'prioridad' => 100,
            'stop_on_match' => true,
            'dominio' => 'configuracion',
            'punto' => 'demo.punto.piloto',
            'condiciones' => ['logic' => 'and', 'rules' => []],
            'acciones' => [],
        ]));
    }

    public function editar(int $id): View
    {
        can('listar-regla-negocio');

        return $this->formView(ReglaNegocio::query()->findOrFail($id));
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->exigirDisenar();

        $datos = $this->validar($request);
        $regla = ReglaNegocio::query()->create($datos);
        ReglaNegocioVersionSupport::snapshot($regla, 'create', auth()->id());

        return redirect()->route('editar_regla_negocio', $regla->id)
            ->with('mensaje', 'Regla creada. Activá el motor global y la regla para que opere.');
    }

    public function actualizar(Request $request, int $id): RedirectResponse
    {
        $this->exigirDisenar();

        $regla = ReglaNegocio::query()->findOrFail($id);
        ReglaNegocioVersionSupport::snapshot($regla, 'update', auth()->id());
        $regla->update($this->validar($request, $regla->id, $regla));

        return redirect()->route('editar_regla_negocio', $regla->id)
            ->with('mensaje', 'Regla actualizada (versión guardada).');
    }

    public function eliminar(int $id): RedirectResponse
    {
        $this->exigirDisenar();

        EloquentAuditDeleteSupport::each(
            ReglaNegocio::query()->whereKey($id)
        );

        return redirect()->route('consultar_regla_negocio')
            ->with('mensaje', 'Regla eliminada.');
    }

    public function actualizarMotor(Request $request): RedirectResponse
    {
        $this->exigirActivar();

        $cfg = ReglaNegocioConfig::actual();
        $cfg->motor_habilitado = $request->boolean('motor_habilitado');
        $cfg->save();

        return redirect()->route('consultar_regla_negocio')
            ->with('mensaje', $cfg->motor_habilitado
                ? 'Motor de reglas de negocio ACTIVADO. Solo corren reglas con Activo = sí.'
                : 'Motor de reglas de negocio desactivado. Las reglas se conservan pero no se evalúan.');
    }

    public function togglearActivo(int $id): RedirectResponse
    {
        $this->exigirActivar();

        $regla = ReglaNegocio::query()->findOrFail($id);
        ReglaNegocioVersionSupport::snapshot($regla, 'toggle_activo', auth()->id());
        $regla->activo = ! $regla->activo;
        $regla->save();

        return redirect()->route('consultar_regla_negocio')
            ->with('mensaje', $regla->activo
                ? 'Regla '.$regla->codigo.' ACTIVADA.'
                : 'Regla '.$regla->codigo.' desactivada.');
    }

    public function probar(Request $request, int $id): RedirectResponse
    {
        $this->exigirDisenar();

        $regla = ReglaNegocio::query()->findOrFail($id);
        $raw = trim((string) $request->input('contexto_json', '{}'));
        $contexto = json_decode($raw, true);
        if (! is_array($contexto)) {
            return back()->withInput()->with('error', 'Contexto JSON inválido.');
        }

        $resultado = $this->motor->simular($regla, $contexto);

        return back()->withInput()->with(
            ($resultado['cumple'] && $resultado['permitido']) ? 'mensaje' : 'error',
            $resultado['detalle']
        );
    }

    public function restaurarVersion(int $id, int $versionId): RedirectResponse
    {
        $this->exigirDisenar();

        $regla = ReglaNegocio::query()->findOrFail($id);
        if (! $this->puedeActivar()) {
            // Sin permiso de activar: al restaurar no puede quedar activa si el snapshot lo estaba
            // (se fuerza después).
        }
        ReglaNegocioVersionSupport::restaurar($regla, $versionId, auth()->id());
        $regla = $regla->fresh();
        if ($regla && $regla->activo && ! $this->puedeActivar()) {
            $regla->activo = false;
            $regla->save();
        }

        return redirect()->route('editar_regla_negocio', $id)
            ->with('mensaje', 'Versión restaurada.');
    }

    private function formView(ReglaNegocio $regla): View
    {
        $puntos = config('regla_negocio_puntos.catalogo', []);
        $puntoActual = old('punto', $regla->punto);
        $camposPunto = [];
        if (is_string($puntoActual) && isset($puntos[$puntoActual]['campos']) && is_array($puntos[$puntoActual]['campos'])) {
            $camposPunto = $puntos[$puntoActual]['campos'];
        }

        $versiones = collect();
        if ($regla->exists) {
            $versiones = ReglaNegocioVersion::query()
                ->where('regla_negocio_id', $regla->id)
                ->orderByDesc('nro_version')
                ->limit(20)
                ->get();
        }

        return view('configuracion.regla_negocio.form', [
            'regla' => $regla,
            'puntos' => $puntos,
            'campos' => array_merge(config('regla_negocio.campos', []), $camposPunto),
            'operadores' => config('regla_negocio.operadores', []),
            'tipos_accion' => collect(config('regla_negocio_acciones.catalogo', []))
                ->mapWithKeys(fn ($meta, $clave) => [$clave => $meta['etiqueta'] ?? $clave])
                ->all(),
            'motor_vivo' => $this->motor->motorHabilitado(),
            'versiones' => $versiones,
            'puede_disenar' => $this->puedeDisenar(),
            'puede_activar' => $this->puedeActivar(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $ignoreId = null, ?ReglaNegocio $actual = null): array
    {
        $puntos = array_keys(config('regla_negocio_puntos.catalogo', []));
        $accionesPermitidas = array_keys(config('regla_negocio_acciones.catalogo', []));

        $datos = $request->validate([
            'codigo' => [
                'required', 'string', 'max:80',
                Rule::unique('regla_negocio', 'codigo')->ignore($ignoreId),
            ],
            'nombre' => 'required|string|max:160',
            'descripcion' => 'nullable|string|max:500',
            'dominio' => 'nullable|string|max:40',
            'punto' => ['required', 'string', 'max:120', Rule::in($puntos)],
            'activo' => 'nullable|boolean',
            'stop_on_match' => 'nullable|boolean',
            'prioridad' => 'nullable|integer|min:1|max:9999',
            'condiciones_json' => 'nullable|string|max:20000',
            'acciones_json' => 'nullable|string|max:20000',
        ]);

        $condiciones = $this->decodeJsonField(
            $datos['condiciones_json'] ?? '',
            ['logic' => 'and', 'rules' => []],
            'condiciones'
        );
        $acciones = $this->decodeJsonField($datos['acciones_json'] ?? '', [], 'acciones');
        if (! array_is_list($acciones) && $acciones !== []) {
            $acciones = array_values($acciones);
        }
        foreach ($acciones as $accion) {
            if (! is_array($accion)) {
                throw ValidationException::withMessages([
                    'acciones_json' => 'Cada acción debe ser un objeto JSON.',
                ]);
            }
            $tipo = (string) ($accion['tipo'] ?? '');
            if ($tipo === '' || ! in_array($tipo, $accionesPermitidas, true)) {
                throw ValidationException::withMessages([
                    'acciones_json' => 'Acción no permitida: '.($tipo !== '' ? $tipo : '(sin tipo)').
                        '. Catálogo: '.implode(', ', $accionesPermitidas),
                ]);
            }
        }

        $punto = trim($datos['punto']);
        $dominio = trim((string) ($datos['dominio'] ?? ''));
        if ($dominio === '') {
            $dominio = (string) (config("regla_negocio_puntos.catalogo.{$punto}.dominio") ?? 'configuracion');
        }

        $activo = $request->boolean('activo');
        if ($activo && ! $this->puedeActivar()) {
            // Diseñador sin permiso de activar: conserva estado previo o fuerza false.
            $activo = $actual ? (bool) $actual->activo : false;
        }

        return [
            'codigo' => trim($datos['codigo']),
            'nombre' => trim($datos['nombre']),
            'descripcion' => isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : null,
            'dominio' => $dominio,
            'punto' => $punto,
            'activo' => $activo,
            'stop_on_match' => $request->boolean('stop_on_match', true),
            'prioridad' => max(1, (int) ($datos['prioridad'] ?? 100)),
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

    private function puedeDisenar(): bool
    {
        return can('disenar-regla-negocio', false)
            || can('actualizar-regla-negocio', false);
    }

    private function puedeActivar(): bool
    {
        return can('activar-regla-negocio', false)
            || can('actualizar-regla-negocio', false);
    }

    private function exigirDisenar(): void
    {
        if (! $this->puedeDisenar()) {
            can('disenar-regla-negocio');
        }
    }

    private function exigirActivar(): void
    {
        if (! $this->puedeActivar()) {
            can('activar-regla-negocio');
        }
    }
}
