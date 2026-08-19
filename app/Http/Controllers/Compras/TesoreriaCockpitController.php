<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\CashPositionSupport;
use App\Support\Compras\TesoreriaWorkbenchSupport;
use Illuminate\Http\Request;

/**
 * Cockpit / workbench de tesorería: KPIs + grilla unificada SP+IE+PP.
 */
class TesoreriaCockpitController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        if (! can('listar-propuesta-pago', false)
            && ! can('listar-pagoproveedor', false)
            && ! can('listar-ingresos-egresos-caja', false)
            && ! can('listar-solicitud-pago', false)) {
            can('listar-propuesta-pago');
        }

        $empresaId = (int) $request->query('empresa_id', 0);
        $tipo = strtoupper(trim((string) $request->query('tipo', '')));
        if (! in_array($tipo, ['PP', 'SP', 'IE'], true)) {
            $tipo = '';
        }
        $dias = max(14, min(180, (int) $request->query('dias', 60)));

        $resumen = CashPositionSupport::resumir($empresaId > 0 ? $empresaId : null);
        $workbench = TesoreriaWorkbenchSupport::grillaOperativa(
            $empresaId > 0 ? $empresaId : null,
            $tipo !== '' ? $tipo : null,
            $dias,
            150
        );
        $empresa_query = $this->empresaRepository->allFiltrado();

        $accesos = [
            [
                'titulo' => 'Propuesta de pagos',
                'desc' => 'Lote de pagos, aprobación PP y ejecución a OP',
                'ruta' => route('propuesta_pago'),
                'icono' => 'fa-list-alt',
                'can' => can('listar-propuesta-pago', false),
            ],
            [
                'titulo' => 'Órdenes de pago',
                'desc' => 'OP individuales y retenciones',
                'ruta' => route('pagoproveedor'),
                'icono' => 'fa-file-invoice-dollar',
                'can' => can('listar-pagoproveedor', false),
            ],
            [
                'titulo' => 'Aplicar cuenta corriente',
                'desc' => 'NC y pagos a cuenta contra facturas',
                'ruta' => route('aplicacion_cuentacorriente_proveedor'),
                'icono' => 'fa-compress-alt',
                'can' => can('aplicar-cuentacorriente-proveedor', false),
            ],
            [
                'titulo' => 'Ingreso / Egreso',
                'desc' => 'Omnibus de tesorería (TRA, canje, movimientos)',
                'ruta' => route('ingresoegreso'),
                'icono' => 'fa-exchange-alt',
                'can' => can('listar-ingresos-egresos-caja', false),
            ],
            [
                'titulo' => 'Solicitud de pago',
                'desc' => 'SP / gastos / anticipos',
                'ruta' => route('consultar_solicitudpago'),
                'icono' => 'fa-hand-holding-usd',
                'can' => can('listar-solicitud-pago', false),
            ],
            [
                'titulo' => 'Clearing bancario',
                'desc' => 'Match OP ↔ transferencias / extracto IB',
                'ruta' => route('clearing_bancario', $empresaId > 0 ? ['empresa_id' => $empresaId] : []),
                'icono' => 'fa-balance-scale',
                'can' => can('listar-propuesta-pago', false) || can('ejecutar-propuesta-pago', false),
            ],
            [
                'titulo' => 'Cash position',
                'desc' => 'Saldos IB + deuda + forecast 7/15/30',
                'ruta' => route('cash_position', $empresaId > 0 ? ['empresa_id' => $empresaId] : []),
                'icono' => 'fa-chart-line',
                'can' => can('listar-propuesta-pago', false) || can('listar-pagoproveedor', false),
            ],
            [
                'titulo' => 'Interbanking',
                'desc' => 'Saldos, transferencias y conciliación',
                'ruta' => route('interbanking'),
                'icono' => 'fa-university',
                'can' => can('listar-saldo-cuenta-interbanking', false) || can('listar-propuesta-pago', false),
            ],
        ];

        return view('compras.tesoreria_cockpit.index', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresaId,
            'tipo' => $tipo,
            'dias' => $dias,
            'accesos' => $accesos,
            'total_saldos_ib' => $resumen['total_saldos_ib'],
            'total_deuda' => $resumen['total_deuda'],
            'total_propuestas' => $resumen['total_propuestas'],
            'disponible_vs_deuda' => $resumen['disponible_vs_deuda'],
            'forecast' => $resumen['forecast'],
            'filas' => $workbench['filas'],
            'contadores_wb' => $workbench['contadores'],
            'total_monto_wb' => $workbench['total_monto'],
        ]);
    }
}
