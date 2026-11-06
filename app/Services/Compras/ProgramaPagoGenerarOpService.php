<?php

namespace App\Services\Compras;

use App\Models\Caja\Cuentacaja;
use App\Models\Compras\ProgramaPago;
use App\Models\Compras\ProgramaPagoOp;
use App\Support\Compras\ProgramaPagoOpPlanSupport;
use Illuminate\Support\Facades\DB;

/**
 * Genera las OP completas de los proveedores tildados en el programa de pagos.
 * Una OP por proveedor, con todos sus cheques y una transferencia por la
 * diferencia del total. La cuenta de caja es la misma para toda la tanda.
 */
class ProgramaPagoGenerarOpService
{
    public function __construct(
        private PagoproveedorService $pagoproveedorService,
    ) {
    }

    /**
     * @param  list<array{linea_id:int,fecha:string}>  $pedidos
     * @return array{
     *   generadas: list<array<string, mixed>>,
     *   errores: list<string>,
     *   avisos: list<string>
     * }
     */
    public function generar(ProgramaPago $programa, array $pedidos, int $cuentacajaId): array
    {
        if (! $programa->esEditable()) {
            return [
                'generadas' => [],
                'errores' => ['El programa está cerrado. Reabrilo para generar órdenes de pago.'],
                'avisos' => [],
            ];
        }

        $lineaIds = [];
        $pedidosPorLinea = [];
        foreach ($pedidos as $pedido) {
            $lineaId = (int) ($pedido['linea_id'] ?? 0);
            $fecha = trim((string) ($pedido['fecha'] ?? ''));
            if ($lineaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                continue;
            }
            $lineaIds[] = $lineaId;
            $pedidosPorLinea[$lineaId] = $fecha;
        }
        if ($pedidosPorLinea === []) {
            return [
                'generadas' => [],
                'errores' => ['No hay órdenes de pago para generar.'],
                'avisos' => [],
            ];
        }

        // Un plan por fecha de pago: las retenciones estimadas usan esa fecha (acumulados).
        $idsPorFecha = [];
        foreach ($pedidosPorLinea as $lineaId => $fecha) {
            $idsPorFecha[$fecha][] = $lineaId;
        }
        $aGenerar = [];
        foreach ($idsPorFecha as $fecha => $idsFecha) {
            foreach (ProgramaPagoOpPlanSupport::armar($programa, $idsFecha, $fecha) as $fila) {
                $lineaId = (int) $fila['linea_id'];
                if (! isset($pedidosPorLinea[$lineaId]) || $fila['pagoproveedor_id']) {
                    continue;
                }
                $fila['fecha'] = $fecha;
                $aGenerar[] = $fila;
            }
        }
        if ($aGenerar === []) {
            return [
                'generadas' => [],
                'errores' => ['Esos proveedores ya tienen orden de pago, o no quedaron montos.'],
                'avisos' => [],
            ];
        }

        $necesitaCuenta = false;
        foreach ($aGenerar as $fila) {
            if ($fila['necesita_cuenta']) {
                $necesitaCuenta = true;
                break;
            }
        }
        $cuentaOk = Cuentacaja::existeParaEmpresa($cuentacajaId, (int) $programa->empresa_id);
        if ($necesitaCuenta && ! $cuentaOk) {
            return [
                'generadas' => [],
                'errores' => ['Elegí la cuenta de caja de la empresa desde la que sale la transferencia.'],
                'avisos' => [],
            ];
        }
        // Si la preview no pedía transferencia pero el cálculo real sí, hace falta cuenta cargada.
        $cuentacajaId = $cuentaOk ? $cuentacajaId : 0;

        $generadas = [];
        $errores = [];
        $avisos = [];
        foreach ($aGenerar as $fila) {
            $etiqueta = trim($fila['codigo'].' '.$fila['nombre']);
            try {
                $aplicaciones = $fila['programado'] > 0.009
                    ? ProgramaPagoOpPlanSupport::aplicarFifo(
                        (int) $programa->empresa_id,
                        (int) $fila['proveedor_id'],
                        (float) $fila['programado'],
                        $fila['fecha']
                    )
                    : [];
                $chequeIds = [];
                foreach ($fila['cheques'] as $ch) {
                    if (! empty($ch['ya_en_op'])) {
                        throw new \RuntimeException(
                            'El cheque '.$ch['numerocheque'].' ya está en otra orden de pago.'
                        );
                    }
                    $chequeIds[] = (int) $ch['id'];
                }

                $resultado = $this->pagoproveedorService->crearDesdeProgramaPago(
                    (int) $programa->empresa_id,
                    (int) $fila['proveedor_id'],
                    $fila['fecha'],
                    (float) $fila['programado'],
                    $aplicaciones,
                    $chequeIds,
                    $cuentacajaId > 0 ? $cuentacajaId : null,
                    'Programa de pagos #'.$programa->id.' | '.trim($fila['codigo'].' '.$fila['nombre']),
                    true,
                );
                if (! empty($resultado['errores'])) {
                    $errores[] = $etiqueta.': '.$resultado['errores'];
                    continue;
                }

                $opId = (int) $resultado['pagoproveedor_id'];
                DB::transaction(function () use ($fila, $opId) {
                    ProgramaPagoOp::query()->create([
                        'programa_pago_linea_id' => (int) $fila['linea_id'],
                        'clave' => ProgramaPagoOp::CLAVE_PROVEEDOR,
                        'pagoproveedor_id' => $opId,
                    ]);
                });

                $generadas[] = [
                    'pagoproveedor_id' => $opId,
                    'numero' => (string) ($resultado['numerotransaccion'] ?? ''),
                    'proveedor' => trim($fila['codigo'].' '.$fila['nombre']),
                    'etiqueta' => '',
                    'programado' => (float) $fila['programado'],
                    'cheques' => (float) $fila['suma_cheques'],
                    'transferencia' => (float) ($resultado['transferencia'] ?? 0),
                    'anticipo' => (float) ($resultado['anticipo'] ?? 0),
                    'fecha' => $fila['fecha'],
                ];
                if (! empty($resultado['aviso'])) {
                    $avisos[] = (string) $resultado['aviso'];
                }
            } catch (\Throwable $e) {
                $errores[] = $etiqueta.': '.$e->getMessage();
            }
        }

        return [
            'generadas' => $generadas,
            'errores' => $errores,
            'avisos' => $avisos,
        ];
    }
}
