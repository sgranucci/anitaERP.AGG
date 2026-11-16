<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Importacion;
use App\Models\Compras\Importacion_Gasto;
use App\Models\Compras\Importacion_Prorrateo;
use App\Models\Compras\Importacion_Prorrateo_Linea;
use App\Support\Compras\ImportacionEstados;
use App\Support\Compras\ImportacionGastoTratamiento;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Motor de landed cost híbrido: prorrateo estimado/real y preview contable.
 */
class ImportacionLandedCostService
{
    /**
     * @param  list<array{articulo_id?: int|null, ordencompra_articulo_id?: int|null, sku?: string, valor?: float, cantidad?: float, peso?: float, volumen?: float}>  $partidas
     * @return array{lineas: list<array<string, mixed>>, totales: array<string, float>, preview_asiento: list<array<string, mixed>>}
     */
    public function calcular(Importacion $importacion, array $partidas, bool $usarReales = true): array
    {
        $gastos = $importacion->gastos()
            ->with('tipo')
            ->where('naturaleza', $usarReales ? 'real' : 'estimado')
            ->get();

        if ($gastos->isEmpty() && $usarReales) {
            $gastos = $importacion->gastos()->with('tipo')->where('naturaleza', 'estimado')->get();
        }

        $lineas = [];
        $totalInventariable = 0.0;
        $totalGasto = 0.0;

        foreach ($gastos as $gasto) {
            /** @var Importacion_Gasto $gasto */
            $importe = (float) $gasto->importe_moneda_local;
            if ($importe <= 0) {
                $importe = $this->aMonedaLocal($gasto, $importacion);
            }
            $baseKey = (string) ($gasto->base_prorrateo ?: ($gasto->tipo->base_prorrateo ?? ImportacionGastoTratamiento::BASE_VALOR));
            $inventariable = ($gasto->tipo->tratamiento ?? ImportacionGastoTratamiento::INVENTARIABLE)
                === ImportacionGastoTratamiento::INVENTARIABLE;

            $asignaciones = $this->prorratear($partidas, $importe, $baseKey);
            foreach ($asignaciones as $asig) {
                $lineas[] = [
                    'importacion_gasto_id' => $gasto->id,
                    'articulo_id' => $asig['articulo_id'] ?? null,
                    'ordencompra_articulo_id' => $asig['ordencompra_articulo_id'] ?? null,
                    'sku' => $asig['sku'] ?? null,
                    'base' => $asig['base'],
                    'importe_asignado' => round($asig['importe'], 4),
                    'inventariable' => $inventariable,
                    'gasto_tipo' => $gasto->tipo->codigo ?? '',
                ];
                if ($inventariable) {
                    $totalInventariable += $asig['importe'];
                } else {
                    $totalGasto += $asig['importe'];
                }
            }
        }

        $fob = (float) $importacion->fob_estimado;
        $coef = $fob > 0.00001 ? round(($totalInventariable + $totalGasto) / $fob, 6) : null;

        return [
            'lineas' => $lineas,
            'totales' => [
                'inventariable' => round($totalInventariable, 4),
                'gasto' => round($totalGasto, 4),
                'landed' => round($fob + $totalInventariable, 4),
                'coeficiente_embarque' => $coef ?? 0.0,
            ],
            'preview_asiento' => $this->previewAsiento($importacion, $totalInventariable, $totalGasto),
        ];
    }

    /**
     * Persiste un cálculo de prorrateo (sin aplicar a stock/asiento aún).
     *
     * @param  list<array{articulo_id?: int|null, ordencompra_articulo_id?: int|null, sku?: string, valor?: float, cantidad?: float, peso?: float, volumen?: float}>  $partidas
     */
    public function guardarCalculo(Importacion $importacion, array $partidas, bool $usarReales = true): Importacion_Prorrateo
    {
        if (! ImportacionEstados::aceptaGastos((string) $importacion->estado)
            && (string) $importacion->estado !== ImportacionEstados::NACIONALIZADA) {
            throw new RuntimeException('La carpeta no admite cálculo de costos en su estado actual.');
        }

        $resultado = $this->calcular($importacion, $partidas, $usarReales);

        return DB::transaction(function () use ($importacion, $resultado) {
            $previos = Importacion_Prorrateo::query()
                ->where('importacion_id', $importacion->id)
                ->where('aplicado', false);
            EloquentAuditDeleteSupport::each($previos);

            $cab = Importacion_Prorrateo::query()->create([
                'importacion_id' => $importacion->id,
                'usuario_id' => Auth::id(),
                'calculado_en' => now(),
                'aplicado' => false,
                'detalle_json' => json_encode($resultado['totales'], JSON_UNESCAPED_UNICODE),
            ]);

            foreach ($resultado['lineas'] as $lin) {
                Importacion_Prorrateo_Linea::query()->create([
                    'importacion_prorrateo_id' => $cab->id,
                    'importacion_gasto_id' => $lin['importacion_gasto_id'],
                    'articulo_id' => $lin['articulo_id'],
                    'ordencompra_articulo_id' => $lin['ordencompra_articulo_id'],
                    'sku' => $lin['sku'],
                    'base' => $lin['base'],
                    'importe_asignado' => $lin['importe_asignado'],
                    'inventariable' => $lin['inventariable'],
                ]);
            }

            $importacion->gastos_reales = (float) $resultado['totales']['inventariable'] + (float) $resultado['totales']['gasto'];
            $importacion->landed_cost = (float) $resultado['totales']['landed'];
            $importacion->coeficiente_embarque = (float) $resultado['totales']['coeficiente_embarque'];
            $importacion->save();

            return $cab->load('lineas');
        });
    }

