<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Contable\Centrocosto;
use App\Services\Compras\ComprobanteService;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use RuntimeException;

/**
 * Factura prorrateada multi-CC: 2ª letra P + 3ª = tipo ítem (FPB/FPS/FPL/FPU…).
 *
 * Se activa cuando la OC tiene ≥2 centros destino que resuelven a ≥2 abreviaturas finas.
 * El tipo P no tiene plantilla fija: los conceptos G/I se unen desde los finos origen.
 */
final class PrecargaProveedorProrrateoMultiCcSupport
{
    public function __construct(
        private ComprobanteService $comprobanteService,
    ) {}

    public static function esTipoProrrateado(string $abreviatura): bool
    {
        $abrev = strtoupper(trim($abreviatura));
        if (strlen($abrev) < 3) {
            return false;
        }

        return $abrev[1] === 'P'
            && in_array($abrev[0], ['F', 'C', 'D'], true)
            && in_array($abrev[2], ['B', 'S', 'L', 'U'], true);
    }

    /**
     * @param  'FC'|'ND'|'NC'|string  $tipoComprobante
     * @param  list<array{codigo: string, tipoiva: string, peso: float}>  $centrosConPeso
     * @return array{
     *   activo: bool,
     *   tipocomprobante: string,
     *   tipos_origen: list<string>,
     *   centros: list<string>,
     *   pesos_por_fino: array<string, float>,
     *   conceptos: list<array<string, mixed>>,
     *   concepto_ids: list<int>
     * }
     */
    public function resolverDesdeCentros(
        string $tipoComprobante,
        string $tipoItem,
        array $centrosConPeso,
    ): array {
        $tipoComprobante = strtoupper(trim($tipoComprobante));
        $tipoItem = strtoupper(trim($tipoItem) ?: 'B');
        if (! in_array($tipoItem, ['B', 'S', 'L', 'U'], true)) {
            $tipoItem = 'B';
        }

        $pesosPorFino = [];
        $centros = [];
        foreach ($centrosConPeso as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            if ($codigo === '' || $codigo === '0') {
                continue;
            }
            $centros[$codigo] = true;
            $tipoIva = (string) ($fila['tipoiva'] ?? '');
            $fino = PrecargaProveedorAbreviaturaTipoSupport::abreviatura(
                $tipoComprobante,
                $codigo,
                $tipoIva,
                $tipoItem,
            );
            if ($fino === '' || self::esTipoProrrateado($fino)) {
                continue;
            }
            $peso = max(0.0, (float) ($fila['peso'] ?? 0));
            $pesosPorFino[$fino] = ($pesosPorFino[$fino] ?? 0.0) + $peso;
        }

        $tiposOrigen = array_keys($pesosPorFino);
        sort($tiposOrigen);

        $inicial = match ($tipoComprobante) {
            'FC' => 'F',
            'ND' => 'D',
            'NC' => 'C',
            default => '',
        };
        $abrevP = $inicial !== '' ? $inicial.'P'.$tipoItem : '';

        $activo = count($tiposOrigen) >= 2 && $abrevP !== '';
        if (! $activo) {
            return [
                'activo' => false,
                'tipocomprobante' => '',
                'tipos_origen' => $tiposOrigen,
                'centros' => array_keys($centros),
                'pesos_por_fino' => $pesosPorFino,
                'conceptos' => [],
                'concepto_ids' => [],
            ];
        }

        $totalPeso = array_sum($pesosPorFino);
        if ($totalPeso <= 0) {
            $igual = 1.0 / count($pesosPorFino);
            foreach ($pesosPorFino as $fino => $_) {
                $pesosPorFino[$fino] = $igual;
            }
            $totalPeso = 1.0;
        }

        $seenCodigo = [];
        $conceptoIds = [];
        foreach ($tiposOrigen as $fino) {
            $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($fino);
            if (! $comprobante) {
                continue;
            }
            $pesoRel = round(($pesosPorFino[$fino] ?? 0) / $totalPeso, 6);
            foreach ($comprobante->tipotransaccion_compra_concepto_ivacompras ?? [] as $linea) {
                $concepto = $linea->concepto_ivacompras;
                if (! $concepto) {
                    continue;
                }
                $tipoConc = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
                $codigo = (int) $concepto->codigo;
                $id = (int) $concepto->id;
                $conceptoIds[$id] = true;
                if (isset($seenCodigo[$codigo])) {
                    if (! in_array($fino, $seenCodigo[$codigo]['finos_origen'], true)) {
                        $seenCodigo[$codigo]['finos_origen'][] = $fino;
                    }
                    if ($tipoConc === 'I') {
                        $seenCodigo[$codigo]['peso'] = round(
                            (float) ($seenCodigo[$codigo]['peso'] ?? 0) + $pesoRel,
                            6
                        );
                    }
                    continue;
                }
                $concepto->loadMissing('impuestos');
                $seenCodigo[$codigo] = [
                    'id_concepto' => $codigo,
                    'concepto_ivacompra_id' => $id,
                    'nombre' => (string) $concepto->nombre,
                    'descripcion_ai' => (string) ($concepto->nombre_ia ?: $concepto->nombre),
                    'tipoconcepto' => $tipoConc,
                    'alicuota_iva' => $this->inferirAlicuota($concepto),
                    'fino_origen' => $fino,
                    'finos_origen' => [$fino],
                    // Sólo el IVA se reparte entre finos; los gravados comparten código.
                    'peso' => $tipoConc === 'I' ? $pesoRel : null,
                ];
            }
        }

        return [
            'activo' => true,
            'tipocomprobante' => $abrevP,
            'tipos_origen' => $tiposOrigen,
            'centros' => array_keys($centros),
            'pesos_por_fino' => $pesosPorFino,
            'conceptos' => array_values($seenCodigo),
            'concepto_ids' => array_map('intval', array_keys($conceptoIds)),
        ];
    }

