<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Rubro;
use App\Support\Stock\InterformingSifabSupport;
use App\Support\Stock\SifabMaestroConsultaCatalogo;
use Illuminate\Http\Request;

/**
 * Consulta modal genérica de maestros SIFAB (artículo INTERFORMING).
 */
class SifabMaestroConsultaController extends Controller
{
    public function consulta(Request $request, string $recurso)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        if (! SifabMaestroConsultaCatalogo::puedeConsultar()) {
            abort(403);
        }

        $def = SifabMaestroConsultaCatalogo::def($recurso);
        if ($def === null) {
            abort(404);
        }

        $consulta = trim((string) ($request->get('consulta') ?? ''));
        $query = SifabMaestroConsultaCatalogo::queryBase($recurso);
        if ($query === null) {
            abort(404);
        }

        if ($recurso === 'subrubro') {
            $rubroInterno = trim((string) ($request->get('rubro_codigo_interno') ?? ''));
            if ($rubroInterno !== '' && preg_match('/^-?\d+$/', $rubroInterno)) {
                $rubroId = Rubro::query()
                    ->where('codigo_interno_sifab', (int) $rubroInterno)
                    ->value('id');
                if ($rubroId) {
                    $query->where('rubro_id', (int) $rubroId);
                }
            }
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('nombre', 'like', '%'.$consulta.'%')
                    ->orWhere('codigo', 'like', '%'.$consulta.'%');
                if (preg_match('/^-?\d+$/', $consulta)) {
                    $q->orWhere('codigo_interno_sifab', (int) $consulta)
                        ->orWhere('id', (int) $consulta);
                }
            });
        }

        $data = $query
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->limit(200)
            ->get(['id', 'codigo_interno_sifab', 'codigo', 'nombre']);

        $puedeAbrirAbm = SifabMaestroConsultaCatalogo::puedeAbrirAbm($recurso);
        $output = ['data' => ''];

        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e((string) $row->id).'</td>';
                $output['data'] .= '<td class="codigo-interno">'.e((string) ($row->codigo_interno_sifab ?? '')).'</td>';
                $output['data'] .= '<td class="codigo">'.e((string) ($row->codigo ?? '')).'</td>';
                $output['data'] .= '<td class="nombre">'.e((string) $row->nombre).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultasifabmaestro">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $urlConsulta = route($def['edit_route'], [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return response()->json($output, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function resolver(Request $request, string $recurso, string $codigo)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        if (! SifabMaestroConsultaCatalogo::puedeConsultar()) {
            abort(403);
        }

        $def = SifabMaestroConsultaCatalogo::def($recurso);
        if ($def === null) {
            abort(404);
        }

        $codigo = trim(urldecode($codigo));
        if ($codigo === '' || strtoupper($codigo) === 'NULL') {
            return response()->json(['error' => 'Código vacío'], 404);
        }

        $query = SifabMaestroConsultaCatalogo::queryBase($recurso);
        if ($query === null) {
            abort(404);
        }

        if ($recurso === 'subrubro') {
            $rubroInterno = trim((string) ($request->get('rubro_codigo_interno') ?? ''));
            if ($rubroInterno !== '' && preg_match('/^-?\d+$/', $rubroInterno)) {
                $rubroId = Rubro::query()
                    ->where('codigo_interno_sifab', (int) $rubroInterno)
                    ->value('id');
                if ($rubroId) {
                    $query->where('rubro_id', (int) $rubroId);
                }
            }
        }

        $row = null;
        if (preg_match('/^-?\d+$/', $codigo)) {
            $row = (clone $query)->where('codigo_interno_sifab', (int) $codigo)->first();
        }
        if ($row === null) {
            $row = (clone $query)->where('codigo', $codigo)->first();
        }
        if ($row === null && preg_match('/^-?\d+$/', $codigo)) {
            $row = (clone $query)->where('id', (int) $codigo)->first();
        }

        if (! $row) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }

        return response()->json([
            'id' => (int) $row->id,
            'codigo_interno_sifab' => $row->codigo_interno_sifab !== null ? (string) $row->codigo_interno_sifab : null,
            'codigo' => $row->codigo !== null ? (string) $row->codigo : null,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) $row->nombre,
        ]);
    }
}
