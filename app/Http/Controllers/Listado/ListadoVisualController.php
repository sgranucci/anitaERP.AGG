<?php

namespace App\Http\Controllers\Listado;

use App\Http\Controllers\Controller;
use App\Support\Caja\IngresoEgresoListadoColumnas;
use App\Support\Ticket\AdministracionTicketListadoColumnas;
use App\Support\Compras\ComprobanteProveedorListadoColumnas;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Stock\ArticuloListadoColumnas;
use Illuminate\Http\Request;

class ListadoVisualController extends Controller
{
    public function quitarGrafico(Request $request)
    {
        $recurso = (string) $request->query('recurso', '');
        $pantalla = $this->pantalla($recurso);
        if ($pantalla === null) {
            abort(404);
        }
        can($pantalla['permiso']);

        if ($request->filled('vista_id')) {
            $vista = ListadoVistaSupport::findParaUsuario(
                (int) $request->query('vista_id'),
                $recurso,
                (int) auth()->id()
            );
            if ($vista && (int) $vista->usuario_id === (int) auth()->id() && is_array($vista->filtros_json)) {
                $json = $vista->filtros_json;
                $json['grafico'] = ListadoVisualSupport::graficoVacio();
                $json['graficos'] = [];
                $vista->filtros_json = $json;
                $vista->save();
            }
        }

        $qs = $request->query();
        unset($qs['grafico'], $qs['graficos'], $qs['grafico_click'], $qs['grafico_click_dimension'], $qs['recurso']);
        $qs['grafico_off'] = 1;

        return redirect()->route($pantalla['ruta'], $qs)->with('mensaje', 'Se quitó el gráfico.');
    }

    /**
     * @return array{permiso: string, ruta: string}|null
     */
    private function pantalla(string $recurso): ?array
    {
        return match ($recurso) {
            ComprobanteProveedorListadoColumnas::RECURSO => [
                'permiso' => 'listar-comprobante-proveedor',
                'ruta' => 'comprobante_proveedor',
            ],
            ArticuloListadoColumnas::RECURSO => [
                'permiso' => 'listar-articulos',
                'ruta' => 'articulo',
            ],
            IngresoEgresoListadoColumnas::RECURSO => [
                'permiso' => 'listar-ingresos-egresos-caja',
                'ruta' => 'ingresoegreso',
            ],
            AdministracionTicketListadoColumnas::RECURSO => [
                'permiso' => 'listar-ticket',
                'ruta' => 'consulta_administracion_ticket',
            ],
            default => null,
        };
    }
}
