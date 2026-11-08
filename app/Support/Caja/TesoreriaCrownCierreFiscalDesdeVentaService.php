<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Ventas\Venta;
use RuntimeException;

/**
 * Equivalente ERP de Anita p-cierrefact (lee_venta + CIEF_graba_cierrefiscal):
 * acumula venta del día por PV/sucursal (FAC* → tot_venta, NC* → tot_cred)
 * y graba/actualiza tesoreria_crown_cierre_fiscal (informe Z).
 */
final class TesoreriaCrownCierreFiscalDesdeVentaService
{
    /**
     * @return array{
     *     fecha: string,
     *     sucursales: int,
     *     creados: int,
     *     actualizados: int,
     *     omitidos_tomados: int,
     *     filas: list<array<string, mixed>>
     * }
     */
    public static function procesarFecha(
        string $fechaYmd,
        ?TesoreriaCrownJornada $jornada = null,
        ?int $sucursalFiltro = null,
        bool $forzarTomados = false,
        bool $dryRun = false,
        bool $todasConVenta = false,
    ): array {
        $fechaYmd = substr(trim($fechaYmd), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            throw new RuntimeException('Fecha inválida (use Y-m-d).');
        }

        if ($jornada === null) {
            $jornada = TesoreriaCrownJornada::query()
                ->whereDate('fecha', $fechaYmd)
                ->orderBy('id')
                ->first();
        }

        $acumulados = self::acumularDesdeVenta($fechaYmd, $sucursalFiltro);
        $nombres = self::nombresSucursal(array_keys($acumulados));

        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $filasOut = [];

        foreach ($acumulados as $sucursal => $acc) {
            $existente = TesoreriaCrownCierreFiscal::query()
                ->where('sucursal', $sucursal)
                ->whereDate('fecha_jor', $fechaYmd)
                ->where('nro_z', '>', 0)
                ->orderByDesc('nro_z')
                ->first();

            if ($existente === null && ! $todasConVenta) {
                // Sin cierre Z ERP del día: no inventar Z (evita Roca/otros PV ajenos al diario Crown).
                continue;
            }

            if ($existente !== null && $existente->comprobante_id !== null && ! $forzarTomados) {
                $omitidos++;
                $filasOut[] = self::resumenFila($existente, 'omitido_tomado');

                continue;
            }

            $totVentaCalc = round((float) $acc['tot_venta'], 2);
            $totCredCalc = round((float) $acc['tot_cred'], 2);
            // Si ya hay Z (p.ej. sync / cierre previo), no pisarlo con otro total
            // salvo --forzar-tomados. CIPO ya trae venta; el Z histórico no se reescribe solo.
            if ($existente !== null && ! $forzarTomados) {
                $ventaExist = round((float) $existente->tot_venta, 2);
                $credExist = round((float) $existente->tot_cred, 2);
                if (abs($totVentaCalc - $ventaExist) > 0.01 || abs($totCredCalc - $credExist) > 0.01) {
                    $omitidos++;
                    $filasOut[] = array_merge(self::resumenFila($existente, 'omitido_difiere_existente'), [
                        'tot_venta_calc' => $totVentaCalc,
                        'tot_cred_calc' => $totCredCalc,
                    ]);

                    continue;
                }
            }

            $nroZ = $existente !== null
                ? (int) $existente->nro_z
                : self::resolverNroZ($sucursal);
            $hora = $existente !== null && trim((string) $existente->hora) !== ''
                ? substr(trim((string) $existente->hora), 0, 8)
                : date('H:i:s');
            $nombre = $nombres[$sucursal]
                ?? trim((string) ($existente?->nombre_sucursal ?? ''))
                ?? '';

            $attrs = [
                'jornada_id' => $jornada?->id,
                'sucursal' => $sucursal,
                'fecha_jor' => $fechaYmd,
                'hora' => $hora,
                'nro_x' => 0,
                'nro_z' => $nroZ,
                'tipo' => 'Z',
                'nombre_sucursal' => $nombre !== '' ? $nombre : (string) $sucursal,
                'tot_venta' => $totVentaCalc,
                'tot_cred' => $totCredCalc,
                'tot_iva' => round((float) $acc['tot_iva'], 2),
                'tot_iva_cred' => round((float) $acc['tot_iva_cred'], 2),
                'ult_fact_b' => (int) $acc['ult_fact_b'],
            ];

            $filasOut[] = array_merge($attrs, [
                'cant_fact' => (int) $acc['cant_fact'],
                'cant_cred' => (int) $acc['cant_cred'],
                'accion' => $dryRun
                    ? ($existente === null ? 'crearia' : 'actualizaria')
                    : ($existente === null ? 'creado' : 'actualizado'),
            ]);

            if ($dryRun) {
                continue;
            }

            if ($existente === null) {
                TesoreriaCrownCierreFiscal::query()->create($attrs);
                $creados++;

                continue;
            }

            $existente->fill($attrs);
            if ($existente->isDirty()) {
                $existente->save();
                $actualizados++;
            }
        }

        return [
            'fecha' => $fechaYmd,
            'sucursales' => count($acumulados),
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos_tomados' => $omitidos,
            'filas' => $filasOut,
        ];
    }

