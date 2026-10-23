<?php

namespace App\Http\Controllers\Listado;

use App\Http\Controllers\Controller;
use App\Support\Caja\ChequeListadoColumnas;
use App\Support\Caja\IngresoEgresoListadoColumnas;
use App\Support\Compras\ComprobanteProveedorListadoColumnas;
use App\Support\Compras\ProveedorListadoColumnas;
use App\Support\Contable\BienUsoListadoColumnas;
use App\Support\Logistica\SolicitudLogisticaListadoColumnas;
use App\Support\Stock\ArticuloListadoColumnas;
use App\Support\Sueldos\EmpleadoSueldosListadoColumnas;
use App\Support\Ticket\AdministracionTicketListadoColumnas;
use App\Support\Ventas\ClienteListadoColumnas;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
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
            ChequeListadoColumnas::RECURSO => [
                'permiso' => 'listar-cheque',
                'ruta' => 'cheque',
            ],
            ProveedorListadoColumnas::RECURSO => [
                'permiso' => 'listar-proveedor',
                'ruta' => 'proveedor',
            ],
            ClienteListadoColumnas::RECURSO => [
                'permiso' => 'listar-clientes',
                'ruta' => 'cliente',
            ],
            EmpleadoSueldosListadoColumnas::RECURSO => [
                'permiso' => 'listar-empleado-sueldos',
                'ruta' => 'consultar_empleado_sueldos',
            ],
            BienUsoListadoColumnas::RECURSO => [
                'permiso' => 'listar-bien-uso',
                'ruta' => 'bien_uso',
            ],
            SolicitudLogisticaListadoColumnas::RECURSO => [
                'permiso' => 'listar-logistica-solicitud',
                'ruta' => 'logistica_solicitud',
            ],
            'compras.ordencompra' => [
                'permiso' => 'listar-ordencompra',
                'ruta' => 'consultar_ordencompra',
            ],
            'stock.recepcion_proveedor' => [
                'permiso' => 'listar-recepcion-proveedor',
                'ruta' => 'recepcion_proveedor',
            ],
            'presupuesto.capex' => [
                'permiso' => 'listar-capex',
                'ruta' => 'consultar_capex',
            ],
            'presupuesto.partidagasto' => [
                'permiso' => 'listar-partidagasto',
                'ruta' => 'consultar_partidagasto',
            ],
            'caja.cobranza' => [
                'permiso' => 'listar-cobranza',
                'ruta' => 'cobranza',
            ],
            'compras.requisicion' => [
                'permiso' => 'listar-requisicion',
                'ruta' => 'consultar_requisicion',
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
