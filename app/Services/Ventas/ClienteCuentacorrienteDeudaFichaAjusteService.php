<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Support\Configuracion\CotizacionVigenteSupport;
use Illuminate\Support\Facades\DB;

/**
 * Graba el renglón que iguala la ficha de clientes con la deuda.
 * Solo escribe cliente_cuentacorriente. No arma asiento ni llama a Anita.
 */
final class ClienteCuentacorrienteDeudaFichaAjusteService
{
    /**
     * @param  array{fecha: string, leyenda: string, grupos: list<array<string, mixed>>}  $informe
     * @return array{grabados: int, borrados: int, sin_cambios: int}
     */
    public function aplicar(array $informe): array
    {
        $fecha = (string) $informe['fecha'];
        $leyenda = (string) $informe['leyenda'];
        $resultado = ['grabados' => 0, 'borrados' => 0, 'sin_cambios' => 0];

        DB::transaction(function () use ($informe, $fecha, $leyenda, &$resultado) {
            foreach ($informe['grupos'] as $grupo) {
                $accion = (string) ($grupo['accion'] ?? 'ninguna');
                if ($accion === 'ninguna') {
                    $resultado['sin_cambios']++;

                    continue;
                }

                $ids = array_values(array_map('intval', $grupo['ajuste_ids'] ?? []));
                if ($accion === 'borrar') {
                    $resultado['borrados'] += $this->borrar($ids);

                    continue;
                }

                $total = round((float) $grupo['ajuste_propuesto'], 2);
                $monedaId = (int) ($grupo['moneda_id'] ?? CotizacionVigenteSupport::MONEDA_LOCAL_ID);
                if ($monedaId <= 0) {
                    $monedaId = CotizacionVigenteSupport::MONEDA_LOCAL_ID;
                }
                $cotizacion = $monedaId === CotizacionVigenteSupport::MONEDA_LOCAL_ID
                    ? 1.0
                    : CotizacionVigenteSupport::ventaValorOUno($fecha, $monedaId);
                $primero = $ids[0] ?? 0;
                if ($primero > 0) {
                    $fila = Cliente_Cuentacorriente::query()->find($primero);
                    if ($fila !== null) {
                        $fila->fecha = $fecha;
                        $fila->fechavencimiento = $fecha;
                        $fila->total = $total;
                        $fila->moneda_id = $monedaId;
                        $fila->cotizacion = $cotizacion;
                        $fila->leyenda = $leyenda;
                        $fila->venta_id = null;
                        $fila->cobranza_id = null;
                        $fila->save();
                        $resultado['grabados']++;
                        $resultado['borrados'] += $this->borrar(array_slice($ids, 1));

                        continue;
                    }
                }

                Cliente_Cuentacorriente::query()->create([
                    'fecha' => $fecha,
                    'fechavencimiento' => $fecha,
                    'cliente_id' => (int) $grupo['cliente_id'],
                    'empresa_id' => (int) $grupo['empresa_id'],
                    'total' => $total,
                    'moneda_id' => $monedaId,
                    'cotizacion' => $cotizacion,
                    'leyenda' => $leyenda,
                    'venta_id' => null,
                    'cobranza_id' => null,
                ]);
                $resultado['grabados']++;
                $resultado['borrados'] += $this->borrar(array_slice($ids, 1));
            }
        });

        return $resultado;
    }

    /**
     * @param  list<int>  $ids
     */
    private function borrar(array $ids): int
    {
        $borrados = 0;
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            $fila = Cliente_Cuentacorriente::query()->find($id);
            if ($fila === null) {
                continue;
            }
            $fila->delete();
            $borrados++;
        }

        return $borrados;
    }
}