    /**
     * IDs de concepto permitidos para un tipo P (unión de orígenes) o plantilla normal.
     *
     * @return list<int>
     */
    public function idsPermitidosParaTipo(Tipotransaccion_Compra $tipo, ?string $numeroOc = null): array
    {
        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (! self::esTipoProrrateado($abrev)) {
            return ComprobanteProveedorConceptosIvaCoherenciaSupport::idsPermitidosDesdeTipoTransaccion($tipo);
        }

        if ($numeroOc === null || trim($numeroOc) === '') {
            // Sin OC no se puede reconstruir la unión; whitelist vacía = fallback genérico en coherencia.
            return [];
        }

        $tipoComprobante = match ($abrev[0] ?? '') {
            'F' => 'FC',
            'C' => 'NC',
            'D' => 'ND',
            default => '',
        };
        if ($tipoComprobante === '') {
            return [];
        }

        $tipoItem = strtoupper(substr($abrev, 2, 1) ?: 'B');
        if (! in_array($tipoItem, ['B', 'S', 'L', 'U'], true)) {
            $tipoItem = 'B';
        }

        $centrosConPeso = self::centrosConPesoParaOc((string) $numeroOc, []);
        if ($centrosConPeso === []) {
            return [];
        }

        return $this->resolverDesdeCentros($tipoComprobante, $tipoItem, $centrosConPeso)['concepto_ids'];
    }

    /**
     * @param  list<array{codigo: string, tipoiva: string, peso: float}>  $centrosConPeso
     * @return list<int>
     */
    public function idsPermitidosDesdeCentros(string $tipoComprobante, string $tipoItem, array $centrosConPeso): array
    {
        $res = $this->resolverDesdeCentros($tipoComprobante, $tipoItem, $centrosConPeso);

        return $res['concepto_ids'];
    }

