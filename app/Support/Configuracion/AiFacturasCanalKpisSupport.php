<?php

namespace App\Support\Configuracion;

use App\Models\Ai\AiDecision;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Recepcion_Error;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte predefinido de Gobernanza: facturas/precargas por canal de entrada
 * (agente API, scan, mail/batch/PDF IA) + errores de recepción y de la skill.
 */
final class AiFacturasCanalKpisSupport
{
    public const SKILL_FACTURA = 'extraer_factura_proveedor';

    public const PRESET_7D = '7d';

    public const PRESET_30D = '30d';

    public const PRESET_MES = 'mes';

    /**
     * Canales “hero” que ven los usuarios finales (agrupación de negocio).
     *
     * @return array<string, array{etiqueta: string, origenes: list<string>, ayuda: string}>
     */
    public static function canalesHero(): array
    {
        return [
            'agente_api' => [
                'etiqueta' => 'Agente / API',
                'origenes' => [PrecargaComprobanteOrigenEntrada::API],
                'ayuda' => 'Ingresos aceptados por el agente externo (AGG u otra API).',
            ],
            'scan' => [
                'etiqueta' => 'Scan Anita',
                'origenes' => [PrecargaComprobanteOrigenEntrada::SCAN_ANITA],
                'ayuda' => 'PDF escaneado sin OCR/IA (importes al generar el comprobante).',
            ],
            'ia_anita' => [
                'etiqueta' => 'IA Anita',
                'origenes' => [
                    PrecargaComprobanteOrigenEntrada::MAIL,
                    PrecargaComprobanteOrigenEntrada::BATCH_IA,
                    PrecargaComprobanteOrigenEntrada::PDF_IA,
                ],
                'ayuda' => 'Correo + lote carpeta + PDF cargado con lectura IA.',
            ],
            'portal' => [
                'etiqueta' => 'Portal proveedor',
                'origenes' => [PrecargaComprobanteOrigenEntrada::PORTAL],
                'ayuda' => 'Cargas desde el portal de proveedores.',
            ],
            'legajo' => [
                'etiqueta' => 'Legajo',
                'origenes' => [PrecargaComprobanteOrigenEntrada::LEGAJO],
                'ayuda' => 'PDF adjunto al legajo de compras (sin OCR).',
            ],
            'manual' => [
                'etiqueta' => 'Manual',
                'origenes' => [PrecargaComprobanteOrigenEntrada::MANUAL],
                'ayuda' => 'Alta manual en el ABM de precarga.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function presetsEtiquetas(): array
    {
        return [
            self::PRESET_7D => 'Últimos 7 días',
            self::PRESET_30D => 'Últimos 30 días',
            self::PRESET_MES => 'Mes en curso',
        ];
    }

    /**
     * Resuelve fechas desde preset o inputs libres. Default: últimos 30 días.
     *
     * @return array{fecha_desde: string, fecha_hasta: string, preset: string}
     */
    public static function resolverPeriodo(?string $preset, ?string $desde, ?string $hasta): array
    {
        $preset = trim((string) $preset);
        $desde = trim((string) $desde);
        $hasta = trim((string) $hasta);

        $hoy = Carbon::today();

        if ($preset === self::PRESET_7D) {
            return [
                'fecha_desde' => $hoy->copy()->subDays(6)->toDateString(),
                'fecha_hasta' => $hoy->toDateString(),
                'preset' => self::PRESET_7D,
            ];
        }

        if ($preset === self::PRESET_MES) {
            return [
                'fecha_desde' => $hoy->copy()->startOfMonth()->toDateString(),
                'fecha_hasta' => $hoy->toDateString(),
                'preset' => self::PRESET_MES,
            ];
        }

        if ($preset === self::PRESET_30D || ($desde === '' && $hasta === '')) {
            return [
                'fecha_desde' => $hoy->copy()->subDays(29)->toDateString(),
                'fecha_hasta' => $hoy->toDateString(),
                'preset' => self::PRESET_30D,
            ];
        }

        if ($desde === '') {
            $desde = $hoy->copy()->subDays(29)->toDateString();
        }
        if ($hasta === '') {
            $hasta = $hoy->toDateString();
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'preset' => '',
        ];
    }

    /**
     * Históricamente null/vacío en precarga = agente externo.
     */
    public static function normalizarOrigen(?string $origen): string
    {
        $o = strtoupper(trim((string) $origen));

        return $o === '' ? PrecargaComprobanteOrigenEntrada::API : $o;
    }

    public static function canalHeroDeOrigen(string $origenNormalizado): string
    {
        foreach (self::canalesHero() as $clave => $meta) {
            if (in_array($origenNormalizado, $meta['origenes'], true)) {
                return $clave;
            }
        }

        return 'otros';
    }

    /**
     * @return array{
     *   periodo: array{fecha_desde: string, fecha_hasta: string, preset: string},
     *   total_precargas: int,
     *   por_canal: array<string, array{etiqueta: string, ayuda: string, total: int, pendientes: int, generadas: int, cargadas_anita: int, anuladas: int, para_revisar: int, con_error: int}>,
     *   por_origen: list<array{origen: string, etiqueta: string, total: int, pendientes: int, generadas: int, cargadas_anita: int, anuladas: int, para_revisar: int, con_error: int}>,
     *   errores_recepcion: array{total: int, por_origen: array<string, int>},
     *   decisiones_ia: array{
     *     total: int,
     *     confirmadas: int,
     *     editadas: int,
     *     auto_aplicadas: int,
     *     descartadas: int,
     *     pendientes: int,
     *     errores: int,
     *     tasa_aceptacion: ?float
     *   }
     * }
     */
    public static function calcular(string $fechaDesde, string $fechaHasta, string $preset = ''): array
    {
        $periodo = [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'preset' => $preset,
        ];

        $porCanal = [];
        foreach (self::canalesHero() as $clave => $meta) {
            $porCanal[$clave] = self::filaCanalVacia($meta['etiqueta'], $meta['ayuda']);
        }
        $porCanal['otros'] = self::filaCanalVacia('Otros', 'Orígenes no catalogados.');

        $porOrigen = [];
        $totalPrecargas = 0;

        if (Schema::hasTable('precarga_comprobante_proveedor')) {
            $tieneMarcaError = Schema::hasColumn('precarga_comprobante_proveedor', 'marca_error');
            $tieneParaRevisar = Schema::hasColumn('precarga_comprobante_proveedor', 'pararevisar');

            $select = [
                'origen_entrada',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN UPPER(COALESCE(estado, '')) = '".PrecargaComprobanteEstados::PENDIENTE."' THEN 1 ELSE 0 END) as pendientes"),
                DB::raw("SUM(CASE WHEN UPPER(COALESCE(estado, '')) = '".PrecargaComprobanteEstados::GENERADA."' THEN 1 ELSE 0 END) as generadas"),
                DB::raw("SUM(CASE WHEN UPPER(COALESCE(estado, '')) = '".PrecargaComprobanteEstados::CARGADA_ANITA."' THEN 1 ELSE 0 END) as cargadas_anita"),
                DB::raw("SUM(CASE WHEN UPPER(COALESCE(estado, '')) = '".PrecargaComprobanteEstados::ANULADA."' THEN 1 ELSE 0 END) as anuladas"),
            ];
            $select[] = $tieneParaRevisar
                ? DB::raw('SUM(CASE WHEN COALESCE(pararevisar, 0) = 1 THEN 1 ELSE 0 END) as para_revisar')
                : DB::raw('0 as para_revisar');
            $select[] = $tieneMarcaError
                ? DB::raw("SUM(CASE WHEN marca_error IS NOT NULL AND TRIM(marca_error) <> '' THEN 1 ELSE 0 END) as con_error")
                : DB::raw('0 as con_error');

            $rows = Precarga_Comprobante_Proveedor::query()
                ->whereDate('created_at', '>=', $fechaDesde)
                ->whereDate('created_at', '<=', $fechaHasta)
                ->select($select)
                ->groupBy('origen_entrada')
                ->get();

            foreach ($rows as $row) {
                $origen = self::normalizarOrigen($row->origen_entrada ?? null);
                $canal = self::canalHeroDeOrigen($origen);
                $counts = [
                    'total' => (int) $row->total,
                    'pendientes' => (int) $row->pendientes,
                    'generadas' => (int) $row->generadas,
                    'cargadas_anita' => (int) $row->cargadas_anita,
                    'anuladas' => (int) $row->anuladas,
                    'para_revisar' => (int) $row->para_revisar,
                    'con_error' => (int) $row->con_error,
                ];
                $totalPrecargas += $counts['total'];

                foreach ($counts as $k => $v) {
                    $porCanal[$canal][$k] = (int) ($porCanal[$canal][$k] ?? 0) + $v;
                }

                if (! isset($porOrigen[$origen])) {
                    $porOrigen[$origen] = [
                        'origen' => $origen,
                        'etiqueta' => PrecargaComprobanteOrigenEntrada::etiqueta($origen),
                        'total' => 0,
                        'pendientes' => 0,
                        'generadas' => 0,
                        'cargadas_anita' => 0,
                        'anuladas' => 0,
                        'para_revisar' => 0,
                        'con_error' => 0,
                    ];
                }
                foreach ($counts as $k => $v) {
                    $porOrigen[$origen][$k] += $v;
                }
            }
        }

        $porOrigenList = array_values($porOrigen);
        usort($porOrigenList, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'periodo' => $periodo,
            'total_precargas' => $totalPrecargas,
            'por_canal' => $porCanal,
            'por_origen' => $porOrigenList,
            'errores_recepcion' => self::erroresRecepcion($fechaDesde, $fechaHasta),
            'decisiones_ia' => self::decisionesFactura($fechaDesde, $fechaHasta),
        ];
    }

    /**
     * @return array{etiqueta: string, ayuda: string, total: int, pendientes: int, generadas: int, cargadas_anita: int, anuladas: int, para_revisar: int, con_error: int}
     */
    private static function filaCanalVacia(string $etiqueta, string $ayuda): array
    {
        return [
            'etiqueta' => $etiqueta,
            'ayuda' => $ayuda,
            'total' => 0,
            'pendientes' => 0,
            'generadas' => 0,
            'cargadas_anita' => 0,
            'anuladas' => 0,
            'para_revisar' => 0,
            'con_error' => 0,
        ];
    }

    /**
     * @return array{total: int, por_origen: array<string, int>}
     */
    private static function erroresRecepcion(string $fechaDesde, string $fechaHasta): array
    {
        $out = ['total' => 0, 'por_origen' => []];
        if (! Schema::hasTable('precarga_comprobante_recepcion_error')) {
            return $out;
        }

        $rows = Precarga_Comprobante_Recepcion_Error::query()
            ->whereDate('created_at', '>=', $fechaDesde)
            ->whereDate('created_at', '<=', $fechaHasta)
            ->select('origen', DB::raw('COUNT(*) as c'))
            ->groupBy('origen')
            ->pluck('c', 'origen');

        foreach ($rows as $origen => $c) {
            $clave = self::normalizarOrigen(is_string($origen) ? $origen : null);
            $out['por_origen'][$clave] = (int) ($out['por_origen'][$clave] ?? 0) + (int) $c;
            $out['total'] += (int) $c;
        }

        return $out;
    }

    /**
     * Listado drill-down: precargas del período con el mismo criterio de origen que el KPI.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public static function listarPrecargasPorOrigen(
        string $origen,
        string $fechaDesde,
        string $fechaHasta,
        bool $paginar = true,
        int $porPagina = 50,
    ) {
        $origen = self::normalizarOrigen($origen);

        $tieneParaRevisar = Schema::hasColumn('precarga_comprobante_proveedor', 'pararevisar');
        $tieneMarcaError = Schema::hasColumn('precarga_comprobante_proveedor', 'marca_error');

        $select = [
            'precarga_comprobante_proveedor.id',
            'precarga_comprobante_proveedor.created_at',
            'precarga_comprobante_proveedor.empresa_id',
            'precarga_comprobante_proveedor.proveedor_id',
            'precarga_comprobante_proveedor.letra',
            'precarga_comprobante_proveedor.sucursal',
            'precarga_comprobante_proveedor.numerocomprobante',
            'precarga_comprobante_proveedor.fechafactura',
            'precarga_comprobante_proveedor.numeroordencompra',
            'precarga_comprobante_proveedor.total',
            'precarga_comprobante_proveedor.estado',
            'precarga_comprobante_proveedor.origen_entrada',
            'precarga_comprobante_proveedor.rutaalmacenamiento',
            'empresa.nombre as nombreempresa',
            'proveedor.nombre as nombreproveedor',
            'tipotransaccion_compra.abreviatura as abreviaturatipo',
        ];
        $select[] = $tieneParaRevisar
            ? 'precarga_comprobante_proveedor.pararevisar'
            : DB::raw('0 as pararevisar');
        $select[] = $tieneMarcaError
            ? 'precarga_comprobante_proveedor.marca_error'
            : DB::raw('null as marca_error');

        $query = Precarga_Comprobante_Proveedor::query()
            ->leftJoin('empresa', 'empresa.id', '=', 'precarga_comprobante_proveedor.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'precarga_comprobante_proveedor.proveedor_id')
            ->leftJoin('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'precarga_comprobante_proveedor.tipotransaccion_compra_id')
            ->whereDate('precarga_comprobante_proveedor.created_at', '>=', $fechaDesde)
            ->whereDate('precarga_comprobante_proveedor.created_at', '<=', $fechaHasta)
            ->select($select)
            ->selectSub(function ($sub) {
                $sub->from('comprobante_proveedor')
                    ->select('id')
                    ->whereColumn(
                        'comprobante_proveedor.precarga_comprobante_proveedor_id',
                        'precarga_comprobante_proveedor.id'
                    )
                    ->orderBy('comprobante_proveedor.id')
                    ->limit(1);
            }, 'comprobante_proveedor_id')
            ->orderByDesc('precarga_comprobante_proveedor.id');

        self::aplicarFiltroOrigen($query, $origen);

        if ($paginar) {
            return $query->paginate($porPagina);
        }

        return $query->limit(5000)->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarFiltroOrigen($query, string $origenNormalizado): void
    {
        if ($origenNormalizado === PrecargaComprobanteOrigenEntrada::API) {
            $query->where(function ($q) {
                $q->where('precarga_comprobante_proveedor.origen_entrada', PrecargaComprobanteOrigenEntrada::API)
                    ->orWhereNull('precarga_comprobante_proveedor.origen_entrada')
                    ->orWhere('precarga_comprobante_proveedor.origen_entrada', '');
            });

            return;
        }

        $query->where('precarga_comprobante_proveedor.origen_entrada', $origenNormalizado);
    }

    /**
     * @return array{
     *   total: int,
     *   confirmadas: int,
     *   editadas: int,
     *   auto_aplicadas: int,
     *   descartadas: int,
     *   pendientes: int,
     *   errores: int,
     *   tasa_aceptacion: ?float
     * }
     */
    private static function decisionesFactura(string $fechaDesde, string $fechaHasta): array
    {
        $vacio = [
            'total' => 0,
            'confirmadas' => 0,
            'editadas' => 0,
            'auto_aplicadas' => 0,
            'descartadas' => 0,
            'pendientes' => 0,
            'errores' => 0,
            'tasa_aceptacion' => null,
        ];

        if (! Schema::hasTable('ai_decision')) {
            return $vacio;
        }

        $kpis = AiDecisionKpisSupport::calcular([
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'skill' => self::SKILL_FACTURA,
            'consultar' => true,
        ]);

        $por = $kpis['por_accion'] ?? [];

        return [
            'total' => (int) ($kpis['total'] ?? 0),
            'confirmadas' => (int) ($por[AiDecision::ACCION_CONFIRMADA] ?? 0),
            'editadas' => (int) ($por[AiDecision::ACCION_EDITADA] ?? 0),
            'auto_aplicadas' => (int) ($por[AiDecision::ACCION_AUTO_APLICADA] ?? 0),
            'descartadas' => (int) ($por[AiDecision::ACCION_DESCARTADA] ?? 0),
            'pendientes' => (int) ($kpis['pendientes'] ?? 0),
            'errores' => (int) ($kpis['errores'] ?? 0),
            'tasa_aceptacion' => $kpis['tasa_aceptacion'] ?? null,
        ];
    }
}
