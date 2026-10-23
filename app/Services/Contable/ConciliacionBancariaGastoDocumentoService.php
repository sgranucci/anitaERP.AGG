<?php

namespace App\Services\Contable;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingMovimiento;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Services\Caja\IngresoEgresoService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaGastoDocumentoSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaHashSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaResultadoCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Genera el egreso de tesorería y el ICO de los gastos bancarios del mes.
 */
class ConciliacionBancariaGastoDocumentoService
{
    public function __construct(
        private readonly IngresoEgresoService $ingresoEgresoService,
    ) {
    }

    /**
     * @param  array<string, int|string>  $overridesSaldo
     * @return array<string, mixed>
     */
    public function borrador(
        int $empresaId,
        Cuentacaja $cuentacaja,
        int $mes,
        int $anio,
        array $overridesSaldo = [],
    ): array {
        return ConciliacionBancariaGastoDocumentoSupport::armar(
            $this->movimientosDelMes($empresaId, (string) $cuentacaja->cuenta_interbanking, $mes, $anio, (int) $cuentacaja->id),
            $empresaId,
            $cuentacaja,
            $mes,
            $anio,
            $overridesSaldo,
        );
    }

    /**
     * @return array{ok: bool, mensaje: string, caja_movimiento_id?: int, numero?: string, url?: string}
     */
    public function generar(int $empresaId, int $cuentacajaId, int $mes, int $anio): array
    {
        $cuentacaja = Cuentacaja::query()->with('cuentacontables')->findOrFail($cuentacajaId);
        if (! $cuentacaja->perteneceAEmpresa($empresaId)) {
            throw new RuntimeException('La cuenta de caja no pertenece a la empresa indicada.');
        }

        $cache = ConciliacionBancariaResultadoCache::leer($empresaId, $cuentacajaId, $mes, $anio);
        $overrides = is_array($cache['codigos_saldo_excel'] ?? null) ? $cache['codigos_saldo_excel'] : [];
        $borrador = $this->borrador($empresaId, $cuentacaja, $mes, $anio, $overrides);

        if (($borrador['ya_generado']['id'] ?? 0) > 0) {
            $id = (int) $borrador['ya_generado']['id'];

            return [
                'ok' => true,
                'mensaje' => 'El egreso de este período ya estaba generado.',
                'caja_movimiento_id' => $id,
                'numero' => (string) ($borrador['ya_generado']['numero'] ?? ''),
                'url' => route('editar_ingresoegreso', ['id' => $id, 'origen' => 'ingresoegreso']),
            ];
        }

        if (! ($borrador['listo'] ?? false)) {
            $motivo = $borrador['faltantes'][0] ?? 'No hay gastos bancarios para documentar.';
            throw new RuntimeException($motivo);
        }

        $tipoCaja = Tipotransaccion_Caja::query()
            ->where('abreviatura', (string) $borrador['tipo_caja'])
            ->first();
        if ($tipoCaja === null) {
            throw new RuntimeException('No existe el tipo de caja '.$borrador['tipo_caja'].'.');
        }

        $tipoCompra = Tipotransaccion_Compra::query()
            ->where('abreviatura', (string) $borrador['tipo_compra'])
            ->first();
        if ($tipoCompra === null) {
            throw new RuntimeException('No existe el tipo de comprobante '.$borrador['tipo_compra'].'.');
        }

        $payload = $this->payload($borrador, $empresaId, (int) $tipoCaja->id, (int) $tipoCompra->id);
        $request = Request::create('/caja/ingresoegreso', 'POST', $payload);
        $request->setUserResolver(static fn () => Auth::user());

        $respuesta = $this->ingresoEgresoService->guardaIngresoEgreso($request);
        if (! is_array($respuesta) || isset($respuesta['errores'])) {
            $error = is_array($respuesta) ? (string) ($respuesta['errores'] ?? 'No se pudo grabar el egreso.') : 'No se pudo grabar el egreso.';
            throw new RuntimeException($error);
        }

        $id = (int) ($respuesta['caja_movimiento_id'] ?? 0);
        $numero = '';
        if ($id > 0) {
            $numero = (string) (Caja_Movimiento::query()->whereKey($id)->value('numerotransaccion') ?? '');
        }

        return [
            'ok' => true,
            'mensaje' => 'Egreso '.$numero.' e ICO generados.',
            'caja_movimiento_id' => $id,
            'numero' => $numero,
            'url' => $id > 0 ? route('editar_ingresoegreso', ['id' => $id, 'origen' => 'ingresoegreso']) : route('ingresoegreso'),
        ];
    }

