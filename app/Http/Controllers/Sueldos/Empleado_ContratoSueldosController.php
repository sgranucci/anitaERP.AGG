<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Contrato_Plantilla_Sueldos;
use App\Models\Sueldos\Empleado_Contrato_Emision_Sueldos;
use App\Models\Sueldos\Empleado_Contrato_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Services\Sueldos\ContratoEmisionService;
use App\Support\Sueldos\ContratoDocumentoTipos;
use App\Support\Sueldos\ContratoModalidadSijp;
use App\Support\Sueldos\EmpleadoLegajoVisualizacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class Empleado_ContratoSueldosController extends Controller
{
    public function __construct(
        private ContratoEmisionService $emisionService,
    ) {}

    public function panel($empleadoId)
    {
        EmpleadoLegajoVisualizacion::autorizarComoEdicion();
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        return $this->responderPanel($empleado);
    }

    public function guardar(Request $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);
        $datos = $this->validarContrato($request, $empleado);
        $datos['empleado_id'] = $empleado->id;
        $datos['nro_orden'] = $this->proximoNroOrden($empleado->id);
        if (empty($datos['dias']) && ! empty($datos['desde_fecha']) && ! empty($datos['hasta_fecha'])) {
            $datos['dias'] = Carbon::parse($datos['desde_fecha'])->diffInDays(Carbon::parse($datos['hasta_fecha'])) + 1;
        }
        Empleado_Contrato_Sueldos::create($datos);

        return $this->responderPanel($empleado, 'Contrato registrado');
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $contrato = Empleado_Contrato_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($contrato->empleado_id);
        $datos = $this->validarContrato($request, $empleado, $contrato->id);
        if (empty($datos['dias']) && ! empty($datos['desde_fecha']) && ! empty($datos['hasta_fecha'])) {
            $datos['dias'] = Carbon::parse($datos['desde_fecha'])->diffInDays(Carbon::parse($datos['hasta_fecha'])) + 1;
        }
        $contrato->update($datos);

        return $this->responderPanel($empleado, 'Contrato actualizado');
    }

    public function eliminar($id)
    {
        can('actualizar-empleado-sueldos');
        $contrato = Empleado_Contrato_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($contrato->empleado_id);
        $contrato->delete();

        return $this->responderPanel($empleado, 'Contrato eliminado');
    }

    public function previsualizar(Request $request, $empleadoId)
    {
        can('emitir-contrato-empleado-sueldos');
        $empleado = Empleado_Sueldos::with(['sindicato', 'categoria', 'lugartrabajo', 'centrocosto'])->findOrFail($empleadoId);
        $datos = $this->validarEmisionRequest($request);
        $contrato = $this->contratoDeEmision($empleado, $datos);

        try {
            $preview = $this->emisionService->previsualizar(
                $empleado,
                $datos['tipo'],
                $contrato,
                $datos['fecha_emision'] ?? null,
                $datos['liquidacion'] ?? null,
                $datos['contrato_plantilla_id'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'html' => $preview['html'],
            'titulo' => $preview['titulo'],
            'plantilla' => $preview['plantilla'],
            'fuente_word' => (bool) ($preview['fuente_word'] ?? false),
        ]);
    }

    public function emitir(Request $request, $empleadoId)
    {
        can('emitir-contrato-empleado-sueldos');
        $empleado = Empleado_Sueldos::with(['sindicato', 'categoria', 'lugartrabajo', 'centrocosto'])->findOrFail($empleadoId);
        $datos = $this->validarEmisionRequest($request);
        $contrato = $this->contratoDeEmision($empleado, $datos);
        $formato = strtolower((string) ($datos['formato'] ?? 'pdf'));

        try {
            $resultado = $this->emisionService->emitir(
                $empleado,
                $datos['tipo'],
                $contrato,
                $datos['fecha_emision'] ?? null,
                $datos['liquidacion'] ?? null,
                $datos['contrato_plantilla_id'] ?? null,
                $formato,
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        if ($request->boolean('descargar', true)) {
            $headers = [];
            if (($resultado['formato'] ?? $formato) === 'pdf') {
                $headers['Content-Type'] = 'application/pdf';
            } else {
                $headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }

            return response()->download(
                $resultado['path'],
                $resultado['emision']->archivo_nombre,
                $headers
            );
        }

        $etiqueta = (($resultado['formato'] ?? $formato) === 'docx') ? 'Word' : 'PDF';

        return $this->responderPanel($empleado, $etiqueta.' emitido: '.$resultado['emision']->archivo_nombre);
    }

    public function descargarEmision($id)
    {
        can('emitir-contrato-empleado-sueldos');
        $emision = Empleado_Contrato_Emision_Sueldos::findOrFail($id);
        $path = $emision->rutaAbsoluta();
        abort_unless(is_file($path), 404);

        $headers = [];
        if (str_ends_with(strtolower((string) $emision->archivo_nombre), '.pdf')
            || str_ends_with(strtolower((string) $emision->archivo_path), '.pdf')) {
            $headers['Content-Type'] = 'application/pdf';
        }

        return response()->download($path, $emision->archivo_nombre, $headers);
    }

    /**
     * @return array{tipo: string, empleado_contrato_id?: int|null, fecha_emision?: string|null, liquidacion?: int|null, contrato_plantilla_id?: int|null, formato?: string}
     */
    private function validarEmisionRequest(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', Rule::in(ContratoDocumentoTipos::keys())],
            'empleado_contrato_id' => ['nullable', 'integer'],
            'fecha_emision' => ['nullable', 'date'],
            'liquidacion' => ['nullable', 'integer'],
            'contrato_plantilla_id' => ['nullable', 'integer', 'exists:contrato_plantilla_sueldos,id'],
            'formato' => ['nullable', Rule::in(['pdf', 'docx'])],
        ]);
    }

    /**
     * @param  array{empleado_contrato_id?: int|null}  $datos
     */
    private function contratoDeEmision(Empleado_Sueldos $empleado, array $datos): ?Empleado_Contrato_Sueldos
    {
        if (empty($datos['empleado_contrato_id'])) {
            return null;
        }

        return Empleado_Contrato_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->whereKey($datos['empleado_contrato_id'])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validarContrato(Request $request, Empleado_Sueldos $empleado, ?int $id = null): array
    {
        $datos = $request->validate([
            'desde_fecha' => ['nullable', 'date'],
            'hasta_fecha' => ['nullable', 'date', 'after_or_equal:desde_fecha'],
            'comentario' => ['nullable', 'string', 'max:160'],
            'modalidad' => ['nullable', 'string', 'max:10'],
            'centrocosto_id' => ['nullable', 'integer', 'exists:centrocosto,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categoria_sueldos,id'],
            'liquidacion' => ['nullable', 'integer'],
            'motivo' => ['nullable', Rule::in(array_keys(Empleado_Contrato_Sueldos::MOTIVOS))],
            'dias' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'empleado_reemplazo_id' => [
                'nullable', 'integer', 'exists:empleado_sueldos,id',
                Rule::notIn([(int) $empleado->id]),
            ],
        ]);

        foreach (['centrocosto_id', 'categoria_id', 'empleado_reemplazo_id', 'liquidacion', 'dias'] as $k) {
            if (array_key_exists($k, $datos) && ($datos[$k] === null || $datos[$k] === '')) {
                $datos[$k] = null;
            }
        }

        return $datos;
    }

    private function proximoNroOrden(int $empleadoId): int
    {
        return (int) Empleado_Contrato_Sueldos::query()
            ->where('empleado_id', $empleadoId)
            ->max('nro_orden') + 1;
    }

    private function responderPanel(Empleado_Sueldos $empleado, ?string $mensaje = null)
    {
        $contratos = Empleado_Contrato_Sueldos::query()
            ->with(['categoria', 'centrocosto', 'empleadoReemplazo'])
            ->where('empleado_id', $empleado->id)
            ->orderBy('nro_orden')
            ->get();

        $emisiones = Empleado_Contrato_Emision_Sueldos::query()
            ->with(['plantilla', 'usuario'])
            ->where('empleado_id', $empleado->id)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $plantillas = Contrato_Plantilla_Sueldos::query()
            ->where('activo', true)
            ->orderBy('tipo')
            ->orderBy('codigo')
            ->get();

        $hayFuenteWord = $plantillas->contains(fn (Contrato_Plantilla_Sueldos $p) => $p->esFuenteWord());

        $html = view('sueldos.empleado.partials.contratos', [
            'empleado' => $empleado,
            'contratos' => $contratos,
            'emisiones' => $emisiones,
            'plantillas' => $plantillas,
            'hayFuenteWord' => $hayFuenteWord,
            'tipos' => ContratoDocumentoTipos::LABELS,
            'motivos' => Empleado_Contrato_Sueldos::MOTIVOS,
            'modalidades' => ContratoModalidadSijp::opciones(),
            'puedeEditar' => can('actualizar-empleado-sueldos', false),
            'puedeEmitir' => can('emitir-contrato-empleado-sueldos', false),
        ])->render();

        return response()->json([
            'html' => $html,
            'mensaje' => $mensaje,
        ]);
    }
}
