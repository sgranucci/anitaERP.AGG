<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Ventas\ClienteAnitaZonamultSupport;
use App\Support\Ventas\ClienteProvinciaIibbCompletarSupport;
use App\Support\Ventas\ClienteProvinciaIibbSupport;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Ventas\ElBierzoFacturaBPercepcionCabaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Contrasta venta+venibr Anita vs simulación ImpuestoService (facturación admin).
 */
class VentasControlPercepcionAnitaPvCommand extends Command
{
    protected $signature = 'ventas:control-percepcion-anita-pv
                            {--fecha= : YYYY-MM-DD (default: ayer; ignora si hay --desde)}
                            {--desde= : YYYY-MM-DD inicio de rango}
                            {--hasta= : YYYY-MM-DD fin de rango (inclusive)}
                            {--sucursal=10 : Sucursal Anita / código PV}';

    protected $description = 'Simula percepciones y totales ERP contra venta/venibr Anita de un PV y fecha o rango';

    /** @var array<string, Articulo|null> */
    private array $articuloPorSku = [];

    /** @var array<string, Cliente|null> */
    private array $clientePorCodigoCache = [];

    public function handle(ImpuestoService $impuestoService): int
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        [$desde, $hasta] = $this->resolverRango();
        $sucursal = (int) $this->option('sucursal');

