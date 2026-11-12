<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Contrato_Plantilla_Asignacion_Sueldos;
use App\Models\Sueldos\Contrato_Plantilla_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Sueldos\ContratoDocumentoTipos;
use App\Support\Sueldos\ContratoModalidadSijp;
use App\Support\Sueldos\ContratoPlantillaTags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class Contrato_Plantilla_SueldosController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('listar-contrato-plantilla-sueldos');

        $plantillas = Contrato_Plantilla_Sueldos::query()
            ->orderBy('tipo')
            ->orderBy('codigo')
            ->get();

        $asignaciones = Contrato_Plantilla_Asignacion_Sueldos::query()
            ->with(['plantilla', 'sindicato', 'empresa'])
            ->orderByDesc('prioridad')
            ->orderBy('tipo')
            ->get();

        return view('sueldos.contrato_plantilla.index', [
            'plantillas' => $plantillas,
            'asignaciones' => $asignaciones,
            'tipos' => ContratoDocumentoTipos::LABELS,
            'modalidades' => ContratoModalidadSijp::opciones(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'tags' => ContratoPlantillaTags::CATALOGO,
        ]);
    }

    public function crear()
    {
        can('crear-contrato-plantilla-sueldos');

        return view('sueldos.contrato_plantilla.crear', [
            'tipos' => ContratoDocumentoTipos::LABELS,
            'tags' => ContratoPlantillaTags::CATALOGO,
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-contrato-plantilla-sueldos');
        $datos = $this->validarPlantilla($request);
        $archivo = $request->file('archivo');

        $plantilla = new Contrato_Plantilla_Sueldos($datos);
        $plantilla->archivo_path = '';
        $plantilla->archivo_nombre = $archivo->getClientOriginalName();
        $plantilla->save();

        $rel = $archivo->storeAs(
            'sueldos/contratos/plantillas/'.$plantilla->id,
            $this->nombreArchivoSeguro($archivo->getClientOriginalName()),
            'local'
        );
        $plantilla->update(['archivo_path' => $rel]);

        return redirect()
            ->route('editar_contrato_plantilla_sueldos', $plantilla->id)
            ->with('mensaje', 'Plantilla creada. Ahora puede cargar reglas de asignación.');
    }

    public function editar($id)
    {
        can('editar-contrato-plantilla-sueldos');
        $data = Contrato_Plantilla_Sueldos::with(['asignaciones.sindicato', 'asignaciones.empresa'])->findOrFail($id);

        return view('sueldos.contrato_plantilla.editar', [
            'data' => $data,
            'tipos' => ContratoDocumentoTipos::LABELS,
            'modalidades' => ContratoModalidadSijp::opciones(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'tags' => ContratoPlantillaTags::CATALOGO,
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-contrato-plantilla-sueldos');
        $plantilla = Contrato_Plantilla_Sueldos::findOrFail($id);
        $datos = $this->validarPlantilla($request, $plantilla->id);
        unset($datos['archivo']);

        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            if ($plantilla->archivo_path) {
                Storage::disk('local')->delete($plantilla->archivo_path);
            }
            $rel = $archivo->storeAs(
                'sueldos/contratos/plantillas/'.$plantilla->id,
                $this->nombreArchivoSeguro($archivo->getClientOriginalName()),
                'local'
            );
            $datos['archivo_path'] = $rel;
            $datos['archivo_nombre'] = $archivo->getClientOriginalName();
            $datos['version'] = (int) $plantilla->version + 1;
        }

        $plantilla->update($datos);

        return redirect()
            ->route('consultar_contrato_plantilla_sueldos')
            ->with('mensaje', 'Plantilla actualizada con éxito');
    }

    public function eliminar($id)
    {
        can('borrar-contrato-plantilla-sueldos');
        $plantilla = Contrato_Plantilla_Sueldos::findOrFail($id);
        if ($plantilla->archivo_path) {
            Storage::disk('local')->delete($plantilla->archivo_path);
        }
        EloquentAuditDeleteSupport::each(
            Contrato_Plantilla_Asignacion_Sueldos::query()->where('contrato_plantilla_id', $plantilla->id)
        );
        $plantilla->delete();

        return redirect()
            ->route('consultar_contrato_plantilla_sueldos')
            ->with('mensaje', 'Plantilla eliminada');
    }

    public function descargar($id)
    {
        can('listar-contrato-plantilla-sueldos');
        $plantilla = Contrato_Plantilla_Sueldos::findOrFail($id);
        $path = $plantilla->rutaAbsoluta();
        abort_unless(is_file($path), 404);

        return response()->download($path, $plantilla->archivo_nombre ?: ($plantilla->codigo.'.docx'));
    }

    public function guardarAsignacion(Request $request)
    {
        can('actualizar-contrato-plantilla-sueldos');
        $datos = $this->validarAsignacion($request);
        Contrato_Plantilla_Asignacion_Sueldos::create($datos);

        return redirect()
            ->back()
            ->with('mensaje', 'Regla de asignación agregada');
    }

    public function eliminarAsignacion($id)
    {
        can('actualizar-contrato-plantilla-sueldos');
        $asig = Contrato_Plantilla_Asignacion_Sueldos::findOrFail($id);
        $asig->delete();

        return redirect()
            ->back()
            ->with('mensaje', 'Regla de asignación eliminada');
    }

    /**
     * @return array<string, mixed>
     */
    private function validarPlantilla(Request $request, ?int $id = null): array
    {
        $reqArchivo = $id ? ['nullable'] : ['required'];
        $datos = $request->validate([
            'codigo' => [
                'required', 'string', 'max:40',
                Rule::unique('contrato_plantilla_sueldos', 'codigo')->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(ContratoDocumentoTipos::keys())],
            'activo' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'archivo' => array_merge($reqArchivo, ['file', 'mimes:doc,docx', 'max:10240']),
        ]);
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function validarAsignacion(Request $request): array
    {
        $datos = $request->validate([
            'contrato_plantilla_id' => ['required', 'integer', 'exists:contrato_plantilla_sueldos,id'],
            'tipo' => ['required', Rule::in(ContratoDocumentoTipos::keys())],
            'modalidad' => ['nullable', 'string', 'max:10'],
            'sindicato_id' => ['nullable', 'integer', 'exists:sindicato_sueldos,id'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
            'prioridad' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $datos['modalidad'] = $datos['modalidad'] !== null && $datos['modalidad'] !== ''
            ? trim((string) $datos['modalidad'])
            : null;
        $datos['sindicato_id'] = ! empty($datos['sindicato_id']) ? (int) $datos['sindicato_id'] : null;
        $datos['empresa_id'] = ! empty($datos['empresa_id']) ? (int) $datos['empresa_id'] : null;
        $datos['prioridad'] = (int) ($datos['prioridad'] ?? 100);
        $datos['activo'] = $request->has('activo') ? $request->boolean('activo') : true;

        // Sindicato vacío desde consulta modal
        if ($request->filled('sindicato_id') && (int) $request->input('sindicato_id') === 0) {
            $datos['sindicato_id'] = null;
        }

        return $datos;
    }

    private function nombreArchivoSeguro(string $nombre): string
    {
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION) ?: 'docx');
        $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $base) ?: 'plantilla';

        return $base.'.'.$ext;
    }
}
