<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Services\Arca\ArcaMtxcaFacturaElectronicaService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Alinea en Anita (Informix) una FAC ya autorizada en ARCA/MTXCA
 * para que IVA ventas coincida con lo informado a AFIP.
 *
 * Tablas: venta, climov, comprob, compaux, stkmov, subdiario (+ venibr si existe).
 * No toca CAE ni saldos de stkmae.
 */
final class AlinearFacturaAnitaArcaService
{
    private const SISTEMA_VENTAS = 'ventas';

    private const CUENTA_IVA = '211170000';

    /** Fallback IIBB si no hay venibr / ibrxprov (PBA histórico Bierzo). */
    private const CUENTA_IIBB_FALLBACK = '211271000';

    private const CUENTA_PERC_IVA = '211290000';

    private const CUENTA_VENTAS = '301100000';

    /** Contrapartida de toda línea de FAC: deudores por ventas. */
    private const CUENTA_DEUDORES = '113100000';

    /** Abasto (ARCA tributo 99) — cuenta vista en FAC Bierzo. */
    private const CUENTA_ABASTO = '302080000';

    /** @var array<string, string>|null provincia Anita => cuenta contable */
    private ?array $mapaCuentasIibbCache = null;

    public function __construct(
        private readonly ArcaMtxcaFacturaElectronicaService $mtxca,
        private readonly ApiAnita $api,
    ) {
    }

