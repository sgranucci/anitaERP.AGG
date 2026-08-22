<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEmpleadoSancion_Sueldos;
use App\Models\Sueldos\Empleado_Sancion_Archivo_Sueldos;
use App\Models\Sueldos\Empleado_Sancion_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Tipo_Sancion_Sueldos;
use App\Services\Sueldos\SancionNovedadSyncService;
use App\Support\Sueldos\EmpleadoSancionSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Empleado_SancionSueldosController extends Controller
{
    public function __construct(private SancionNovedadSyncService $novedadSync)
    {
    }

    public function panel($empleadoId)
    {
        $this->assertPuedeVer();
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        return $this->responderPanel($empleado);
    }

    public function guardar(ValidacionEmpleadoSancion_Sueldos $request, $empleadoId)
    {
        can('crear-sancion-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);
        $datos = $this->normalizar($request, $empleado, null);
        $datos['estado'] = EmpleadoSancionSupport::ESTADO_BORRADOR;
        $datos['usuario_id'] = $this->usuarioId();
        $sancion = Empleado_Sancion_Sueldos::create($datos);
        $this->guardarArchivos($request, $sancion);
        $this->novedadSync->sincronizar($sancion->fresh(['tipo.concepto', 'empleado']));

        return $this->responderPanel($empleado, 'Sanción registrada con éxito');
    }

    public function actualizar(ValidacionEmpleadoSancion_Sueldos $request, $id)
    {
        can('actualizar-sancion-empleado-sueldos');
        $sancion = Empleado_Sancion_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($sancion->empleado_id);
        if (! $sancion->esEditable()) {
            return $this->responderPanel($empleado, 'La sanción no admite edición en este estado', true);
        }
        $sancion->update($this->normalizar($request, $empleado, $sancion));
        $this->guardarArchivos($request, $sancion);
        $this->novedadSync->sincronizar($sancion->fresh(['tipo.concepto', 'empleado']));

        return $this->responderPanel($empleado, 'Sanción actualizada con éxito');
    }

    public function transicion(Request $request, $id)
    {
        can('actualizar-sancion-empleado-sueldos');
        $sancion = Empleado_Sancion_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($sancion->empleado_id);
        $accion = (string) $request->input('accion', '');
        $hoy = Carbon::now()->toDateString();

        switch ($accion) {
            case 'notificar':
                $sancion->estado = EmpleadoSancionSupport::ESTADO_NOTIFICADA;
                $sancion->fecha_notificacion = $sancion->fecha_notificacion ?: $hoy;
                break;
            case 'descargo':
                $texto = trim((string) $request->input('descargo_texto', ''));
                if ($texto === '') {
                    return $this->responderPanel($empleado, 'Debe cargar el descargo', true);
                }
                $sancion->estado = EmpleadoSancionSupport::ESTADO_CON_DESCARGO;
                $sancion->descargo_texto = $texto;
                $sancion->descargo_fecha = $request->input('descargo_fecha') ?: $hoy;
                break;
            case 'firmar':
                $sancion->estado = EmpleadoSancionSupport::ESTADO_FIRME;
                $sancion->resolucion_texto = trim((string) $request->input('resolucion_texto', '')) ?: $sancion->resolucion_texto;
                $sancion->resolucion_fecha = $request->input('resolucion_fecha') ?: $hoy;
                if (! $sancion->fecha_recepcion) {
                    $sancion->fecha_recepcion = $hoy;
                }
                break;
            case 'impugnar':
                $sancion->estado = EmpleadoSancionSupport::ESTADO_IMPUGNADA;
                $sancion->resolucion_texto = trim((string) $request->input('resolucion_texto', '')) ?: $sancion->resolucion_texto;
                $sancion->resolucion_fecha = $hoy;
                break;
            case 'anular':
                can('anular-sancion-empleado-sueldos');
                $sancion->estado = EmpleadoSancionSupport::ESTADO_ANULADA;
                $sancion->resolucion_texto = trim((string) $request->input('resolucion_texto', '')) ?: $sancion->resolucion_texto;
                $sancion->resolucion_fecha = $hoy;
                break;
            default:
                return $this->responderPanel($empleado, 'Acción no reconocida', true);
        }

        $sancion->save();
        $this->novedadSync->sincronizar($sancion->fresh(['tipo.concepto', 'empleado']));

        return $this->responderPanel($empleado, 'Estado actualizado: '.$sancion->estadoLabel());
    }

    public function eliminar(Request $request, $id)
    {
        can('anular-sancion-empleado-sueldos');
        $sancion = Empleado_Sancion_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($sancion->empleado_id);
        $sancionId = (int) $sancion->id;
        foreach ($sancion->archivos as $archivo) {
            $this->borrarArchivoFisico($archivo);
            $archivo->delete();
        }
        $sancion->delete();
        $this->novedadSync->anularPorSancion($sancionId);

        return $this->responderPanel($empleado, 'Sanción eliminada');
    }

    public function notificacion($id)
    {
        can('imprimir-sancion-sueldos');
        $sancion = Empleado_Sancion_Sueldos::with(['empleado.empresa', 'tipo', 'motivo'])->findOrFail($id);
        $view = view('sueldos.empleado_sancion.notificacion', ['sancion' => $sancion])->render();
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $nombre = 'notificacion_sancion_'.$sancion->id.'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($view)->save($path.'/'.$nombre);

        return response()->download($path.'/'.$nombre);
    }

    public function descargarArchivo($id)
    {
        $this->assertPuedeVer();
        $archivo = Empleado_Sancion_Archivo_Sueldos::findOrFail($id);
        $disk = Storage::disk('public');
        if (! $disk->exists($archivo->path)) {
            abort(404);
        }

        return $disk->download($archivo->path, $archivo->nombre_original);
    }

    public function quitarArchivo($id)
    {
        can('actualizar-sancion-empleado-sueldos');
        $archivo = Empleado_Sancion_Archivo_Sueldos::findOrFail($id);
        $sancion = Empleado_Sancion_Sueldos::findOrFail($archivo->sancion_id);
        $empleado = Empleado_Sueldos::findOrFail($sancion->empleado_id);
        $this->borrarArchivoFisico($archivo);
        $archivo->delete();

        return $this->responderPanel($empleado, 'Archivo quitado');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizar(ValidacionEmpleadoSancion_Sueldos $request, Empleado_Sueldos $empleado, ?Empleado_Sancion_Sueldos $existente): array
    {
        $tipo = Tipo_Sancion_Sueldos::findOrFail($request->input('tipo_sancion_id'));
        $tipoDias = $request->input('tipo_dias') ?: $tipo->tipo_dias ?: 'corridos';
        $desde = $request->filled('fecha_desde') ? Carbon::parse($request->input('fecha_desde')) : null;
        $hasta = $request->filled('fecha_hasta') ? Carbon::parse($request->input('fecha_hasta')) : null;
        $dias = $request->input('cant_dias');
        if (($dias === null || $dias === '') && $desde && $hasta) {
            $dias = EmpleadoSancionSupport::contarDias($desde, $hasta, $tipoDias);
        }
        if ($tipo->tope_dias && (int) $dias > (int) $tipo->tope_dias) {
            $dias = (int) $tipo->tope_dias;
        }

        return [
            'empleado_id' => $empleado->id,
            'tipo_sancion_id' => $tipo->id,
            'motivo_sancion_id' => (int) $request->input('motivo_sancion_id'),
            'fecha_hecho' => $request->input('fecha_hecho'),
            'fecha_desde' => $desde?->toDateString(),
            'fecha_hasta' => $hasta?->toDateString(),
            'cant_dias' => (int) ($dias ?? 0),
            'tipo_dias' => $tipoDias,
            'importe_perdida' => (float) ($request->input('importe_perdida') ?: 0),
            'fecha_notificacion' => $request->input('fecha_notificacion') ?: $existente?->fecha_notificacion,
            'fecha_recepcion' => $request->input('fecha_recepcion') ?: $existente?->fecha_recepcion,
            'comentario' => trim((string) $request->input('comentario')),
            'descargo_texto' => $request->input('descargo_texto') ?: $existente?->descargo_texto,
            'descargo_fecha' => $request->input('descargo_fecha') ?: $existente?->descargo_fecha,
            'resolucion_texto' => $request->input('resolucion_texto') ?: $existente?->resolucion_texto,
            'resolucion_fecha' => $request->input('resolucion_fecha') ?: $existente?->resolucion_fecha,
        ];
    }

    private function guardarArchivos(Request $request, Empleado_Sancion_Sueldos $sancion): void
    {
        $files = $request->file('archivos', []);
        if (! is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $dir = 'archivos/empleados/'.$sancion->empleado_id.'/sanciones/'.$sancion->id;
            $nombre = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs($dir, $nombre, 'public');
            Empleado_Sancion_Archivo_Sueldos::create([
                'sancion_id' => $sancion->id,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $path,
                'usuario_id' => $this->usuarioId(),
            ]);
        }
    }

    private function borrarArchivoFisico(Empleado_Sancion_Archivo_Sueldos $archivo): void
    {
        $disk = Storage::disk('public');
        if ($archivo->path && $disk->exists($archivo->path)) {
            $disk->delete($archivo->path);
        }
    }

    private function responderPanel(Empleado_Sueldos $empleado, ?string $mensaje = null, bool $error = false)
    {
        $sanciones = $empleado->sanciones()->with(['tipo', 'motivo', 'archivos', 'novedad'])->get();
        $resumen = EmpleadoSancionSupport::resumenEmpleado((int) $empleado->id);
        $html = view('sueldos.empleado.partials.sanciones', [
            'empleado' => $empleado,
            'sanciones' => $sanciones,
            'resumen' => $resumen,
            'puedeEditar' => can('crear-sancion-empleado-sueldos', false) || can('actualizar-sancion-empleado-sueldos', false),
            'puedeAnular' => can('anular-sancion-empleado-sueldos', false),
            'puedeImprimir' => can('imprimir-sancion-sueldos', false),
        ])->render();

        return response()->json([
            'html' => $html,
            'mensaje' => $mensaje,
            'error' => $error,
            'resumen' => $resumen,
        ]);
    }

    private function assertPuedeVer(): void
    {
        if (can('listar-sancion-empleado-sueldos', false) || can('editar-empleado-sueldos', false)) {
            return;
        }
        abort(403);
    }

    private function usuarioId(): ?int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : null;
    }
}
