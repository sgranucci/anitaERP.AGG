<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Contable\Asiento_Movimiento;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Impuesto;
use App\Support\Ventas\FacturanteLineaIvaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Las facturas ya importadas de Facturante quedaron con el renglón neto y un solo «Total Iva».
 * Las deja como una emisión del local: precio con IVA, gravado / IVA / total, y el asiento en esa base.
 */
final class FacturanteIvaNormalizacionService
{
    /**
     * @return array{revisadas:int,corregibles:int,omitidas:int,muestras:list<string>,omitidas_detalle:list<string>}
     */
    public function previsualizar(): array
    {
        $resumen = [
            'revisadas' => 0,
            'corregibles' => 0,
            'omitidas' => 0,
            'muestras' => [],
            'omitidas_detalle' => [],
        ];
        foreach ($this->candidatas() as $venta) {
            $resumen['revisadas']++;
            $plan = $this->plan($venta);
            if ($plan === null) {
                continue;
            }
            if ($plan['omitir'] !== '') {
                $resumen['omitidas']++;
                if (count($resumen['omitidas_detalle']) < 15) {
                    $resumen['omitidas_detalle'][] = $venta->codigo.' '.$plan['omitir'];
                }
                continue;
            }
            $resumen['corregibles']++;
            if (count($resumen['muestras']) < 8) {
                $resumen['muestras'][] = $venta->codigo
                    .' total='.number_format(abs((float) $venta->total), 2, ',', '.')
                    .' renglón '.$plan['suma_actual'].' → '.$plan['suma_nueva']
                    .' gravado '.$plan['gravado'];
            }
        }

        return $resumen;
    }

