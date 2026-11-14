<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownBovedaVigenciaSupport;
use App\Support\Caja\TesoreriaCrownKardexValoresSupport;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TesoreriaCrownBovedaTipoController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda-tipo');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtroVigencia = (string) $request->input('filtro_vigencia', 'vigentes');
        $fechaVigencia = TesoreriaCrownBovedaVigenciaSupport::normalizarFecha(
            $request->input('fecha_vigencia')
        );
        $filtrosQuery = $this->filtrosQuery($sala?->id, $filtroVigencia, $fechaVigencia);
        $tipos = $this->consultaSala((int) ($sala->id ?? 0), $filtroVigencia, $fechaVigencia);

        return view('caja.tesoreria_crown.boveda_tipo.index', [
            'salas' => $salas,
            'sala' => $sala,
            'tipos' => $tipos,
            'filtrosQuery' => $filtrosQuery,
            'filtroVigencia' => $filtroVigencia,
            'fechaVigencia' => $fechaVigencia,
            'operacionesMap' => $this->operacionesMap(),
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda-tipo');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        if (! $sala) {
            return redirect()->route('tesoreria_crown_boveda_tipo');
        }
        $filtroVigencia = (string) $request->input('filtro_vigencia', 'vigentes');
        $fechaVigencia = TesoreriaCrownBovedaVigenciaSupport::normalizarFecha(
            $request->input('fecha_vigencia')
        );
        $coleccion = $this->consultaSala((int) $sala->id, $filtroVigencia, $fechaVigencia);
        $sala->loadMissing('empresas');
        $nombreEmpresa = $sala->empresas->nombre ?? config('app.empresa');
        foreach ($coleccion as $fila) {
            $fila->nombreempresa = $nombreEmpresa;
        }
        $ops = $this->operacionesMap();

        $armado = [
            'titulo' => 'Tipos de movimiento',
            'subtitulo' => $this->subtitulo($sala),
            'archivo' => 'tipos_movimiento_tesoreria_crown',
            'titulo_corto' => 'Tipos movimiento',
            'columnas' => [
                ['label' => 'Código', 'ancho' => 12, 'texto' => true],
                ['label' => 'Abrev.', 'ancho' => 12, 'texto' => true],
                ['label' => 'Nombre', 'ancho' => 32, 'wrap' => true],
                ['label' => 'Efecto', 'ancho' => 14],
                ['label' => 'Operación', 'ancho' => 18, 'wrap' => true],
                ['label' => 'Rep. propina', 'ancho' => 12],
                ['label' => 'Vig. desde', 'ancho' => 12],
                ['label' => 'Vig. hasta', 'ancho' => 12],
                ['label' => 'Activo', 'ancho' => 10],
            ],
            'filas' => $coleccion->map(function (TesoreriaCrownBovedaTipo $tipo) use ($ops) {
                return [
                    (string) $tipo->codigo,
                    $tipo->abreviatura,
                    $tipo->nombre,
                    TesoreriaCrownBovedaSupport::efectoTexto((string) $tipo->signo),
                    $ops[(string) $tipo->operacion] ?? (string) $tipo->operacion,
                    ($tipo->para_reporte_propina ?? false) ? 'Sí' : 'No',
                    $tipo->vigente_desde?->format('d/m/Y') ?? '',
                    $tipo->vigente_hasta?->format('d/m/Y') ?? '',
                    $tipo->activo ? 'Sí' : 'No',
                ];
            })->all(),
            'coleccion' => $coleccion,
        ];

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, $armado);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_boveda_tipo', $this->filtrosQuery($sala?->id));
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-tipo');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }

        return view('caja.tesoreria_crown.boveda_tipo.form', [
            'tipo' => new TesoreriaCrownBovedaTipo([
                'sala_id' => $sala->id,
                'signo' => '0',
                'operacion' => '0',
                'activo' => true,
                'para_reporte_propina' => false,
            ]),
            'sala' => $sala,
            'operaciones' => TesoreriaCrownKardexValoresSupport::operaciones(),
            'accion' => route('guardar_tesoreria_crown_boveda_tipo'),
            'metodo' => 'POST',
        ]);
    }

    public function guardar(ValidacionTesoreriaCrownBovedaTipo $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda-tipo');

        $datos = $this->datos($request);
        TesoreriaCrownBovedaTipo::query()->create($datos);

        return redirect()
            ->route('tesoreria_crown_boveda_tipo', ['sala_id' => $datos['sala_id']])
            ->with('mensaje', 'Tipo de movimiento creado.');
    }

    public function editar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-boveda-tipo');

        $tipo = TesoreriaCrownBovedaTipo::query()->findOrFail($id);
        $sala = TesoreriaCrownSalaSupport::exigida((int) $tipo->sala_id);

        return view('caja.tesoreria_crown.boveda_tipo.form', [
            'tipo' => $tipo,
            'sala' => $sala,
            'operaciones' => TesoreriaCrownKardexValoresSupport::operaciones(),
            'accion' => route('actualizar_tesoreria_crown_boveda_tipo', $id),
            'metodo' => 'PUT',
        ]);
    }

    public function actualizar(ValidacionTesoreriaCrownBovedaTipo $request, $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-boveda-tipo');

        $tipo = TesoreriaCrownBovedaTipo::query()->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $tipo->sala_id);
        $datos = $this->datos($request);
        $datos['codigo'] = (int) $tipo->codigo;
        $datos['sala_id'] = (int) $tipo->sala_id;
        $tipo->update($datos);

        return redirect()
            ->route('tesoreria_crown_boveda_tipo', ['sala_id' => $tipo->sala_id])
            ->with('mensaje', 'Tipo de movimiento actualizado.');
    }

    public function eliminar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-boveda-tipo');

        $tipo = TesoreriaCrownBovedaTipo::query()->findOrFail($id);
        $salaId = (int) $tipo->sala_id;
        TesoreriaCrownSalaSupport::exigida($salaId);
        if ($tipo->movimientos()->exists()) {
            return redirect()
                ->route('tesoreria_crown_boveda_tipo', ['sala_id' => $salaId])
                ->with('mensaje-error', 'El tipo tiene movimientos. No se puede eliminar.');
        }
        if ($tipo->subtipos()->exists()) {
            return redirect()
                ->route('tesoreria_crown_boveda_tipo', ['sala_id' => $salaId])
                ->with('mensaje-error', 'El tipo tiene subtipos. Eliminá o reasigná los subtipos primero.');
        }
        $tipo->delete();

        return redirect()
            ->route('tesoreria_crown_boveda_tipo', ['sala_id' => $salaId])
            ->with('mensaje', 'Tipo de movimiento eliminado.');
    }

    /**
     * @return array<string, int>
     */
    private function filtrosQuery(?int $salaId, string $filtroVigencia = 'vigentes', ?string $fechaVigencia = null): array
    {
        $out = [];
        if ($salaId && $salaId > 0) {
            $out['sala_id'] = $salaId;
        }
        if ($filtroVigencia !== '' && $filtroVigencia !== 'todos') {
            $out['filtro_vigencia'] = $filtroVigencia;
        }
        if ($fechaVigencia) {
            $out['fecha_vigencia'] = $fechaVigencia;
        }

        return $out;
    }

    private function subtitulo(?\App\Models\Configuracion\Sala $sala): string
    {
        if (! $sala) {
            return '';
        }

        return 'Sala '.$sala->codigo.' — '.$sala->nombre;
    }

    /**
     * @return Collection<int, TesoreriaCrownBovedaTipo>
     */
    private function consultaSala(
        int $salaId,
        string $filtroVigencia = 'vigentes',
        ?string $fechaVigencia = null
    ): Collection {
        if ($salaId <= 0) {
            return collect();
        }
        $fecha = TesoreriaCrownBovedaVigenciaSupport::normalizarFecha($fechaVigencia);
        $q = TesoreriaCrownBovedaTipo::query()->where('sala_id', $salaId);
        TesoreriaCrownBovedaVigenciaSupport::aplicarFiltroAbm($q, $filtroVigencia, $fecha);

        return $q->orderBy('codigo')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(ValidacionTesoreriaCrownBovedaTipo $request): array
    {
        $datos = $request->validated();
        $sala = TesoreriaCrownSalaSupport::exigida((int) $datos['sala_id']);

        return [
            'sala_id' => (int) $sala->id,
            'codigo' => (int) $datos['codigo'],
            'abreviatura' => strtoupper(trim((string) $datos['abreviatura'])),
            'nombre' => trim((string) $datos['nombre']),
            'signo' => (string) $datos['signo'],
            'operacion' => (string) $datos['operacion'],
            'activo' => TesoreriaCrownAcceso::marcado($datos['activo']),
            'para_reporte_propina' => TesoreriaCrownAcceso::marcado($datos['para_reporte_propina'] ?? 0),
            'vigente_desde' => $datos['vigente_desde'] ?? null,
            'vigente_hasta' => $datos['vigente_hasta'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function operacionesMap(): array
    {
        $map = [];
        foreach (TesoreriaCrownKardexValoresSupport::operaciones() as $op) {
            $map[(string) $op['codigo']] = (string) $op['nombre'];
        }

        return $map;
    }
}
