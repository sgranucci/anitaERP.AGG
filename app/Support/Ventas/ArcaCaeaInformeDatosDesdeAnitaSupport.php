<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Ventas\Cliente;
use InvalidArgumentException;

/**
 * Arma el payload de presentación CAEA desde un comprobante Informix (Anita).
 */
final class ArcaCaeaInformeDatosDesdeAnitaSupport
{
    private const SISTEMA = 'ventas';

    /**
     * @return array{
     *   datos: array<string, mixed>,
     *   cabecera: array<string, mixed>,
     *   fuente: string
     * }
     */
    public static function construir(
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
        int $empresaId,
        string $nroCaea,
    ): array {
        $tipoAnita = strtoupper(trim($tipoAnita));
        $letra = strtoupper(trim($letra));
        if ($tipoAnita === '' || $letra === '' || $sucursal < 1 || $numero < 1) {
            throw new InvalidArgumentException('Comprobante Anita inválido (tipo/letra/sucursal/número).');
        }

        $cab = self::leerCabecera($tipoAnita, $letra, $sucursal, $numero);
        if ($cab === null) {
            throw new InvalidArgumentException(sprintf(
                'No se encontró en Anita %s %s %d-%d.',
                $tipoAnita,
                $letra,
                $sucursal,
                $numero,
            ));
        }

        $cbteTipo = ArcaCaeaAnitaTipoAfipSupport::tipoAfipDesdeAnita($tipoAnita, $letra);
        if ($cbteTipo <= 0) {
            throw new InvalidArgumentException("No se pudo mapear tipo Anita {$tipoAnita}/{$letra} a AFIP.");
        }

        $clienteCodigo = (int) preg_replace('/\D+/', '', (string) ($cab['ven_cliente'] ?? '')) ?: 0;
        $cliente = $clienteCodigo > 0
            ? Cliente::query()->with('tipodocumentos')->where('codigo', (string) $clienteCodigo)->first()
            : null;

        $doc = preg_replace('/\D+/', '', (string) ($cliente?->numerodocumento ?? '')) ?? '';
        $tipodoc = (int) ($cliente?->tipodocumentos?->codigoexterno ?? 0);
        if ($doc === '' || $tipodoc <= 0) {
            $tipodoc = 80;
            $doc = $doc !== '' ? $doc : '0';
        }

        $gravado = abs((float) ($cab['ven_gravado'] ?? 0));
        $exento = abs((float) ($cab['ven_exento'] ?? 0));
        $iva = abs((float) ($cab['ven_impuesto1'] ?? 0));
        $percIva = abs((float) ($cab['ven_percepcion_iva'] ?? 0));
        $iibb = abs((float) ($cab['ven_perc_ing_bruto'] ?? 0));
        $total = abs((float) ($cab['ven_monto'] ?? 0));
        $fecha = self::normalizarFechaYmd((string) ($cab['ven_fecha'] ?? ''));
        $fechaVto = self::normalizarFechaYmd((string) ($cab['ven_fecha_vto'] ?? $fecha));

        $tributos = [];
        $totalTributo = 0.0;
        if ($percIva > 0) {
            $tributos[] = [
                'id' => 1,
                'base_imp' => $gravado,
                'alicuota' => 0,
                'desc' => 'Percepcion IVA',
                'importe' => $percIva,
            ];
            $totalTributo += $percIva;
        }

        foreach (self::leerVenibr($tipoAnita, $letra, $sucursal, $numero) as $ibr) {
            $imp = abs((float) ($ibr['veni_importe'] ?? 0));
            if ($imp <= 0) {
                continue;
            }
            $tributos[] = [
                'id' => 2,
                'base_imp' => $gravado,
                'alicuota' => (float) ($ibr['veni_porcentaje'] ?? 0),
                'desc' => 'Impuestos provinciales',
                'importe' => $imp,
            ];
            $totalTributo += $imp;
        }

        if ($totalTributo <= 0 && $iibb > 0) {
            $tributos[] = [
                'id' => 2,
                'base_imp' => $gravado,
                'alicuota' => 0,
                'desc' => 'Impuestos provinciales',
                'importe' => $iibb,
            ];
            $totalTributo = $iibb;
        }

        $impuestos = [];
        if ($iva > 0) {
            $impuestos[] = [
                'id' => 5,
                'base_imp' => $gravado,
                'importe' => $iva,
            ];
        }

        $items = self::armarItems($tipoAnita, $letra, $sucursal, $numero, $gravado, $iva);
        $datosAdicionales = self::datosAdicionalesFce($cbteTipo, $empresaId);

        $datos = [
            'cbte_tipo' => $cbteTipo,
            'letra' => $letra,
            'tipodoc' => $tipodoc,
            'numerodocumento' => $doc,
            'condicioniva_id' => (int) ($cliente?->condicioniva_id ?? 1),
            'numerocomprobante' => $numero,
            'fechacomprobante' => $fecha,
            'fechavencimiento' => $fechaVto !== '' ? $fechaVto : $fecha,
            'cbte_fch_hs_gen' => $fecha.'120000',
            'total' => $total,
            'nogravado' => 0.0,
            'gravado' => $gravado,
            'exento' => $exento,
            'iva' => $iva,
            'tributo' => round($totalTributo, 2),
            'moneda' => 'PES',
            'cotizacion' => 1.0,
            'concepto' => 1,
            'impuestos' => $impuestos,
            'tributos' => $tributos,
            'comprobantesasociados' => [],
            'items' => $items,
            'items_importe_con_iva' => true,
            'datos_adicionales' => $datosAdicionales,
            'caea' => $nroCaea,
        ];

        return [
            'datos' => $datos,
            'cabecera' => $cab,
            'fuente' => 'anita',
            'cliente_nombre' => (string) ($cliente?->nombre ?? $cab['ven_nombre_cliente'] ?? ''),
            'tipo_anita' => $tipoAnita,
        ];
    }