    /**
     * @param  array<string, mixed>  $borrador
     * @return array<string, mixed>
     */
    private function payload(array $borrador, int $empresaId, int $tipoCajaId, int $tipoCompraId): array
    {
        $total = round((float) $borrador['total'], 2);
        $fecha = (string) $borrador['fecha'];
        $detalle = (string) $borrador['detalle'];
        $monedaId = 1;

        $cuentas = [];
        $codigos = [];
        $nombres = [];
        $centros = [];
        $monedasAsiento = [];
        $cotizAsiento = [];
        $debe = [];
        $haber = [];
        $obs = [];
        $manual = [];
        $conceptos = [];
        $debitos = [];

        foreach ($borrador['lineas'] as $linea) {
            $importe = round((float) $linea['importe'], 2);
            $cuentaId = (int) $linea['cuentacontable_id'];
            $cuentas[] = $cuentaId;
            $codigos[] = (string) $linea['cuenta_codigo'];
            $nombres[] = (string) $linea['cuenta_nombre'];
            $centros[] = '';
            $monedasAsiento[] = $monedaId;
            $cotizAsiento[] = 1;
            $debe[] = $importe;
            $haber[] = '';
            $obs[] = $linea['codigo'].' '.$linea['descripcion'];
            $manual[] = 'S';

            if ((int) ($linea['concepto_ivacompra_id'] ?? 0) > 0) {
                $conceptos[] = [
                    'concepto_ivacompra_id' => (int) $linea['concepto_ivacompra_id'],
                    'monto' => $importe,
                    'cuentacontabledebe_id' => $cuentaId,
                ];
            } else {
                $debitos[] = [
                    'cuentacontable_id' => $cuentaId,
                    'importe' => $importe,
                    'centrocosto_id' => 0,
                ];
            }
        }

        $cuentas[] = (int) $borrador['cuenta_banco_id'];
        $codigos[] = '';
        $nombres[] = (string) $borrador['cuentacaja_nombre'];
        $centros[] = '';
        $monedasAsiento[] = $monedaId;
        $cotizAsiento[] = 1;
        $debe[] = '';
        $haber[] = $total;
        $obs[] = $detalle;
        $manual[] = 'S';

        $comprobante = [
            'tipotransaccion_compra_id' => $tipoCompraId,
            'tipo_tesoreria' => (string) $borrador['tipo_tesoreria'],
            'cuentacaja_id' => (int) $borrador['cuentacaja_id'],
            'fechacomprobante' => $fecha,
            'fechaiva' => $fecha,
            'moneda_id' => $monedaId,
            'cotizacion' => 1,
            'subtotal' => $total,
            'total' => $total,
            'letra' => 'A',
            'sucursal' => 0,
            'leyenda' => $detalle,
            'conceptos' => $conceptos,
            'debitos_gasto' => $debitos,
        ];

        return [
            'empresa_id' => $empresaId,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'fecha' => $fecha,
            'detalle' => $detalle,
            'usuario_id' => (int) Auth::id(),
            'cuentacaja_ids' => [(int) $borrador['cuentacaja_id']],
            'montos' => [$total],
            'moneda_ids' => [$monedaId],
            'cotizaciones' => [1],
            'observaciones' => [$detalle],
            'cuentacontable_ids' => $cuentas,
            'codigoasientos' => $codigos,
            'nombrecuentacontables' => $nombres,
            'centrocostoasiento_ids' => $centros,
            'monedaasiento_ids' => $monedasAsiento,
            'cotizacionasientos' => $cotizAsiento,
            'debeasientos' => $debe,
            'haberasientos' => $haber,
            'observacionasientos' => $obs,
            'carga_cuentacontable_manuales' => $manual,
            'comprobantes_ivacompra_json' => json_encode([$comprobante], JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movimientosDelMes(int $empresaId, string $cuentaInterbanking, int $mes, int $anio, int $cuentacajaId): array
    {
        $desde = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth();

        $rows = InterbankingMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('account_number', $cuentaInterbanking)
            ->whereDate('process_date', '>=', $desde->toDateString())
            ->whereDate('process_date', '<=', $hasta->toDateString())
            ->orderBy('process_date')
            ->orderBy('id')
            ->get();

        $movs = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $arr['hash'] = ConciliacionBancariaHashSupport::hashBanco($cuentacajaId, (string) $row->dedupe_hash);
            $movs[] = $arr;
        }

        return $movs;
    }
}