    public function cerrarCostos(Importacion $importacion): Importacion
    {
        if ((string) $importacion->estado !== ImportacionEstados::NACIONALIZADA) {
            throw new RuntimeException('Solo se pueden cerrar costos de una carpeta nacionalizada.');
        }

        if ($importacion->exige_stock_para_cerrar) {
            $tieneStock = $importacion->lotes()->exists() || $importacion->recepciones()->exists();
            if (! $tieneStock) {
                throw new RuntimeException('La configuración exige stock/recepción vinculada antes de cerrar costos.');
            }
        }

        if (! ImportacionEstados::puedeTransicionar((string) $importacion->estado, ImportacionEstados::COSTOS_CERRADOS)) {
            throw new RuntimeException('Transición de estado no permitida.');
        }

        $ultimo = $importacion->prorrateos()->latest('id')->first();
        if ($ultimo && ! $ultimo->aplicado) {
            $ultimo->aplicado = true;
            $ultimo->aplicado_en = now();
            $ultimo->save();
        }

        $importacion->estado = ImportacionEstados::COSTOS_CERRADOS;
        $importacion->fecha_cierre_costos = now()->toDateString();
        $importacion->save();

        return $importacion;
    }

    /**
     * Recalcula totales de cabecera desde gastos cargados.
     */
    public function refrescarTotalesCabecera(Importacion $importacion): void
    {
        $est = (float) $importacion->gastos()->where('naturaleza', 'estimado')->sum('importe_moneda_local');
        $real = (float) $importacion->gastos()->where('naturaleza', 'real')->sum('importe_moneda_local');
        $importacion->gastos_estimados = $est;
        $importacion->gastos_reales = $real;
        $fob = (float) $importacion->fob_estimado;
        $baseGastos = $real > 0 ? $real : $est;
        $importacion->landed_cost = $fob + $baseGastos;
        $importacion->coeficiente_embarque = $fob > 0.00001 ? round($baseGastos / $fob, 6) : null;
        $importacion->save();
    }

    /**
     * Simulador pre-OC (Fase 5): estima landed cost sin carpeta persistida.
     *
     * @param  list<array{valor?: float, cantidad?: float, peso?: float, volumen?: float}>  $partidas
     * @param  list<array{importe: float, base_prorrateo?: string, inventariable?: bool}>  $gastos
     * @return array{landed: float, coeficiente: float, lineas: list<array<string, mixed>>}
     */
    public function simular(float $fob, array $partidas, array $gastos): array
    {
        $lineas = [];
        $inv = 0.0;
        $gas = 0.0;
        foreach ($gastos as $g) {
            $importe = (float) ($g['importe'] ?? 0);
            $base = (string) ($g['base_prorrateo'] ?? ImportacionGastoTratamiento::BASE_VALOR);
            $inventariable = (bool) ($g['inventariable'] ?? true);
            foreach ($this->prorratear($partidas, $importe, $base) as $asig) {
                $lineas[] = $asig + ['inventariable' => $inventariable];
                if ($inventariable) {
                    $inv += $asig['importe'];
                } else {
                    $gas += $asig['importe'];
                }
            }
        }

        return [
            'landed' => round($fob + $inv, 4),
            'coeficiente' => $fob > 0.00001 ? round(($inv + $gas) / $fob, 6) : 0.0,
            'lineas' => $lineas,
            'totales' => [
                'inventariable' => round($inv, 4),
                'gasto' => round($gas, 4),
            ],
        ];
    }