    /**
     * Redistribuye montos I (y deja G consolidados) según pesos por fino origen.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<string, float>  $pesosPorFino
     * @param  list<string>  $tiposOrigen
     * @return list<array<string, mixed>>
     */
    public function prorratearLineasIva(
        array $lineas,
        array $pesosPorFino,
        array $tiposOrigen,
    ): array {
        if ($lineas === [] || count($tiposOrigen) < 2) {
            return $lineas;
        }

        $totalPeso = array_sum($pesosPorFino);
        if ($totalPeso <= 0) {
            return $lineas;
        }

        $conceptos = Concepto_Ivacompra::query()
            ->with('impuestos')
            ->whereIn('id', array_values(array_unique(array_map(
                static fn (array $l): int => (int) ($l['concepto_ivacompra_id'] ?? 0),
                $lineas
            ))))
            ->get()
            ->keyBy('id');

        $ivaPorTasa = [];
        $otras = [];
        // Los gravados no se reparten entre finos (comparten códigos), pero sí se consolidan
        // por concepto: mezclar 21% con 10,5% en una sola línea falsearía la apertura.
        $gravadoPorConcepto = [];

        foreach ($lineas as $linea) {
            $id = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $monto = round((float) ($linea['monto'] ?? 0), 2);
            $c = $conceptos->get($id);
            $tipoConc = strtoupper(trim((string) ($c?->tipoconcepto ?? '')));
            if ($tipoConc === 'I') {
                $tasa = $this->inferirAlicuota($c) ?? 21.0;
                $key = $this->claveTasa($tasa);
                $ivaPorTasa[$key] = round(($ivaPorTasa[$key] ?? 0) + $monto, 2);
                continue;
            }
            if ($tipoConc === 'G') {
                if (! isset($gravadoPorConcepto[$id])) {
                    $gravadoPorConcepto[$id] = $linea;
                    $gravadoPorConcepto[$id]['monto'] = 0.0;
                }
                $gravadoPorConcepto[$id]['monto'] = round($gravadoPorConcepto[$id]['monto'] + $monto, 2);
                continue;
            }
            $otras[] = $linea;
        }

        // Mapa fino → conceptos I por tasa (desde plantillas origen).
        $iPorFinoYTasa = [];
        foreach ($tiposOrigen as $fino) {
            $comp = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($fino);
            if (! $comp) {
                continue;
            }
            foreach ($comp->tipotransaccion_compra_concepto_ivacompras ?? [] as $lin) {
                $c = $lin->concepto_ivacompras;
                if (! $c || strtoupper(trim((string) ($c->tipoconcepto ?? ''))) !== 'I') {
                    continue;
                }
                $c->loadMissing('impuestos');
                $tasa = $this->inferirAlicuota($c) ?? 21.0;
                $key = $this->claveTasa($tasa);
                if (! isset($iPorFinoYTasa[$fino][$key])) {
                    $iPorFinoYTasa[$fino][$key] = [
                        'concepto_ivacompra_id' => (int) $c->id,
                        'codigo_concepto_anita' => (int) $c->codigo,
                    ];
                }
            }
        }

        $out = [];
        foreach ($gravadoPorConcepto as $lineaGravado) {
            if (abs((float) $lineaGravado['monto']) < 0.0001) {
                continue;
            }
            $out[] = $lineaGravado;
        }

        foreach ($ivaPorTasa as $claveTasa => $importeIva) {
            if (abs($importeIva) < 0.0001) {
                continue;
            }
            $finosConConcepto = [];
            foreach ($tiposOrigen as $fino) {
                if (isset($iPorFinoYTasa[$fino][$claveTasa])) {
                    $finosConConcepto[] = $fino;
                }
            }
            if ($finosConConcepto === []) {
                // Sin concepto I para esa tasa en orígenes: conservar líneas originales de esa tasa.
                foreach ($lineas as $linea) {
                    $id = (int) ($linea['concepto_ivacompra_id'] ?? 0);
                    $c = $conceptos->get($id);
                    if (strtoupper(trim((string) ($c?->tipoconcepto ?? ''))) !== 'I') {
                        continue;
                    }
                    $tasa = $this->inferirAlicuota($c) ?? 21.0;
                    if ($this->claveTasa($tasa) === $claveTasa) {
                        $out[] = $linea;
                    }
                }
                continue;
            }

            $pesoGrupo = 0.0;
            foreach ($finosConConcepto as $fino) {
                $pesoGrupo += (float) ($pesosPorFino[$fino] ?? 0);
            }
            if ($pesoGrupo <= 0) {
                $pesoGrupo = count($finosConConcepto);
                $pesosLocales = array_fill_keys($finosConConcepto, 1.0);
            } else {
                $pesosLocales = $pesosPorFino;
            }

            $asignado = 0.0;
            $ultimo = count($finosConConcepto) - 1;
            foreach ($finosConConcepto as $i => $fino) {
                $meta = $iPorFinoYTasa[$fino][$claveTasa];
                if ($i === $ultimo) {
                    $parte = round($importeIva - $asignado, 2);
                } else {
                    $parte = round($importeIva * (($pesosLocales[$fino] ?? 0) / $pesoGrupo), 2);
                    $asignado += $parte;
                }
                if (abs($parte) < 0.0001) {
                    continue;
                }
                $out[] = [
                    'concepto_ivacompra_id' => $meta['concepto_ivacompra_id'],
                    'codigo_concepto_anita' => $meta['codigo_concepto_anita'],
                    'monto' => $parte,
                ];
            }
        }

        return array_merge($out, $otras);
    }

