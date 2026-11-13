<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Support\Sueldos\ConsultaMaestroSueldosCatalogo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ConsultaMaestro_SueldosController extends Controller
{
    public function consulta(Request $request, string $maestro): JsonResponse
    {
        $cfg = $this->config($maestro);
        $this->assertPuedeConsultar($cfg);

        $texto = trim((string) $request->input('consulta', ''));
        $campoNombre = $cfg['campo_nombre'];
        $query = $cfg['model']::query()->orderBy('codigo')->limit(80);
        if ($texto !== '') {
            $query->where(function ($q) use ($texto, $campoNombre) {
                $q->where('codigo', 'like', '%'.$texto.'%')
                    ->orWhere($campoNombre, 'like', '%'.$texto.'%');
            });
        }

        $filas = $query->get(['id', 'codigo', $campoNombre, ...$cfg['extras']]);
        $puedeAbrirAbm = can($cfg['permiso_editar'], false) || can($cfg['permiso_listar'], false);
        $html = '';
        if ($filas->isEmpty()) {
            $html = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($filas as $row) {
                $nombre = (string) ($row->{$campoNombre} ?? '');
                $html .= '<tr>';
                $html .= '<td class="maestro_sueldos_id">'.e((string) $row->id).'</td>';
                $html .= '<td class="codigomaestro_sueldos_fila">'.e((string) $row->codigo).'</td>';
                $html .= '<td class="nombremaestro_sueldos_fila">'.e($nombre).'</td>';
                $html .= '<td class="text-nowrap"><a href="#" class="btn btn-warning btn-sm eligeconsultamaestro_sueldos">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route($cfg['ruta_editar'], [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $html .= '</td></tr>';
            }
        }

        return response()->json(['data' => $html]);
    }

    public function leerPorCodigo(string $maestro, string $codigo): JsonResponse
    {
        $cfg = $this->config($maestro);
        $this->assertPuedeConsultar($cfg);

        $codigo = trim($codigo);
        if ($codigo === '') {
            return response()->json(['error' => 'Código vacío'], 404);
        }

        $model = $cfg['model'];
        /** @var Model|null $row */
        $row = $model::query()->where('codigo', $codigo)->first();
        if ($row === null && ctype_digit($codigo)) {
            $row = $model::query()->where('codigo', (int) $codigo)->first();
        }
        if ($row === null) {
            return response()->json(['error' => $cfg['label'].' no encontrado'], 404);
        }

        return response()->json($this->payload($cfg, $row));
    }

    public function leer(string $maestro, int $id): JsonResponse
    {
        $cfg = $this->config($maestro);
        $this->assertPuedeConsultar($cfg);

        /** @var Model|null $row */
        $row = $cfg['model']::query()->find($id);
        if ($row === null) {
            return response()->json(['error' => $cfg['label'].' no encontrado'], 404);
        }

        return response()->json($this->payload($cfg, $row));
    }

    /**
     * @return array{model: class-string, titulo: string, label: string, campo_nombre: string, extras: list<string>, permiso_listar: string, permiso_editar: string, ruta_editar: string}
     */
    private function config(string $maestro): array
    {
        $cfg = ConsultaMaestroSueldosCatalogo::get($maestro);
        if ($cfg === null) {
            abort(404, 'Maestro no soportado');
        }

        return $cfg;
    }

    /**
     * @param  array{model: class-string, titulo: string, label: string, campo_nombre: string, extras: list<string>, permiso_listar: string, permiso_editar: string, ruta_editar: string}  $cfg
     * @return array<string, mixed>
     */
    private function payload(array $cfg, Model $row): array
    {
        $out = [
            'id' => (int) $row->getAttribute('id'),
            'codigo' => $row->getAttribute('codigo'),
            'nombre' => (string) $row->getAttribute($cfg['campo_nombre']),
        ];
        foreach ($cfg['extras'] as $extra) {
            if (Schema::hasColumn($row->getTable(), $extra) || array_key_exists($extra, $row->getAttributes())) {
                $out[$extra] = $row->getAttribute($extra);
            }
        }

        return $out;
    }

    /**
     * @param  array{permiso_listar: string, permiso_editar: string}  $cfg
     */
    private function assertPuedeConsultar(array $cfg): void
    {
        if (can($cfg['permiso_listar'], false)
            || can($cfg['permiso_editar'], false)
            || can('listar-empleado-sueldos', false)
            || can('editar-empleado-sueldos', false)
            || can('visualizar-empleado-sueldos', false)
            || can('actualizar-empleado-sueldos', false)
            || can('listar-contrato-plantilla-sueldos', false)
            || can('actualizar-contrato-plantilla-sueldos', false)
            || can('crear-contrato-plantilla-sueldos', false)) {
            return;
        }
        abort(403);
    }
}