    /**
     * @param  list<array{articulo_id?: int|null, ordencompra_articulo_id?: int|null, sku?: string, valor?: float, cantidad?: float, peso?: float, volumen?: float}>  $partidas
     * @return list<array{articulo_id: ?int, ordencompra_articulo_id: ?int, sku: ?string, base: float, importe: float}>
     */
    public function prorratear(array $partidas, float $importe, string $baseKey): array
    {
        if ($partidas === [] || $importe == 0.0) {
            return [];
        }

        $bases = [];
        foreach ($partidas as $i => $p) {
            $bases[$i] = match ($baseKey) {
                ImportacionGastoTratamiento::BASE_CANTIDAD => max(0.0, (float) ($p['cantidad'] ?? 0)),
                ImportacionGastoTratamiento::BASE_PESO => max(0.0, (float) ($p['peso'] ?? 0)),
                ImportacionGastoTratamiento::BASE_VOLUMEN => max(0.0, (float) ($p['volumen'] ?? 0)),
                ImportacionGastoTratamiento::BASE_IGUAL => 1.0,
                default => max(0.0, (float) ($p['valor'] ?? 0)),
            };
        }

        $suma = array_sum($bases);
        if ($suma <= 0.0000001) {
            $n = count($partidas);
            $cada = $importe / $n;
            $out = [];
            foreach ($partidas as $p) {
                $out[] = [
                    'articulo_id' => isset($p['articulo_id']) ? (int) $p['articulo_id'] : null,
                    'ordencompra_articulo_id' => isset($p['ordencompra_articulo_id']) ? (int) $p['ordencompra_articulo_id'] : null,
                    'sku' => $p['sku'] ?? null,
                    'base' => 1.0,
                    'importe' => round($cada, 4),
                ];
            }

            return $out;
        }

        $out = [];
        $acum = 0.0;
        $last = array_key_last($partidas);
        foreach ($partidas as $i => $p) {
            $share = ($bases[$i] / $suma) * $importe;
            if ($i === $last) {
                $share = $importe - $acum;
            } else {
                $share = round($share, 4);
                $acum += $share;
            }
            $out[] = [
                'articulo_id' => isset($p['articulo_id']) ? (int) $p['articulo_id'] : null,
                'ordencompra_articulo_id' => isset($p['ordencompra_articulo_id']) ? (int) $p['ordencompra_articulo_id'] : null,
                'sku' => $p['sku'] ?? null,
                'base' => round($bases[$i], 6),
                'importe' => round($share, 4),
            ];
        }

        return $out;
    }

    private function aMonedaLocal(Importacion_Gasto $gasto, Importacion $importacion): float
    {
        $importe = (float) $gasto->importe;
        $monedaId = (int) ($gasto->moneda_id ?: $importacion->moneda_id ?: 1);
        if ($monedaId <= 1) {
            return $importe;
        }
        $cot = (float) ($gasto->cotizacion ?: 0);
        if ($cot <= 0) {
            $fecha = $gasto->fecha?->format('Y-m-d') ?: $importacion->fecha_apertura?->format('Y-m-d') ?: date('Y-m-d');
            $cot = CotizacionVigenteSupport::ventaValorOUno($fecha, $monedaId);
        }

        return round($importe * $cot, 4);
    }

    /**
     * @return list<array{cuenta_clave: string, cuenta_id: ?int, dh: string, importe: float, concepto: string}>
     */
    private function previewAsiento(Importacion $importacion, float $inventariable, float $gasto): array
    {
        $empresaId = (int) $importacion->empresa_id;
        $ctaInv = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::IMPORTACION_GASTOS_INVENTARIABLES);
        $ctaGasto = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::IMPORTACION_GASTOS_RESULTADO);
        $lineas = [];
        if ($inventariable > 0) {
            $lineas[] = [
                'cuenta_clave' => CuentaAutomaticaClaves::IMPORTACION_GASTOS_INVENTARIABLES,
                'cuenta_id' => $ctaInv,
                'dh' => 'D',
                'importe' => round($inventariable, 4),
                'concepto' => 'Landed cost inventariable '.$importacion->codigo,
            ];
            $lineas[] = [
                'cuenta_clave' => CuentaAutomaticaClaves::IMPORTACION_GASTOS_INVENTARIABLES,
                'cuenta_id' => $ctaInv,
                'dh' => 'H',
                'importe' => round($inventariable, 4),
                'concepto' => 'Clearing landed cost '.$importacion->codigo,
            ];
        }
        if ($gasto > 0) {
            $lineas[] = [
                'cuenta_clave' => CuentaAutomaticaClaves::IMPORTACION_GASTOS_RESULTADO,
                'cuenta_id' => $ctaGasto,
                'dh' => 'D',
                'importe' => round($gasto, 4),
                'concepto' => 'Gastos importación '.$importacion->codigo,
            ];
            $lineas[] = [
                'cuenta_clave' => CuentaAutomaticaClaves::IMPORTACION_GASTOS_RESULTADO,
                'cuenta_id' => $ctaGasto,
                'dh' => 'H',
                'importe' => round($gasto, 4),
                'concepto' => 'Contrapartida gastos importación '.$importacion->codigo,
            ];
        }

        return $lineas;
    }
}