    /**
     * @return array{
     *   clave: string,
     *   backup_path: string,
     *   antes: array,
     *   arca: array,
     *   plan: list<array{tabla: string, descripcion: string, valores: string, where: string}>,
     *   aplicados: list<array{tabla: string, descripcion: string, ok: bool, raw?: string, error?: string}>,
     *   despues: ?array
     * }
     */
    public function alinear(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        int $cbteTipoAfip,
        bool $aplicar,
    ): array {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra));
        $clave = sprintf('%s %s %d %d', $tipo, $letra, $sucursal, $numero);

        $antes = $this->leerEstadoAnita($tipo, $letra, $sucursal, $numero);
        if ($antes['venta'] === null) {
            throw new \RuntimeException("No existe venta Anita para {$clave}.");
        }

        $arcaRaw = $this->mtxca->consultarComprobante($empresaId, $sucursal, $cbteTipoAfip, $numero);
        $arca = $this->normalizarArca($arcaRaw);

        $plan = $this->armarPlan($tipo, $letra, $sucursal, $numero, $antes, $arca);

        $dir = storage_path('app/reportes/alineacion_anita_arca');
        File::ensureDirectoryExists($dir);
        $stamp = date('Ymd_His');
        $backupPath = $dir.sprintf('/backup_%s_%s_%d_%d_%s.json', $tipo, $letra, $sucursal, $numero, $stamp);
        File::put($backupPath, json_encode([
            'clave' => $clave,
            'generado' => date('c'),
            'aplicar' => $aplicar,
            'antes' => $antes,
            'arca' => $arca,
            'plan' => $plan,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $aplicados = [];
        if ($aplicar) {
            foreach ($plan as $paso) {
                try {
                    $acc = $paso['acc'] ?? 'update';
                    $payload = [
                        'acc' => $acc,
                        'tabla' => $paso['tabla'],
                        'sistema' => self::SISTEMA_VENTAS,
                    ];
                    if ($acc === 'update') {
                        $payload['valores'] = $paso['valores'];
                        $payload['whereArmado'] = $paso['where'];
                    } elseif ($acc === 'delete') {
                        $payload['whereArmado'] = $paso['where'];
                    } elseif ($acc === 'insert') {
                        $payload['campos'] = $paso['campos'];
                        $payload['valores'] = $paso['valores'];
                    } else {
                        throw new \RuntimeException("acc no soportado: {$acc}");
                    }
                    $raw = $this->api->apiCallEscritura(
                        $payload,
                        $paso['descripcion'],
                        'alineacion.anita_arca.'.$acc,
                        $acc !== 'insert'
                    );
                    $aplicados[] = [
                        'tabla' => $paso['tabla'],
                        'descripcion' => $paso['descripcion'],
                        'ok' => true,
                        'raw' => $raw,
                    ];
                } catch (\Throwable $e) {
                    Log::error('alineacion.anita_arca.write_fail', [
                        'clave' => $clave,
                        'paso' => $paso,
                        'error' => $e->getMessage(),
                    ]);
                    $aplicados[] = [
                        'tabla' => $paso['tabla'],
                        'descripcion' => $paso['descripcion'],
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                    throw $e;
                }
            }
        }

        $despues = $aplicar ? $this->leerEstadoAnita($tipo, $letra, $sucursal, $numero) : null;
        if ($despues !== null) {
            $this->assertSubdiarioCuadraVenMonto($despues);
        }

        return [
            'clave' => $clave,
            'backup_path' => $backupPath,
            'antes' => $antes,
            'arca' => $arca,
            'plan' => $plan,
            'aplicados' => $aplicados,
            'despues' => $despues,
        ];
    }

    /**
     * @param  array{venta: ?array, subdiario: list<array>}  $estado
     */
    public function assertSubdiarioCuadraVenMonto(array $estado): void
    {
        $venMonto = round((float) ($estado['venta']['ven_monto'] ?? 0), 2);
        $suma = 0.0;
        foreach ($estado['subdiario'] as $r) {
            $suma += (float) ($r['subd_importe'] ?? 0);
        }
        $suma = round($suma, 2);
        if (abs($suma - $venMonto) >= 0.02) {
            throw new \RuntimeException(
                "subdiario no cuadra con ven_monto: suma={$this->money($suma)} ven_monto={$this->money($venMonto)}"
            );
        }
    }

    /**
     * @return array{venta: ?array, climov: list<array>, comprob: list<array>, compaux: list<array>, subdiario: list<array>, venibr: list<array>}
     */
    public function leerEstadoAnita(string $tipo, string $letra, int $sucursal, int $numero): array
    {
        $whereVenta = $this->whereComprobante('ven', $tipo, $letra, $sucursal, $numero);
        $venta = $this->listarUno('venta', '
            ven_cliente,ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,
            ven_exento,ven_gravado,ven_impuesto1,ven_percepcion_iva,ven_monto,
            ven_monto_desc,ven_porc_desc,ven_tot_abasto,
            ven_t_ult_cobro,ven_t_cobrado,ven_fecha_cobro,ven_perc_ing_bruto,ven_logistica
        ', $whereVenta);

        $climov = $this->listar('climov', '
            cliv_cliente,cliv_tipo,cliv_letra,cliv_sucursal,cliv_nro,cliv_nro_cuota,
            cliv_monto,cliv_t_cobrado,cliv_fecha_cobro,cliv_estado
        ', $this->whereComprobante('cliv', $tipo, $letra, $sucursal, $numero));

        $comprob = $this->listar('comprob', '
            comp_cliente,comp_tipo,comp_letra,comp_sucursal,comp_nro_fact,
            comp_total,comp_iva,comp_exento,comp_gravado
        ', " WHERE comp_tipo='{$tipo}' AND comp_letra='{$letra}' AND comp_sucursal='{$sucursal}' AND comp_nro_fact='{$numero}' ");

        $compaux = $this->listar('compaux', '
            compa_cliente,compa_tipo,compa_letra,compa_sucursal,compa_nro_fact,
            compa_orden,compa_articulo,compa_cantidad,compa_precio,compa_desc,compa_dto
        ', " WHERE compa_tipo='{$tipo}' AND compa_letra='{$letra}' AND compa_sucursal='{$sucursal}' AND compa_nro_fact='{$numero}' ");

        usort($compaux, static fn (array $a, array $b): int => ((int) ($a['compa_orden'] ?? 0)) <=> ((int) ($b['compa_orden'] ?? 0)));

        $stkmov = $this->listar('stkmov', '
            stkv_articulo,stkv_tipo,stkv_letra,stkv_sucursal,stkv_nro,
            stkv_cantidad,stkv_precio,stkv_nro_orden,stkv_deposito
        ', " WHERE stkv_tipo='{$tipo}' AND stkv_letra='{$letra}' AND stkv_sucursal='{$sucursal}' AND stkv_nro='{$numero}' ");

        usort($stkmov, static fn (array $a, array $b): int => ((int) ($a['stkv_nro_orden'] ?? 0)) <=> ((int) ($b['stkv_nro_orden'] ?? 0)));

        // subd_desc_mov va último: el bridge parte el CSV por `|` y una descripción con `|` corre los campos siguientes.
        $subdiario = $this->listar('subdiario', '
            subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_cuenta,subd_contrapartida,
            subd_importe,subd_tipo_mov,subd_sistema,subd_fecha,subd_emisor,subd_desc_mov
        ', " WHERE subd_sistema='V' AND subd_tipo='{$tipo}' AND subd_letra='{$letra}' AND subd_sucursal='{$sucursal}' AND subd_nro='{$numero}' ");

        $venibr = $this->listar('venibr', '
            veni_tipo,veni_letra,veni_sucursal,veni_nro,veni_provincia,
            veni_codigo_perc,veni_porcentaje,veni_importe
        ', " WHERE veni_tipo='{$tipo}' AND veni_letra='{$letra}' AND veni_sucursal='{$sucursal}' AND veni_nro='{$numero}' ");

        return [
            'venta' => $venta,
            'climov' => $climov,
            'comprob' => $comprob,
            'compaux' => $compaux,
            'stkmov' => $stkmov,
            'subdiario' => $subdiario,
            'venibr' => $venibr,
        ];
    }

    /**
     * @param  object|array  $raw
     * @return array{
     *   gravado: float,
     *   iva: float,
     *   total: float,
     *   otros_tributos: float,
     *   iibb: float,
     *   perc_iva: float,
     *   logistica: float,
     *   items: list<array{codigo: string, descripcion: string, cantidad: float, precio: float, importe_neto: float}>
     * }
     */
    private function normalizarArca(object|array $raw): array
    {
        $c = is_array($raw) ? ($raw['comprobante'] ?? $raw) : ($raw->comprobante ?? $raw);
        $c = (array) $c;

        $gravado = round((float) ($c['importeGravado'] ?? 0), 2);
        $iva = 0.0;
        $subIva = $c['arraySubtotalesIVA'] ?? null;
        if (is_object($subIva)) {
            $subIva = (array) $subIva;
        }
        if (is_array($subIva)) {
            $st = $subIva['subtotalIVA'] ?? null;
            if (is_object($st)) {
                $st = (array) $st;
            }
            if (is_array($st)) {
                $filasIva = isset($st[0]) || array_is_list($st) ? $st : [$st];
                foreach ($filasIva as $fila) {
                    $fila = (array) $fila;
                    $iva += (float) ($fila['importe'] ?? 0);
                }
            }
        }
        $iva = round($iva, 2);

        $total = round((float) ($c['importeTotal'] ?? 0), 2);
        $otros = round((float) ($c['importeOtrosTributos'] ?? 0), 2);

        $iibb = 0.0;
        $percIva = 0.0;
        $abasto = 0.0;
        $otrosArr = $c['arrayOtrosTributos'] ?? null;
        if (is_object($otrosArr)) {
            $otrosArr = (array) $otrosArr;
        }
        $tributos = [];
        if (is_array($otrosArr)) {
            $ot = $otrosArr['otroTributo'] ?? [];
            if (is_object($ot)) {
                $ot = [$ot];
            }
            if (is_array($ot)) {
                $tributos = array_is_list($ot) ? $ot : [$ot];
            }
        }
        foreach ($tributos as $t) {
            $t = (array) $t;
            $cod = (int) ($t['codigo'] ?? 0);
            $imp = round((float) ($t['importe'] ?? 0), 2);
            if ($cod === 2) {
                $iibb += $imp;
            } elseif ($cod === 1) {
                $percIva += $imp;
            } elseif ($cod === 99) {
                $abasto += $imp;
            } elseif ($imp >= 0.005) {
                throw new \RuntimeException(
                    "ARCA tributo no mapeado codigo={$cod} importe={$imp} desc=".($t['descripcion'] ?? '')
                );
            }
        }
        $iibb = round($iibb, 2);
        $percIva = round($percIva, 2);
        $abasto = round($abasto, 2);

        $itemsRaw = [];
        $arrItems = $c['arrayItems'] ?? null;
        if (is_object($arrItems)) {
            $arrItems = (array) $arrItems;
        }
        if (is_array($arrItems)) {
            $it = $arrItems['item'] ?? [];
            if (is_object($it)) {
                $it = [$it];
            }
            if (is_array($it)) {
                $itemsRaw = array_is_list($it) ? $it : [$it];
            }
        }

        $items = [];
        $logistica = 0.0;
        foreach ($itemsRaw as $item) {
            $item = (array) $item;
            $codigo = trim((string) ($item['codigo'] ?? ''));
            $desc = trim((string) ($item['descripcion'] ?? ''));
            $cant = round((float) ($item['cantidad'] ?? 0), 4);
            $precio = round((float) ($item['precioUnitario'] ?? 0), 6);
            $ivaItem = round((float) ($item['importeIVA'] ?? 0), 2);
            $importeItem = round((float) ($item['importeItem'] ?? 0), 2);
            $neto = round($importeItem - $ivaItem, 2);

            if (strcasecmp($codigo, 'texto') === 0 && stripos($desc, 'logistic') !== false) {
                $logistica = $neto;
            }

            $items[] = [
                'codigo' => $codigo,
                'descripcion' => $desc,
                'cantidad' => $cant,
                'precio' => $precio,
                'importe_neto' => $neto,
            ];
        }

        return [
            'gravado' => $gravado,
            'iva' => $iva,
            'total' => $total,
            'otros_tributos' => $otros,
            'iibb' => $iibb,
            'perc_iva' => $percIva,
            'abasto' => $abasto,
            'logistica' => $logistica,
            'items' => $items,
        ];
    }

    /**
     * @param  array  $antes
     * @param  array  $arca
     * @return list<array{tabla: string, descripcion: string, valores: string, where: string}>
     */
    private function armarPlan(string $tipo, string $letra, int $sucursal, int $numero, array $antes, array $arca): array
    {
        $plan = [];
        $venta = $antes['venta'];
        $logistica = round((float) $arca['logistica'], 2);
        $gravado = round((float) $arca['gravado'], 2);
        $iva = round((float) $arca['iva'], 2);
        $iibb = round((float) $arca['iibb'], 2);
        $percIva = round((float) $arca['perc_iva'], 2);
        $abasto = round((float) ($arca['abasto'] ?? 0), 2);
        $total = round((float) $arca['total'], 2);

        // ven_gravado ya incluye logística; cabecera: gravado+iva+iibb+perc_iva+abasto = ven_monto
        $sumaCab = round($gravado + $iva + $iibb + $percIva + $abasto, 2);
        if (abs($sumaCab - $total) >= 0.02) {
            throw new \RuntimeException(
                "ARCA inconsistente: gravado+iva+iibb+perc+abasto={$this->money($sumaCab)} total={$this->money($total)}"
            );
        }

        // Descuento de cabecera: se mantiene ven_porc_desc; se recalcula ven_monto_desc
        // sobre el gravado ARCA (base neta = gravado / (1 - porc/100)).
        $porcDesc = round((float) ($venta['ven_porc_desc'] ?? 0), 4);
        $montoDescNuevo = $this->montoDescuentoDesdeGravadoYPorcentaje($gravado, $porcDesc);

        // --- venta ---
        $plan[] = [
            'tabla' => 'venta',
            'descripcion' => 'venta cabecera montos fiscales → ARCA',
            'valores' => sprintf(
                " ven_gravado='%s', ven_impuesto1='%s', ven_percepcion_iva='%s', ven_perc_ing_bruto='%s', ven_logistica='%s', ven_tot_abasto='%s', ven_monto='%s', ven_monto_desc='%s', ven_exento='0.00' ",
                $this->money($gravado),
                $this->money($iva),
                $this->money($percIva),
                $this->money($iibb),
                $this->money($logistica),
                $this->money($abasto),
                $this->money($total),
                $this->money($montoDescNuevo),
            ),
            'where' => $this->whereComprobante('ven', $tipo, $letra, $sucursal, $numero),
        ];

        // --- climov: actualiza deuda; conserva cobrado real ---
        // Si queda saldo (cobrado < monto ARCA) → Impago "I"; solo "C" si está cubierto.
        foreach ($antes['climov'] as $cli) {
            $cuota = (int) ($cli['cliv_nro_cuota'] ?? 1);
            $cobrado = round((float) ($cli['cliv_t_cobrado'] ?? 0), 2);
            $estado = ($cobrado + 0.009 >= $total) ? 'C' : 'I';
            $plan[] = [
                'acc' => 'update',
                'tabla' => 'climov',
                'descripcion' => sprintf(
                    'climov cuota %d monto → ARCA (cobrado intacto, estado %s)',
                    $cuota,
                    $estado
                ),
                'valores' => sprintf(" cliv_monto='%s', cliv_estado='%s' ", $this->money($total), $estado),
                'where' => $this->whereComprobante('cliv', $tipo, $letra, $sucursal, $numero)
                    ." AND cliv_nro_cuota='{$cuota}' ",
            ];
        }

        // --- comprob ---
        foreach ($antes['comprob'] as $_) {
            $plan[] = [
                'tabla' => 'comprob',
                'descripcion' => 'comprob totales → ARCA',
                'valores' => sprintf(
                    " comp_total='%s', comp_iva='%s', comp_gravado='%s', comp_exento='0' ",
                    $this->money($total),
                    $this->money($iva),
                    $this->money($gravado),
                ),
                'where' => " WHERE comp_tipo='{$tipo}' AND comp_letra='{$letra}' AND comp_sucursal='{$sucursal}' AND comp_nro_fact='{$numero}' ",
            ];
            break;
        }

        // --- compaux: cantidades según ARCA (match codigo + precio cero/no-cero) ---
        $plan = array_merge($plan, $this->planCantidadesDesdeArca(
            'compaux',
            $antes['compaux'],
            $arca['items'],
            $tipo,
            $letra,
            $sucursal,
            $numero,
        ));

        // --- stkmov: mismas cantidades ARCA (mismo criterio de match) ---
        $plan = array_merge($plan, $this->planCantidadesDesdeArca(
            'stkmov',
            $antes['stkmov'],
            $arca['items'],
            $tipo,
            $letra,
            $sucursal,
            $numero,
        ));

        // --- IIBB: ARCA manda un globo; Anita discrimina venibr + subdiario por provincia (ibrxprov) ---
        $repartoIibb = $this->repartirIibbPorAlicuota($iibb, $antes['venibr']);
        $cuentasIibb = $this->cuentasSubdiarioIibbDesdeReparto($repartoIibb);

        // --- subdiario: logística va dentro del gravado (una sola 301100000 = ven_gravado) ---
        $sub = $antes['subdiario'];
        $porCuenta = [
            self::CUENTA_IVA => $iva,
            self::CUENTA_PERC_IVA => $percIva,
            self::CUENTA_ABASTO => $abasto,
        ];
        foreach ($cuentasIibb as $cuenta => $importe) {
            $porCuenta[$cuenta] = round((float) ($porCuenta[$cuenta] ?? 0) + (float) $importe, 2);
        }
        // Si ARCA tiene IIBB pero no hay venibr, un solo asiento fallback
        if ($iibb >= 0.005 && $cuentasIibb === []) {
            $porCuenta[self::CUENTA_IIBB_FALLBACK] = $iibb;
        }

        $plan = array_merge($plan, $this->planSubdiario(
            $tipo,
            $letra,
            $sucursal,
            $numero,
            $sub,
            $porCuenta,
            $gravado,
            $venta,
            array_keys($this->mapaCuentasIibbPorProvincia()),
        ));

        $plan = array_merge(
            $plan,
            $this->planVenibr($tipo, $letra, $sucursal, $numero, $iibb, $antes['venibr'], $repartoIibb)
        );

        return $plan;
    }

    /**
     * @param  list<array{provincia: string, importe: float}>  $reparto
     * @return array<string, float> cuenta => importe
     */
    private function cuentasSubdiarioIibbDesdeReparto(array $reparto): array
    {
        $mapa = $this->mapaCuentasIibbPorProvincia();
        $out = [];
        foreach ($reparto as $item) {
            $prov = (string) ($item['provincia'] ?? '');
            $provKey = (string) ((int) $prov);
            $cuenta = $mapa[$prov] ?? $mapa[$provKey] ?? null;
            if ($cuenta === null || $cuenta === '') {
                throw new \RuntimeException(
                    "ibrxprov sin ibrxp_cta_contable para provincia {$prov}. Revisar tabla ibrxprov en Anita."
                );
            }
            $cuenta = (string) ((int) $cuenta);
            $out[$cuenta] = round((float) ($out[$cuenta] ?? 0) + (float) $item['importe'], 2);
        }

        return $out;
    }

    /**
     * Provincia Anita (veni_provincia / ibrxp_provincia) → cuenta contable IIBB.
     *
     * @return array<string, string>
     */
    public function mapaCuentasIibbPorProvincia(): array
    {
        if ($this->mapaCuentasIibbCache !== null) {
            return $this->mapaCuentasIibbCache;
        }

        $parsed = ApiAnita::parsearRespuestaLista($this->api->apiCall([
            'acc' => 'list',
            'tabla' => 'ibrxprov',
            'sistema' => self::SISTEMA_VENTAS,
            'campos' => 'ibrxp_provincia,ibrxp_desc,ibrxp_cta_contable',
            'whereArmado' => ' WHERE 1=1 ',
        ]));
        if ($parsed['error_lectura']) {
            throw new \RuntimeException('Error leyendo ibrxprov: '.$parsed['error_lectura']);
        }

        $mapa = [];
        foreach ($parsed['filas'] as $fila) {
            $r = (array) $fila;
            $prov = (string) ((int) ($r['ibrxp_provincia'] ?? 0));
            $cta = (string) ((int) ($r['ibrxp_cta_contable'] ?? 0));
            if ($prov === '0' || $cta === '0') {
                continue;
            }
            $mapa[$prov] = $cta;
        }
        if ($mapa === []) {
            throw new \RuntimeException('ibrxprov sin filas útiles (provincia→cuenta).');
        }

        return $this->mapaCuentasIibbCache = $mapa;
    }

    /**
     * Importe de descuento de cabecera a partir del gravado neto y el % grabado.
     * Anita: gravado = base * (1 - porc/100) ⇒ monto_desc = gravado * porc / (100 - porc).
     */
    public function montoDescuentoDesdeGravadoYPorcentaje(float $gravado, float $porcDesc): float
    {
        $gravado = round($gravado, 2);
        $porcDesc = round($porcDesc, 4);
        if ($porcDesc <= 0.0001 || $porcDesc >= 100) {
            return 0.0;
        }

        return round($gravado * $porcDesc / (100.0 - $porcDesc), 2);
    }

    /**
     * Actualiza cantidades de compaux o stkmov al valor ARCA.
     * Match por código de artículo + precio cero/no-cero. Logística (texto) se omite.
     *
     * @param  'compaux'|'stkmov'  $tabla
     * @param  list<array>  $lineasAnita
     * @param  list<array{codigo: string, descripcion: string, cantidad: float, precio: float}>  $itemsArca
     * @return list<array{acc?: string, tabla: string, descripcion: string, valores?: string, where: string}>
     */
    private function planCantidadesDesdeArca(
        string $tabla,
        array $lineasAnita,
        array $itemsArca,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
    ): array {
        $pool = $itemsArca;
        $plan = [];

        foreach ($lineasAnita as $linea) {
            if ($tabla === 'compaux') {
                $codigo = trim((string) ($linea['compa_articulo'] ?? ''));
                $precioAnita = round((float) ($linea['compa_precio'] ?? 0), 6);
                $orden = (int) ($linea['compa_orden'] ?? 0);
                $cantAnita = round((float) ($linea['compa_cantidad'] ?? 0), 4);
                $cantRaw = $cantAnita;
            } else {
                $codigo = trim((string) ($linea['stkv_articulo'] ?? ''));
                $precioAnita = round((float) ($linea['stkv_precio'] ?? 0), 6);
                $orden = (int) ($linea['stkv_nro_orden'] ?? 0);
                $cantRaw = (float) ($linea['stkv_cantidad'] ?? 0);
                $cantAnita = round(abs($cantRaw), 4);
            }

            if (strcasecmp($codigo, 'texto') === 0) {
                continue;
            }

            $esCero = abs($precioAnita) < 0.000001;
            $idxMatch = null;
            foreach ($pool as $i => $item) {
                if (strcasecmp((string) $item['codigo'], $codigo) !== 0) {
                    continue;
                }
                if (strcasecmp((string) $item['codigo'], 'texto') === 0) {
                    continue;
                }
                $esCeroArca = abs((float) $item['precio']) < 0.000001;
                if ($esCero !== $esCeroArca) {
                    continue;
                }
                $idxMatch = $i;
                break;
            }
            if ($idxMatch === null) {
                throw new \RuntimeException(
                    "Sin match ARCA para {$tabla} orden={$orden} art={$codigo} cant={$cantAnita} pu={$precioAnita}"
                );
            }
            $match = $pool[$idxMatch];
            unset($pool[$idxMatch]);
            $pool = array_values($pool);

            $cantNueva = round((float) $match['cantidad'], 4);
            if (abs($cantNueva - $cantAnita) < 0.0001) {
                continue;
            }

            // Preservar signo de stkmov si venía negativo
            $cantGrabar = $cantNueva;
            if ($tabla === 'stkmov' && $cantRaw < 0) {
                $cantGrabar = -1 * $cantNueva;
            }

            if ($tabla === 'compaux') {
                $plan[] = [
                    'acc' => 'update',
                    'tabla' => 'compaux',
                    'descripcion' => sprintf(
                        'compaux ord=%d %s cant %s→%s',
                        $orden,
                        $codigo,
                        $this->qty($cantAnita),
                        $this->qty($cantNueva)
                    ),
                    'valores' => sprintf(" compa_cantidad='%s' ", $this->qty($cantNueva)),
                    'where' => " WHERE compa_tipo='{$tipo}' AND compa_letra='{$letra}' AND compa_sucursal='{$sucursal}' AND compa_nro_fact='{$numero}' AND compa_orden='{$orden}' ",
                ];
            } else {
                $plan[] = [
                    'acc' => 'update',
                    'tabla' => 'stkmov',
                    'descripcion' => sprintf(
                        'stkmov ord=%d %s cant %s→%s',
                        $orden,
                        $codigo,
                        $this->qty($cantAnita),
                        $this->qty($cantNueva)
                    ),
                    'valores' => sprintf(" stkv_cantidad='%s' ", $this->qty($cantGrabar)),
                    'where' => " WHERE stkv_tipo='{$tipo}' AND stkv_letra='{$letra}' AND stkv_sucursal='{$sucursal}' AND stkv_nro='{$numero}' AND stkv_nro_orden='{$orden}' AND stkv_articulo='{$codigo}' ",
                ];
            }
        }

        // Ítems ARCA restantes (p.ej. Logística texto): no van a compaux/stkmov
        foreach ($pool as $rest) {
            if (strcasecmp((string) $rest['codigo'], 'texto') === 0 && stripos((string) $rest['descripcion'], 'logistic') !== false) {
                continue;
            }
            // Si es stkmov y quedó algo sin mapear tras haber consumido todo en compaux del mismo pool
            // (pools separados), es error real.
            throw new \RuntimeException(
                "Quedó ítem ARCA sin mapear a {$tabla}: ".json_encode($rest, JSON_UNESCAPED_UNICODE)
            );
        }

        return $plan;
    }

    /**
     * Distribuye el IIBB de ARCA entre filas venibr.
     * Una provincia → todo el globo.
     * Varias → prorrateo por alícuota (veni_porcentaje); el resto a la última fila.
     *
     * @param  list<array>  $venibrAntes
     * @param  list<array{provincia: string, alicuota: float, importe_antes: float, importe: float, peso: float}>|null  $reparto
     * @return list<array{acc?: string, tabla: string, descripcion: string, valores?: string, where: string}>
     */
    private function planVenibr(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        float $iibbArca,
        array $venibrAntes,
        ?array $reparto = null,
    ): array {
        if ($venibrAntes === []) {
            return [];
        }

        $iibbArca = round($iibbArca, 2);
        $reparto ??= $this->repartirIibbPorAlicuota($iibbArca, $venibrAntes);
        $plan = [];

        foreach ($reparto as $item) {
            $prov = (string) $item['provincia'];
            $importeNuevo = round((float) $item['importe'], 2);
            $impOld = round((float) $item['importe_antes'], 2);
            $alicuota = (float) $item['alicuota'];
            if (abs($impOld - $importeNuevo) < 0.005) {
                continue;
            }
            $plan[] = [
                'acc' => 'update',
                'tabla' => 'venibr',
                'descripcion' => sprintf(
                    'venibr prov %s (alícuota %s%%) %s→%s (globo ARCA %s)',
                    $prov,
                    rtrim(rtrim(number_format($alicuota, 4, '.', ''), '0'), '.') ?: '0',
                    $this->money($impOld),
                    $this->money($importeNuevo),
                    $this->money($iibbArca)
                ),
                'valores' => sprintf(" veni_importe='%s' ", $this->money($importeNuevo)),
                'where' => " WHERE veni_tipo='{$tipo}' AND veni_letra='{$letra}' AND veni_sucursal='{$sucursal}' AND veni_nro='{$numero}' AND veni_provincia='{$prov}' ",
            ];
        }

        return $plan;
    }

    /**
     * @param  list<array>  $venibrAntes
     * @return list<array{provincia: string, alicuota: float, importe_antes: float, importe: float, peso: float}>
     */
    public function repartirIibbPorAlicuota(float $iibbArca, array $venibrAntes): array
    {
        $iibbArca = round($iibbArca, 2);
        $n = count($venibrAntes);
        if ($n === 0) {
            return [];
        }

        if ($n === 1) {
            $ibr = $venibrAntes[0];

            return [[
                'provincia' => (string) ($ibr['veni_provincia'] ?? ''),
                'alicuota' => (float) ($ibr['veni_porcentaje'] ?? 0),
                'importe_antes' => round((float) ($ibr['veni_importe'] ?? 0), 2),
                'importe' => $iibbArca,
                'peso' => 1.0,
            ]];
        }

        $pesos = [];
        $sumaPesos = 0.0;
        foreach ($venibrAntes as $i => $ibr) {
            $alicuota = round((float) ($ibr['veni_porcentaje'] ?? 0), 6);
            // Si la alícuota viniera en 0, cae al importe original (misma proporción).
            $peso = $alicuota > 0.0000001
                ? $alicuota
                : max(0.0, round((float) ($ibr['veni_importe'] ?? 0), 2));
            $pesos[$i] = $peso;
            $sumaPesos += $peso;
        }

        if ($sumaPesos <= 0.0000001) {
            $igual = 1.0 / $n;
            foreach ($venibrAntes as $i => $_) {
                $pesos[$i] = $igual;
            }
            $sumaPesos = 1.0;
        }

        $out = [];
        $acum = 0.0;
        foreach ($venibrAntes as $i => $ibr) {
            $esUltima = ($i === $n - 1);
            if ($esUltima) {
                $importe = round($iibbArca - $acum, 2);
            } else {
                $importe = round($iibbArca * ($pesos[$i] / $sumaPesos), 2);
                $acum += $importe;
            }
            $out[] = [
                'provincia' => (string) ($ibr['veni_provincia'] ?? ''),
                'alicuota' => (float) ($ibr['veni_porcentaje'] ?? 0),
                'importe_antes' => round((float) ($ibr['veni_importe'] ?? 0), 2),
                'importe' => $importe,
                'peso' => (float) $pesos[$i],
            ];
        }

        return $out;
    }

    /**
     * Logística no va en línea aparte: se consolida en 301100000 = ven_gravado.
     * IIBB se imputa por provincia según ibrxprov (cuentas en $porCuenta).
     *
     * @param  list<array>  $sub
     * @param  array<string, float>  $porCuenta
     * @param  array  $venta
     * @param  list<string>  $cuentasIibbCatalogo  cuentas ibrxprov (para no borrarlas si están en el plan)
     * @return list<array{acc?: string, tabla: string, descripcion: string, valores?: string, where: string}>
     */
    private function planSubdiario(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        array $sub,
        array $porCuenta,
        float $gravado,
        array $venta,
        array $cuentasIibbCatalogo = [],
    ): array {
        $plan = [];
        $whereBase = " WHERE subd_sistema='V' AND subd_tipo='{$tipo}' AND subd_letra='{$letra}' AND subd_sucursal='{$sucursal}' AND subd_nro='{$numero}' ";

        $cuentasObjetivo = [];
        foreach (array_keys($porCuenta) as $cuentaKey) {
            $cuentasObjetivo[(string) $cuentaKey] = true;
        }
        $cuentasObjetivo[self::CUENTA_VENTAS] = true;

        $catalogoIibb = [];
        foreach ($cuentasIibbCatalogo as $c) {
            $catalogoIibb[(string) ((int) $c)] = true;
        }

        foreach ($porCuenta as $cuentaKey => $importeNuevo) {
            $cuenta = (string) $cuentaKey;
            $importeNuevo = round((float) $importeNuevo, 2);
            $filas = array_values(array_filter($sub, static function (array $r) use ($cuenta): bool {
                return self::mismaCuentaSubdiario($r['subd_cuenta'] ?? '', $cuenta);
            }));

            // ARCA sin ese tributo y Anita tampoco lo tiene → no hacer nada
            if ($filas === [] && $importeNuevo < 0.005) {
                continue;
            }

            if ($filas === [] && $importeNuevo >= 0.005) {
                // Insertar línea faltante copiando contrapartida/fecha/emisor/descripción de otra fila del comprobante.
                // Sin subd_emisor la línea entra al mayor de deudores pero no se puede atribuir a ningún cliente,
                // y descuadra el control del mayor contra la deuda de cuenta corriente.
                $plantilla = $this->plantillaSubdiario($sub);
                if ($plantilla === null) {
                    throw new \RuntimeException("subdiario cuenta {$cuenta}: no hay plantilla para insertar {$this->money($importeNuevo)}");
                }

                $emisor = trim((string) ($plantilla['subd_emisor'] ?? ''));
                if ($emisor === '' || ltrim($emisor, '0') === '') {
                    $emisor = trim((string) ($venta['ven_cliente'] ?? ''));
                }
                if ($emisor === '' || ltrim($emisor, '0') === '') {
                    throw new \RuntimeException(
                        "subdiario cuenta {$cuenta}: no se pudo resolver el emisor (cliente) para insertar {$this->money($importeNuevo)}"
                    );
                }

                $plan[] = [
                    'acc' => 'insert',
                    'tabla' => 'subdiario',
                    'descripcion' => "subdiario insertar {$cuenta}={$this->money($importeNuevo)} (emisor {$emisor})",
                    'campos' => 'subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_cuenta,subd_contrapartida,'
                        .'subd_importe,subd_tipo_mov,subd_sistema,subd_fecha,subd_emisor,subd_desc_mov',
                    'valores' => sprintf(
                        "'%s','%s','%s','%s','%s','%s','%s','H','V','%s','%s','%s'",
                        $tipo,
                        $letra,
                        $sucursal,
                        $numero,
                        $cuenta,
                        (string) ($plantilla['subd_contrapartida'] ?? self::CUENTA_DEUDORES),
                        $this->money($importeNuevo),
                        (string) ($plantilla['subd_fecha'] ?? $venta['ven_fecha'] ?? ''),
                        $this->sqlTexto($emisor),
                        $this->sqlTexto(trim((string) ($plantilla['subd_desc_mov'] ?? ''))),
                    ),
                    'where' => '',
                ];
                continue;
            }

            if (count($filas) !== 1) {
                $vistos = array_map(static function (array $r): string {
                    return (string) ($r['subd_cuenta'] ?? '?').'='.(string) ($r['subd_importe'] ?? '?');
                }, $sub);
                throw new \RuntimeException(
                    "subdiario cuenta {$cuenta}: se esperaba 1 fila, hay ".count($filas)
                    .' | vistos=['.implode('; ', $vistos).']'
                );
            }

            // Anita tenía la cuenta pero ARCA no → borrar
            if ($importeNuevo < 0.005) {
                $impOld = round((float) $filas[0]['subd_importe'], 2);
                $plan[] = [
                    'acc' => 'delete',
                    'tabla' => 'subdiario',
                    'descripcion' => "subdiario borrar {$cuenta} {$this->money($impOld)} (no está en ARCA)",
                    'where' => $whereBase." AND subd_cuenta='{$cuenta}' AND subd_importe='{$this->money($impOld)}' ",
                ];
                continue;
            }

            $impOld = round((float) $filas[0]['subd_importe'], 2);
            if (abs($impOld - $importeNuevo) < 0.005) {
                continue;
            }
            $plan[] = [
                'acc' => 'update',
                'tabla' => 'subdiario',
                'descripcion' => "subdiario {$cuenta} {$this->money($impOld)}→{$this->money($importeNuevo)}",
                'valores' => sprintf(" subd_importe='%s' ", $this->money($importeNuevo)),
                'where' => $whereBase." AND subd_cuenta='{$cuenta}' AND subd_importe='{$this->money($impOld)}' ",
            ];
        }

        // Cuentas sobrantes: desconocidas, o IIBB de ibrxprov no usadas en este comprobante
        foreach ($sub as $r) {
            $cuentaDel = (string) ((int) round((float) ($r['subd_cuenta'] ?? 0)));
            if ($cuentaDel === '0') {
                continue;
            }
            if (isset($cuentasObjetivo[$cuentaDel])) {
                continue;
            }
            // Si es cuenta IIBB del catálogo pero no aplica a este reparto → borrar
            // Si no es del catálogo ni del plan → borrar (sobrante)
            $impOld = round((float) ($r['subd_importe'] ?? 0), 2);
            if ($impOld < 0.005) {
                continue;
            }
            $motivo = isset($catalogoIibb[$cuentaDel])
                ? 'IIBB provincia no usada en este comprobante'
                : 'no mapeada a ARCA';
            $plan[] = [
                'acc' => 'delete',
                'tabla' => 'subdiario',
                'descripcion' => "subdiario borrar cuenta extra {$cuentaDel} {$this->money($impOld)} ({$motivo})",
                'where' => $whereBase." AND subd_cuenta='{$cuentaDel}' AND subd_importe='{$this->money($impOld)}' ",
            ];
        }

        $ventasRows = array_values(array_filter(
            $sub,
            static fn (array $r): bool => self::mismaCuentaSubdiario($r['subd_cuenta'] ?? '', self::CUENTA_VENTAS)
        ));
        if ($ventasRows === []) {
            throw new \RuntimeException('subdiario 301100000: no hay filas de ventas/gravado');
        }

        $logRef = round((float) ($venta['ven_logistica'] ?? 0), 2);
        $filaLog = null;
        $filasGravado = [];
        foreach ($ventasRows as $r) {
            $imp = round((float) ($r['subd_importe'] ?? 0), 2);
            if ($logRef > 0.009 && abs($imp - $logRef) < 0.02 && $filaLog === null) {
                $filaLog = $r;
            } else {
                $filasGravado[] = $r;
            }
        }
        // Si ven_logistica ya se actualizó al valor ARCA y hay 2 filas, la menor suele ser logística.
        if ($filaLog === null && count($ventasRows) === 2) {
            usort($ventasRows, static fn (array $a, array $b): int => ((float) $a['subd_importe']) <=> ((float) $b['subd_importe']));
            $filaLog = $ventasRows[0];
            $filasGravado = [$ventasRows[1]];
        }

        if (count($filasGravado) !== 1) {
            throw new \RuntimeException(
                'subdiario 301100000: se esperaba 1 fila de gravado (+opcional logística a borrar), hay '
                .count($filasGravado).' gravado y '.($filaLog ? '1' : '0').' logística'
            );
        }

        $impVtaOld = round((float) $filasGravado[0]['subd_importe'], 2);
        if (abs($impVtaOld - $gravado) >= 0.005) {
            $plan[] = [
                'acc' => 'update',
                'tabla' => 'subdiario',
                'descripcion' => "subdiario gravado (c/logística) {$this->money($impVtaOld)}→{$this->money($gravado)}",
                'valores' => sprintf(" subd_importe='%s' ", $this->money($gravado)),
                'where' => $whereBase." AND subd_cuenta='".self::CUENTA_VENTAS."' AND subd_importe='{$this->money($impVtaOld)}' ",
            ];
        }

        if ($filaLog !== null) {
            $impLogOld = round((float) $filaLog['subd_importe'], 2);
            $plan[] = [
                'acc' => 'delete',
                'tabla' => 'subdiario',
                'descripcion' => "subdiario borrar logística suelta {$this->money($impLogOld)} (queda en gravado)",
                'where' => $whereBase." AND subd_cuenta='".self::CUENTA_VENTAS."' AND subd_importe='{$this->money($impLogOld)}' ",
            ];
        }

        return $plan;
    }

    private static function mismaCuentaSubdiario(mixed $raw, string $cuenta): bool
    {
        $cod = preg_replace('/\.0+$/', '', trim((string) $raw)) ?? '';

        return $cod === $cuenta
            || (string) ((int) round((float) $raw)) === $cuenta;
    }

    private function whereComprobante(string $prefijo, string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return " WHERE {$prefijo}_tipo='{$tipo}' AND {$prefijo}_letra='{$letra}' AND {$prefijo}_sucursal='{$sucursal}' AND {$prefijo}_nro='{$numero}' ";
    }

    private function money(float $v): string
    {
        return number_format(round($v, 2), 2, '.', '');
    }

    /**
     * Fila de referencia para insertar una línea nueva del comprobante. Prioriza una que
     * traiga emisor: sin él la línea queda huérfana en el mayor de deudores.
     *
     * @param  list<array>  $sub
     * @return array|null
     */
    private function plantillaSubdiario(array $sub): ?array
    {
        foreach ($sub as $fila) {
            $emisor = trim((string) ($fila['subd_emisor'] ?? ''));
            if ($emisor !== '' && ltrim($emisor, '0') !== '') {
                return $fila;
            }
        }

        return $sub[0] ?? null;
    }

    /**
     * Escapa texto para SQL Informix (comilla simple duplicada).
     */
    private function sqlTexto(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    private function qty(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listar(string $tabla, string $campos, string $where): array
    {
        $camposNorm = implode(', ', array_values(array_filter(array_map(
            static fn (string $c): string => trim($c),
            preg_split('/,/', $campos) ?: [],
        ), static fn (string $c): bool => $c !== '')));

        $parsed = ApiAnita::parsearRespuestaLista($this->api->apiCall([
            'acc' => 'list',
            'tabla' => $tabla,
            'sistema' => self::SISTEMA_VENTAS,
            'campos' => $camposNorm,
            'whereArmado' => $where,
        ]));
        if ($parsed['error_lectura']) {
            throw new \RuntimeException("Error leyendo {$tabla}: ".$parsed['error_lectura']);
        }
        $out = [];
        foreach ($parsed['filas'] as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function listarUno(string $tabla, string $campos, string $where): ?array
    {
        $filas = $this->listar($tabla, $campos, $where);

        return $filas[0] ?? null;
    }
}
