<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Impuesto;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportMediosPagoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

/**
 * Réplica ERP de facturas FAC gastronomía ya emitidas en Informix (venta, ítems, impuestos, resvta/cobranza).
 */
final class GastronomiaFacturaImportacionAnitaService
{
    private const TIPO = 'FAC';

    private const LETRA = 'B';

    public function __construct(
        private readonly GastronomiaCobranzaService $cobranzaGastronomiaService,
        private readonly MozoGastronomiaRepositoryInterface $mozoRepository,
    ) {
    }

    /**
     * @return array{importados:int,omitidos:int,errores:list<string>,advertencias:list<string>}
     */
    public function importarRango(
        int $sucursal,
        int $desde,
        int $hasta,
        int $empresaId,
        int $usuarioId,
        bool $dryRun = false,
        ?string $identificadorPc = null,
    ): array {
        if ($desde <= 0 || $hasta < $desde) {
            throw new InvalidArgumentException('Rango de comprobantes inválido.');
        }

        $ctx = $this->resolverContexto($sucursal, $empresaId, $identificadorPc);
        $ret = ['importados' => 0, 'omitidos' => 0, 'errores' => [], 'advertencias' => []];

        $api = new ApiAnita;
        $lista = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => 'ven_nro',
            'whereArmado' => $this->whereComprobante($sucursal, $desde, $hasta),
        ]));

        if (! is_array($lista)) {
            throw new \RuntimeException('No se pudo listar facturas en Anita.');
        }

        foreach ($lista as $item) {
            $nro = (int) ($item->ven_nro ?? 0);
            if ($nro <= 0) {
                continue;
            }

            try {
                $r = $this->importarUno($sucursal, $nro, $ctx, $usuarioId, $dryRun);
                if ($r === 'importado') {
                    $ret['importados']++;
                } elseif ($r === 'omitido') {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = $this->etiqueta($sucursal, $nro).': '.$e->getMessage();
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'omitido'
     */
    private function importarUno(int $sucursal, int $nro, array $ctx, int $usuarioId, bool $dryRun): string
    {
        $codigo = $this->armarCodigo($sucursal, $nro);

        if (Venta::query()->where('codigo', $codigo)->exists()) {
            return 'omitido';
        }

        $cab = $this->leerCabeceraAnita($sucursal, $nro);
        if ($cab === null) {
            throw new InvalidArgumentException('Sin cabecera venta en Anita.');
        }

        $lineasStk = $this->leerStkmov($sucursal, $nro);
        $total = round(abs((float) ($cab->ven_monto ?? 0)), 2);
        if ($lineasStk === [] && $total > 0.02) {
            throw new InvalidArgumentException('Sin ítems stkmov en Anita.');
        }

        $vengrav = $this->leerVengrav($sucursal, $nro);
        $vencae = $this->leerVencae($sucursal, $nro);
        $resvta = $this->leerResvta($sucursal, $nro);

        $fecha = $this->parseFechaAnita((string) ($cab->ven_fecha ?? ''));

        $mozo = $this->resolverMozo($resvta, $cab, (int) $ctx['empresa_id']);
        $medios = $this->resolverMediosPago($resvta, $total, (int) $ctx['empresa_id']);
        $sinCobranza = GastronomiaAnitaImportMediosPagoSupport::esCortesiaSinCobranza($total, $medios);

        if ($dryRun) {
            return 'importado';
        }

        $timestamp = $this->resolverTimestamp($fecha, $resvta);

        DB::transaction(function () use (
            $cab,
            $lineasStk,
            $vengrav,
            $vencae,
            $resvta,
            $ctx,
            $usuarioId,
            $codigo,
            $fecha,
            $total,
            $mozo,
            $medios,
            $sinCobranza,
            $timestamp,
            $sucursal,
            $nro,
        ) {
            $venta = Venta::query()->create([
                'fecha' => $fecha,
                'fechajornada' => $fecha,
                'tipotransaccion_id' => (int) $ctx['tipotransaccion_id'],
                'puntoventa_id' => (int) $ctx['puntoventa_id'],
                'numerocomprobante' => $nro,
                'actividad_arca_id' => (int) config('gastronomia_anita_import.actividad_arca_id', 1),
                'cliente_id' => (int) config('gastronomia_anita_import.cliente_consumidor_final_id', 1),
                'condicionventa_id' => null,
                'vendedor_id' => null,
                'transporte_id' => null,
                'total' => $total,
                'moneda_id' => (int) ($cab->ven_cod_mon ?? 1),
                'cotizacion' => (float) ($cab->ven_cotizacion ?? 1) ?: 1.,
                'estado' => ' ',
                'usuario_id' => $usuarioId,
                'leyenda' => 'Importación Anita '.$codigo,
                'descuento' => (float) ($cab->ven_porc_desc ?? 0),
                'descuentointegrado' => ' ',
                'lugarentrega' => null,
                'cliente_entrega_id' => null,
                'codigo' => $codigo,
                'nombre' => trim((string) ($cab->ven_nombre_cliente ?? 'CONSUMIDOR FINAL')) ?: 'CONSUMIDOR FINAL',
                'domicilio' => (string) ($cab->ven_direccion_cli ?? ''),
                'localidad_id' => null,
                'provincia_id' => null,
                'pais_id' => 1,
                'codigopostal' => (string) ($cab->ven_cod_postal_cli ?? ''),
                'email' => null,
                'telefono' => null,
                'numerodocumento' => (string) ($cab->ven_cuit_cli ?? '0'),
                'condicioniva_id' => (int) config('gastronomia_anita_import.condicioniva_consumidor_final_id', 3),
                'cae' => $vencae?->venc_nro_cae ?? null,
                'fechavencimientocae' => $this->parseFechaCae($vencae?->venc_fecha_vto ?? null),
                'puntoventaremito_id' => null,
                'numeroremito' => 0,
                'cantidadbulto' => 1,
            ]);

            $venta->created_at = $timestamp;
            $venta->updated_at = $timestamp;
            $venta->save();

            $this->crearEmisiones($venta->id, $lineasStk, $cab, $total, (int) ($cab->ven_cod_mon ?? 1), $timestamp);
            $this->crearImpuestos($venta->id, $cab, $vengrav, $timestamp);

            $cuenta = CuentaGastronomia::query()->create([
                'tipo' => CuentaGastronomia::TIPO_CUENTA,
                'empresa_id' => (int) $ctx['empresa_id'],
                'mesa_gastronomia_id' => null,
                'mozo_gastronomia_id' => $mozo?->id,
                'cubiertos' => max(1, (int) ($resvta->resv_cubierto ?? 1)),
                'estado' => CuentaGastronomia::ESTADO_FACTURADA,
                'identificador_pc' => (string) $ctx['identificador_pc'],
                'cliente_id' => (int) config('gastronomia_anita_import.cliente_consumidor_final_id', 1),
                'configuracion_puntoventa_gastronomia_id' => (int) $ctx['configuracion_id'],
                'venta_id' => $venta->id,
            ]);
            $cuenta->created_at = $timestamp;
            $cuenta->updated_at = $timestamp;
            $cuenta->save();

            VentaGastronomiaEmision::query()->create([
                'venta_id' => $venta->id,
                'cuenta_gastronomia_id' => $cuenta->id,
                'identificador_pc' => (string) $ctx['identificador_pc'],
                'configuracion_puntoventa_gastronomia_id' => (int) $ctx['configuracion_id'],
            ]);

            if (! $sinCobranza && $medios !== []) {
                session(['empresa_id' => (int) $ctx['empresa_id']]);
                $this->cobranzaGastronomiaService->registrarCobranzaPos(
                    $venta->fresh(),
                    $medios,
                    $ctx['configuracion'],
                    false,
                );
            }
        });

        return 'importado';
    }

    /**
     * @return array{
     *   empresa_id:int,
     *   puntoventa_id:int,
     *   tipotransaccion_id:int,
     *   configuracion_id:int,
     *   configuracion:ConfiguracionPuntoventaGastronomia,
     *   identificador_pc:string
     * }
     */
    private function resolverContexto(int $sucursal, int $empresaId, ?string $identificadorPc): array
    {
        $puntoventa = Puntoventa::query()
            ->where('codigo', $sucursal)
            ->whereHas('empresas', fn ($q) => $q->where('empresa_id', $empresaId))
            ->first();

        if ($puntoventa === null) {
            $puntoventa = Puntoventa::query()->where('codigo', $sucursal)->first();
        }

        if ($puntoventa === null) {
            throw new InvalidArgumentException('Punto de venta ERP código '.$sucursal.' no encontrado.');
        }

        $pc = $identificadorPc
            ?? (string) (config('gastronomia_anita_import.identificador_pc_por_sucursal')[$sucursal] ?? '');
        if ($pc === '') {
            throw new InvalidArgumentException('Defina identificador_pc para sucursal '.$sucursal.'.');
        }

        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->where('identificador_pc', $pc)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($cfg === null) {
            throw new InvalidArgumentException('Sin configuración PV gastronomía para PC '.$pc.' empresa '.$empresaId.'.');
        }

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? config('gastronomia.tipotransaccion_factura_id', 1));

        return [
            'empresa_id' => $empresaId,
            'puntoventa_id' => (int) $puntoventa->id,
            'tipotransaccion_id' => $tipoFacturaId,
            'configuracion_id' => (int) $cfg->id,
            'configuracion' => $cfg,
            'identificador_pc' => $pc,
        ];
    }

    private function leerCabeceraAnita(int $sucursal, int $nro): ?stdClass
    {
        $api = new ApiAnita;
        $campos = implode(',', [
            'ven_fecha', 'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1',
            'ven_porc_desc', 'ven_monto_desc', 'ven_cod_mon', 'ven_cotizacion',
            'ven_nombre_cliente', 'ven_direccion_cli', 'ven_cod_postal_cli', 'ven_cuit_cli',
            'ven_vendedor',
        ]);
        $raw = $api->apiCall([
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => $campos,
            'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro),
        ]);

        return ApiAnita::primeraFilaLista((string) $raw);
    }

    /**
     * @return list<stdClass>
     */
    private function leerStkmov(int $sucursal, int $nro): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'tabla' => 'stkmov',
            'campos' => 'stkv_articulo,stkv_cantidad,stkv_precio,stkv_cod_impuesto,stkv_descuento',
            'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro, 'stkv'),
        ]);
        $lista = json_decode($raw);

        return is_array($lista) ? $lista : [];
    }

    /**
     * @return list<stdClass>
     */
    private function leerVengrav(int $sucursal, int $nro): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'tabla' => 'vengrav',
            'campos' => 'veng_codigo_tasa,veng_gravado,veng_impuesto,veng_tasa',
            'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro, 'veng'),
        ]);
        $lista = json_decode($raw);

        return is_array($lista) ? $lista : [];
    }

    private function leerVencae(int $sucursal, int $nro): ?stdClass
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'tabla' => 'vencae',
            'campos' => 'venc_nro_cae,venc_fecha_vto',
            'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro, 'venc'),
        ]);

        return ApiAnita::primeraFilaLista((string) $raw);
    }

    private function leerResvta(int $sucursal, int $nro): ?stdClass
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'tabla' => 'resvta',
            'campos' => implode(',', [
                'resv_fecha', 'resv_hora', 'resv_cubierto', 'resv_mozo', 'resv_total',
                'resv_tot_efectivo', 'resv_tot_fiserv', 'resv_tot_qr', 'resv_tot_ctacte', 'resv_tot_tarjeta',
            ]),
            'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro, 'resv'),
        ]);

        return ApiAnita::primeraFilaLista((string) $raw);
    }

    /**
     * @param  list<stdClass>  $lineasStk
     */
    private function crearEmisiones(
        int $ventaId,
        array $lineasStk,
        stdClass $cab,
        float $total,
        int $monedaId,
        Carbon $timestamp,
    ): void {
        if ($lineasStk === []) {
            $impuestoId = (int) config('gastronomia.impuesto_exento_id', 1);
            $em = Venta_Emision::query()->create([
                'venta_id' => $ventaId,
                'numeroitem' => 1,
                'articulo_id' => $this->articuloCortesiaId(),
                'detalle' => 'Cortesía import Anita',
                'cantidad' => 1,
                'precio' => $total,
                'impuesto_id' => $impuestoId,
                'incluyeimpuesto' => '1',
                'moneda_id' => $monedaId,
                'lotestock' => 0,
                'descuento' => 0,
                'descuentointegrado' => ' ',
            ]);
            $em->created_at = $timestamp;
            $em->updated_at = $timestamp;
            $em->save();

            return;
        }

        $item = 0;
        foreach ($lineasStk as $stk) {
            $skuRaw = (string) ($stk->stkv_articulo ?? '');
            if ($skuRaw === '' || $skuRaw === 'texto') {
                continue;
            }

            $sku = ltrim($skuRaw, '0');
            $articulo = Articulo::query()->where('sku', $sku)->orWhere('sku', $skuRaw)->first();
            if ($articulo === null) {
                throw new InvalidArgumentException('Artículo SKU '.$sku.' no existe en ERP.');
            }

            $impuestoId = (int) ($stk->stkv_cod_impuesto ?? 0) ?: null;
            $tasa = 0.;
            if ($impuestoId) {
                $imp = Impuesto::query()->find($impuestoId);
                $tasa = $imp ? (float) $imp->valor : 0.;
            }

            $precioNeto = (float) ($stk->stkv_precio ?? 0);
            $precio = $tasa > 0 ? round($precioNeto * (1 + $tasa / 100), 6) : $precioNeto;

            $em = Venta_Emision::query()->create([
                'venta_id' => $ventaId,
                'numeroitem' => ++$item,
                'pedido_combinacion_id' => null,
                'ordentrabajo_id' => null,
                'lotestock' => 0,
                'articulo_id' => (int) $articulo->id,
                'combinacion_id' => null,
                'detalle' => (string) ($articulo->descripcion ?? $articulo->sku),
                'modulo_id' => null,
                'talle_id' => null,
                'cantidad' => abs((float) ($stk->stkv_cantidad ?? 0)),
                'precio' => $precio,
                'impuesto_id' => $impuestoId,
                'incluyeimpuesto' => '1',
                'moneda_id' => $monedaId,
                'descuento' => (float) ($stk->stkv_descuento ?? 0),
                'descuentointegrado' => ' ',
                'deposito_id' => null,
                'loteimportacion_id' => null,
            ]);
            $em->created_at = $timestamp;
            $em->updated_at = $timestamp;
            $em->save();
        }

        if ($item === 0) {
            throw new InvalidArgumentException('No se generaron renglones de emisión.');
        }
    }

    /**
     * @param  list<stdClass>  $vengrav
     */
    private function crearImpuestos(int $ventaId, stdClass $cab, array $vengrav, Carbon $timestamp): void
    {
        $gravado = round((float) ($cab->ven_gravado ?? 0) + (float) ($cab->ven_exento ?? 0), 2);
        $iva = round((float) ($cab->ven_impuesto1 ?? 0), 2);
        $total = round(abs((float) ($cab->ven_monto ?? 0)), 2);

        $filas = [
            ['concepto' => 'Subtotal', 'base' => 0., 'tasa' => 0., 'importe' => $gravado, 'impuesto_id' => null],
        ];

        foreach ($vengrav as $vg) {
            $tasa = (float) ($vg->veng_tasa ?? 0);
            $filas[] = [
                'concepto' => 'Gravado al '.number_format($tasa, 3, '.', '').'%',
                'base' => 0.,
                'tasa' => $tasa,
                'importe' => round((float) ($vg->veng_gravado ?? 0), 2),
                'impuesto_id' => (int) ($vg->veng_codigo_tasa ?? 0) ?: null,
            ];
            $filas[] = [
                'concepto' => 'Iva '.number_format($tasa, 3, '.', '').'%',
                'base' => round((float) ($vg->veng_gravado ?? 0), 2),
                'tasa' => $tasa,
                'importe' => round((float) ($vg->veng_impuesto ?? 0), 2),
                'impuesto_id' => (int) ($vg->veng_codigo_tasa ?? 0) ?: null,
            ];
        }

        if ($vengrav === [] && $iva > 0) {
            $filas[] = [
                'concepto' => 'Iva 21.000%',
                'base' => $gravado,
                'tasa' => 21.,
                'importe' => $iva,
                'impuesto_id' => 3,
            ];
        }

        $filas[] = ['concepto' => 'Total', 'base' => 0., 'tasa' => 0., 'importe' => $total, 'impuesto_id' => null];

        foreach ($filas as $f) {
            if (abs($f['importe']) < 0.0001 && $f['concepto'] !== 'Total') {
                continue;
            }
            $vi = Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => $f['concepto'],
                'baseimponible' => $f['base'],
                'tasa' => $f['tasa'],
                'importe' => $f['importe'],
                'provincia_id' => null,
                'impuesto_id' => $f['impuesto_id'],
            ]);
            $vi->created_at = $timestamp;
            $vi->updated_at = $timestamp;
            $vi->save();
        }
    }

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>
     */
    private function resolverMediosPago(?stdClass $resvta, float $total, int $empresaId): array
    {
        if ($resvta !== null) {
            return GastronomiaAnitaImportMediosPagoSupport::lineasDesdeResvta($resvta, $empresaId);
        }

        if ($total <= 0.02) {
            return [];
        }

        $efectivo = \App\Support\Ventas\GastronomiaCuentacajaEfectivo::cuentaParaEmpresa($empresaId);
        if ($efectivo === null) {
            throw new InvalidArgumentException('Sin resvta y sin cuenta efectivo para fallback.');
        }

        return [[
            'cuentacaja_id' => (int) $efectivo['id'],
            'moneda_id' => (int) $efectivo['moneda_id'],
            'monto' => round($total, 2),
            'cotizacion' => 1.,
            'observacion' => 'Import Anita (sin resvta, efectivo)',
        ]];
    }

    private function resolverMozo(?stdClass $resvta, stdClass $cab, int $empresaId): ?MozoGastronomia
    {
        $codigo = '';
        if ($resvta !== null && isset($resvta->resv_mozo)) {
            $codigo = (string) $resvta->resv_mozo;
        } elseif (isset($cab->ven_vendedor)) {
            $codigo = (string) $cab->ven_vendedor;
        }

        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        return $this->mozoRepository->findPorCodigo($codigo, $empresaId);
    }

    private function armarCodigo(int $sucursal, int $nro): string
    {
        $digSuc = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digNro = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);

        return self::TIPO.' '.self::LETRA.'-'
            .str_pad((string) $sucursal, $digSuc, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $nro, $digNro, '0', STR_PAD_LEFT);
    }

    private function whereComprobante(
        int $sucursal,
        int $desde,
        ?int $hasta = null,
        string $prefijo = 'ven',
    ): string {
        $hasta ??= $desde;
        $s = (string) $sucursal;

        return ' WHERE '.$prefijo."_tipo='".self::TIPO."' AND ".$prefijo."_letra='".self::LETRA
            ."' AND ".$prefijo."_sucursal='".$s."' AND ".$prefijo.'_nro >= '.$desde
            .' AND '.$prefijo.'_nro <= '.$hasta;
    }

    private function parseFechaAnita(string $yyyymmdd): string
    {
        if (strlen($yyyymmdd) !== 8) {
            return Carbon::today()->format('Y-m-d');
        }

        return Carbon::createFromFormat('Ymd', $yyyymmdd)->format('Y-m-d');
    }

    private function parseFechaCae(mixed $yyyymmdd): ?string
    {
        if ($yyyymmdd === null || $yyyymmdd === '' || $yyyymmdd === 0) {
            return null;
        }

        return $this->parseFechaAnita((string) $yyyymmdd);
    }

    private function resolverTimestamp(string $fecha, ?stdClass $resvta): Carbon
    {
        $hora = '12:00';
        if ($resvta !== null && ! empty($resvta->resv_hora)) {
            $hora = trim((string) $resvta->resv_hora);
            if (strlen($hora) === 4 && ctype_digit($hora)) {
                $hora = substr($hora, 0, 2).':'.substr($hora, 2, 2);
            }
        }

        try {
            return Carbon::parse($fecha.' '.$hora);
        } catch (\Throwable) {
            return Carbon::parse($fecha.' 12:00');
        }
    }

    private function etiqueta(int $sucursal, int $nro): string
    {
        return self::TIPO.' '.self::LETRA.' '.$sucursal.'-'.$nro;
    }

    private function articuloCortesiaId(): int
    {
        $id = (int) Articulo::query()->orderBy('id')->value('id');

        return $id > 0 ? $id : 1;
    }
}