    /**
     * Completa G/I faltantes en un tipo P sólo cuando los conceptos del agente no explican
     * el total. Nunca descarta líneas ni reclasifica exentos que ya cuadran: una compra
     * exenta o no gravada es un caso legítimo y se graba tal como vino.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<string>  $tiposOrigen
     * @return array{lineas: list<array<string, mixed>>, avisos: list<string>, reparo: bool}
     */
    public function repararAperturaSiFaltaGi(
        array $lineas,
        array $tiposOrigen,
        float $subtotal,
        float $total,
    ): array {
        $sinReparo = ['lineas' => $lineas, 'avisos' => [], 'reparo' => false];
        $subtotal = round(abs($subtotal), 2);
        $total = round(abs($total), 2);
        if ($tiposOrigen === [] || $lineas === []) {
            return $sinReparo;
        }

        $tolerancia = ComprobanteProveedorConceptosIvaCoherenciaSupport::TOLERANCIA;

        $conceptos = Concepto_Ivacompra::query()
            ->with('impuestos')
            ->whereIn('id', array_values(array_filter(array_map(
                static fn (array $l): int => (int) ($l['concepto_ivacompra_id'] ?? 0),
                $lineas
            ))))
            ->get()
            ->keyBy('id');

        $sumaI = 0.0;
        $sumaG = 0.0;
        $sumaExenta = 0.0;
        $sumaLineas = 0.0;
        $indicesExentos = [];

        foreach ($lineas as $indice => $linea) {
            $id = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $monto = round((float) ($linea['monto'] ?? 0), 2);
            $sumaLineas = round($sumaLineas + $monto, 2);
            $tipo = strtoupper(trim((string) ($conceptos->get($id)?->tipoconcepto ?? '')));
            if ($tipo === 'I') {
                $sumaI = round($sumaI + $monto, 2);
            } elseif ($tipo === 'G') {
                $sumaG = round($sumaG + $monto, 2);
            } elseif (in_array($tipo, ['E', 'N'], true)) {
                $sumaExenta = round($sumaExenta + $monto, 2);
                $indicesExentos[] = $indice;
            }
        }

        // Los conceptos del agente ya explican el total (típico de facturas exentas o sin
        // IVA discriminado): se graban tal cual, no hay nada que reconstruir.
        if ($total > 0 && abs(abs($sumaLineas) - $total) <= $tolerancia) {
            return $sinReparo;
        }

        // Ya hay IVA: el prorrateo de I se ocupa del reparto entre finos.
        if ($sumaI > $tolerancia) {
            return $sinReparo;
        }

        $faltante = round($total - $sumaLineas, 2);
        if ($total <= 0 || $faltante <= $tolerancia) {
            return $sinReparo;
        }

        $semillaG = $this->conceptoSemillaDesdeOrigenes($tiposOrigen, 'G', 21.0);
        $semillaI = $this->conceptoSemillaDesdeOrigenes($tiposOrigen, 'I', 21.0);
        if ($semillaG === null) {
            return $sinReparo;
        }

        $avisos = [];
        $reparo = false;
        $nuevas = $lineas;
        $netoParaIva = $sumaG;

        if ($sumaG <= $tolerancia
            && $sumaExenta > $tolerancia
            && $indicesExentos !== []
            && ($tasaExenta = $this->tasaQueExplica($sumaExenta, $faltante, $tolerancia)) !== null
        ) {
            // La IA imputó el neto como exento/no gravado, pero la diferencia de cabecera es
            // exactamente el IVA de ese neto: era gravado.
            foreach ($indicesExentos as $indice) {
                $nuevas[$indice]['concepto_ivacompra_id'] = $semillaG['concepto_ivacompra_id'];
                $nuevas[$indice]['codigo_concepto_anita'] = $semillaG['codigo_concepto_anita'];
            }
            $netoParaIva = $sumaExenta;
            $reparo = true;
            $avisos[] = 'Prorrateado: el neto vino como exento/no gravado pero la diferencia de cabecera '
                .'equivale al IVA '.$this->claveTasa($tasaExenta).'%; se reclasificó a gravado '
                .$semillaG['codigo_concepto_anita'].'.';
        } elseif ($sumaG <= $tolerancia
            && $sumaExenta <= $tolerancia
            && $subtotal > $tolerancia
            && abs($faltante - $subtotal) <= $tolerancia
        ) {
            // El agente sólo mandó percepciones: falta exactamente el neto de cabecera.
            $nuevas[] = [
                'concepto_ivacompra_id' => $semillaG['concepto_ivacompra_id'],
                'codigo_concepto_anita' => $semillaG['codigo_concepto_anita'],
                'monto' => $subtotal,
            ];
            $netoParaIva = $subtotal;
            $reparo = true;
            $avisos[] = 'Prorrateado: sin gravado en conceptos; se tomó el subtotal de cabecera como gravado '
                .$semillaG['codigo_concepto_anita'].'.';
        }

        $faltanteIva = round($total - $this->sumaLineas($nuevas), 2);
        if ($faltanteIva > $tolerancia) {
            $tasaIva = $netoParaIva > $tolerancia
                ? $this->tasaQueExplica($netoParaIva, $faltanteIva, $tolerancia)
                : null;
            if ($tasaIva !== null && $semillaI !== null) {
                $nuevas[] = [
                    'concepto_ivacompra_id' => $semillaI['concepto_ivacompra_id'],
                    'codigo_concepto_anita' => $semillaI['codigo_concepto_anita'],
                    'monto' => $faltanteIva,
                ];
                $reparo = true;
                $avisos[] = 'Prorrateado: sin IVA en conceptos; se dedujo IVA '.$this->claveTasa($tasaIva)
                    .'% de cabecera = '.number_format($faltanteIva, 2, ',', '.').'.';
            } else {
                $avisos[] = 'Prorrateado: faltan '.number_format($faltanteIva, 2, ',', '.')
                    .' para llegar al total y no se corresponden con una alícuota de IVA del neto. '
                    .'Revisar apertura manual.';
            }
        }

        return ['lineas' => $nuevas, 'avisos' => $avisos, 'reparo' => $reparo];
    }