        $pv = Puntoventa::query()
            ->whereIn('codigo', [(string) $sucursal, str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT)])
            ->first();
        $empresaId = $pv ? (int) $pv->empresa_id : 1;
        $impuesto21 = (int) (Impuesto::query()->where('valor', 21)->value('id') ?: 1);

        $etiqueta = $desde === $hasta ? $desde : $desde.' … '.$hasta;
        $this->info("Fecha {$etiqueta} · sucursal Anita {$sucursal} · empresa_id {$empresaId} · PV ".($pv->codigo ?? '?'));
        $this->comment('Solo lectura. Sin NCP. IIBB por jurisdicción AFIP. Ítems: compaux + dto pie Anita. UM UN/CAJ usa piezas. Padrón = fecha de cada factura.');

        $filas = [];
        $discrepancias = 0;
        $omitidasNcp = 0;
        $todasCount = 0;

        $dias = [];
        $cursor = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->startOfDay();
        while ($cursor->lte($fin)) {
            $dias[] = $cursor->toDateString();
            $cursor->addDay();
        }

        foreach ($dias as $fecha) {
            $fechaAnita = (int) str_replace('-', '', $fecha);
            $todas = $this->listarVentas($sucursal, $fechaAnita);
            $ventas = [];
            foreach ($todas as $venta) {
                $todasCount++;
                if ($this->esNcp($venta)) {
                    $omitidasNcp++;
                    continue;
                }
                $ventas[] = $venta;
            }
            if ($ventas === []) {
                continue;
            }

            $this->line("  {$fecha}: ".count($ventas).' sin NCP');
            $venibr = $this->listarVenibr($sucursal, $fechaAnita);
            $itemsFactura = $this->listarItems($sucursal, $ventas);
            $itemsPedido = $this->listarItemsPedido($sucursal, $ventas);

            $bar = $this->output->createProgressBar(count($ventas));
            $bar->start();
            foreach ($ventas as $venta) {
                $clave = $this->clave($venta);
                $fechaVenta = $this->fechaYmdDesdeAnita((int) ($venta['ven_fecha'] ?? 0)) ?: $fecha;
                $filas[] = $this->simularUno(
                    $impuestoService,
                    $venta,
                    $venibr[$clave] ?? [],
                    $itemsPedido[$clave] ?? [],
                    $itemsFactura[$clave] ?? [],
                    $empresaId,
                    $fechaVenta,
                    $impuesto21
                );
                if (($filas[array_key_last($filas)]['ok'] ?? true) === false) {
                    $discrepancias++;
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->info('Comprobantes Anita: '.$todasCount.' · sin NCP: '.count($filas).($omitidasNcp > 0 ? " · omitidas NCP: {$omitidasNcp}" : ''));
        if ($filas === []) {
            $this->warn('Sin ventas.');

            return self::SUCCESS;
        }

        $csvEtiqueta = $desde === $hasta ? $desde : $desde.'_'.$hasta;
        $csv = $this->escribirCsv($filas, $csvEtiqueta, $sucursal);
        $this->resumen($filas, $discrepancias, $csv);

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolverRango(): array
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde !== '') {
            $hasta = $hasta !== '' ? $hasta : $desde;

            return [$desde, $hasta];
        }

        $fecha = (string) ($this->option('fecha') ?: now()->subDay()->toDateString());

        return [$fecha, $fecha];
    }

    private function fechaYmdDesdeAnita(int $fechaAnita): ?string
    {
        if ($fechaAnita < 10000101) {
            return null;
        }
        $s = sprintf('%08d', $fechaAnita);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    /**
     * @param  array<string, mixed>  $venta
     * @param  list<array<string, mixed>>  $ibrs
     * @param  list<array<string, mixed>>  $lineasPedido
     * @param  list<array<string, mixed>>  $lineasFactura
     * @return array<string, mixed>
     */
    private function simularUno(
        ImpuestoService $impuestoService,
        array $venta,
        array $ibrs,
        array $lineasPedido,
        array $lineasFactura,
        int $empresaId,
        string $fecha,
        int $impuesto21
    ): array {
        $tipo = trim((string) ($venta['ven_tipo'] ?? ''));
        $letra = strtoupper(trim((string) ($venta['ven_letra'] ?? '')));
        $nro = (int) ($venta['ven_nro'] ?? 0);
        $codigoCli = ClienteProvinciaIibbCompletarSupport::normalizarCodigo((string) ($venta['ven_cliente'] ?? ''));
        $comp = $tipo.' '.$letra.'-'.str_pad((string) ((int) ($venta['ven_sucursal'] ?? 0)), 5, '0', STR_PAD_LEFT).'-'.str_pad((string) $nro, 8, '0', STR_PAD_LEFT);

        $anitaTotal = round((float) ($venta['ven_monto'] ?? 0), 2);
        $anitaGravado = round((float) ($venta['ven_gravado'] ?? 0) + (float) ($venta['ven_gravado_ot'] ?? 0), 2);
        $anitaIva = round((float) ($venta['ven_impuesto1'] ?? 0), 2);
        $anitaPercIva = round((float) ($venta['ven_percepcion_iva'] ?? 0), 2);
        $anitaIibbBa = round((float) ($venta['ven_perc_ing_bruto'] ?? 0), 2);
        $anitaIibbResto = round((float) ($venta['ven_sellado'] ?? 0), 2);
        $anitaIibbHeader = round($anitaIibbBa + $anitaIibbResto, 2);
        $anitaIibbDet = $this->sumarVenibr($ibrs);
        $anitaIibb = abs($anitaIibbDet['total']) > 0.001 ? $anitaIibbDet['total'] : $anitaIibbHeader;

        $cliente = $this->clientePorCodigo($codigoCli);
        $base = [
            'fecha' => $fecha,
            'comprobante' => $comp,
            'tipo' => $tipo,
            'letra' => $letra,
            'nro' => $nro,
            'cliente' => $codigoCli,
            'nombre' => $cliente->nombre ?? trim((string) ($venta['ven_nombre_cliente'] ?? '')),
            'anita_total' => $anitaTotal,
            'anita_gravado' => $anitaGravado,
            'anita_iva' => $anitaIva,
            'anita_perc_iva' => $anitaPercIva,
            'anita_iibb' => $anitaIibb,
            'anita_iibb_detalle' => $anitaIibbDet['detalle'],
            'erp_total' => null,
            'erp_gravado' => null,
            'erp_iva' => null,
            'erp_perc_iva' => null,
            'erp_iibb' => null,
            'erp_iibb_detalle' => '',
            'origen_items' => '',
            'dto_pie' => 0.0,
            'dif_total' => null,
            'dif_iibb' => null,
            'ok' => false,
            'motivo' => '',
        ];

        if (! $cliente) {
            $base['motivo'] = 'cliente no está en ERP';

            return $base;
        }

        $armado = $this->armarItems($lineasPedido, $lineasFactura, $anitaGravado, $impuesto21);
        $dataItems = $armado['items'];
        $dtoPie = round((float) ($venta['ven_porc_desc'] ?? $venta['ven_porc_descuento'] ?? 0), 4);
        $base['dto_pie'] = $dtoPie;
        if (in_array($armado['origen'], ['compaux', 'pedido'], true) && $dtoPie > 0.00001) {
            foreach ($dataItems as $i => $item) {
                $dataItems[$i]['descuentofinal'] = $dtoPie;
            }
            $base['origen_items'] = $armado['origen'].' dto'.$dtoPie;
        } else {
            $base['origen_items'] = $armado['origen'];
        }
        if ($dataItems === []) {
            $base['motivo'] = 'sin ítems para simular';

            return $base;
        }

        $datosCliente = [
            'condicioniva_id' => $cliente->condicioniva_id,
            'numerodocumento' => $cliente->numerodocumento,
            'retieneiva' => $cliente->retieneiva,
            'condicioniibb_id' => $cliente->condicioniibb_id,
            'provincia' => ClienteProvinciaIibbSupport::idParaPercepcionAdmin($cliente),
            'descuentoimportepie' => 0,
            'id' => $cliente->id,
            'abasto_id' => $cliente->abasto_id,
            'porcentajelogistica' => $cliente->porcentajelogistica,
            'empresa_id' => $empresaId,
        ];

        if ($letra === 'B') {
            $datosCliente['omitir_percepciones'] = true;
            if (PercepcionNoCategorizadoSupport::aplicarAunqueSeOmitanOtras(false, $cliente->condicionivas)) {
                $datosCliente['aplicar_percepcion_no_categorizado'] = true;
            }
            if (ElBierzoFacturaBPercepcionCabaSupport::correspondePorLetra($letra)) {
                $datosCliente[ElBierzoFacturaBPercepcionCabaSupport::FLAG] = true;
            }
        }

        try {
            $conceptos = $impuestoService->calculaImpuestoVenta($dataItems, $datosCliente, $fecha);
        } catch (\Throwable $e) {
            $base['motivo'] = 'error motor: '.$e->getMessage();

            return $base;
        }

        $erp = $this->totalesDesdeConceptos($conceptos);
        $base['erp_total'] = $erp['total'];
        $base['erp_gravado'] = $erp['gravado'];
        $base['erp_iva'] = $erp['iva'];
        $base['erp_perc_iva'] = $erp['perc_iva'];
        $base['erp_iibb'] = $erp['iibb'];
        $base['erp_iibb_detalle'] = $erp['iibb_detalle'];
        $base['dif_total'] = round($erp['total'] - $anitaTotal, 2);
        $base['dif_iibb'] = round($erp['iibb'] - $anitaIibb, 2);

        $iibbOk = abs($base['dif_iibb']) < 0.05 && $this->mismasJurisdicciones($anitaIibbDet['por_jur'], $erp['por_jur']);
        $totalOk = abs($base['dif_total']) < 0.05;
        $base['ok'] = $iibbOk && $totalOk;
        if (! $base['ok']) {
            $motivos = [];
            if (! $iibbOk) {
                $motivos[] = 'IIBB Δ '.$base['dif_iibb'].' Anita '.$anitaIibbDet['detalle'].' ERP '.$erp['iibb_detalle'];
            }
            if (! $totalOk) {
                $motivos[] = 'total Δ '.$base['dif_total'];
            }
            $base['motivo'] = implode(' · ', $motivos);
        }

        return $base;
    }

    /**
     * @param  list<array<string, mixed>>  $lineasPedido
     * @param  list<array<string, mixed>>  $lineasFactura
     * @return array{items: list<array<string, mixed>>, origen: string}
     */
    private function armarItems(array $lineasPedido, array $lineasFactura, float $anitaGravado, int $impuesto21): array
    {
        $desdeFactura = $this->mapearLineas($lineasFactura, $impuesto21);
        if ($desdeFactura !== []) {
            return ['items' => $desdeFactura, 'origen' => 'compaux'];
        }
        $desdePedido = $this->mapearLineas($lineasPedido, $impuesto21);
        if ($desdePedido !== []) {
            return ['items' => $desdePedido, 'origen' => 'pedido'];
        }
        if (abs($anitaGravado) < 0.01) {
            return ['items' => [], 'origen' => ''];
        }

        return [
            'items' => [[
                'cantidad' => 1,
                'precio' => $anitaGravado,
                'descuento' => 0,
                'descuentointegrado' => '',
                'descuentofinal' => 0,
                'descuentointegradofinal' => '',
                'incluyeimpuesto' => 'N',
                'impuesto_id' => $impuesto21,
                'sku' => 'SIM',
                'descripcion' => 'Base gravado Anita',
            ]],
            'origen' => 'gravado',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function mapearLineas(array $lineas, int $impuesto21): array
    {
        $items = [];
        foreach ($lineas as $linea) {
            $sku = ltrim((string) ($linea['penv_articulo'] ?? $linea['compa_articulo'] ?? $linea['stkv_articulo'] ?? ''), '0');
            $kilos = (float) ($linea['penv_cantidad'] ?? $linea['compa_cantidad'] ?? $linea['stkv_cantidad'] ?? 0);
            $piezas = (float) ($linea['penv_pieza'] ?? $linea['compa_pieza'] ?? $linea['stkv_cant_unidad'] ?? 0);
            $precio = (float) ($linea['penv_precio'] ?? $linea['compa_precio'] ?? $linea['stkv_precio'] ?? 0);
            $dto = (float) ($linea['penv_dto_art'] ?? $linea['compa_dto'] ?? 0);
            $incl = strtoupper(trim((string) ($linea['penv_incl_impuesto'] ?? $linea['compa_incl_impuesto'] ?? 'N')));
            $articulo = $this->articuloPorSku($sku);
            $abrUm = strtoupper(trim((string) ($articulo?->unidadesdemedidas?->abreviatura ?? '')));
            $cobraPorUnidad = in_array($abrUm, ['UN', 'CAJ'], true) && abs($piezas) > 0.00001;
            $cantidad = $cobraPorUnidad ? $piezas : $kilos;
            if (abs($cantidad) < 0.00001 && abs($precio) < 0.00001) {
                continue;
            }
            $items[] = [
                'cantidad' => $cantidad,
                'precio' => $precio,
                'descuento' => $dto,
                'descuentointegrado' => '',
                'descuentofinal' => 0,
                'descuentointegradofinal' => '',
                'incluyeimpuesto' => in_array($incl, ['S', 'Y', '1'], true) ? 'S' : 'N',
                'impuesto_id' => (int) ($articulo->impuesto_id ?? $impuesto21),
                'sku' => $sku,
                'descripcion' => trim((string) ($linea['compa_desc'] ?? $linea['penv_articulo'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return array<string, mixed>
     */
    private function totalesDesdeConceptos(array $conceptos): array
    {
        $gravado = 0.0;
        $iva = 0.0;
        $percIva = 0.0;
        $iibb = 0.0;
        $total = 0.0;
        $dets = [];
        $porJur = [];

        foreach ($conceptos as $c) {
            $nombre = (string) ($c['concepto'] ?? '');
            $imp = round((float) ($c['importe'] ?? 0), 2);
            if ($nombre === 'Total') {
                $total = $imp;

                continue;
            }
            if ($nombre === 'Subtotal' || str_starts_with($nombre, 'Descuento')) {
                continue;
            }
            if (str_starts_with($nombre, 'Gravado')) {
                $gravado += $imp;
            }
            if (str_starts_with($nombre, 'Iva ')) {
                $iva += $imp;
            }
            if (str_contains($nombre, 'Percepcion IVA') || str_contains($nombre, 'Percepción IVA')) {
                $percIva += $imp;
            }
            $jur = (int) ($c['jurisdiccion'] ?? 0);
            if ($jur > 0) {
                $iibb += $imp;
                $dets[] = $jur.'='.number_format($imp, 2, '.', '');
                $porJur[$jur] = round(($porJur[$jur] ?? 0) + $imp, 2);
            }
        }

        return [
            'total' => $total,
            'gravado' => round($gravado, 2),
            'iva' => round($iva, 2),
            'perc_iva' => round($percIva, 2),
            'iibb' => round($iibb, 2),
            'iibb_detalle' => $dets === [] ? '—' : implode(' ', $dets),
            'por_jur' => $porJur,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ibrs
     * @return array{total: float, detalle: string, por_jur: array<int, float>}
     */
    private function sumarVenibr(array $ibrs): array
    {
        $total = 0.0;
        $dets = [];
        $porJur = [];
        foreach ($ibrs as $r) {
            $zona = (int) ($r['veni_provincia'] ?? 0);
            $jur = ClienteAnitaZonamultSupport::jurisdiccionDesdeCodigoZonamult($zona);
            if ($jur < 900) {
                $jur = $zona;
            }
            $imp = round((float) ($r['veni_importe'] ?? 0), 2);
            $tasa = round((float) ($r['veni_porcentaje'] ?? 0), 4);
            if (abs($imp) < 0.001) {
                continue;
            }
            $total += $imp;
            $dets[] = $jur.'@'.$tasa.'='.number_format($imp, 2, '.', '');
            $porJur[$jur] = round(($porJur[$jur] ?? 0) + $imp, 2);
        }

        return [
            'total' => round($total, 2),
            'detalle' => $dets === [] ? '—' : implode(' ', $dets),
            'por_jur' => $porJur,
        ];
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function mismasJurisdicciones(array $a, array $b): bool
    {
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
        foreach ($keys as $k) {
            if (abs(($a[$k] ?? 0) - ($b[$k] ?? 0)) >= 0.05) {
                return false;
            }
        }

        return true;
    }

    private function clientePorCodigo(string $codigo): ?Cliente
    {
        if ($codigo === '') {
            return null;
        }
        if (array_key_exists($codigo, $this->clientePorCodigoCache)) {
            return $this->clientePorCodigoCache[$codigo];
        }
        $vars = [$codigo];
        if (ctype_digit($codigo)) {
            $vars[] = str_pad($codigo, 6, '0', STR_PAD_LEFT);
        }

        return $this->clientePorCodigoCache[$codigo] = Cliente::query()->whereIn('codigo', $vars)->first();
    }

    private function articuloPorSku(string $sku): ?Articulo
    {
        if ($sku === '') {
            return null;
        }
        $clave = strtoupper($sku);
        if (array_key_exists($clave, $this->articuloPorSku)) {
            return $this->articuloPorSku[$clave];
        }

        return $this->articuloPorSku[$clave] = Articulo::query()
            ->with('unidadesdemedidas')
            ->where('sku', $sku)
            ->orWhere('sku', str_pad($sku, 13, '0', STR_PAD_LEFT))
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarVentas(int $sucursal, int $fechaAnita): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venta',
            'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_cliente,ven_nombre_cliente,'
                .'ven_monto,ven_gravado,ven_gravado_ot,ven_impuesto1,ven_perc_ing_bruto,ven_sellado,'
                .'ven_percepcion_iva,ven_logistica,ven_tot_abasto,ven_exento,ven_empresa,ven_zonamult,ven_porc_desc',
            'whereArmado' => " WHERE ven_sucursal = '{$sucursal}' AND ven_fecha = {$fechaAnita} ",
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException($err);
        }

        $out = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function listarVenibr(int $sucursal, int $fechaAnita): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venta, venibr',
            'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro,veni_provincia,veni_porcentaje,veni_importe,veni_codigo_perc',
            'whereArmado' => ' WHERE veni_tipo=ven_tipo AND veni_letra=ven_letra'
                .' AND veni_sucursal=ven_sucursal AND veni_nro=ven_nro'
                ." AND ven_sucursal = '{$sucursal}' AND ven_fecha = {$fechaAnita} ",
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        $filas = $err === null ? ApiAnita::decodificarListaFilas($raw) : [];

        $out = [];
        foreach ($filas as $fila) {
            $arr = (array) $fila;
            $out[$this->clave($arr)][] = $arr;
        }

        return $out;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    /**
     * @param  list<array<string, mixed>>  $ventas
     * @return array<string, list<array<string, mixed>>>
     */
    private function listarItems(int $sucursal, array $ventas): array
    {
        return $this->listarItemsCompauxPorNros($sucursal, $ventas);
    }

    /**
     * El join venta+compaux a veces vuelve vacío en el bridge; se lee compaux por nro de FAC.
     *
     * @param  list<array<string, mixed>>  $ventas
     * @return array<string, list<array<string, mixed>>>
     */
    private function listarItemsCompauxPorNros(int $sucursal, array $ventas): array
    {
        $nros = [];
        $clavePorNroTipo = [];
        foreach ($ventas as $venta) {
            $nro = (int) ($venta['ven_nro'] ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $nros[$nro] = true;
            $clavePorNroTipo[$nro][trim((string) ($venta['ven_tipo'] ?? '')).'|'.trim((string) ($venta['ven_letra'] ?? ''))] = $this->clave($venta);
        }
        $nros = array_keys($nros);
        if ($nros === []) {
            return [];
        }

        $api = new ApiAnita;
        $out = [];
        foreach (array_chunk($nros, 40) as $chunk) {
            $in = implode(',', $chunk);
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'compaux',
                'campos' => 'compa_tipo,compa_letra,compa_sucursal,compa_nro_fact,compa_articulo,compa_cantidad,compa_pieza,compa_precio,compa_dto,compa_orden',
                'whereArmado' => " WHERE compa_sucursal = {$sucursal} AND compa_nro_fact IN ({$in}) ",
            ]);
            if (ApiAnita::extraerMensajeError($raw) !== null) {
                continue;
            }
            foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                $arr = (array) $fila;
                $tipo = trim((string) ($arr['compa_tipo'] ?? ''));
                $letra = trim((string) ($arr['compa_letra'] ?? ''));
                $nro = (int) ($arr['compa_nro_fact'] ?? 0);
                $clave = $clavePorNroTipo[$nro][$tipo.'|'.$letra] ?? null;
                if ($clave === null) {
                    continue;
                }
                $out[$clave][] = $arr;
            }
        }

        return $out;
    }

    /**
     * Ítems del pedido Anita que originó cada factura (referped → pendmov).
     *
     * @param  list<array<string, mixed>>  $ventas
     * @return array<string, list<array<string, mixed>>>
     */
    private function listarItemsPedido(int $sucursal, array $ventas): array
    {
        $nros = [];
        foreach ($ventas as $venta) {
            $nros[(int) ($venta['ven_nro'] ?? 0)] = true;
        }
        unset($nros[0]);
        if ($nros === []) {
            return [];
        }

        $referencias = $this->listarReferped($sucursal, array_keys($nros));
        if ($referencias === []) {
            return [];
        }

        $pedidos = [];
        foreach ($referencias as $refs) {
            foreach ($refs as $ref) {
                $clavePed = $this->clavePedido($ref);
                if ($clavePed !== '') {
                    $pedidos[$clavePed] = $ref;
                }
            }
        }

        $lineasPorPedido = [];
        foreach ($pedidos as $clavePed => $ref) {
            $porOrden = [];
            foreach ($this->listarPendmov($ref) as $linea) {
                $porOrden[(int) ($linea['penv_orden'] ?? 0)] = $linea;
            }
            $lineasPorPedido[$clavePed] = $porOrden;
        }

        $out = [];
        foreach ($referencias as $claveFac => $refs) {
            $lineas = [];
            $vistos = [];
            foreach ($refs as $ref) {
                $clavePed = $this->clavePedido($ref);
                $orden = (int) ($ref['refp_orden_ped'] ?? -1);
                $marca = $clavePed.'|'.$orden;
                if ($clavePed === '' || isset($vistos[$marca])) {
                    continue;
                }
                $vistos[$marca] = true;
                if (isset($lineasPorPedido[$clavePed][$orden])) {
                    $lineas[] = $lineasPorPedido[$clavePed][$orden];
                }
            }
            if ($lineas !== []) {
                $out[$claveFac] = $lineas;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $nros
     * @return array<string, list<array<string, mixed>>>
     */
    private function listarReferped(int $sucursal, array $nros): array
    {
        $nros = array_values(array_unique(array_filter(array_map('intval', $nros))));
        if ($nros === []) {
            return [];
        }

        $api = new ApiAnita;
        $out = [];
        foreach (array_chunk($nros, 40) as $chunk) {
            $in = implode(',', $chunk);
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'referped',
                'campos' => 'refp_tipo_fact,refp_letra_fact,refp_sucursal_fact,refp_nro_fact,refp_orden_fact,'
                    .'refp_tipo_ped,refp_letra_ped,refp_sucursal_ped,refp_nro_ped,refp_orden_ped',
                'whereArmado' => " WHERE refp_sucursal_fact = {$sucursal} AND refp_nro_fact IN ({$in}) ",
            ]);
            if (ApiAnita::extraerMensajeError($raw) !== null) {
                continue;
            }
            foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                $arr = (array) $fila;
                $clave = trim((string) ($arr['refp_tipo_fact'] ?? '')).'|'
                    .trim((string) ($arr['refp_letra_fact'] ?? '')).'|'
                    .(int) ($arr['refp_sucursal_fact'] ?? 0).'|'
                    .(int) ($arr['refp_nro_fact'] ?? 0);
                $out[$clave][] = $arr;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $ref
     * @return list<array<string, mixed>>
     */
    private function listarPendmov(array $ref): array
    {
        $tipo = addslashes(trim((string) ($ref['refp_tipo_ped'] ?? 'PED')));
        $letra = addslashes(trim((string) ($ref['refp_letra_ped'] ?? 'X')));
        $suc = (int) ($ref['refp_sucursal_ped'] ?? 0);
        $nro = (int) ($ref['refp_nro_ped'] ?? 0);
        if ($nro <= 0) {
            return [];
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmov',
            'campos' => 'penv_articulo,penv_cantidad,penv_pieza,penv_precio,penv_dto_art,penv_incl_impuesto,penv_orden',
            'whereArmado' => " WHERE penv_tipo='{$tipo}' AND penv_letra='{$letra}'"
                ." AND penv_sucursal={$suc} AND penv_nro={$nro} ",
        ]);
        if (ApiAnita::extraerMensajeError($raw) !== null) {
            return [];
        }

        $out = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    private function clavePedido(array $ref): string
    {
        $nro = (int) ($ref['refp_nro_ped'] ?? 0);
        if ($nro <= 0) {
            return '';
        }

        return trim((string) ($ref['refp_tipo_ped'] ?? '')).'|'
            .trim((string) ($ref['refp_letra_ped'] ?? '')).'|'
            .(int) ($ref['refp_sucursal_ped'] ?? 0).'|'
            .$nro;
    }

    /**
     * @param  array<string, mixed>  $venta
     */
    private function esNcp(array $venta): bool
    {
        return str_starts_with(strtoupper(trim((string) ($venta['ven_tipo'] ?? ''))), 'NCP');
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function clave(array $fila): string
    {
        return trim((string) ($fila['ven_tipo'] ?? '')).'|'
            .trim((string) ($fila['ven_letra'] ?? '')).'|'
            .(int) ($fila['ven_sucursal'] ?? 0).'|'
            .(int) ($fila['ven_nro'] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function escribirCsv(array $filas, string $fecha, int $sucursal): string
    {
        $rel = 'control_percepcion_anita_pv'.$sucursal.'_'.$fecha.'.csv';
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, [
            'ok', 'fecha', 'comprobante', 'cliente', 'nombre',
            'anita_total', 'erp_total', 'dif_total',
            'anita_iibb', 'erp_iibb', 'dif_iibb',
            'anita_iibb_detalle', 'erp_iibb_detalle',
            'anita_gravado', 'erp_gravado', 'anita_iva', 'erp_iva',
            'anita_perc_iva', 'erp_perc_iva',
            'dto_pie', 'origen_items', 'motivo',
        ]);
        foreach ($filas as $f) {
            fputcsv($fh, [
                $f['ok'] ? 'OK' : 'DIFF',
                $f['fecha'] ?? '',
                $f['comprobante'], $f['cliente'], $f['nombre'],
                $f['anita_total'], $f['erp_total'], $f['dif_total'],
                $f['anita_iibb'], $f['erp_iibb'], $f['dif_iibb'],
                $f['anita_iibb_detalle'], $f['erp_iibb_detalle'],
                $f['anita_gravado'], $f['erp_gravado'], $f['anita_iva'], $f['erp_iva'],
                $f['anita_perc_iva'] ?? '', $f['erp_perc_iva'] ?? '',
                $f['dto_pie'] ?? 0,
                $f['origen_items'] ?? '',
                $f['motivo'],
            ]);
        }
        rewind($fh);
        Storage::disk('local')->put($rel, stream_get_contents($fh) ?: '');
        fclose($fh);

        return storage_path('app/'.$rel);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function resumen(array $filas, int $discrepancias, string $csv): void
    {
        $ok = count($filas) - $discrepancias;
        $this->info('OK '.$ok.' · discrepancias '.$discrepancias.' · CSV '.$csv);

        $cats = ['iibb' => 0, 'solo_total' => 0, 'gravado' => 0, 'sin_items' => 0, 'cliente' => 0, 'error' => 0];
        foreach ($filas as $f) {
            if ($f['ok']) {
                continue;
            }
            $mot = (string) ($f['motivo'] ?? '');
            if (str_contains($mot, 'cliente no')) {
                $cats['cliente']++;
            } elseif (str_contains($mot, 'sin ítems') || str_contains($mot, 'sin items')) {
                $cats['sin_items']++;
            } elseif (str_contains($mot, 'error motor')) {
                $cats['error']++;
            } elseif (abs((float) ($f['dif_iibb'] ?? 0)) >= 0.05) {
                $cats['iibb']++;
            } elseif (abs((float) ($f['anita_gravado'] ?? 0) - (float) ($f['erp_gravado'] ?? 0)) >= 1) {
                $cats['gravado']++;
            } else {
                $cats['solo_total']++;
            }
        }
        if ($discrepancias > 0) {
            $this->line('Por tipo: IIBB='.$cats['iibb']
                .' · solo total (perc.IVA/redondeo)='.$cats['solo_total']
                .' · gravado='.$cats['gravado']
                .' · sin ítems='.$cats['sin_items']
                .' · cliente='.$cats['cliente']
                .' · error='.$cats['error']);
        }

        $diffs = array_values(array_filter($filas, static fn (array $f) => $f['ok'] === false));
        if ($diffs === []) {
            $this->info('Sin discrepancias (tolerancia $0.05).');

            return;
        }

        usort($diffs, static fn (array $a, array $b) => abs((float) $b['dif_iibb']) <=> abs((float) $a['dif_iibb']));
        $this->newLine();
        $this->warn('Discrepancias (ordenadas por |Δ IIBB|)');
        $this->table(
            ['Fecha', 'Comprobante', 'Cliente', 'Anita IIBB', 'ERP IIBB', 'Δ IIBB', 'Anita tot', 'ERP tot', 'Δ tot', 'Motivo'],
            array_map(static fn (array $f) => [
                $f['fecha'] ?? '',
                $f['comprobante'],
                $f['cliente'].' '.$f['nombre'],
                $f['anita_iibb'],
                $f['erp_iibb'],
                $f['dif_iibb'],
                $f['anita_total'],
                $f['erp_total'],
                $f['dif_total'],
                mb_substr((string) $f['motivo'], 0, 80),
            ], array_slice($diffs, 0, 40))
        );
        if (count($diffs) > 40) {
            $this->line('… '.(count($diffs) - 40).' más en el CSV.');
        }
    }
}