    public function aplicar(): array
    {
        $aplicadas = 0;
        $omitidas = [];
        foreach ($this->candidatas() as $venta) {
            $plan = $this->plan($venta);
            if ($plan === null) {
                continue;
            }
            if ($plan['omitir'] !== '') {
                $omitidas[] = $venta->codigo.' '.$plan['omitir'];
                continue;
            }
            DB::transaction(function () use ($venta, $plan) {
                foreach ($plan['precios'] as $emisionId => $precio) {
                    $emision = Venta_Emision::query()->whereKey($emisionId)->first();
                    if ($emision) {
                        $emision->precio = $precio;
                        $emision->incluyeimpuesto = '1';
                        $emision->save();
                    }
                }
                foreach ($plan['stocks'] as $movimientoId => $precio) {
                    $mov = Articulo_Movimiento::query()->whereKey($movimientoId)->first();
                    if ($mov) {
                        $mov->precio = $precio;
                        $mov->save();
                    }
                }
                Venta_Impuesto::query()->where('venta_id', $venta->id)->get()->each(function (Venta_Impuesto $fila) {
                    $fila->delete();
                });
                foreach ($plan['conceptos'] as $concepto) {
                    Venta_Impuesto::query()->create([
                        'venta_id' => $venta->id,
                        'concepto' => $concepto['concepto'],
                        'baseimponible' => $concepto['baseimponible'] ?? 0,
                        'tasa' => $concepto['tasa'] ?? 0,
                        'importe' => $concepto['importe'] ?? 0,
                        'provincia_id' => $concepto['provincia_id'] ?? null,
                        'impuesto_id' => $concepto['impuesto_id'] ?? null,
                    ]);
                }
                foreach ($plan['asiento'] as $movimientoId => $monto) {
                    $mov = Asiento_Movimiento::query()->whereKey($movimientoId)->first();
                    if ($mov) {
                        $mov->monto = $monto;
                        $mov->save();
                    }
                }
            });
            $aplicadas++;
        }

        return ['aplicadas' => $aplicadas, 'omitidas' => $omitidas];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Venta>
     */
    private function candidatas()
    {
        return Venta::query()
            ->where('leyenda', 'Facturante')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function plan(Venta $venta): ?array
    {
        $emisiones = Venta_Emision::query()->where('venta_id', $venta->id)->orderBy('numeroitem')->get();
        if ($emisiones->isEmpty()) {
            return null;
        }
        $lineas = [];
        foreach ($emisiones as $emision) {
            $lineas[] = [
                'id' => (int) $emision->id,
                'articulo_id' => (int) ($emision->articulo_id ?? 0),
                'cantidad' => (float) $emision->cantidad,
                'precio' => (float) $emision->precio,
                'descuento' => (float) ($emision->descuento ?? 0),
                'impuesto_id' => (int) ($emision->impuesto_id ?? 0),
            ];
        }
        $total = abs((float) $venta->total);
        $suma = FacturanteLineaIvaSupport::sumaImportes($lineas);
        if ($suma <= 0.0001 || abs($total / $suma - 1) < 0.005) {
            return null;
        }
        $nuevas = FacturanteLineaIvaSupport::precioUnitarioConIva($lineas, $total);
        if (abs(FacturanteLineaIvaSupport::sumaImportes($nuevas) - $suma) < 0.001) {
            return null;
        }

        $conceptosActuales = Venta_Impuesto::query()->where('venta_id', $venta->id)->get()
            ->map(fn (Venta_Impuesto $f) => $f->only(['concepto', 'baseimponible', 'tasa', 'importe', 'provincia_id', 'impuesto_id']))
            ->all();
        $yaTieneGravado = false;
        foreach ($conceptosActuales as $concepto) {
            if (str_starts_with((string) ($concepto['concepto'] ?? ''), 'Gravado')) {
                $yaTieneGravado = true;
            }
        }
        if ($yaTieneGravado) {
            return ['omitir' => 'ya tiene gravado'];
        }

        $conceptos = FacturanteLineaIvaSupport::conceptosComoEmisionLocal($nuevas, $conceptosActuales);
        $gravado = 0.0;
        $iva = 0.0;
        foreach ($conceptos as $concepto) {
            $nombre = (string) $concepto['concepto'];
            if (str_starts_with($nombre, 'Gravado') || $nombre === 'Exento') {
                $gravado += (float) $concepto['importe'];
            }
            if (str_starts_with($nombre, 'Iva ')) {
                $iva += (float) $concepto['importe'];
            }
        }
        $gravado = round($gravado, 2);
        $iva = round($iva, 2);
        if (abs(($gravado + $iva) - $total) > 0.05) {
            return ['omitir' => 'gravado+iva no cierra con el total'];
        }

        $asiento = $this->planAsiento((int) $venta->id, $gravado, $total);
        if (isset($asiento['omitir'])) {
            return ['omitir' => $asiento['omitir']];
        }

        $precios = [];
        $stocks = [];
        foreach ($lineas as $i => $antes) {
            $despues = (float) $nuevas[$i]['precio'];
            if (abs($despues - (float) $antes['precio']) < 0.0001) {
                continue;
            }
            $precios[(int) $antes['id']] = $despues;
            $movs = Articulo_Movimiento::query()
                ->where('venta_id', $venta->id)
                ->where('articulo_id', (int) $antes['articulo_id'])
                ->whereRaw('ABS(precio - ?) < 0.02', [(float) $antes['precio']])
                ->get();
            foreach ($movs as $mov) {
                $stocks[(int) $mov->id] = $despues;
            }
        }

        return [
            'omitir' => '',
            'suma_actual' => number_format($suma, 2, ',', '.'),
            'suma_nueva' => number_format(FacturanteLineaIvaSupport::sumaImportes($nuevas), 2, ',', '.'),
            'gravado' => number_format($gravado, 2, ',', '.'),
            'precios' => $precios,
            'stocks' => $stocks,
            'conceptos' => $conceptos,
            'asiento' => $asiento['montos'],
        ];
    }

    /**
     * @return array{montos: array<int, float>}|array{omitir: string}
     */
    private function planAsiento(int $ventaId, float $gravado, float $total): array
    {
        $asientoId = DB::table('asiento')->where('venta_id', $ventaId)->value('id');
        if (! $asientoId) {
            return ['omitir' => 'sin asiento'];
        }
        $movs = DB::table('asiento_movimiento as m')
            ->join('cuentacontable as c', 'c.id', '=', 'm.cuentacontable_id')
            ->where('m.asiento_id', $asientoId)
            ->get(['m.id', 'm.monto', 'c.codigo']);
        $iva = [];
        $deudores = [];
        $ventas = [];
        foreach ($movs as $mov) {
            $codigo = (string) $mov->codigo;
            if (str_starts_with($codigo, '2131')) {
                $iva[] = $mov;
            } elseif (str_starts_with($codigo, '1131')) {
                $deudores[] = $mov;
            } else {
                $ventas[] = $mov;
            }
        }
        if ($iva === [] || count($deudores) !== 1 || $ventas === []) {
            return ['omitir' => 'asiento con otra forma'];
        }
        $signoVentas = ((float) $ventas[0]->monto) < 0 ? -1 : 1;
        $signoDeudores = ((float) $deudores[0]->monto) < 0 ? -1 : 1;
        if ($signoVentas === $signoDeudores) {
            return ['omitir' => 'asiento con signos iguales'];
        }
        $absVentas = 0.0;
        foreach ($ventas as $mov) {
            $absVentas += abs((float) $mov->monto);
        }
        if ($absVentas < 0.01) {
            return ['omitir' => 'asiento de ventas en cero'];
        }
        $factor = $gravado / $absVentas;
        $montos = [];
        $ultimoVentas = 0;
        foreach ($ventas as $mov) {
            $ultimoVentas = (int) $mov->id;
            $montos[$ultimoVentas] = round((float) $mov->monto * $factor, 2);
        }
        $ivaImporte = 0.0;
        foreach ($iva as $mov) {
            $ivaImporte += (float) $mov->monto;
        }
        $deudor = round($signoDeudores * $total, 2);
        $montos[(int) $deudores[0]->id] = $deudor;
        $suma = $ivaImporte + $deudor;
        foreach ($montos as $id => $monto) {
            if ($id !== (int) $deudores[0]->id) {
                $suma += $monto;
            }
        }
        $ajuste = round(0 - $suma, 2);
        if (abs($ajuste) >= 0.01 && abs($ajuste) <= 0.05 && $ultimoVentas > 0) {
            $montos[$ultimoVentas] = round($montos[$ultimoVentas] + $ajuste, 2);
            $ajuste = 0.0;
        }
        if (abs($ajuste) > 0.001) {
            return ['omitir' => 'asiento no balancea'];
        }

        return ['montos' => $montos];
    }
}
