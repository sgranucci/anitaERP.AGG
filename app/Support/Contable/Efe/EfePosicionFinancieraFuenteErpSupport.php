<?php

namespace App\Support\Contable\Efe;

use App\Models\Caja\AperturaGasto;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Caja\RendicionMaquina;
use App\Models\Caja\Cuentacaja;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoRendvalorCodigoSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaRendvalorCodigoSupport;
use App\Support\Caja\PosicionFinancieraOrdenConceptoSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Contable\CierreRendicionBingoConceptoTipos;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaFechajornadaSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lecturas ERP para posición financiera híbrida (ERP-first por día).
 *
 * Regla: si el bloque tiene data operativa ERP ese día, ese día se toma solo
 * de ERP; si no, el llamador conserva Anita. Nunca se suman ambas fuentes
 * el mismo día/bloque.
 */
class EfePosicionFinancieraFuenteErpSupport
{
    /**
     * @param  list<int>  $diasMes
     * @return array{
     *   base: array<string, array<int, float>>,
     *   premios: array<string, array<int, float>>,
     *   dias: array<int, true>
     * }
     */
    public function bingo(int $empresaId, Carbon $desde, Carbon $hasta, array $diasMes): array
    {
        $base = [
            'VENTA BINGO' => $this->vector($diasMes),
            'SOBRANTES' => $this->vector($diasMes),
            'VALES' => $this->vector($diasMes),
            'REDONDEO' => $this->vector($diasMes),
        ];
        $premios = [];
        $dias = [];

        $rendiciones = RendicionBingoCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha_jornada', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha_jornada')
            ->orderBy('id')
            ->get([
                'id', 'fecha_jornada', 'total_cartones', 'sobrante_faltante',
                'vales', 'redondeo', 'conceptos_json',
            ]);

        if ($rendiciones->isEmpty()) {
            return compact('base', 'premios', 'dias');
        }

        $conceptos = BingoConceptoRendicion::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', BingoConceptoRendicion::ESTADO_ACTIVO)
            ->get()
            ->keyBy('id');

        foreach ($rendiciones as $rendicion) {
            $dia = (int) $rendicion->fecha_jornada?->day;
            if (! in_array($dia, $diasMes, true)) {
                continue;
            }
            $dias[$dia] = true;

            $this->sumar($base, 'VENTA BINGO', $dia, (float) ($rendicion->total_cartones ?? 0));
            $this->sumar($base, 'SOBRANTES', $dia, (float) ($rendicion->sobrante_faltante ?? 0));
            $this->sumar($base, 'VALES', $dia, (float) ($rendicion->vales ?? 0));
            $this->sumar($base, 'REDONDEO', $dia, (float) ($rendicion->redondeo ?? 0));

            foreach ((array) ($rendicion->conceptos_json ?? []) as $linea) {
                if (! is_array($linea)) {
                    continue;
                }
                // l-posfinanc.c no tiene línea Depósito: Total bingo = venta + concbingo
                // (salvo CONCB_PORC_RECAUD) + SOBRANTES + VALES + REDONDEO. El saldo de
                // rendición es ese resultado, no un premio; incluirlo anula el total.
                if (! empty($linea['es_saldo_rendicion'])) {
                    continue;
                }
                $conceptoId = (int) ($linea['concepto_id'] ?? $linea['bingo_concepto_rendicion_id'] ?? 0);
                $concepto = $conceptos->get($conceptoId);
                if ($concepto === null || $concepto->es_saldo_rendicion) {
                    continue;
                }
                $desc = trim((string) ($concepto->detalle ?? ''));
                if ($desc === '') {
                    continue;
                }
                $destinoBase = $this->etiquetaBaseBingo($desc);
                if ($destinoBase !== null) {
                    $monto = (float) ($linea['monto'] ?? $linea['importe'] ?? 0);
                    if ($concepto->signo === BingoConceptoRendicion::SIGNO_RESTA) {
                        $monto = -abs($monto);
                    } elseif ($concepto->signo === BingoConceptoRendicion::SIGNO_SUMA) {
                        $monto = abs($monto);
                    }
                    if (abs($monto) >= 0.0001) {
                        $this->sumar($base, $destinoBase, $dia, $monto);
                    }

                    continue;
                }
                // Porcentuales sobre cartones: misma lógica que Anita tipo 0/1.
                if ($concepto->base_calculo === BingoConceptoRendicion::BASE_TOTAL_CARTONES
                    && (float) ($concepto->porcentaje ?? 0) > 0) {
                    $venta = (float) ($rendicion->total_cartones ?? 0);
                    $importe = -1 * $venta * ((float) $concepto->porcentaje / 100);
                    if ($concepto->signo === BingoConceptoRendicion::SIGNO_SUMA) {
                        $importe = abs($importe);
                    }
                    $this->sumar($premios, $desc, $dia, $importe, $diasMes);

                    continue;
                }
                $monto = (float) ($linea['monto'] ?? $linea['importe'] ?? 0);
                if (abs($monto) < 0.0001) {
                    continue;
                }
                if ($concepto->signo === BingoConceptoRendicion::SIGNO_RESTA) {
                    $monto = -abs($monto);
                }
                $this->sumar($premios, $desc, $dia, $monto, $diasMes);
            }
        }

