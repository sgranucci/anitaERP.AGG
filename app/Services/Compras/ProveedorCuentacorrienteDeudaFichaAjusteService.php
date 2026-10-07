<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Configuracion\CotizacionVigenteSupport;
use Illuminate\Support\Facades\DB;

/**
 * Graba el renglón que iguala la ficha de proveedores con la deuda, en pesos.
 * Solo escribe proveedor_cuentacorriente. No arma asiento, no llama a Anita ni al bridge.
 */
final class ProveedorCuentacorrienteDeudaFichaAjusteService
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
                $primero = $ids[0] ?? 0;
                if ($primero > 0) {
                    $fila = Proveedor_Cuentacorriente::query()->find($primero);
                    if ($fila !== null) {
                        $fila->fecha = $fecha;
                        $fila->fechavencimiento = $fecha;
                        $fila->total = $total;
                        $fila->moneda_id = CotizacionVigenteSupport::MONEDA_LOCAL_ID;
                        $fila->cotizacion = 1;
                        $fila->leyenda = $leyenda;
                        $fila->comprobante_proveedor_id = null;
                        $fila->comprobante_proveedor_cuota_id = null;
                        $fila->pagoproveedor_id = null;
                        $fila->save();
                        $resultado['grabados']++;
                        $resultado['borrados'] += $this->borrar(array_slice($ids, 1));

                        continue;
                    }
                }

                Proveedor_Cuentacorriente::query()->create([
                    'fecha' => $fecha,
                    'fechavencimiento' => $fecha,
                    'proveedor_id' => (int) $grupo['proveedor_id'],
                    'empresa_id' => (int) $grupo['empresa_id'],
                    'total' => $total,
                    'moneda_id' => CotizacionVigenteSupport::MONEDA_LOCAL_ID,
                    'cotizacion' => 1,
                    'leyenda' => $leyenda,
                    'comprobante_proveedor_id' => null,
                    'comprobante_proveedor_cuota_id' => null,
                    'pagoproveedor_id' => null,
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
            $fila = Proveedor_Cuentacorriente::query()->find($id);
            if ($fila === null) {
                continue;
            }
            $fila->delete();
            $borrados++;
        }

        return $borrados;
    }
}
