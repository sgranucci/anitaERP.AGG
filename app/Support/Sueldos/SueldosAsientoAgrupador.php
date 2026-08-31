<?php

namespace App\Support\Sueldos;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

/**
 * Detalle de la corrida → líneas por cuenta + centro de costo + D/H.
 * Solo conceptos con override de imputación (AS Anita). No usa fallback
 * por tipo: eso duplicaría los haberes que ya van dentro de 3501/3502/…
 * Si falta el AS 3522, el haber a pagar es el neto de los recibos (no el
 * residual del diario): si entonces Debe ≠ Haber, el mapeo está incompleto.
 */
final class SueldosAsientoAgrupador
{
    /**
     * @return array{
     *   empresa_id: int,
     *   total_debe: float,
     *   total_haber: float,
     *   haber_a_pagar: float,
     *   total_neto_cabecera: float,
     *   total_bruto_cabecera: float,
     *   total_contribuciones_recibos: float,
     *   lineas: list<array<string, mixed>>,
     *   informe_conceptos: list<array<string, mixed>>,
     *   errores: list<string>,
     *   advertencias: list<string>,
     *   conceptos_usados: int,
     *   renglones_omitidos: int
     * }
     */
    public static function armar(Liquidacion_Sueldos $liq): array
    {
        $empresaId = (int) $liq->empresa_id;
        $detalles = Liquidacion_Detalle_Sueldos::query()
            ->where('liquidacion_id', $liq->id)
            ->whereNotNull('concepto_id')
            ->where('importe', '!=', 0)
            ->with(['concepto:id,codigo,descripcion,tipo'])
            ->get();

        $empleadoIds = $detalles->pluck('empleado_id')->unique()->filter()->values()->all();
        $ccPorEmpleado = $empleadoIds === []
            ? collect()
            : Empleado_Sueldos::query()->whereIn('id', $empleadoIds)->pluck('centrocosto_id', 'id');

        $acum = [];
        $restas = [];
        $errores = [];
        $conceptosUsados = [];
        $informe = [];
        $omitidos = 0;

        foreach ($detalles as $detalle) {
            $concepto = $detalle->concepto;
            if ($concepto === null) {
                $omitidos++;
                continue;
            }

            $importe = round((float) $detalle->importe, 2);
            if (abs($importe) < 0.01) {
                continue;
            }

            $cid = (int) $concepto->id;
            if ((string) $detalle->columna === 'neto' || (string) $concepto->tipo === 'neto') {
                $omitidos++;
                self::acumularInforme($informe, $concepto, $importe, 'omitido', false, 0, 0, 'Neto: va por sueldos.a_pagar, no se imputa.');
                continue;
            }

            $resuelto = SueldosAsientoMapeoSupport::resolver($empresaId, $concepto);
            $origen = (string) ($resuelto['origen'] ?? '');
            $debeId = (int) ($resuelto['cuenta_debe_id'] ?? 0);
            $haberId = (int) ($resuelto['cuenta_haber_id'] ?? 0);

            if ($origen !== 'concepto') {
                $omitidos++;
                self::acumularInforme(
                    $informe,
                    $concepto,
                    $importe,
                    $origen !== '' ? $origen : 'sin_as',
                    false,
                    $debeId,
                    $haberId,
                    self::motivoOmitido($concepto, $origen)
                );
                continue;
            }

            if ($debeId <= 0 && $haberId <= 0) {
                $errores[] = 'Concepto AS '.$concepto->codigo.' ('.$concepto->descripcion.') sin cuenta debe/haber.';
                self::acumularInforme($informe, $concepto, $importe, 'concepto', false, 0, 0, 'Override sin cuenta.');
                continue;
            }

            $ccId = (int) ($ccPorEmpleado[$detalle->empleado_id] ?? 0);
            $obs = $concepto->codigo.' '.$concepto->descripcion;
            $abs = abs($importe);
            $invertido = $importe < 0;
            if ($debeId > 0) {
                self::sumar($acum, $debeId, $ccId, $invertido ? 'H' : 'D', $abs, $obs);
            }
            if ($haberId > 0) {
                self::sumar($acum, $haberId, $ccId, $invertido ? 'D' : 'H', $abs, $obs);
            }

            $restaCodigo = trim((string) ($resuelto['resta_codigo'] ?? ''));
            if ($restaCodigo !== '') {
                $restas[] = [
                    'codigo' => $restaCodigo,
                    'cc_id' => $ccId,
                    'importe' => $importe,
                ];
            }

            $conceptosUsados[$cid] = true;
            self::acumularInforme($informe, $concepto, $importe, 'concepto', true, $debeId, $haberId, 'Override AS.');
        }

        foreach ($restas as $resta) {
            $cuentaId = self::cuentaIdPorCodigo($empresaId, $resta['codigo']);
            if ($cuentaId === null) {
                $errores[] = 'No existe la cuenta '.$resta['codigo'].' para restar cargas (empresa '.$empresaId.').';
                continue;
            }
            $clave = self::clave($cuentaId, (int) $resta['cc_id'], 'D');
            if (! isset($acum[$clave])) {
                $errores[] = 'Resta '.$resta['codigo'].' sin línea de debe en el centro de costo '
                    .((int) $resta['cc_id'] > 0 ? $resta['cc_id'] : 'sin CC').'.';
                continue;
            }
            $acum[$clave]['importe'] = round($acum[$clave]['importe'] - (float) $resta['importe'], 2);
            if ($acum[$clave]['importe'] < -0.009) {
                $errores[] = 'La resta sobre '.$resta['codigo'].' deja la línea en negativo.';
            }
        }

        $recibos = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liq->id)
            ->get(['empleado_id', 'neto_a_pagar', 'total_bruto', 'total_contribuciones']);