        // Canones Municipalidad/Lotería: no viven en conceptos_json de sala; se
        // calculan como en el asiento de cierre (% sobre VENTA BINGO del día).
        $this->aplicarCanonesPagoBingo($base, $premios, $dias, $diasMes);

        return compact('base', 'premios', 'dias');
    }

    /**
     * @param  array<string, array<int, float>>  $base
     * @param  array<string, array<int, float>>  $premios
     * @param  array<int, true>  $dias
     * @param  list<int>  $diasMes
     */
    private function aplicarCanonesPagoBingo(array $base, array &$premios, array $dias, array $diasMes): void
    {
        foreach (CierreRendicionBingoConceptoTipos::extrasCatalogoReporte() as $extra) {
            if (($extra['tipo_conc'] ?? '') !== CierreRendicionBingoConceptoTipos::PAGO) {
                continue;
            }
            $desc = trim((string) ($extra['desc'] ?? ''));
            $pct = (float) ($extra['porcentaje'] ?? 0);
            if ($desc === '' || $pct <= 0) {
                continue;
            }
            foreach (array_keys($dias) as $dia) {
                $dia = (int) $dia;
                $venta = (float) ($base['VENTA BINGO'][$dia] ?? 0);
                if ($venta <= 0) {
                    continue;
                }
                $this->sumar($premios, $desc, $dia, -1 * $venta * ($pct / 100), $diasMes);
            }
        }
    }

    /**
     * Días con cierre C de máquinas en ERP (paridad Anita post-2010).
     *
     * @param  list<int>  $diasMes
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, string>  $apgastoDesc
     * @return array{
     *   base: array<string, array<int, float>>,
     *   medios: array<string, array<int, float>>,
     *   gastos: array<string, array<int, float>>,
     *   dias: array<int, true>
     * }
     */
    public function maquinasCompletas(
        int $empresaId,
        Carbon $desde,
        Carbon $hasta,
        array $diasMes,
        array $valormae,
        array $apgastoDesc,
    ): array {
        $base = [
            'MAQUINAS VENTAS' => $this->vector($diasMes),
            'MAQUINAS CAJA' => $this->vector($diasMes),
            'Vales fondo fijo' => $this->vector($diasMes),
            'Vales administracion' => $this->vector($diasMes),
            'Variacion de FF' => $this->vector($diasMes),
            'Diferencia de caja' => $this->vector($diasMes),
            'Caja en transito' => $this->vector($diasMes),
            'Pago 24' => $this->vector($diasMes),
        ];
        $medios = [];
        $gastos = [];
        foreach ($apgastoDesc as $desc) {
            $gastos[$desc] = $this->vector($diasMes);
        }
        $dias = [];

        $rendiciones = RendicionMaquina::query()
            ->with(['valores.cuentacaja', 'gastos.aperturaGasto'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($query) {
                $query->whereNull('estado')
                    ->orWhere('estado', '!=', RendicionMaquina::ESTADO_ANULADA);
            })
            ->where('turno', RendicionMaquinaTurno::COMPLETO)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        /** @var array<int, RendicionMaquina> $porDia */
        $porDia = [];
        foreach ($rendiciones as $rendicion) {
            $dia = (int) $rendicion->fecha?->day;
            if (! in_array($dia, $diasMes, true)) {
                continue;
            }
            $porDia[$dia] = $rendicion;
        }

        foreach ($porDia as $dia => $rendicion) {
            $dias[$dia] = true;
            $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
            $calc = is_array($rendicion->calc_json['variables'] ?? null)
                ? $rendicion->calc_json['variables']
                : [];

            $venta = $this->ventaMaquinasDesdeInputs($inputs);
            $deposito = (float) ($calc['deposito'] ?? $calc['calc.deposito'] ?? $inputs['deposito'] ?? 0);
            $difCajaRaw = (float) ($rendicion->dif_caja ?? $inputs['dif_caja'] ?? 0);
            $variacionFf = (float) ($inputs['variacion_ff'] ?? 0);
            $vales = (float) ($inputs['vales'] ?? 0);
            $reintegros = (float) ($inputs['reintegros'] ?? 0);
            $pago24 = (float) ($inputs['vta_ant_gastro'] ?? 0);

            $this->sumar($base, 'MAQUINAS VENTAS', $dia, $venta);
            $this->sumar($base, 'MAQUINAS CAJA', $dia, $deposito);
            $this->sumar($base, 'Vales administracion', $dia, $vales);
            $this->sumar($base, 'Vales fondo fijo', $dia, $reintegros);
            $this->sumar($base, 'Variacion de FF', $dia, $variacionFf);
            $this->sumar($base, 'Diferencia de caja', $dia, $difCajaRaw + $variacionFf);
            $cajaTransito = $venta > $deposito
                ? ($venta + $difCajaRaw) - $deposito
                : ($deposito - ($venta + $difCajaRaw)) * -1;
            $this->sumar($base, 'Caja en transito', $dia, $cajaTransito);
            $this->sumar($base, 'Pago 24', $dia, $pago24);

            foreach ($rendicion->valores as $valor) {
                $codigo = $this->resolverCodigoValormaeMaquina(
                    $valor->codigo_valormae,
                    $valor->cuentacaja,
                    $valormae,
                );
                if ($codigo === 15) {
                    continue;
                }
                $desc = ($codigo !== null ? ($valormae[$codigo]['desc'] ?? null) : null);
                if ($desc === null || $desc === '') {
                    // Sin catálogo Anita: aún así marcar como medio para no restarlo del Total.
                    $desc = trim((string) ($valor->cuentacaja?->nombre ?? ''));
                    if ($desc === '') {
                        continue;
                    }
                }
                // En ERP el monto de rendicion_maquina_valor ya está en pesos
                // (la cotización es referencia). No volver a multiplicar.
                $this->sumar($medios, $desc, $dia, (float) $valor->monto, $diasMes);
            }

            foreach ($rendicion->gastos as $gasto) {
                $codigo = (int) ($gasto->aperturaGasto?->codigo ?? 0);
                $desc = $apgastoDesc[$codigo]
                    ?? trim((string) ($gasto->aperturaGasto?->nombre ?? ''));
                if ($desc === '') {
                    continue;
                }
                $this->sumar($gastos, $desc, $dia, (float) $gasto->monto, $diasMes);
            }
        }

        return compact('base', 'medios', 'gastos', 'dias');
    }

    /**
     * @param  list<int>  $diasMes
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>  $codigosPermitidos
     * @return array{filas: array<string, array<int, float>>, dias: array<int, true>}
     */
    public function gastroEstac(
        int $empresaId,
        Carbon $desde,
        Carbon $hasta,
        array $diasMes,
        string $bloque,
        string $etiquetaZ,
        string $etiquetaTotal,
        array $valormae,
        array $codigosPermitidos,
        callable $clasificarSucursal,
        bool $redondeoNegado = false,
    ): array {
        $filas = [
            $etiquetaZ => $this->vector($diasMes),
            'Notas de credito' => $this->vector($diasMes),
            'Diferencia abandono de pago' => $this->vector($diasMes),
            'Redondeo' => $this->vector($diasMes),
            'Diferencia de caja' => $this->vector($diasMes),
        ];
        $mapperClass = $bloque === 'estac'
            ? RendicionEstacionamientoRendvalorCodigoSupport::class
            : RendicionGastronomiaRendvalorCodigoSupport::class;
        foreach (PosicionFinancieraOrdenConceptoSupport::ordenarValormaePermitidos(
            $empresaId,
            $valormae,
            $codigosPermitidos,
            $mapperClass,
        ) as $meta) {
            $desc = trim((string) ($meta['desc'] ?? ''));
            if ($desc !== '') {
                $filas[$desc] = $this->vector($diasMes);
            }
        }
        $filas[$etiquetaTotal] = $this->vector($diasMes);
        $dias = [];

        if ($bloque === 'estac') {
            $query = RendicionEstacionamientoCaja::query()
                ->with(['movimientos.cuentacaja', 'puntoventaCae', 'turnoOperativo.jornada'])
                ->where('empresa_id', $empresaId)
                ->where(function ($q) {
                    $q->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
                        ->orWhereNull('tipo')
                        ->orWhere('tipo', '');
                })
                ->whereHas('turnoOperativo.jornada', function ($q) use ($desde, $hasta) {
                    $q->whereBetween('fecha_jornada', [$desde->toDateString(), $hasta->toDateString()]);
                });
        } else {
            $query = RendicionGastronomiaCaja::query()
                ->with(['movimientos.cuentacaja', 'puntoventaCae', 'turnoOperativo.jornada'])
                ->where('empresa_id', $empresaId)
                ->where(function ($q) {
                    $q->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
                        ->orWhereNull('tipo')
                        ->orWhere('tipo', '');
                })
                ->whereHas('turnoOperativo.jornada', function ($q) use ($desde, $hasta) {
                    $q->whereBetween('fecha_jornada', [$desde->toDateString(), $hasta->toDateString()]);
                });
        }

        /** @var Collection<int, RendicionGastronomiaCaja|RendicionEstacionamientoCaja> $rendiciones */
        $rendiciones = $query->orderBy('id')->get();

        foreach ($rendiciones as $rendicion) {
            $sucursal = (int) ($rendicion->puntoventaCae?->codigo ?? $rendicion->puntoventa_cae_id ?? 0);
            if ($clasificarSucursal($empresaId, $sucursal) !== $bloque) {
                continue;
            }
            $fechaJornada = $rendicion->turnoOperativo?->jornada?->fecha_jornada;
            $dia = (int) ($fechaJornada?->day ?? 0);
            if (! in_array($dia, $diasMes, true)) {
                continue;
            }
            $dias[$dia] = true;

            $z = (float) ($rendicion->totalfactura ?? 0) + (float) ($rendicion->totalnotacredito ?? 0);
            $this->sumar($filas, $etiquetaZ, $dia, $z);
            $this->sumar($filas, 'Notas de credito', $dia, (float) ($rendicion->totalnotacredito ?? 0));
            $red = (float) ($rendicion->totalredondeo ?? 0)
                + (float) ($rendicion->totalredondeoinvitacion ?? 0);
            $this->sumar($filas, 'Redondeo', $dia, $redondeoNegado ? -$red : $red);
            $this->sumar($filas, 'Diferencia de caja', $dia, -1 * (float) ($rendicion->sobrantefaltante ?? 0));

            foreach ($rendicion->movimientos as $mov) {
                $cuenta = $mov->cuentacaja;
                if ($cuenta === null) {
                    continue;
                }
                try {
                    if ($mapperClass::omitirEnRendvalorAnita($cuenta)) {
                        continue;
                    }
                    $codigo = $mapperClass::codigoDesdeCuentacaja($empresaId, $cuenta);
                } catch (RuntimeException) {
                    continue;
                }
                if (! isset($valormae[$codigo], $codigosPermitidos[$codigo])) {
                    continue;
                }
                // Movimientos ERP ya expresan el importe en pesos de la operación.
                $this->sumar($filas, $valormae[$codigo]['desc'], $dia, (float) $mov->monto);
            }
        }

        if ($bloque === 'gastro') {
            $this->sumarCierresWaitry(
                $empresaId,
                $desde,
                $hasta,
                $diasMes,
                $filas,
                $dias,
                $etiquetaZ,
                $valormae,
                $codigosPermitidos,
            );
        }

        foreach ($diasMes as $dia) {
            if (! isset($dias[$dia])) {
                continue;
            }
            $totalSinZ = 0.0;
            foreach ($filas as $etiqueta => $porDia) {
                if ($etiqueta === $etiquetaZ || $etiqueta === $etiquetaTotal) {
                    continue;
                }
                $totalSinZ += (float) ($porDia[$dia] ?? 0);
            }
            $filas[$etiquetaTotal][$dia] = round($totalSinZ, 2);
        }

        return ['filas' => $filas, 'dias' => $dias];
    }

    /**
     * Factura CAEA del proceso Cierre Waitry: no vive en rendición de salón.
     * Solo gastronomía ( Anita sucursal 20 / PV CAEA ). Estacionamiento no entra.
     *
     * @param  list<int>  $diasMes
     * @param  array<string, array<int, float>>  $filas
     * @param  array<int, true>  $dias
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>  $codigosPermitidos
     */
    private function sumarCierresWaitry(
        int $empresaId,
        Carbon $desde,
        Carbon $hasta,
        array $diasMes,
        array &$filas,
        array &$dias,
        string $etiquetaZ,
        array $valormae,
        array $codigosPermitidos,
    ): void {
        $ventas = DB::table('venta as v')
            ->join('puntoventa as p', 'p.id', '=', 'v.puntoventa_id')
            ->where('p.empresa_id', $empresaId)
            ->where('v.leyenda', 'like', CierreJornadaProcesoFacturaFechajornadaSupport::LEYENDA_PREFIJO.'%')
            ->whereBetween('v.fechajornada', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('v.id')
            ->get(['v.id', 'v.fechajornada', 'v.total']);

        if ($ventas->isEmpty()) {
            return;
        }

        $ventaIds = $ventas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cuentasPorVenta = DB::table('caja_movimiento as cm')
            ->join('caja_movimiento_cuentacaja as cmc', 'cmc.caja_movimiento_id', '=', 'cm.id')
            ->whereIn('cm.venta_id', $ventaIds)
            ->get(['cm.venta_id', 'cmc.cuentacaja_id', 'cmc.monto']);

        $cuentaIds = $cuentasPorVenta->pluck('cuentacaja_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        $cuentas = $cuentaIds === []
            ? collect()
            : Cuentacaja::query()->whereIn('id', $cuentaIds)->get()->keyBy('id');

        $mediosPorVenta = $cuentasPorVenta->groupBy(fn ($row) => (int) $row->venta_id);

        foreach ($ventas as $venta) {
            $dia = (int) Carbon::parse((string) $venta->fechajornada)->day;
            if (! in_array($dia, $diasMes, true)) {
                continue;
            }
            $dias[$dia] = true;
            $this->sumar($filas, $etiquetaZ, $dia, (float) $venta->total);

            foreach ($mediosPorVenta->get((int) $venta->id, collect()) as $linea) {
                $cuenta = $cuentas->get((int) $linea->cuentacaja_id);
                if (! $cuenta instanceof Cuentacaja) {
                    continue;
                }
                try {
                    if (RendicionGastronomiaRendvalorCodigoSupport::omitirEnRendvalorAnita($cuenta)) {
                        continue;
                    }
                    $codigo = RendicionGastronomiaRendvalorCodigoSupport::codigoDesdeCuentacaja($empresaId, $cuenta);
                } catch (RuntimeException) {
                    continue;
                }
                if (! isset($valormae[$codigo], $codigosPermitidos[$codigo])) {
                    continue;
                }
                $this->sumar($filas, $valormae[$codigo]['desc'], $dia, (float) $linea->monto);
            }
        }
    }

    /**
     * @param  array<string, array<int, float>>  $anita
     * @param  array<string, array<int, float>>  $erp
     * @param  array<int, true>  $diasErp
     * @param  list<int>  $diasMes
     * @return array<string, array<int, float>>
     */
    public function mergePorDia(array $anita, array $erp, array $diasErp, array $diasMes): array
    {
        if ($diasErp === []) {
            return $anita;
        }

        $etiquetas = array_unique(array_merge(array_keys($anita), array_keys($erp)));
        $out = [];
        foreach ($etiquetas as $etiqueta) {
            $out[$etiqueta] = $this->vector($diasMes);
            foreach ($diasMes as $dia) {
                $out[$etiqueta][$dia] = isset($diasErp[$dia])
                    ? round((float) ($erp[$etiqueta][$dia] ?? 0), 2)
                    : round((float) ($anita[$etiqueta][$dia] ?? 0), 2);
            }
        }

        return $out;
    }

    /**
     * Anita manda si ese día ya tiene el bloque; ERP solo completa huecos.
     * Útil cuando la fecha ERP del bloque no es confiable (ej. bingo).
     *
     * @param  array<string, array<int, float>>  $anita
     * @param  array<string, array<int, float>>  $erp
     * @param  array<int, true>  $diasAnita
     * @param  array<int, true>  $diasErp
     * @param  list<int>  $diasMes
     * @return array<string, array<int, float>>
     */
    public function mergePorDiaAnitaPrimero(
        array $anita,
        array $erp,
        array $diasAnita,
        array $diasErp,
        array $diasMes,
    ): array {
        if ($diasAnita === []) {
            return $this->mergePorDia($anita, $erp, $diasErp, $diasMes);
        }

        $etiquetas = array_unique(array_merge(array_keys($anita), array_keys($erp)));
        $out = [];
        foreach ($etiquetas as $etiqueta) {
            $out[$etiqueta] = $this->vector($diasMes);
            foreach ($diasMes as $dia) {
                if (isset($diasAnita[$dia])) {
                    $out[$etiqueta][$dia] = round((float) ($anita[$etiqueta][$dia] ?? 0), 2);
                } elseif (isset($diasErp[$dia])) {
                    $out[$etiqueta][$dia] = round((float) ($erp[$etiqueta][$dia] ?? 0), 2);
                } else {
                    $out[$etiqueta][$dia] = round((float) ($anita[$etiqueta][$dia] ?? 0), 2);
                }
            }
        }

        return $out;
    }

    /**
     * ERP-first por día, pero si una etiqueta queda en cero y Anita tiene monto,
     * completa el hueco (ej. MEP gastronomía ausente en movimientos ERP).
     *
     * @param  array<string, array<int, float>>  $anita
     * @param  array<string, array<int, float>>  $erp
     * @param  array<int, true>  $diasErp
     * @param  list<int>  $diasMes
     * @return array<string, array<int, float>>
     */
    public function mergePorDiaCompletarHuecos(
        array $anita,
        array $erp,
        array $diasErp,
        array $diasMes,
    ): array {
        if ($diasErp === []) {
            return $anita;
        }

        $etiquetas = array_unique(array_merge(array_keys($anita), array_keys($erp)));
        $out = [];
        foreach ($etiquetas as $etiqueta) {
            $out[$etiqueta] = $this->vector($diasMes);
            foreach ($diasMes as $dia) {
                if (! isset($diasErp[$dia])) {
                    $out[$etiqueta][$dia] = round((float) ($anita[$etiqueta][$dia] ?? 0), 2);

                    continue;
                }
                $valorErp = (float) ($erp[$etiqueta][$dia] ?? 0);
                $valorAnita = (float) ($anita[$etiqueta][$dia] ?? 0);
                $out[$etiqueta][$dia] = abs($valorErp) > 0.0001
                    ? round($valorErp, 2)
                    : round($valorAnita, 2);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, float>>  $filas
     * @param  list<int>  $diasMes
     * @return array<string, array<int, float>>
     */
    public function recalcularTotalBloque(
        array $filas,
        string $etiquetaZ,
        string $etiquetaTotal,
        array $diasMes,
        ?array $anita = null,
    ): array {
        foreach ($diasMes as $dia) {
            $suma = 0.0;
            foreach ($filas as $etiqueta => $porDia) {
                if ($etiqueta === $etiquetaZ || $etiqueta === $etiquetaTotal) {
                    continue;
                }
                $suma += (float) ($porDia[$dia] ?? 0);
            }
            $filas[$etiquetaTotal][$dia] = round($suma, 2);

            // Z Anita suele incluir medios que ERP aún no cargó (MEP): preferir el mayor.
            if ($anita !== null) {
                $zErp = (float) ($filas[$etiquetaZ][$dia] ?? 0);
                $zAnita = (float) ($anita[$etiquetaZ][$dia] ?? 0);
                if ($zAnita > $zErp + 0.01) {
                    $filas[$etiquetaZ][$dia] = round($zAnita, 2);
                }
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, array<int, float>>  $mapa
     * @return array<int, true>
     */
    public function diasConMonto(array $mapa): array
    {
        $dias = [];
        foreach ($mapa as $porDia) {
            foreach ($porDia as $dia => $importe) {
                if (abs((float) $importe) > 0.0001) {
                    $dias[(int) $dia] = true;
                }
            }
        }

        return $dias;
    }

    /**
     * @return array<int, string>
     */
    public function apgastoDescDesdeErp(): array
    {
        $map = [];
        foreach (AperturaGasto::query()->orderBy('codigo')->get(['codigo', 'nombre']) as $fila) {
            $codigo = (int) $fila->codigo;
            if ($codigo > 0) {
                $map[$codigo] = trim((string) $fila->nombre);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function ventaMaquinasDesdeInputs(array $inputs): float
    {
        $ingresoRodillos = (float) ($inputs['venta_ficha'] ?? 0)
            + (float) ($inputs['drop_billete'] ?? 0)
            + (float) ($inputs['dropem_rodillo'] ?? $inputs['billem_rodillo'] ?? 0);
        $salidaRodillos = (float) ($inputs['pago_manual'] ?? 0)
            + (float) ($inputs['tito'] ?? 0)
            + (float) ($inputs['hopper'] ?? 0);
        $ingresoRuleta = (float) ($inputs['venta_ruleta'] ?? 0)
            + (float) ($inputs['drop_ruleta'] ?? 0)
            + (float) ($inputs['dropem_ruleta'] ?? $inputs['billem_ruleta'] ?? 0);
        $salidaRuleta = (float) ($inputs['salida_ruleta'] ?? 0)
            + (float) ($inputs['tito_ruleta'] ?? 0);

        return ($ingresoRodillos - $salidaRodillos) + ($ingresoRuleta - $salidaRuleta);
    }

    /**
     * Cuenta ERP → código valormae Anita (misma etiqueta que l-posfinanc).
     *
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     */
    private function resolverCodigoValormaeMaquina(
        mixed $codigoValormae,
        mixed $cuenta,
        array $valormae,
    ): ?int {
        $desdeMae = (int) $codigoValormae;
        if ($desdeMae > 0 && isset($valormae[$desdeMae])) {
            return $desdeMae;
        }

        if (! $cuenta instanceof Cuentacaja) {
            return $desdeMae > 0 ? $desdeMae : null;
        }

        $codigoCuenta = trim((string) $cuenta->codigo);
        if ($codigoCuenta !== '' && ctype_digit($codigoCuenta)) {
            $comoCodigo = (int) $codigoCuenta;
            if (isset($valormae[$comoCodigo])) {
                return $comoCodigo;
            }
        }

        return $this->codigoValormaePorHeuristicaCuenta($cuenta, $valormae)
            ?? ($desdeMae > 0 ? $desdeMae : null);
    }

    /**
     * Heurística por nombre/código de cuentacaja cuando codigo_valormae no viene cargado.
     *
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     */
    private function codigoValormaePorHeuristicaCuenta(Cuentacaja $cuenta, array $valormae): ?int
    {
        $texto = mb_strtoupper(trim((string) $cuenta->nombre).' '.trim((string) $cuenta->codigo));
        $textoNorm = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        $candidatos = [];
        if (str_contains($textoNorm, 'TOTALCOIN') || str_contains($textoNorm, 'TOTAL COIN')) {
            if (str_contains($textoNorm, 'CAJA')) {
                $candidatos = ['TOTALCOIN QR CAJA', 'TOTAL COIN CAJA'];
            } else {
                $candidatos = ['TOTALCOIN QR MAQ', 'TOTAL COIN MAQ', 'TOTALCOIN QR MAQUINAS'];
            }
        } elseif (str_contains($textoNorm, 'MEP') && (str_contains($textoNorm, 'MAQ') || $cuenta->codigo === 'MMEP')) {
            $candidatos = ['MEP MAQUINAS'];
        } elseif (str_contains($textoNorm, 'DEPOSITO') && str_contains($textoNorm, 'QR')) {
            $candidatos = ['DEPOSITO EFECTIVO PAGO QR'];
        } elseif (str_contains($textoNorm, 'CRIPTO') || str_contains($textoNorm, 'USDT') || str_contains($textoNorm, 'SATOSHI')) {
            $candidatos = ['EFECTIVO CRIPTO', 'CRIPTO USDT'];
        } elseif (str_contains($textoNorm, 'DOLAR') || str_contains($textoNorm, 'DÓLAR')) {
            $candidatos = ['EFECTIVO DOLARES', 'EFECTIVO DÓLARES'];
        } elseif (str_contains($textoNorm, 'EURO')) {
            $candidatos = ['EFECTIVO EUROS'];
        } elseif (
            str_contains($textoNorm, 'CAJA PESOS')
            || (str_contains($textoNorm, 'EFECTIVO') && str_contains($textoNorm, 'PESOS') && ! str_contains($textoNorm, 'QR'))
        ) {
            $candidatos = ['EFECTIVO PESOS'];
        } elseif (
            str_contains($textoNorm, 'MACRO')
            || str_contains($textoNorm, 'ITAU')
            || str_contains($textoNorm, 'TRANSF')
            || str_contains($textoNorm, 'CHECK MS')
        ) {
            $candidatos = ['TRANSF. CHECK MS', 'TRANSF CHECK MS'];
        } elseif (preg_match('/\bQR\b/', $textoNorm) && str_contains($textoNorm, 'MAQ')) {
            $candidatos = ['QR'];
        }

        foreach ($candidatos as $needle) {
            $codigo = $this->buscarCodigoValormaePorDesc($needle, $valormae);
            if ($codigo !== null) {
                return $codigo;
            }
        }

        // Contiene mutuo normalizado (último recurso).
        foreach ($valormae as $codigo => $meta) {
            $desc = mb_strtoupper(trim((string) ($meta['desc'] ?? '')));
            if ($desc === '') {
                continue;
            }
            $descCompact = str_replace(['.', ' '], '', $desc);
            $textoCompact = str_replace(['.', ' '], '', $textoNorm);
            if ($descCompact !== '' && (
                str_contains($textoCompact, $descCompact)
                || str_contains($descCompact, $textoCompact)
                || str_contains($textoNorm, $desc)
                || str_contains($desc, $textoNorm)
            )) {
                return (int) $codigo;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     */
    private function buscarCodigoValormaePorDesc(string $needleUpper, array $valormae): ?int
    {
        $needle = mb_strtoupper(trim($needleUpper));
        $needleCompact = str_replace(['.', ' '], '', $needle);
        foreach ($valormae as $codigo => $meta) {
            $desc = mb_strtoupper(trim((string) ($meta['desc'] ?? '')));
            $descCompact = str_replace(['.', ' '], '', $desc);
            if ($desc === $needle || $descCompact === $needleCompact) {
                return (int) $codigo;
            }
            if ($needleCompact !== '' && (
                str_contains($descCompact, $needleCompact) || str_contains($needleCompact, $descCompact)
            )) {
                return (int) $codigo;
            }
        }

        return null;
    }

    /**
     * Conceptos ERP que en l-posfinanc.c son líneas fijas (rendb_sobrante / vales / redondeo),
     * no concbingo. Van a esas filas para no duplicar ni pisar el Total bingo.
     */
    private function etiquetaBaseBingo(string $detalle): ?string
    {
        $clave = mb_strtoupper(trim($detalle));
        $clave = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $clave);

        return match ($clave) {
            'SOBRANTE', 'SOBRANTES' => 'SOBRANTES',
            'VALE', 'VALES' => 'VALES',
            'REDONDEO' => 'REDONDEO',
            default => null,
        };
    }

    /**
     * @param  list<int>  $diasMes
     * @return array<int, float>
     */
    private function vector(array $diasMes): array
    {
        $out = [];
        foreach ($diasMes as $dia) {
            $out[$dia] = 0.0;
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, float>>  $mapa
     * @param  list<int>|null  $diasMes
     */
    private function sumar(array &$mapa, string $etiqueta, int $dia, float $importe, ?array $diasMes = null): void
    {
        if (! isset($mapa[$etiqueta])) {
            $mapa[$etiqueta] = $diasMes === null ? [] : $this->vector($diasMes);
        }
        $mapa[$etiqueta][$dia] = round(($mapa[$etiqueta][$dia] ?? 0) + $importe, 2);
    }
}
