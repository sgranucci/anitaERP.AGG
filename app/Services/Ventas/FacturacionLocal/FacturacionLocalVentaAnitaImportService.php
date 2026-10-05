<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Provincia;
use App\Models\Stock\Depmae;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalEmisionVinculoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Trae a anitaERP cabeceras de venta de los locales que están en Anita y faltan acá.
 * No genera asiento: la contabilidad entra por contable:importar-asientos-anita.
 * No mueve stock: el talle vive en el bridge del local.
 */
final class FacturacionLocalVentaAnitaImportService
{
    /** @var list<int> */
    private const SUCURSALES = [17, 21, 23, 25, 26, 27];

    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly ArticuloQueryInterface $articuloQuery,
        private readonly ApiAnita $api = new ApiAnita(),
    ) {}

    /**
     * @return array{anita:int,faltantes:int,creadas:int,errores:list<string>,detalle:list<string>}
     */
    public function importarFaltantes(string $desdeYmd, string $hastaYmd, bool $dryRun): array
    {
        $filas = $this->listarVenta($desdeYmd, $hastaYmd);
        $faltantes = $this->filtrarFaltantes($filas, $desdeYmd, $hastaYmd);
        $resultado = [
            'anita' => count($filas),
            'faltantes' => count($faltantes),
            'creadas' => 0,
            'errores' => [],
            'detalle' => [],
        ];
        if ($faltantes === [] || $dryRun) {
            foreach ($faltantes as $fila) {
                $resultado['detalle'][] = $this->etiqueta($fila);
            }

            return $resultado;
        }

        $claves = array_map(fn (stdClass $fila) => $this->clave($fila), $faltantes);
        $vencae = $this->indexar('vencae', 'venc', 'venc_nro_cae, venc_fecha_vto', $claves);
        $vengrav = $this->indexar('vengrav', 'veng', 'veng_codigo_tasa, veng_gravado, veng_impuesto, veng_tasa', $claves);
        $venibr = $this->indexar('venibr', 'veni', 'veni_provincia, veni_codigo_perc, veni_porcentaje, veni_importe', $claves);
        $compaux = $this->indexarCompaux($claves);

        foreach ($faltantes as $fila) {
            $etiqueta = $this->etiqueta($fila);
            try {
                $ventaId = $this->grabar($fila, $vencae, $vengrav, $venibr, $compaux);
                $resultado['creadas']++;
                $resultado['detalle'][] = $etiqueta.' → venta '.$ventaId;
            } catch (Throwable $e) {
                $resultado['errores'][] = $etiqueta.': '.$e->getMessage();
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, list<stdClass>>  $vencae
     * @param  array<string, list<stdClass>>  $vengrav
     * @param  array<string, list<stdClass>>  $venibr
     * @param  array<string, list<stdClass>>  $compaux
     */
    private function grabar(stdClass $fila, array $vencae, array $vengrav, array $venibr, array $compaux): int
    {
        $tipo = strtoupper(trim((string) $fila->ven_tipo));
        $letra = strtoupper(trim((string) $fila->ven_letra));
        $sucursal = (int) $fila->ven_sucursal;
        $numero = (int) $fila->ven_nro;
        $clave = $this->clave($fila);
        $fecha = Carbon::createFromFormat('Ymd', trim((string) $fila->ven_fecha))->format('Y-m-d');
        $signo = str_starts_with($tipo, 'NC') ? -1.0 : 1.0;
        $total = abs((float) $fila->ven_monto);
        $gravado = abs((float) ($fila->ven_gravado ?? 0));
        $iva = abs((float) ($fila->ven_impuesto1 ?? 0));
        $exento = abs((float) ($fila->ven_exento ?? 0));

        $puntoventa = Puntoventa::query()
            ->where('codigo', str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT))
            ->first();
        if (! $puntoventa) {
            throw new RuntimeException('No existe el punto de venta '.$sucursal);
        }
        $tipotransaccion = Tipotransaccion::query()
            ->where('abreviatura', $tipo)
            ->where('estado', 'A')
            ->first();
        if (! $tipotransaccion) {
            throw new RuntimeException('No existe el tipo '.$tipo);
        }
        $empresa = Empresa::query()->find($puntoventa->empresa_id);
        if (! $empresa) {
            throw new RuntimeException('No existe la empresa del punto de venta');
        }
        $cliente = Cliente::query()->where('codigo', '0')->orderBy('id')->first();
        if (! $cliente) {
            throw new RuntimeException('No existe el cliente consumidor final (codigo 0)');
        }
        $clienteGraba = clone $cliente;
        $nombre = trim((string) ($fila->ven_nombre_cliente ?? ''));
        if ($nombre !== '') {
            $clienteGraba->nombre = $nombre;
        }
        $documento = trim((string) ($fila->ven_cuit_cli ?? ''));
        if ($documento !== '') {
            $clienteGraba->numerodocumento = $documento;
        }
        $direccion = trim((string) ($fila->ven_direccion_cli ?? ''));
        if ($direccion !== '') {
            $clienteGraba->domicilio = $direccion;
        }

        $lineas = $this->armarLineas($compaux[$clave] ?? [], (int) $empresa->id);
        $conceptos = $this->armarConceptos($vengrav[$clave] ?? [], $venibr[$clave] ?? [], $gravado, $iva, $exento, (float) ($fila->ven_perc_ing_bruto ?? 0));

        $ret = $this->facturacionService->grabaFacturaERP(
            $empresa,
            $tipotransaccion->codigo,
            $tipotransaccion,
            $fecha,
            $clienteGraba,
            $total,
            1,
            1,
            'Importado Anita local',
            $letra,
            $puntoventa,
            $numero,
            null,
            $conceptos,
            [],
            $lineas,
            [],
            trim($tipo.' '.$letra.' '.$puntoventa->codigo.' '.$numero),
            $signo,
            0,
            0,
            [
                'codigoempresa' => $empresa->codigo ?? 1,
                'gravado' => $gravado,
                'iva' => $iva,
                'total' => $total,
                'nogravado' => 0,
                'exento' => $exento,
            ],
            0,
            null,
            null,
            [
                'deposito_id' => $this->depositoId((int) $empresa->id, $sucursal),
                'omitir_contabilidad' => true,
                'omitir_sincronizacion_anita' => true,
                'omitir_stkmov_anita' => true,
                'omitir_solicitud_arca_cae' => true,
                'omitir_numera_anita_fin' => true,
                'omitir_cuenta_corriente' => true,
                'omitir_movimiento_stock' => true,
            ]
        );
        if (! is_array($ret) || trim((string) ($ret['error'] ?? '')) !== '') {
            throw new RuntimeException(trim((string) ($ret['mensaje'] ?? $ret['error'] ?? 'No se pudo grabar la venta')));
        }
        $ventaId = (int) ($ret['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            throw new RuntimeException('La venta no quedó grabada');
        }

        $cae = $vencae[$clave][0] ?? null;
        if ($cae && trim((string) ($cae->venc_nro_cae ?? '')) !== '') {
            $vto = trim((string) ($cae->venc_fecha_vto ?? ''));
            Venta::query()->whereKey($ventaId)->update([
                'cae' => trim((string) $cae->venc_nro_cae),
                'fechavencimientocae' => preg_match('/^\d{8}$/', $vto)
                    ? Carbon::createFromFormat('Ymd', $vto)->format('Y-m-d')
                    : null,
            ]);
        }

        FacturacionLocalEmisionVinculoSupport::vincularVenta($ventaId, 'anita_local');

        return $ventaId;
    }

    /**
     * @param  list<stdClass>  $lineas
     * @return list<array<string, mixed>>
     */
    private function armarLineas(array $lineas, int $empresaId): array
    {
        $cuentaVentaId = (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', '411000003')
            ->value('id') ?? 0);
        $out = [];
        foreach ($lineas as $linea) {
            $skuRaw = trim((string) ($linea->compa_articulo ?? ''));
            if ($skuRaw === '' || $skuRaw === 'texto') {
                continue;
            }
            $sku = ltrim($skuRaw, '0');
            $sku = $sku !== '' ? $sku : '0';
            if ($sku === '0') {
                continue;
            }
            $articulo = $this->articuloQuery->traeArticuloPorSku($sku)
                ?? $this->articuloQuery->traeArticuloPorSku(str_pad($sku, 13, '0', STR_PAD_LEFT));
            $impuestoId = (int) ($linea->compa_tipo_iva ?: 3);
            $tasa = (float) (Impuesto::query()->whereKey($impuestoId)->value('valor') ?? 21);
            $precio = (float) $linea->compa_precio;
            if (strtoupper(trim((string) ($linea->compa_incl_imp ?? 'S'))) === 'S' && $tasa > 0) {
                $precio = round($precio * (1 + ($tasa / 100)), 2);
            }
            $item = [
                'cantidad' => (float) $linea->compa_cantidad,
                'precio' => $precio,
                'descuento' => (float) ($linea->compa_dto ?? 0),
                'descuentointegrado' => '',
                'incluyeimpuesto' => '1',
                'impuesto_id' => $impuestoId > 0 ? $impuestoId : 3,
                'sku' => $sku,
                'descripcion' => (string) ($linea->compa_desc ?? $sku),
                'detalle' => (string) ($linea->compa_desc ?? $sku),
                'moneda_id' => 1,
                'listaprecio_id' => 1,
            ];
            if ($articulo) {
                $item['articulo_id'] = (int) $articulo->id;
                $cuentaArticulo = (int) (DB::table('articulo')->where('id', $articulo->id)->value('cuentacontableventa_id') ?? 0);
                $item['cuentacontable_id'] = $cuentaArticulo > 0 ? $cuentaArticulo : $cuentaVentaId;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  list<stdClass>  $vengrav
     * @param  list<stdClass>  $venibr
     * @return list<array<string, mixed>>
     */
    private function armarConceptos(array $vengrav, array $venibr, float $gravado, float $iva, float $exento, float $percepcionCabecera): array
    {
        $conceptos = [];
        if ($vengrav !== []) {
            foreach ($vengrav as $vg) {
                $importe = abs((float) ($vg->veng_impuesto ?? 0));
                if ($importe < 0.0001) {
                    continue;
                }
                $tasa = (float) ($vg->veng_tasa ?? 0);
                $impuestoId = $this->impuestoIdPorTasa($tasa);
                $conceptos[] = [
                    'concepto' => $tasa > 0 ? 'Iva '.$tasa.'%' : 'Total Iva',
                    'tasa' => $tasa > 0 ? $tasa : 21,
                    'importe' => $importe,
                    'baseimponible' => abs((float) ($vg->veng_gravado ?? 0)),
                    'impuesto_id' => $impuestoId,
                ];
            }
        } elseif ($iva >= 0.0001) {
            $conceptos[] = [
                'concepto' => 'Total Iva',
                'tasa' => 21,
                'importe' => $iva,
                'baseimponible' => $gravado,
                'impuesto_id' => 3,
            ];
        }
        if ($exento >= 0.0001) {
            $conceptos[] = [
                'concepto' => 'Exento',
                'tasa' => 0,
                'importe' => $exento,
                'impuesto_id' => 1,
            ];
        }
        if ($venibr !== []) {
            foreach ($venibr as $ibr) {
                $importe = abs((float) ($ibr->veni_importe ?? 0));
                if ($importe < 0.0001) {
                    continue;
                }
                $conceptos[] = [
                    'concepto' => 'Percepcion IIBB',
                    'tasa' => (float) ($ibr->veni_porcentaje ?? 0),
                    'importe' => $importe,
                    'provincia_id' => $this->provinciaId((int) ($ibr->veni_provincia ?? 0)),
                ];
            }
        } elseif ($percepcionCabecera >= 0.0001) {
            $conceptos[] = [
                'concepto' => 'Percepcion IIBB',
                'jurisdiccion' => '902',
                'provincia_id' => 2,
                'tasa' => $gravado > 0 ? $percepcionCabecera / $gravado : 0,
                'importe' => $percepcionCabecera,
            ];
        }

        return $conceptos;
    }

    private function impuestoIdPorTasa(float $tasa): int
    {
        if ($tasa <= 0) {
            return 1;
        }
        $id = (int) (Impuesto::query()->where('valor', $tasa)->value('id') ?? 0);

        return $id > 0 ? $id : 3;
    }

    private function provinciaId(int $codigoAnita): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }
        $id = Provincia::query()
            ->where('codigoexterno', $codigoAnita)
            ->orWhere('jurisdiccion', $codigoAnita)
            ->orWhere('codigo', (string) $codigoAnita)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function depositoId(int $empresaId, int $sucursal): int
    {
        $codigo = match ($sucursal) {
            25 => '30',
            27 => '27',
            default => '10',
        };
        $id = (int) (Depmae::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('No existe el depósito '.$codigo);
        }

        return $id;
    }

    /**
     * @return list<stdClass>
     */
    private function listarVenta(string $desdeYmd, string $hastaYmd): array
    {
        $suc = implode(',', self::SUCURSALES);
        $filas = $this->listar(
            'venta',
            'ven_tipo, ven_letra, ven_sucursal, ven_nro, ven_fecha, ven_monto, ven_gravado, ven_impuesto1, ven_exento, ven_perc_ing_bruto, ven_nombre_cliente, ven_cuit_cli, ven_direccion_cli',
            " WHERE ven_fecha >= '".$desdeYmd."' AND ven_fecha <= '".$hastaYmd."' AND ven_sucursal IN (".$suc.") ",
            'ven_sucursal, ven_fecha, ven_nro'
        );
        if ($filas === null) {
            throw new RuntimeException('No se pudo leer venta en Anita');
        }

        return $filas;
    }

    /**
     * @param  list<stdClass>  $filas
     * @return list<stdClass>
     */
    private function filtrarFaltantes(array $filas, string $desdeYmd, string $hastaYmd): array
    {
        $desde = Carbon::createFromFormat('Ymd', $desdeYmd)->format('Y-m-d');
        $hasta = Carbon::createFromFormat('Ymd', $hastaYmd)->addDay()->format('Y-m-d');
        $codigosPv = array_map(
            static fn (int $sucursal): string => str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT),
            self::SUCURSALES
        );
        $erp = [];
        $rows = DB::table('venta as v')
            ->join('puntoventa as p', 'p.id', '=', 'v.puntoventa_id')
            ->join('tipotransaccion as t', 't.id', '=', 'v.tipotransaccion_id')
            ->whereIn('p.codigo', $codigosPv)
            ->where('v.fecha', '>=', $desde)
            ->where('v.fecha', '<', $hasta)
            ->get(['t.abreviatura as tipo', 'v.codigo', 'v.numerocomprobante as nro', 'p.codigo as suc']);
        foreach ($rows as $row) {
            $letra = '?';
            if (preg_match('/^\S+\s+([A-Z])-/', (string) $row->codigo, $m)) {
                $letra = $m[1];
            }
            $erp[strtoupper(trim((string) $row->tipo)).'|'.$letra.'|'.(int) $row->suc.'|'.(int) $row->nro] = true;
        }

        $faltantes = [];
        foreach ($filas as $fila) {
            if (! isset($erp[$this->clave($fila)])) {
                $faltantes[] = $fila;
            }
        }

        return $faltantes;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, list<stdClass>>
     */
    private function indexar(string $tabla, string $prefijo, string $campos, array $claves): array
    {
        if ($claves === []) {
            return [];
        }
        $filas = $this->listar($tabla, $prefijo.'_tipo, '.$prefijo.'_letra, '.$prefijo.'_sucursal, '.$prefijo.'_nro, '.$campos, $this->whereClaves($prefijo, $claves), $prefijo.'_sucursal, '.$prefijo.'_nro');
        $idx = [];
        foreach ($filas ?? [] as $fila) {
            $clave = strtoupper(trim((string) $fila->{$prefijo.'_tipo'})).'|'
                .strtoupper(trim((string) $fila->{$prefijo.'_letra'})).'|'
                .(int) $fila->{$prefijo.'_sucursal'}.'|'
                .(int) $fila->{$prefijo.'_nro'};
            $idx[$clave][] = $fila;
        }

        return $idx;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, list<stdClass>>
     */
    private function indexarCompaux(array $claves): array
    {
        if ($claves === []) {
            return [];
        }
        $filas = $this->listar(
            'compaux',
            'compa_tipo, compa_letra, compa_sucursal, compa_nro_fact, compa_orden, compa_articulo, compa_cantidad, compa_precio, compa_desc, compa_tipo_iva, compa_incl_imp, compa_dto',
            $this->whereClaves('compa', $claves, 'compa_nro_fact'),
            'compa_sucursal, compa_nro_fact, compa_orden'
        );
        $idx = [];
        foreach ($filas ?? [] as $fila) {
            $clave = strtoupper(trim((string) $fila->compa_tipo)).'|'
                .strtoupper(trim((string) $fila->compa_letra)).'|'
                .(int) $fila->compa_sucursal.'|'
                .(int) $fila->compa_nro_fact;
            $idx[$clave][] = $fila;
        }

        return $idx;
    }

    /**
     * @param  list<string>  $claves tipo|letra|suc|nro
     */
    private function whereClaves(string $prefijo, array $claves, ?string $campoNro = null): string
    {
        $campoNro ??= $prefijo.'_nro';
        $grupos = [];
        foreach ($claves as $clave) {
            [$tipo, $letra, $suc, $nro] = explode('|', $clave);
            $grupos[$tipo.'|'.$letra.'|'.$suc][] = (int) $nro;
        }
        $parts = [];
        foreach ($grupos as $grupo => $nros) {
            [$tipo, $letra, $suc] = explode('|', $grupo);
            $tipo = str_replace("'", '', $tipo);
            $letra = str_replace("'", '', $letra);
            $parts[] = '('.$prefijo."_tipo='".$tipo."' AND ".$prefijo."_letra='".$letra."' AND ".$prefijo.'_sucursal='.(int) $suc
                .' AND '.$campoNro.' IN ('.implode(',', $nros).'))';
        }

        return ' WHERE '.implode(' OR ', $parts).' ';
    }

    /**
     * @return list<stdClass>|null
     */
    private function listar(string $tabla, string $campos, string $where, string $orderBy): ?array
    {
        $raw = $this->api->apiCall([
            'acc' => 'list',
            'tabla' => $tabla,
            'sistema' => 'ventas',
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => $orderBy,
        ]);
        $texto = is_string($raw) ? $raw : json_encode($raw);
        $error = ApiAnita::extraerMensajeError($texto);
        if ($error !== null) {
            throw new RuntimeException($tabla.': '.$error);
        }
        $filas = json_decode((string) $texto);

        return is_array($filas) ? $filas : [];
    }

    private function clave(stdClass $fila): string
    {
        return strtoupper(trim((string) $fila->ven_tipo)).'|'
            .strtoupper(trim((string) $fila->ven_letra)).'|'
            .(int) $fila->ven_sucursal.'|'
            .(int) $fila->ven_nro;
    }

    private function etiqueta(stdClass $fila): string
    {
        return trim((string) $fila->ven_tipo).' '.trim((string) $fila->ven_letra).' '
            .(int) $fila->ven_sucursal.'-'.(int) $fila->ven_nro
            .' '.$fila->ven_fecha.' '.$fila->ven_monto;
    }
}