        $faltanCc = $recibos->pluck('empleado_id')->unique()->filter()
            ->diff($ccPorEmpleado->keys());
        if ($faltanCc->isNotEmpty()) {
            $extra = Empleado_Sueldos::query()->whereIn('id', $faltanCc->all())->pluck('centrocosto_id', 'id');
            $ccPorEmpleado = $ccPorEmpleado->union($extra);
        }

        $netoPorCc = [];
        $totalBrutoRecibos = 0.0;
        $totalContribRecibos = 0.0;
        foreach ($recibos as $recibo) {
            $ccRecibo = (int) ($ccPorEmpleado[$recibo->empleado_id] ?? 0);
            $netoPorCc[$ccRecibo] = ($netoPorCc[$ccRecibo] ?? 0.0) + (float) $recibo->neto_a_pagar;
            $totalBrutoRecibos += (float) $recibo->total_bruto;
            $totalContribRecibos += (float) $recibo->total_contribuciones;
        }

        $aPagarId = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::SUELDOS_A_PAGAR
        ) ?? 0);

        $haberAPagarExistente = 0.0;
        foreach ($acum as $fila) {
            if ($aPagarId > 0 && (int) $fila['cuenta_id'] === $aPagarId && $fila['dh'] === 'H') {
                $haberAPagarExistente += round((float) $fila['importe'], 2);
            }
        }
        $haberAPagarExistente = round($haberAPagarExistente, 2);
        $netoCab = round((float) ($liq->total_neto ?? 0), 2);
        $advertencias = [];

        if ($netoCab >= 0.01 && $aPagarId <= 0) {
            $errores[] = 'Falta la cuenta automática sueldos.a_pagar para cerrar el neto.';
        } elseif ($netoCab >= 0.01 && $haberAPagarExistente < 0.01) {
            foreach ($netoPorCc as $ccId => $impNeto) {
                $impNeto = round((float) $impNeto, 2);
                if ($impNeto >= 0.01) {
                    self::sumar($acum, $aPagarId, (int) $ccId, 'H', $impNeto, 'Sueldos a pagar (neto de recibos)');
                } elseif ($impNeto <= -0.01) {
                    self::sumar($acum, $aPagarId, (int) $ccId, 'D', abs($impNeto), 'Sueldos a pagar (neto de recibos)');
                }
            }
            $advertencias[] = 'No vino el AS 3522: se imputó el neto de los recibos en sueldos a pagar.';
        }

        $cuentaIds = [];
        $ccIds = [];
        foreach ($acum as $fila) {
            if (round((float) $fila['importe'], 2) < 0.01) {
                continue;
            }
            $cuentaIds[] = (int) $fila['cuenta_id'];
            if ((int) $fila['cc_id'] > 0) {
                $ccIds[] = (int) $fila['cc_id'];
            }
        }
        foreach ($informe as $filaInf) {
            foreach (['cuenta_debe_id', 'cuenta_haber_id'] as $campoCuenta) {
                $idInf = (int) ($filaInf[$campoCuenta] ?? 0);
                if ($idInf > 0) {
                    $cuentaIds[] = $idInf;
                }
            }
        }

        $cuentas = $cuentaIds === []
            ? collect()
            : Cuentacontable::query()
                ->whereIn('id', array_values(array_unique($cuentaIds)))
                ->get(['id', 'codigo', 'nombre', 'empresa_id'])
                ->keyBy('id');
        $centros = $ccIds === []
            ? collect()
            : Centrocosto::query()
                ->whereIn('id', array_values(array_unique($ccIds)))
                ->get(['id', 'codigo', 'nombre'])
                ->keyBy('id');

        $lineas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $haberAPagar = 0.0;
        foreach ($acum as $fila) {
            $importe = round((float) $fila['importe'], 2);
            if ($importe < 0.01) {
                continue;
            }
            $cuenta = $cuentas[$fila['cuenta_id']] ?? null;
            if ($cuenta === null) {
                $errores[] = 'Cuenta id '.$fila['cuenta_id'].' no encontrada.';
                continue;
            }
            if ((int) $cuenta->empresa_id !== $empresaId) {
                $errores[] = 'La cuenta '.$cuenta->codigo.' no pertenece a la empresa de la corrida.';
                continue;
            }
            $cc = ((int) $fila['cc_id'] > 0) ? ($centros[$fila['cc_id']] ?? null) : null;
            $esDebe = $fila['dh'] === 'D';
            if ($esDebe) {
                $totalDebe += $importe;
            } else {
                $totalHaber += $importe;
                if ($aPagarId > 0 && (int) $fila['cuenta_id'] === $aPagarId) {
                    $haberAPagar += $importe;
                }
            }
            $lineas[] = [
                'cuentacontable_id' => (int) $fila['cuenta_id'],
                'cuenta_codigo' => (string) $cuenta->codigo,
                'cuenta_nombre' => (string) $cuenta->nombre,
                'centrocosto_id' => (int) $fila['cc_id'] > 0 ? (int) $fila['cc_id'] : null,
                'centrocosto_codigo' => $cc ? (string) $cc->codigo : '',
                'centrocosto_nombre' => $cc ? (string) $cc->nombre : '',
                'dh' => $fila['dh'],
                'debe' => $esDebe ? $importe : 0.0,
                'haber' => $esDebe ? 0.0 : $importe,
                'observacion' => $fila['observacion'],
            ];
        }

        usort($lineas, function (array $a, array $b): int {
            $cmp = strcmp((string) $a['cuenta_codigo'], (string) $b['cuenta_codigo']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ((int) ($a['centrocosto_id'] ?? 0)) <=> ((int) ($b['centrocosto_id'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['dh'], (string) $b['dh']);
        });

        $informeConceptos = self::hidratarInforme($informe, $cuentas);
        usort($informeConceptos, fn (array $a, array $b) => ((int) $a['codigo']) <=> ((int) $b['codigo']));

        $modo = SueldosAsientoModoSupport::resolver($empresaId);
        $grupos = self::gruposDesdeLineas($lineas, $modo);

        return [
            'empresa_id' => $empresaId,
            'modo' => $modo,
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'haber_a_pagar' => round($haberAPagar, 2),
            'total_neto_cabecera' => $netoCab,
            'total_bruto_cabecera' => round((float) ($liq->total_bruto ?? $totalBrutoRecibos), 2),
            'total_contribuciones_recibos' => round($totalContribRecibos, 2),
            'lineas' => $lineas,
            'grupos' => $grupos,
            'informe_conceptos' => $informeConceptos,
            'errores' => $errores,
            'advertencias' => $advertencias,
            'conceptos_usados' => count($conceptosUsados),
            'renglones_omitidos' => $omitidos,
        ];
    }

    /**
     * ERP: un grupo con las líneas tal cual (CC en todas).
     * Anita: un grupo por CC de origen; dentro, las cuentas 2xx pasan a CC 0.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{centrocosto_id: int|null, etiqueta: string, lineas: list<array<string, mixed>>, total_debe: float, total_haber: float}>
     */
    private static function gruposDesdeLineas(array $lineas, string $modo): array
    {
        if (SueldosAsientoModoSupport::normalizar($modo) !== SueldosAsientoModoSupport::ANITA) {
            return [self::paqueteGrupo($lineas, null, 'Un asiento (modo ERP)')];
        }

        $buckets = [];
        $etiquetas = [];
        foreach ($lineas as $linea) {
            $cc = (int) ($linea['centrocosto_id'] ?? 0);
            $buckets[$cc][] = $linea;
            if (! isset($etiquetas[$cc])) {
                $cod = trim((string) ($linea['centrocosto_codigo'] ?? ''));
                $nom = trim((string) ($linea['centrocosto_nombre'] ?? ''));
                $etiquetas[$cc] = $cc > 0
                    ? trim($cod.' '.$nom)
                    : 'Sin centro de costo';
            }
        }
        ksort($buckets);

        $grupos = [];
        foreach ($buckets as $cc => $filas) {
            $lsOut = [];
            foreach ($filas as $linea) {
                if (SueldosAsientoModoSupport::esCuentaPasivo((string) ($linea['cuenta_codigo'] ?? ''))) {
                    $linea['centrocosto_id'] = null;
                    $linea['centrocosto_codigo'] = '';
                    $linea['centrocosto_nombre'] = '';
                }
                $lsOut[] = $linea;
            }
            $grupos[] = self::paqueteGrupo(
                $lsOut,
                $cc > 0 ? $cc : null,
                $etiquetas[$cc] ?? 'Centro de costo'
            );
        }

        return $grupos;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{centrocosto_id: int|null, etiqueta: string, lineas: list<array<string, mixed>>, total_debe: float, total_haber: float}
     */
    private static function paqueteGrupo(array $lineas, ?int $ccId, string $etiqueta): array
    {
        $debe = 0.0;
        $haber = 0.0;
        foreach ($lineas as $linea) {
            $debe += (float) ($linea['debe'] ?? 0);
            $haber += (float) ($linea['haber'] ?? 0);
        }

        return [
            'centrocosto_id' => $ccId,
            'etiqueta' => $etiqueta,
            'lineas' => $lineas,
            'total_debe' => round($debe, 2),
            'total_haber' => round($haber, 2),
        ];
    }

    /**
     * @param  array<string, array{cuenta_id: int, cc_id: int, dh: string, importe: float, observacion: string}>  $acum
     */
    private static function sumar(array &$acum, int $cuentaId, int $ccId, string $dh, float $importe, string $obs): void
    {
        $clave = self::clave($cuentaId, $ccId, $dh);
        if (! isset($acum[$clave])) {
            $acum[$clave] = [
                'cuenta_id' => $cuentaId,
                'cc_id' => $ccId,
                'dh' => $dh,
                'importe' => 0.0,
                'observacion' => $obs,
            ];
        }
        $acum[$clave]['importe'] = round($acum[$clave]['importe'] + $importe, 2);
    }

    private static function clave(int $cuentaId, int $ccId, string $dh): string
    {
        return $cuentaId.'|'.$ccId.'|'.$dh;
    }

    /**
     * @param  array<int, array<string, mixed>>  $informe
     */
    private static function acumularInforme(
        array &$informe,
        $concepto,
        float $importe,
        string $origen,
        bool $enAsiento,
        int $debeId,
        int $haberId,
        string $motivo
    ): void {
        $cid = (int) $concepto->id;
        if (! isset($informe[$cid])) {
            $informe[$cid] = [
                'concepto_id' => $cid,
                'codigo' => (int) $concepto->codigo,
                'descripcion' => (string) $concepto->descripcion,
                'tipo' => (string) $concepto->tipo,
                'importe' => 0.0,
                'origen' => $origen,
                'en_asiento' => $enAsiento,
                'cuenta_debe_id' => $debeId,
                'cuenta_haber_id' => $haberId,
                'motivo' => $motivo,
            ];
        }
        $informe[$cid]['importe'] = round((float) $informe[$cid]['importe'] + $importe, 2);
        if ($enAsiento) {
            $informe[$cid]['en_asiento'] = true;
            $informe[$cid]['origen'] = 'concepto';
        }
    }

    private static function motivoOmitido($concepto, string $origen): string
    {
        $tipo = (string) ($concepto->tipo ?? '');
        if ($tipo === 'informativo') {
            return 'Informativo sin override AS: no entra al asiento.';
        }
        if (SueldosAsientoMapeoSupport::esTipoImputable($tipo)) {
            return 'Sin override AS: se asume incluido en 35xx (no se postea para no duplicar).';
        }

        return $origen !== '' ? 'Origen '.$origen.': no se postea.' : 'Sin mapeo AS.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $informe
     * @param  \Illuminate\Support\Collection<int, Cuentacontable>  $cuentas
     * @return list<array<string, mixed>>
     */
    private static function hidratarInforme(array $informe, $cuentas): array
    {
        $out = [];
        foreach ($informe as $fila) {
            $debe = $cuentas[(int) ($fila['cuenta_debe_id'] ?? 0)] ?? null;
            $haber = $cuentas[(int) ($fila['cuenta_haber_id'] ?? 0)] ?? null;
            $out[] = [
                'concepto_id' => (int) $fila['concepto_id'],
                'codigo' => (int) $fila['codigo'],
                'descripcion' => (string) $fila['descripcion'],
                'tipo' => (string) $fila['tipo'],
                'tipo_label' => ConceptoTipo::etiquetaTipo((string) $fila['tipo']),
                'importe' => round((float) $fila['importe'], 2),
                'origen' => (string) $fila['origen'],
                'origen_label' => $fila['en_asiento']
                    ? 'AS (concepto)'
                    : (($fila['origen'] ?? '') === 'omitido' ? 'Omitido' : 'No se postea'),
                'en_asiento' => (bool) $fila['en_asiento'],
                'cuenta_debe_codigo' => $debe ? (string) $debe->codigo : '',
                'cuenta_debe_nombre' => $debe ? (string) $debe->nombre : '',
                'cuenta_haber_codigo' => $haber ? (string) $haber->codigo : '',
                'cuenta_haber_nombre' => $haber ? (string) $haber->nombre : '',
                'motivo' => (string) $fila['motivo'],
            ];
        }

        return $out;
    }

    private static function cuentaIdPorCodigo(int $empresaId, string $codigo): ?int
    {
        $id = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id');

        $n = (int) $id;

        return $n > 0 ? $n : null;
    }
}