    public static function existe(string $tipoAnita, string $letra, int $sucursal, int $numero): bool
    {
        return self::leerCabecera($tipoAnita, $letra, $sucursal, $numero) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function leerCabecera(string $tipoAnita, string $letra, int $sucursal, int $numero): ?array
    {
        $filas = self::listar(
            'venta',
            'ven_cliente,ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_fecha_vto,ven_monto,ven_gravado,ven_exento,ven_impuesto1,ven_percepcion_iva,ven_perc_ing_bruto,ven_cod_mon,ven_cotizacion,ven_nombre_cliente',
            " WHERE ven_tipo = '".addslashes($tipoAnita)."'"
            ." AND ven_letra = '".addslashes($letra)."'"
            ." AND ven_sucursal = ".$sucursal
            ." AND ven_nro >= ".max(1, $numero - 5)
            ." AND ven_nro <= ".($numero + 5),
        );

        foreach ($filas as $fila) {
            if ((int) ($fila['ven_nro'] ?? 0) === $numero) {
                return $fila;
            }
        }

        // Fallback: listar por sucursal/tipo/letra y filtrar (Informix a veces falla el rango).
        $filas = self::listar(
            'venta',
            'ven_cliente,ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_fecha_vto,ven_monto,ven_gravado,ven_exento,ven_impuesto1,ven_percepcion_iva,ven_perc_ing_bruto,ven_cod_mon,ven_cotizacion,ven_nombre_cliente',
            " WHERE ven_tipo = '".addslashes($tipoAnita)."'"
            ." AND ven_letra = '".addslashes($letra)."'"
            ." AND ven_sucursal = ".$sucursal,
        );
        foreach ($filas as $fila) {
            if ((int) ($fila['ven_nro'] ?? 0) === $numero) {
                return $fila;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function leerVenibr(string $tipoAnita, string $letra, int $sucursal, int $numero): array
    {
        return self::listar(
            'venibr',
            'veni_tipo,veni_letra,veni_sucursal,veni_nro,veni_provincia,veni_codigo_perc,veni_porcentaje,veni_importe',
            " WHERE veni_tipo = '".addslashes($tipoAnita)."'"
            ." AND veni_letra = '".addslashes($letra)."'"
            ." AND veni_sucursal = ".$sucursal
            ." AND veni_nro = ".$numero,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function armarItems(
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
        float $gravado,
        float $iva,
    ): array {
        $lineas = self::listar(
            'compaux',
            'compa_orden,compa_articulo,compa_cantidad,compa_precio,compa_desc',
            " WHERE compa_tipo = '".addslashes($tipoAnita)."'"
            ." AND compa_letra = '".addslashes($letra)."'"
            ." AND compa_sucursal = ".$sucursal
            ." AND compa_nro_fact = ".$numero,
        );

        if ($lineas === []) {
            return [[
                'sku' => 'GEN',
                'descripcion' => 'Conceptos facturados',
                'cantidad' => 1,
                'precio' => $gravado,
                'codigounidadmedida' => 1,
                'codigo_mtx' => '7790000000000',
                'unidades_mtx' => 1,
                'impuesto_id' => 0,
                'importe_iva' => $iva,
            ]];
        }

        $skus = [];
        foreach ($lineas as $l) {
            $sku = ltrim((string) ($l['compa_articulo'] ?? ''), '0');
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
        $articulos = $skus !== []
            ? Articulo::query()->whereIn('sku', array_unique($skus))->get()->keyBy('sku')
            : collect();

        $impuesto21 = Impuesto::query()->where('codigoarca', 5)->orderBy('id')->first();
        $impuestoId = (int) ($impuesto21?->id ?? 0);

        $items = [];
        $netoAcum = 0.0;
        foreach ($lineas as $l) {
            $qty = (float) ($l['compa_cantidad'] ?? 0);
            $precio = (float) ($l['compa_precio'] ?? 0);
            if ($qty <= 0 || $precio == 0.0) {
                continue;
            }
            $skuRaw = (string) ($l['compa_articulo'] ?? '');
            $sku = ltrim($skuRaw, '0');
            $sku = $sku === '' ? $skuRaw : $sku;
            $art = $articulos->get($sku);
            $neto = round($qty * $precio, 2);
            $netoAcum += $neto;
            $items[] = [
                'articulo_id' => (int) ($art?->id ?? 0),
                'sku' => $skuRaw !== '' ? $skuRaw : $sku,
                'descripcion' => (string) ($l['compa_desc'] ?? $art?->descripcion ?? 'Item'),
                'cantidad' => $qty,
                'precio' => $precio,
                'codigounidadmedida' => 1,
                'codigo_mtx' => trim((string) ($art?->codigobarra ?? $art?->nomenclador ?? '')),
                'unidades_mtx' => max(1, (int) ($art?->unidadreferenciacodigobarra ?? 1)),
                'impuesto_id' => $impuestoId > 0 ? $impuestoId : (int) ($art?->impuesto_id ?? 0),
            ];
        }

        if ($items === []) {
            return [[
                'sku' => 'GEN',
                'descripcion' => 'Conceptos facturados',
                'cantidad' => 1,
                'precio' => $gravado,
                'codigounidadmedida' => 1,
                'codigo_mtx' => '7790000000000',
                'unidades_mtx' => 1,
                'importe_iva' => $iva,
            ]];
        }

        // Ajuste de redondeo al gravado de cabecera + prorrateo IVA.
        $diff = round($gravado - $netoAcum, 2);
        if (abs($diff) >= 0.01 && isset($items[0]) && (float) $items[0]['cantidad'] > 0) {
            $items[0]['precio'] = round(
                ((float) $items[0]['precio'] * (float) $items[0]['cantidad'] + $diff) / (float) $items[0]['cantidad'],
                6,
            );
        }

        $netoFinal = 0.0;
        foreach ($items as $it) {
            $netoFinal += round((float) $it['cantidad'] * (float) $it['precio'], 2);
        }
        $ivaAsignado = 0.0;
        $last = count($items) - 1;
        foreach ($items as $i => &$it) {
            $netoLinea = round((float) $it['cantidad'] * (float) $it['precio'], 2);
            if ($i === $last) {
                $it['importe_iva'] = round($iva - $ivaAsignado, 2);
            } else {
                $share = $netoFinal > 0 ? $netoLinea / $netoFinal : 0;
                $it['importe_iva'] = round($iva * $share, 2);
                $ivaAsignado += $it['importe_iva'];
            }
            if (trim((string) ($it['codigo_mtx'] ?? '')) === '') {
                $it['codigo_mtx'] = '7790000000000';
                $it['unidades_mtx'] = 1;
            }
        }
        unset($it);

        return $items;
    }

    /**
     * @return list<array{t:int, c1:string}>
     */
    private static function datosAdicionalesFce(int $cbteTipo, int $empresaId): array
    {
        if (! ArcaCaeaAnitaTipoAfipSupport::esFce($cbteTipo)) {
            return [];
        }

        $cbu = trim((string) (
            config("arca.caea.fce.cbu_por_empresa.{$empresaId}")
            ?: config('arca.caea.fce.cbu_emisor', '')
        ));
        $opcion = trim((string) config('arca.caea.fce.opcion_transferencia', 'ADC'));
        if ($cbu === '') {
            throw new InvalidArgumentException(
                'FCE requiere CBU emisor: configure ARCA_FCE_CBU_EMPRESA_'.$empresaId.' o ARCA_FCE_CBU_EMISOR en .env.'
            );
        }

        return [
            ['t' => 21, 'c1' => $cbu],
            ['t' => 27, 'c1' => $opcion !== '' ? $opcion : 'ADC'],
        ];
    }

    private static function normalizarFechaYmd(string $fecha): string
    {
        $d = preg_replace('/\D+/', '', $fecha) ?? '';
        if (strlen($d) >= 8) {
            return substr($d, 0, 8);
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listar(string $tabla, string $campos, string $where): array
    {
        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'tabla' => $tabla,
            'sistema' => self::SISTEMA,
            'campos' => $campos,
            'whereArmado' => $where,
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new InvalidArgumentException("Error leyendo Anita {$tabla}: ".$parsed['error_lectura']);
        }

        $out = [];
        foreach ($parsed['filas'] as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }
}
