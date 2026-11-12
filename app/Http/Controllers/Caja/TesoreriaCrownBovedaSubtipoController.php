<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTesoreriaCrownBovedaSubtipo;
use App\Models\Caja\TesoreriaCrownBovedaSubtipo;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TesoreriaCrownBovedaSubtipoController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda-subtipo');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $tipoId = (int) $request->input('tipo_id', 0);
        $filtrosQuery = $this->filtrosQuery($sala?->id, $tipoId);
        $subtipos = $this->consultaSala((int) ($sala->id ?? 0), $tipoId);

        return view('caja.tesoreria_crown.boveda_subtipo.index', [
            'salas' => $salas,
            'sala' => $sala,
            'subtipos' => $subtipos,
            'tipoId' => $tipoId,
            'filtrosQuery' => $filtrosQuery,
            'tipoFiltro' => $tipoId > 0
                ? TesoreriaCrownBovedaTipo::query()
                    ->where('sala_id', (int) ($sala->id ?? 0))
                    ->whereKey($tipoId)
                    ->first()
                : null,
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda-subtipo');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        if (! $sala) {
            return redirect()->route('tesoreria_crown_boveda_subtipo');
        }
        $tipoId = (int) $request->input('tipo_id', 0);
        $coleccion = $this->consultaSala((int) $sala->id, $tipoId);
        $sala->loadMissing('empresas');
        $nombreEmpresa = $sala->empresas->nombre ?? config('app.empresa');
        foreach ($coleccion as $fila) {
            $fila->nombreempresa = $nombreEmpresa;
        }
        $tipoFiltro = $tipoId > 0
            ? TesoreriaCrownBovedaTipo::query()
                ->where('sala_id', (int) $sala->id)
                ->whereKey($tipoId)
                ->first()
            : null;

        $armado = [
            'titulo' => 'Subtipos de movimiento',
            'subtitulo' => $this->subtitulo($sala, $tipoFiltro),
            'archivo' => 'subtipos_movimiento_tesoreria_crown',
            'titulo_corto' => 'Subtipos movimiento',
            'columnas' => [
                ['label' => 'Tipo', 'ancho' => 36, 'wrap' => true],
                ['label' => 'Código', 'ancho' => 14, 'texto' => true],
                ['label' => 'Nombre', 'ancho' => 36, 'wrap' => true],
                ['label' => 'Billetaje', 'ancho' => 16],
                ['label' => 'Activo', 'ancho' => 12],
            ],
            'filas' => $coleccion->map(function (TesoreriaCrownBovedaSubtipo $subtipo) {
                return [
                    $subtipo->tipo?->etiqueta() ?? '',
                    $subtipo->codigo,
                    $subtipo->nombre,
                    ($subtipo->mueve_billetaje ?? true) ? 'Sí' : 'No',
                    $subtipo->activo ? 'Sí' : 'No',
                ];
            })->all(),
            'coleccion' => $coleccion,
        ];

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, $armado);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_boveda_subtipo', $this->filtrosQuery($sala?->id, $tipoId));
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-subtipo');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }

        $tipoId = (int) old('tipo_id', $request->input('tipo_id', 0));
        $tipo = $tipoId > 0
            ? TesoreriaCrownBovedaTipo::query()->where('sala_id', $sala->id)->whereKey($tipoId)->first()
            : null;

        return view('caja.tesoreria_crown.boveda_subtipo.form', [
            'subtipo' => new TesoreriaCrownBovedaSubtipo([
                'sala_id' => $sala->id,
                'tipo_id' => $tipo?->id,
                'activo' => true,
                'mueve_billetaje' => true,
            ]),
            'sala' => $sala,
            'tipo' => $tipo,
            'accion' => route('guardar_tesoreria_crown_boveda_subtipo'),
            'metodo' => 'POST',
        ]);
    }

    public function guardar(ValidacionTesoreriaCrownBovedaSubtipo $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-subtipo');

        $datos = $this->datos($request);
        TesoreriaCrownBovedaSubtipo::query()->create($datos);

        return redirect()
            ->route('tesoreria_crown_boveda_subtipo', [
                'sala_id' => $datos['sala_id'],
                'tipo_id' => $datos['tipo_id'],
            ])
            ->with('mensaje', 'Subtipo creado.');
    }

    public function editar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-boveda-subtipo');

        $subtipo = TesoreriaCrownBovedaSubtipo::query()->with('tipo')->findOrFail($id);
        $sala = TesoreriaCrownSalaSupport::exigida((int) $subtipo->sala_id);

        return view('caja.tesoreria_crown.boveda_subtipo.form', [
            'subtipo' => $subtipo,
            'sala' => $sala,
            'tipo' => $subtipo->tipo,
            'accion' => route('actualizar_tesoreria_crown_boveda_subtipo', $id),
            'metodo' => 'PUT',
        ]);
    }

    public function actualizar(ValidacionTesoreriaCrownBovedaSubtipo $request, $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-boveda-subtipo');

        $subtipo = TesoreriaCrownBovedaSubtipo::query()->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $subtipo->sala_id);
        $datos = $this->datos($request);
        $datos['codigo'] = $subtipo->codigo;
        $datos['tipo_id'] = (int) $subtipo->tipo_id;
        $datos['sala_id'] = (int) $subtipo->sala_id;
        $subtipo->update($datos);

        return redirect()
            ->route('tesoreria_crown_boveda_subtipo', [
                'sala_id' => $subtipo->sala_id,
                'tipo_id' => $subtipo->tipo_id,
            ])
            ->with('mensaje', 'Subtipo actualizado.');
    }

    public function eliminar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-boveda-subtipo');

        $subtipo = TesoreriaCrownBovedaSubtipo::query()->findOrFail($id);
        $salaId = (int) $subtipo->sala_id;
        $tipoId = (int) $subtipo->tipo_id;
        TesoreriaCrownSalaSupport::exigida($salaId);
        if ($subtipo->movimientos()->exists()) {
            return redirect()
                ->route('tesoreria_crown_boveda_subtipo', [
                    'sala_id' => $salaId,
                    'tipo_id' => $tipoId,
                ])
                ->with('mensaje-error', 'El subtipo tiene movimientos. No se puede eliminar.');
        }
        $subtipo->delete();

        return redirect()
            ->route('tesoreria_crown_boveda_subtipo', [
                'sala_id' => $salaId,
                'tipo_id' => $tipoId,
            ])
            ->with('mensaje', 'Subtipo eliminado.');
    }

    /**
     * @return array<string, int>
     */
    private function filtrosQuery(?int $salaId, int $tipoId = 0): array
    {
        $out = [];
        if ($salaId && $salaId > 0) {
            $out['sala_id'] = $salaId;
        }
        if ($tipoId > 0) {
            $out['tipo_id'] = $tipoId;
        }

        return $out;
    }

    private function subtitulo(?\App\Models\Configuracion\Sala $sala, ?TesoreriaCrownBovedaTipo $tipo): string
    {
        $partes = [];
        if ($sala) {
            $partes[] = 'Sala '.$sala->codigo.' — '.$sala->nombre;
        }
        if ($tipo) {
            $partes[] = 'Tipo '.$tipo->etiqueta();
        }

        return implode(' · ', $partes);
    }

    /**
     * @return Collection<int, TesoreriaCrownBovedaSubtipo>
     */
    private function consultaSala(int $salaId, int $tipoId = 0): Collection
    {
        if ($salaId <= 0) {
            return collect();
        }

        $query = TesoreriaCrownBovedaSubtipo::query()
            ->with('tipo')
            ->where('sala_id', $salaId);
        if ($tipoId > 0) {
            $query->where('tipo_id', $tipoId);
        }

        return $query
            ->orderBy(
                TesoreriaCrownBovedaTipo::query()
                    ->select('codigo')
                    ->whereColumn('tesoreria_crown_boveda_tipo.id', 'tesoreria_crown_boveda_subtipo.tipo_id')
            )
            ->orderBy('codigo')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(ValidacionTesoreriaCrownBovedaSubtipo $request): array
    {
        $datos = $request->validated();
        $sala = TesoreriaCrownSalaSupport::exigida((int) $datos['sala_id']);
        $tipo = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $sala->id)
            ->whereKey((int) $datos['tipo_id'])
            ->firstOrFail();

        return [
            'sala_id' => (int) $sala->id,
            'tipo_id' => (int) $tipo->id,
            'codigo' => strtoupper(trim((string) $datos['codigo'])),
            'nombre' => trim((string) $datos['nombre']),
            'activo' => TesoreriaCrownAcceso::marcado($datos['activo']),
            'mueve_billetaje' => TesoreriaCrownAcceso::marcado($datos['mueve_billetaje'] ?? 1),
        ];
    }
}