    /**
     * Alícuota (21/10,5/27/5) cuyo IVA sobre $neto explica $importe, o null.
     */
    private function tasaQueExplica(float $neto, float $importe, float $tolerancia): ?float
    {
        if ($neto <= 0 || $importe <= 0) {
            return null;
        }

        foreach ([21.0, 10.5, 27.0, 5.0, 2.5] as $tasa) {
            if (abs(round($neto * $tasa / 100, 2) - $importe) <= $tolerancia) {
                return $tasa;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sumaLineas(array $lineas): float
    {
        $suma = 0.0;
        foreach ($lineas as $linea) {
            $suma = round($suma + (float) ($linea['monto'] ?? 0), 2);
        }

        return $suma;
    }

    /**
     * @param  list<string>  $tiposOrigen
     * @return array{concepto_ivacompra_id: int, codigo_concepto_anita: int}|null
     */
    private function conceptoSemillaDesdeOrigenes(array $tiposOrigen, string $tipoconcepto, float $tasaPreferida): ?array
    {
        $tipoconcepto = strtoupper($tipoconcepto);
        $candidatos = [];
        foreach ($tiposOrigen as $fino) {
            $comp = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($fino);
            if (! $comp) {
                continue;
            }
            foreach ($comp->tipotransaccion_compra_concepto_ivacompras ?? [] as $lin) {
                $c = $lin->concepto_ivacompras;
                if (! $c || strtoupper(trim((string) ($c->tipoconcepto ?? ''))) !== $tipoconcepto) {
                    continue;
                }
                $c->loadMissing('impuestos');
                $tasa = $this->inferirAlicuota($c);
                $candidatos[] = [
                    'concepto_ivacompra_id' => (int) $c->id,
                    'codigo_concepto_anita' => (int) $c->codigo,
                    'tasa' => $tasa,
                    'codigo' => (int) $c->codigo,
                ];
            }
        }
        if ($candidatos === []) {
            return null;
        }

        // Preferir alícuota cercana a la pedida; para G priorizar código 50 (bienes 21).
        usort($candidatos, static function (array $a, array $b) use ($tasaPreferida, $tipoconcepto): int {
            if ($tipoconcepto === 'G') {
                if ($a['codigo'] === 50 && $b['codigo'] !== 50) {
                    return -1;
                }
                if ($b['codigo'] === 50 && $a['codigo'] !== 50) {
                    return 1;
                }
            }
            $da = abs(($a['tasa'] ?? $tasaPreferida) - $tasaPreferida);
            $db = abs(($b['tasa'] ?? $tasaPreferida) - $tasaPreferida);

            return $da <=> $db;
        });

        return [
            'concepto_ivacompra_id' => $candidatos[0]['concepto_ivacompra_id'],
            'codigo_concepto_anita' => $candidatos[0]['codigo_concepto_anita'],
        ];
    }

    private function inferirAlicuota(?object $concepto): ?float
    {
        if ($concepto === null) {
            return null;
        }
        if ($concepto->impuestos && isset($concepto->impuestos->valor)) {
            return (float) $concepto->impuestos->valor;
        }
        $texto = strtolower((string) ($concepto->nombre_ia ?? '').' '.($concepto->nombre ?? ''));
        if (preg_match('/\b(21|10[,.]5|27|5)\s*%/', $texto, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function claveTasa(float $tasa): string
    {
        return rtrim(rtrim(number_format($tasa, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * @param  iterable<int, object>  $itemsOrdenCompra
     * @return list<array{codigo: string, tipoiva: string, peso: float}>
     */
    public static function centrosConPesoDesdeItemsAnita(iterable $itemsOrdenCompra): array
    {
        $agrupado = [];
        foreach ($itemsOrdenCompra as $item) {
            $codigo = PrecargaProveedorCentrocostoDestinoSupport::normalizarCodigoPublico(
                $item->penvp_ccosto_dest ?? null
            ) ?: PrecargaProveedorCentrocostoDestinoSupport::normalizarCodigoPublico(
                $item->penvp_ccosto ?? null
            );
            if ($codigo === '') {
                continue;
            }
            $cant = (float) ($item->penvp_cantidad ?? 0);
            $precio = (float) ($item->penvp_precio ?? 0);
            $peso = max(0.0, $cant * $precio);
            if (! isset($agrupado[$codigo])) {
                $cc = Centrocosto::query()->where('codigo', $codigo)->first();
                $agrupado[$codigo] = [
                    'codigo' => $codigo,
                    'tipoiva' => (string) ($cc->tipoiva ?? ''),
                    'peso' => 0.0,
                ];
            }
            $agrupado[$codigo]['peso'] += $peso > 0 ? $peso : 1.0;
        }

        return array_values($agrupado);
    }

    /**
     * Preferido: CC destino reales de la OC ERP (Anita a menudo deja penvp_ccosto_dest vacío
     * y copia el origen en todas las líneas).
     *
     * @return list<array{codigo: string, tipoiva: string, peso: float}>
     */
    public static function centrosConPesoDesdeOrdencompraErp(Ordencompra $oc): array
    {
        $oc->loadMissing([
            'ordencompra_articulos.centrocostos_destino:id,codigo,tipoiva',
            'centrocostos:id,codigo,tipoiva',
        ]);

        $agrupado = [];
        foreach ($oc->ordencompra_articulos ?? [] as $linea) {
            $cc = $linea->centrocostos_destino;
            $codigo = PrecargaProveedorCentrocostoDestinoSupport::normalizarCodigoPublico($cc->codigo ?? null);
            if ($codigo === '') {
                continue;
            }
            $cant = (float) ($linea->cantidad ?? 0);
            $precio = (float) ($linea->precio ?? 0);
            $peso = max(0.0, $cant * $precio);
            if (! isset($agrupado[$codigo])) {
                $agrupado[$codigo] = [
                    'codigo' => $codigo,
                    'tipoiva' => (string) ($cc->tipoiva ?? ''),
                    'peso' => 0.0,
                ];
            }
            $agrupado[$codigo]['peso'] += $peso > 0 ? $peso : 1.0;
        }

        if ($agrupado === []) {
            $header = $oc->centrocostos;
            $codigo = PrecargaProveedorCentrocostoDestinoSupport::normalizarCodigoPublico($header->codigo ?? null);
            if ($codigo !== '') {
                $agrupado[$codigo] = [
                    'codigo' => $codigo,
                    'tipoiva' => (string) ($header->tipoiva ?? ''),
                    'peso' => 1.0,
                ];
            }
        }

        return array_values($agrupado);
    }

    /**
     * ERP si tiene destinos de línea; si no, Anita.
     *
     * @param  iterable<int, object>  $itemsOrdenCompraAnita
     * @return list<array{codigo: string, tipoiva: string, peso: float}>
     */
    public static function centrosConPesoParaOc(
        string $numeroOc,
        iterable $itemsOrdenCompraAnita = [],
    ): array {
        $numero = trim($numeroOc);
        if ($numero !== '') {
            try {
                $oc = Ordencompra::query()
                    ->where('numeroordencompra', $numero)
                    ->orWhere('numeroordencompra', ltrim($numero, '0'))
                    ->first();
                if ($oc) {
                    $desdeErp = self::centrosConPesoDesdeOrdencompraErp($oc);
                    // Si ERP trae ≥2 CC, o al menos 1 distinto al único Anita, preferir ERP.
                    if (count($desdeErp) >= 2) {
                        return $desdeErp;
                    }
                    if (count($desdeErp) === 1) {
                        $anita = self::centrosConPesoDesdeItemsAnita($itemsOrdenCompraAnita);
                        if ($anita === [] || count($anita) <= 1) {
                            return $desdeErp;
                        }
                    }
                }
            } catch (\Throwable) {
                // fallback Anita
            }
        }

        return self::centrosConPesoDesdeItemsAnita($itemsOrdenCompraAnita);
    }

    /**
     * Familia AFIP genérica desde abreviatura fina o P.
     */
    public static function familiaDesdeAbreviatura(string $abreviatura): string
    {
        return match (strtoupper(substr(trim($abreviatura), 0, 1))) {
            'C' => 'NC',
            'D' => 'ND',
            default => 'FC',
        };
    }

    /**
     * @throws RuntimeException
     */
    public static function assertTipoProrrateadoExiste(string $abreviatura): Tipotransaccion_Compra
    {
        $tipo = Tipotransaccion_Compra::query()
            ->where('abreviatura', strtoupper(trim($abreviatura)))
            ->first();
        if (! $tipo) {
            throw new RuntimeException(
                'Tipo de comprobante prorrateado «'.$abreviatura.'» no existe en el maestro. '
                .'Ejecute la migración de tipos FP*/CP*/DP*.'
            );
        }

        return $tipo;
    }
}
