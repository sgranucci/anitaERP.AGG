<?php

declare(strict_types=1);

namespace App\Services\Ventas\Crown;

use App\ApiAnita;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Impuesto;
use App\Services\Ventas\VentaDeudaImportarDesdeAnitaService;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportBridgeReader;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportFormatoSupport;
use RuntimeException;
use Throwable;

/**
 * Crown: trae ventas Anita → ERP para reimpresión (sin stock / asiento / escritura Anita).
 *
 * Lecturas bulk: venta + vencae + vengrav/venibr/comprob/compaux si existen.
 * Si no hay renglones en compaux, arma una línea sintética con el total de cabecera.
 */
final class VentaAnitaCrownImportService
{
    public const LEYENDA = 'Importado Anita Crown (reimpresión)';

    /**
     * Tipos de comprobante de venta vistos en Crown 2026 (+ fiscales clásicos).
     *
     * @var list<string>
     */
    public const TIPOS = [
        'FAC', 'FSL', 'FME', 'CME', 'CSL', 'FCE', 'FOL', 'FA', 'FAH', 'FRE',
        'NCD', 'NCE', 'NCA', 'NCH', 'NCI',
        'NDA', 'NDH', 'NDI', 'NDV',
        'CRE', 'TIC', 'TF',
    ];

    public function __construct(
        private readonly VentaDeudaImportarDesdeAnitaService $ventaImport = new VentaDeudaImportarDesdeAnitaService,
        private readonly ClienteCuentacorrienteAnitaImportBridgeReader $reader = new ClienteCuentacorrienteAnitaImportBridgeReader,
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importar(
        string $desdeIso,
        string $hastaIso,
        bool $dryRun = true,
        int $usuarioId = 1,
        ?array $tipos = null,
    ): array {
        $tiposOk = array_values(array_unique(array_map(
            static fn (string $t): string => ClienteCuentacorrienteAnitaImportClaveSupport::tipo($t),
            $tipos ?? self::TIPOS
        )));

        $statsCabecera = $this->ventaImport->importarPorRangoFecha(
            $desdeIso,
            $hastaIso,
            $dryRun,
            $usuarioId,
            $tiposOk,
            self::LEYENDA,
            vencaeBulk: true,
            commitPorFila: true,
        );

        $detalle = $this->inspeccionarDetalleAnita($desdeIso, $hastaIso, $tiposOk);
        $stats = [
            'cabecera' => $statsCabecera,
            'detalle_anita' => $detalle,
            'emisiones' => [
                'a_crear_desde_compaux' => 0,
                'a_crear_sinteticas' => 0,
                'creadas_compaux' => 0,
                'creadas_sinteticas' => 0,
                'ya_tenian' => 0,
                'sin_venta_erp' => 0,
                'errores' => [],
            ],
            'impuestos_vengrav' => [
                'docs_con_vengrav' => $detalle['docs_vengrav'],
                'enriquecidas' => 0,
                'errores' => [],
            ],
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
        ];

        if ($dryRun) {
            $faltantesEstimados = (int) ($statsCabecera['a_crear'] ?? 0);
            $conCompaux = (int) $detalle['docs_compaux'];
            $stats['emisiones']['a_crear_desde_compaux'] = min($conCompaux, $faltantesEstimados);
            $stats['emisiones']['a_crear_sinteticas'] = max(0, $faltantesEstimados - $conCompaux);

            return $stats;
        }

        $stats['emisiones'] = $this->backfillEmisiones($desdeIso, $hastaIso, $detalle['compaux_por_clave']);
        $stats['impuestos_vengrav'] = $this->enriquecerImpuestosVengrav(
            $desdeIso,
            $hastaIso,
            $detalle['vengrav_por_clave']
        );
        $stats['modo'] = 'ejecutar';

        return $stats;
    }

    /**
     * @param  list<string>  $tiposOk
     * @return array{
     *   vencae:int,vengrav:int,venibr:int,comprob:int,compaux:int,
     *   docs_vencae:int,docs_vengrav:int,docs_venibr:int,docs_comprob:int,docs_compaux:int,
     *   errores_tablas:list<string>,
     *   vengrav_por_clave:array<string,list<array<string,mixed>>>,
     *   compaux_por_clave:array<string,list<array<string,mixed>>>
     * }
     */
    private function inspeccionarDetalleAnita(string $desdeIso, string $hastaIso, array $tiposOk): array
    {
        $desdeYmd = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso);
        $hastaYmd = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso);
        $tiposFlip = array_fill_keys($tiposOk, true);