    /**
     * @return array<int, array{
     *     tot_venta: float,
     *     tot_cred: float,
     *     tot_iva: float,
     *     tot_iva_cred: float,
     *     cant_fact: int,
     *     cant_cred: int,
     *     ult_fact_a: int,
     *     ult_fact_b: int,
     *     ult_cred_a: int,
     *     ult_cred_b: int
     * }>
     */
    public static function acumularDesdeVenta(string $fechaYmd, ?int $sucursalFiltro = null): array
    {
        $ventas = Venta::query()
            ->with([
                'tipotransacciones:id,abreviatura',
                'puntoventas:id,codigo,nombre',
                'venta_impuestos:id,venta_id,concepto,importe,tasa',
            ])
            ->where(function ($q) use ($fechaYmd) {
                $q->whereDate('fechajornada', $fechaYmd)
                    ->orWhere(function ($x) use ($fechaYmd) {
                        $x->whereNull('fechajornada')->whereDate('fecha', $fechaYmd);
                    });
            })
            ->get();

        $out = [];
        foreach ($ventas as $venta) {
            $sucursal = self::sucursalDeVenta($venta);
            if ($sucursal <= 0) {
                continue;
            }
            if ($sucursalFiltro !== null && $sucursal !== $sucursalFiltro) {
                continue;
            }

            $monto = abs(round((float) $venta->total, 2));
            // p-cierrefact: omite invitaciones / montos ~0 salvo -i
            if ($monto <= 0.01) {
                continue;
            }

            if (! isset($out[$sucursal])) {
                $out[$sucursal] = [
                    'tot_venta' => 0.0,
                    'tot_cred' => 0.0,
                    'tot_iva' => 0.0,
                    'tot_iva_cred' => 0.0,
                    'cant_fact' => 0,
                    'cant_cred' => 0,
                    'ult_fact_a' => 0,
                    'ult_fact_b' => 0,
                    'ult_cred_a' => 0,
                    'ult_cred_b' => 0,
                ];
            }

            $abrev = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));
            $iva = self::ivaDeVenta($venta);
            $letraB = self::esLetraB($venta);
            $numero = (int) ($venta->numerocomprobante ?? 0);

            if (str_starts_with($abrev, 'NC')) {
                $out[$sucursal]['tot_cred'] = round($out[$sucursal]['tot_cred'] + $monto, 2);
                $out[$sucursal]['tot_iva_cred'] = round($out[$sucursal]['tot_iva_cred'] + $iva, 2);
                $out[$sucursal]['cant_cred']++;
                if ($letraB) {
                    $out[$sucursal]['ult_cred_b'] = max($out[$sucursal]['ult_cred_b'], $numero);
                } else {
                    $out[$sucursal]['ult_cred_a'] = max($out[$sucursal]['ult_cred_a'], $numero);
                }
            } else {
                $out[$sucursal]['tot_venta'] = round($out[$sucursal]['tot_venta'] + $monto, 2);
                $out[$sucursal]['tot_iva'] = round($out[$sucursal]['tot_iva'] + $iva, 2);
                if (str_starts_with($abrev, 'FA')) {
                    $out[$sucursal]['cant_fact']++;
                }
                if ($letraB) {
                    $out[$sucursal]['ult_fact_b'] = max($out[$sucursal]['ult_fact_b'], $numero);
                } else {
                    $out[$sucursal]['ult_fact_a'] = max($out[$sucursal]['ult_fact_a'], $numero);
                }
            }
        }

        ksort($out);

        return $out;
    }

    private static function sucursalDeVenta(Venta $venta): int
    {
        $codigo = trim((string) ($venta->puntoventas->codigo ?? ''));
        if ($codigo === '') {
            return 0;
        }

        return (int) ltrim($codigo, '0') ?: (int) $codigo;
    }

    private static function ivaDeVenta(Venta $venta): float
    {
        $sum = 0.0;
        foreach ($venta->venta_impuestos as $imp) {
            $concepto = trim((string) ($imp->concepto ?? ''));
            if ($concepto === '') {
                continue;
            }
            if (preg_match('/^iva\b/i', $concepto) !== 1 && stripos($concepto, 'Iva ') !== 0) {
                continue;
            }
            $sum += abs((float) $imp->importe);
        }

        return round($sum, 2);
    }

    private static function esLetraB(Venta $venta): bool
    {
        $afip = (int) ($venta->codigo_afip ?? 0);
        // Clase A típicas; el resto (6,7,8,…) se trata como B como en el circuito retail Crown.
        if (in_array($afip, [1, 2, 3, 4, 5, 51, 52, 53, 54, 55], true)) {
            return false;
        }

        return true;
    }

    private static function resolverNroZ(int $sucursal): int
    {
        $maxLocal = (int) TesoreriaCrownCierreFiscal::query()
            ->where('sucursal', $sucursal)
            ->max('nro_z');

        return max(1, $maxLocal + 1);
    }

    /**
     * @param  list<int>  $numeros
     * @return array<int, string>
     */
    private static function nombresSucursal(array $numeros): array
    {
        $numeros = array_values(array_filter(array_map('intval', $numeros), fn ($n) => $n > 0));
        if ($numeros === []) {
            return [];
        }
        $out = [];
        foreach ($numeros as $n) {
            $nombre = TesoreriaCrownCierreFiscal::query()
                ->where('sucursal', $n)
                ->where('nombre_sucursal', '!=', '')
                ->whereNotNull('nombre_sucursal')
                ->orderByDesc('id')
                ->value('nombre_sucursal');
            $out[$n] = $nombre !== null && trim((string) $nombre) !== ''
                ? trim((string) $nombre)
                : (string) $n;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resumenFila(TesoreriaCrownCierreFiscal $fila, string $accion): array
    {
        return [
            'sucursal' => (int) $fila->sucursal,
            'nro_z' => (int) $fila->nro_z,
            'tot_venta' => round((float) $fila->tot_venta, 2),
            'tot_cred' => round((float) $fila->tot_cred, 2),
            'accion' => $accion,
        ];
    }
}