        $out = [
            'vencae' => 0,
            'vengrav' => 0,
            'venibr' => 0,
            'comprob' => 0,
            'compaux' => 0,
            'docs_vencae' => 0,
            'docs_vengrav' => 0,
            'docs_venibr' => 0,
            'docs_comprob' => 0,
            'docs_compaux' => 0,
            'errores_tablas' => [],
            'vengrav_por_clave' => [],
            'compaux_por_clave' => [],
        ];

        // vencae: pocas filas; traer todo y filtrar por claves de venta del rango en dry/ejecutar vía cabecera.
        try {
            $filas = $this->listarTablaOpcional(
                'vencae',
                'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae,venc_fecha_vto',
                ' WHERE venc_nro > 0 '
            );
            $docs = [];
            foreach ($filas as $fila) {
                $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['venc_tipo'] ?? ''));
                if ($tipo === '' || ! isset($tiposFlip[$tipo])) {
                    continue;
                }
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $tipo,
                    (string) ($fila['venc_letra'] ?? ''),
                    (int) ($fila['venc_sucursal'] ?? 0),
                    (int) ($fila['venc_nro'] ?? 0),
                );
                $docs[$clave] = true;
                $out['vencae']++;
            }
            $out['docs_vencae'] = count($docs);
        } catch (Throwable $e) {
            $out['errores_tablas'][] = 'vencae: '.$e->getMessage();
        }

        try {
            $filas = $this->listarTablaOpcional(
                'vengrav',
                'veng_tipo,veng_letra,veng_sucursal,veng_nro,veng_codigo_tasa,veng_gravado,veng_impuesto,veng_tasa',
                ' WHERE veng_nro > 0 '
            );
            $docs = [];
            foreach ($filas as $fila) {
                $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['veng_tipo'] ?? ''));
                if ($tipo === '' || ! isset($tiposFlip[$tipo])) {
                    continue;
                }
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $tipo,
                    (string) ($fila['veng_letra'] ?? ''),
                    (int) ($fila['veng_sucursal'] ?? 0),
                    (int) ($fila['veng_nro'] ?? 0),
                );
                $out['vengrav_por_clave'][$clave][] = $fila;
                $docs[$clave] = true;
                $out['vengrav']++;
            }
            $out['docs_vengrav'] = count($docs);
        } catch (Throwable $e) {
            $out['errores_tablas'][] = 'vengrav: '.$e->getMessage();
        }

        try {
            $filas = $this->listarTablaOpcional(
                'venibr',
                'veni_tipo,veni_letra,veni_sucursal,veni_nro,veni_provincia,veni_importe,veni_porcentaje',
                ' WHERE veni_nro > 0 '
            );
            $docs = [];
            foreach ($filas as $fila) {
                $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['veni_tipo'] ?? ''));
                if ($tipo === '' || ! isset($tiposFlip[$tipo])) {
                    continue;
                }
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $tipo,
                    (string) ($fila['veni_letra'] ?? ''),
                    (int) ($fila['veni_sucursal'] ?? 0),
                    (int) ($fila['veni_nro'] ?? 0),
                );
                $docs[$clave] = true;
                $out['venibr']++;
            }
            $out['docs_venibr'] = count($docs);
        } catch (Throwable $e) {
            $out['errores_tablas'][] = 'venibr: '.$e->getMessage();
        }

        try {
            $filas = $this->listarTablaOpcional(
                'comprob',
                'comp_tipo,comp_letra,comp_sucursal,comp_nro_fact,comp_fecha,comp_total,comp_gravado,comp_iva,comp_exento,comp_leyenda',
                ' WHERE comp_fecha >= '.(int) $desdeYmd.' AND comp_fecha <= '.(int) $hastaYmd.' '
            );
            $docs = [];
            foreach ($filas as $fila) {
                $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['comp_tipo'] ?? ''));
                if ($tipo === '' || ! isset($tiposFlip[$tipo])) {
                    continue;
                }
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $tipo,
                    (string) ($fila['comp_letra'] ?? ''),
                    (int) ($fila['comp_sucursal'] ?? 0),
                    (int) ($fila['comp_nro_fact'] ?? 0),
                );
                $docs[$clave] = true;
                $out['comprob']++;
            }
            $out['docs_comprob'] = count($docs);
        } catch (Throwable $e) {
            $out['errores_tablas'][] = 'comprob: '.$e->getMessage();
        }

        try {
            $filas = $this->listarTablaOpcional(
                'compaux',
                'compa_tipo,compa_letra,compa_sucursal,compa_nro_fact,compa_orden,compa_articulo,compa_cantidad,compa_precio,compa_desc,compa_tipo_iva,compa_incl_imp,compa_dto,compa_fecha',
                ' WHERE compa_fecha >= '.(int) $desdeYmd.' AND compa_fecha <= '.(int) $hastaYmd.' '
            );
            $docs = [];
            foreach ($filas as $fila) {
                $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['compa_tipo'] ?? ''));
                if ($tipo === '' || ! isset($tiposFlip[$tipo])) {
                    continue;
                }
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $tipo,
                    (string) ($fila['compa_letra'] ?? ''),
                    (int) ($fila['compa_sucursal'] ?? 0),
                    (int) ($fila['compa_nro_fact'] ?? 0),
                );
                $out['compaux_por_clave'][$clave][] = $fila;
                $docs[$clave] = true;
                $out['compaux']++;
            }
            $out['docs_compaux'] = count($docs);
        } catch (Throwable $e) {
            $out['errores_tablas'][] = 'compaux: '.$e->getMessage();
        }

        return $out;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $compauxPorClave
     * @return array<string, mixed>
     */
    private function backfillEmisiones(string $desdeIso, string $hastaIso, array $compauxPorClave): array
    {
        $stats = [
            'a_crear_desde_compaux' => 0,
            'a_crear_sinteticas' => 0,
            'creadas_compaux' => 0,
            'creadas_sinteticas' => 0,
            'ya_tenian' => 0,
            'sin_venta_erp' => 0,
            'errores' => [],
        ];

        $ventas = Venta::query()
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->where('leyenda', self::LEYENDA)
            ->orderBy('id')
            ->get(['id', 'codigo', 'total', 'nombre']);

        $conEmision = Venta_Emision::query()
            ->whereIn('venta_id', $ventas->pluck('id'))
            ->distinct()
            ->pluck('venta_id')
            ->all();
        $conEmisionFlip = array_fill_keys(array_map('intval', $conEmision), true);

        foreach ($ventas as $venta) {
            $ventaId = (int) $venta->id;
            if (isset($conEmisionFlip[$ventaId])) {
                $stats['ya_tenian']++;

                continue;
            }
            $partes = $this->parseCodigoVenta((string) $venta->codigo);
            if ($partes === null) {
                $stats['errores'][] = 'Código inválido venta '.$ventaId.' '.$venta->codigo;
                $stats['sin_venta_erp']++;

                continue;
            }
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                $partes['tipo'],
                $partes['letra'],
                $partes['sucursal'],
                $partes['numero'],
            );

            try {
                $lineas = $compauxPorClave[$clave] ?? [];
                if ($lineas !== []) {
                    $this->persistirEmisionesDesdeCompaux($ventaId, $lineas);
                    $stats['creadas_compaux']++;
                } else {
                    $this->persistirEmisionSintetica(
                        $ventaId,
                        (string) ($venta->codigo ?? ''),
                        (string) ($venta->nombre ?? 'Consumidor final'),
                        abs((float) $venta->total)
                    );
                    $stats['creadas_sinteticas']++;
                }
            } catch (Throwable $e) {
                $stats['errores'][] = $venta->codigo.': '.$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $vengravPorClave
     * @return array<string, mixed>
     */
    private function enriquecerImpuestosVengrav(string $desdeIso, string $hastaIso, array $vengravPorClave): array
    {
        $stats = [
            'docs_con_vengrav' => count($vengravPorClave),
            'enriquecidas' => 0,
            'errores' => [],
        ];
        if ($vengravPorClave === []) {
            return $stats;
        }

        $ventas = Venta::query()
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->where('leyenda', self::LEYENDA)
            ->orderBy('id')
            ->get(['id', 'codigo', 'total']);

        foreach ($ventas as $venta) {
            $partes = $this->parseCodigoVenta((string) $venta->codigo);
            if ($partes === null) {
                continue;
            }
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                $partes['tipo'],
                $partes['letra'],
                $partes['sucursal'],
                $partes['numero'],
            );
            $filas = $vengravPorClave[$clave] ?? [];
            if ($filas === []) {
                continue;
            }
            try {
                $this->reemplazarImpuestosDesdeVengrav((int) $venta->id, $filas, abs((float) $venta->total));
                $stats['enriquecidas']++;
            } catch (Throwable $e) {
                $stats['errores'][] = $venta->codigo.': '.$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function persistirEmisionesDesdeCompaux(int $ventaId, array $lineas): void
    {
        usort($lineas, static function (array $a, array $b): int {
            return ((int) ($a['compa_orden'] ?? 0)) <=> ((int) ($b['compa_orden'] ?? 0));
        });

        $nro = 0;
        foreach ($lineas as $linea) {
            $nro++;
            $sku = trim((string) ($linea['compa_articulo'] ?? ''));
            $desc = trim((string) ($linea['compa_desc'] ?? ''));
            if ($desc === '') {
                $desc = $sku !== '' && strtolower($sku) !== 'texto' ? $sku : 'Ítem';
            }
            $impuestoId = (int) ($linea['compa_tipo_iva'] ?? 3);
            if ($impuestoId <= 0) {
                $impuestoId = 3;
            }
            Venta_Emision::query()->create([
                'venta_id' => $ventaId,
                'numeroitem' => $nro,
                'articulo_id' => null,
                'detalle' => $desc,
                'cantidad' => (float) ($linea['compa_cantidad'] ?? 1),
                'precio' => (float) ($linea['compa_precio'] ?? 0),
                'impuesto_id' => $impuestoId,
                'incluyeimpuesto' => strtoupper(trim((string) ($linea['compa_incl_imp'] ?? 'S'))) === 'S' ? '1' : '0',
                'moneda_id' => 1,
                'descuento' => (float) ($linea['compa_dto'] ?? 0),
                'descuentointegrado' => '',
            ]);
        }
    }

    private function persistirEmisionSintetica(int $ventaId, string $codigo, string $nombre, float $totalAbs): void
    {
        $detalle = trim($codigo.' — '.$nombre);
        if ($detalle === '—') {
            $detalle = 'Venta importada Anita';
        }
        Venta_Emision::query()->create([
            'venta_id' => $ventaId,
            'numeroitem' => 1,
            'articulo_id' => null,
            'detalle' => mb_substr($detalle, 0, 240),
            'cantidad' => 1,
            'precio' => $totalAbs,
            'impuesto_id' => 3,
            'incluyeimpuesto' => '1',
            'moneda_id' => 1,
            'descuento' => 0,
            'descuentointegrado' => '',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function reemplazarImpuestosDesdeVengrav(int $ventaId, array $filas, float $totalAbs): void
    {
        Venta_Impuesto::query()->where('venta_id', $ventaId)->delete();

        $gravado = 0.0;
        $iva = 0.0;
        $conceptos = [];
        foreach ($filas as $vg) {
            $base = abs((float) ($vg['veng_gravado'] ?? 0));
            $importe = abs((float) ($vg['veng_impuesto'] ?? 0));
            $tasa = (float) ($vg['veng_tasa'] ?? 0);
            $gravado += $base;
            $iva += $importe;
            if ($importe < 0.0001 && $base < 0.0001) {
                continue;
            }
            $conceptos[] = [
                'concepto' => $tasa > 0 ? ('Iva '.$tasa.'%') : 'Total Iva',
                'baseimponible' => $base,
                'tasa' => $tasa > 0 ? $tasa : 21.0,
                'importe' => $importe,
                'impuesto_id' => $this->impuestoIdPorTasa($tasa),
            ];
        }

        Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => 'Subtotal',
            'baseimponible' => 0,
            'tasa' => 0,
            'importe' => round($gravado, 2),
            'impuesto_id' => null,
        ]);
        if ($gravado > 0.0001) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Gravado',
                'baseimponible' => 0,
                'tasa' => 21,
                'importe' => round($gravado, 2),
                'impuesto_id' => 3,
            ]);
        }
        foreach ($conceptos as $c) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => $c['concepto'],
                'baseimponible' => $c['baseimponible'],
                'tasa' => $c['tasa'],
                'importe' => $c['importe'],
                'impuesto_id' => $c['impuesto_id'],
            ]);
        }
        Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => 'Total',
            'baseimponible' => 0,
            'tasa' => 0,
            'importe' => round($totalAbs > 0 ? $totalAbs : ($gravado + $iva), 2),
            'impuesto_id' => null,
        ]);
    }

    private function impuestoIdPorTasa(float $tasa): int
    {
        return match (true) {
            abs($tasa - 10.5) < 0.01 => 2,
            abs($tasa - 27.0) < 0.01 => 4,
            abs($tasa) < 0.01 => 1,
            default => 3,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarTablaOpcional(string $tabla, string $campos, string $where): array
    {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $raw = (string) $this->api->apiCall([
            'acc' => 'list',
            'sistema' => $perfil['sistema'] ?? 'ventas',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => '1',
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            // Tabla inexistente / columna distinta: no corta el import de cabeceras.
            if (preg_match('/not found|no such|no existe|does not exist|unknown/i', $err)) {
                return [];
            }
            throw new RuntimeException($tabla.': '.$err);
        }
        $filas = ApiAnita::decodificarListaFilas($raw);
        $out = [];
        foreach ($filas as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    /**
     * @return array{tipo:string,letra:string,sucursal:int,numero:int}|null
     */
    private function parseCodigoVenta(string $codigo): ?array
    {
        if (! preg_match('/^([A-Z]+)\s+([A-Z])-(\d+)-(\d+)$/', trim($codigo), $m)) {
            return null;
        }

        return [
            'tipo' => $m[1],
            'letra' => $m[2],
            'sucursal' => (int) $m[3],
            'numero' => (int) $m[4],
        ];
    }
}
