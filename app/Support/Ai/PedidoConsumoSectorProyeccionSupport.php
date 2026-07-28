<?php

namespace App\Support\Ai;

use App\Models\Contable\Centrocosto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Saldo_Deposito;
use App\Models\Stock\Depmae;
use App\Support\Compras\ArticuloProveedorOperativoSupport;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Proyecta qué pedir por centro de costo midiendo consumo en un depósito explícito.
 *
 * v1: salidas AM (cantidad &lt; 0) / días del rango → cobertura − stock − pendientes.
 * Split compra vs sala según stock en depósito origen.
 *
 * Hooks roadmap (params / meta, sin lógica completa aún):
 * - multiplicador_evento, solo_sabados
 * - lead_time_dias, buffer_dias
 * - cruzar_bom_gastronomia
 */
final class PedidoConsumoSectorProyeccionSupport
{
    public const DOCUMENTO_COMPRA = 'compra';

    public const DOCUMENTO_SALA = 'sala';

    /**
     * @param  array<string,mixed>  $params
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   score?: float,
     *   parrafos?: list<string>,
     *   links?: list<array{etiqueta: string, url: string}>,
     *   tabla?: array{columnas: list<array{key: string, label: string}>, filas: list<array<string, string>>},
     *   datos?: array<string,mixed>
     * }
     */
    public static function proyectar(array $params): array
    {
        $cc = self::resolverCentrocosto($params);
        if ($cc === null) {
            return [
                'ok' => false,
                'error' => 'Indique el centro de costo (código o id). Ejemplo: «pedido consumo CC 93 depósito 12 últimos 60 días».',
            ];
        }

        $depositoConsumo = self::resolverDeposito(
            $params['deposito_consumo_id'] ?? $params['deposito_id'] ?? null,
            $params['deposito_codigo'] ?? $params['deposito_consumo_codigo'] ?? null
        );
        if ($depositoConsumo === null) {
            return [
                'ok' => false,
                'error' => 'Indique el depósito de consumo (obligatorio). Ejemplo: depósito 12 o código del depósito.',
            ];
        }

        if (! UsuarioDepositoAutorizado::depositoAutorizado((int) $depositoConsumo->id)) {
            return [
                'ok' => false,
                'error' => 'No está autorizado a consultar el depósito «'.($depositoConsumo->codigo ?? $depositoConsumo->id).'».',
            ];
        }

        $empresaId = (int) ($params['empresa_id'] ?? $depositoConsumo->empresa_id ?? 0);

        $diasCobertura = max(1, (int) ($params['dias_cobertura'] ?? 7));
        $soloInsumo = array_key_exists('solo_insumo', $params)
            ? (bool) $params['solo_insumo']
            : true;

        $hasta = self::fechaODefault($params['fecha_hasta'] ?? null, Carbon::today());
        $desde = self::fechaODefault(
            $params['fecha_desde'] ?? null,
            $hasta->copy()->subDays(59)
        );
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $diasRango = max(1, $desde->diffInDays($hasta) + 1);

        // Roadmap hooks (expuestos en meta; v1 no altera el cálculo salvo multiplicador_evento).
        $multiplicadorEvento = max(0.1, (float) ($params['multiplicador_evento'] ?? 1.0));
        $soloSabados = ! empty($params['solo_sabados']);
        $leadTimeDias = isset($params['lead_time_dias']) ? max(0, (int) $params['lead_time_dias']) : null;
        $bufferDias = max(0, (int) ($params['buffer_dias'] ?? 0));
        if ($leadTimeDias !== null) {
            $diasCobertura = max(1, $leadTimeDias + $bufferDias);
        }

        $depositoOrigen = self::resolverDepositoOrigen($params, $empresaId, (int) $depositoConsumo->id);

        $consumos = self::agregarConsumos(
            (int) $depositoConsumo->id,
            $desde->toDateString(),
            $hasta->toDateString(),
            $soloInsumo,
            $soloSabados
        );

        if ($consumos === []) {
            return [
                'ok' => true,
                'score' => 0.7,
                'parrafos' => [
                    'Sin salidas de stock en el depósito de consumo para el período.',
                    'CC '.$cc->codigo.' · Depósito '.($depositoConsumo->codigo ?? $depositoConsumo->id)
                        .' · '.$desde->format('d/m/Y').'–'.$hasta->format('d/m/Y').' ('.$diasRango.' días).',
                ],
                'links' => self::linksBase($cc, $depositoConsumo),
                'tabla' => [
                    'columnas' => self::columnasTabla(),
                    'filas' => [],
                ],
                'datos' => [
                    'centrocosto_id' => (int) $cc->id,
                    'deposito_consumo_id' => (int) $depositoConsumo->id,
                    'deposito_origen_id' => $depositoOrigen?->id,
                    'lineas' => [],
                    'borrador_compra' => null,
                    'borrador_sala' => null,
                    '_meta' => self::metaRoadmap($params, $multiplicadorEvento, $soloSabados, $leadTimeDias, $bufferDias),
                ],
            ];
        }

        $articuloIds = array_map('intval', array_keys($consumos));
        $articulos = Articulo::query()
            ->whereIn('id', $articuloIds)
            ->get(['id', 'sku', 'descripcion', 'oficinacompra_id'])
            ->keyBy('id');

        $saldosConsumo = self::saldosPorArticulo($articuloIds, (int) $depositoConsumo->id);
        $saldosOrigen = $depositoOrigen
            ? self::saldosPorArticulo($articuloIds, (int) $depositoOrigen->id)
            : [];
        $pendientes = self::pendientesEntrada($articuloIds, (int) $cc->id);

        $maxLineas = max(1, min(200, (int) ($params['max_lineas'] ?? 80)));
        $lineas = [];
        foreach ($consumos as $articuloId => $consumoPeriodo) {
            $art = $articulos->get($articuloId);
            if (! $art) {
                continue;
            }
            $consumoDiario = round($consumoPeriodo / $diasRango, 4);
            $qtyBruta = round($consumoDiario * $diasCobertura * $multiplicadorEvento, 2);
            $stock = round((float) ($saldosConsumo[$articuloId] ?? 0), 2);
            $pend = round((float) ($pendientes[$articuloId] ?? 0), 2);
            $neto = round(max(0, $qtyBruta - $stock - $pend), 2);
            if ($neto <= 0.009) {
                continue;
            }

            $stockOrigen = round((float) ($saldosOrigen[$articuloId] ?? 0), 2);
            $documento = ($depositoOrigen && $stockOrigen + 0.009 >= $neto)
                ? self::DOCUMENTO_SALA
                : self::DOCUMENTO_COMPRA;

            $proveedor = null;
            if ($documento === self::DOCUMENTO_COMPRA) {
                $proveedor = self::proveedorSugerido((int) $articuloId);
            }

            $sku = (string) ($art->sku ?? $articuloId);
            $nombre = (string) ($art->descripcion ?? '');

            $lineas[] = [
                'articulo_id' => (int) $articuloId,
                'sku' => $sku,
                'nombre' => $nombre,
                'consumo_periodo' => round($consumoPeriodo, 2),
                'consumo_diario' => $consumoDiario,
                'stock' => $stock,
                'stock_origen' => $stockOrigen,
                'pendiente' => $pend,
                'qty_sugerida' => $neto,
                'documento' => $documento,
                'proveedor_id' => $proveedor['proveedor_id'] ?? null,
                'proveedor_codigo' => $proveedor['codigo'] ?? null,
                'proveedor_nombre' => $proveedor['nombre'] ?? null,
            ];
        }

        usort($lineas, static fn ($a, $b) => $b['qty_sugerida'] <=> $a['qty_sugerida']);
        $lineas = array_slice($lineas, 0, $maxLineas);

        $lineasCompra = array_values(array_filter($lineas, static fn ($l) => $l['documento'] === self::DOCUMENTO_COMPRA));
        $lineasSala = array_values(array_filter($lineas, static fn ($l) => $l['documento'] === self::DOCUMENTO_SALA));

        $borradorCompra = $lineasCompra === [] ? null : self::armarBorradorCompra(
            $cc,
            $depositoConsumo,
            $empresaId,
            $lineasCompra,
            $hasta->copy()->addDays($diasCobertura)->toDateString()
        );
        $borradorSala = $lineasSala === [] ? null : self::armarBorradorSala(
            $cc,
            $depositoConsumo,
            $depositoOrigen,
            $empresaId,
            $lineasSala,
            $hasta->copy()->addDays($diasCobertura)->toDateString()
        );

        $parrafos = [
            'Pedido por consumo — CC '.$cc->codigo.' «'.($cc->nombre ?? '').'».',
            'Depósito consumo: '.($depositoConsumo->codigo ?? '').' '
                .($depositoConsumo->nombre ?? '').' · Período '.$desde->format('d/m/Y')
                .'–'.$hasta->format('d/m/Y').' ('.$diasRango.' días) · Cobertura '.$diasCobertura.' días.',
            'Líneas a pedir: '.count($lineas)
                .' (compra: '.count($lineasCompra).', sala/TM: '.count($lineasSala).').',
            'La IA solo propone; confirme el borrador para crear la requisición.',
        ];
        if ($multiplicadorEvento > 1.001 || $multiplicadorEvento < 0.999) {
            $parrafos[] = 'Multiplicador evento aplicado: ×'.rtrim(rtrim(number_format($multiplicadorEvento, 2, '.', ''), '0'), '.');
        }
        if ($soloSabados) {
            $parrafos[] = 'Consumo filtrado a días sábado (hook evento).';
        }
        if ($depositoOrigen) {
            $parrafos[] = 'Depósito origen (sala): '.($depositoOrigen->codigo ?? $depositoOrigen->id)
                .' '.($depositoOrigen->nombre ?? '');
        }

        $filas = [];
        foreach ($lineas as $l) {
            $filas[] = [
                'sku' => $l['sku'],
                'nombre' => mb_substr($l['nombre'], 0, 48),
                'consumo_periodo' => self::fmtNum($l['consumo_periodo']),
                'consumo_diario' => self::fmtNum($l['consumo_diario'], 3),
                'stock' => self::fmtNum($l['stock']),
                'pendiente' => self::fmtNum($l['pendiente']),
                'qty_sugerida' => self::fmtNum($l['qty_sugerida']),
                'documento' => $l['documento'],
                'proveedor' => (string) ($l['proveedor_nombre'] ?? $l['proveedor_codigo'] ?? ''),
            ];
        }

        $links = self::linksBase($cc, $depositoConsumo);
        if (can('crear-requisicion', false)) {
            $links[] = [
                'etiqueta' => 'Alta requisición de compra',
                'url' => route('crear_requisicion'),
            ];
        }
        if (can('crear-requisicion-sala', false)) {
            try {
                $links[] = [
                    'etiqueta' => 'Alta requisición de sala',
                    'url' => route('crear_requisicion_sala'),
                ];
            } catch (\Throwable) {
            }
        }

        return [
            'ok' => true,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => self::columnasTabla(),
                'filas' => $filas,
            ],
            'datos' => [
                'centrocosto_id' => (int) $cc->id,
                'centrocosto_codigo' => (string) $cc->codigo,
                'deposito_consumo_id' => (int) $depositoConsumo->id,
                'deposito_consumo_codigo' => (string) ($depositoConsumo->codigo ?? ''),
                'deposito_origen_id' => $depositoOrigen ? (int) $depositoOrigen->id : null,
                'empresa_id' => $empresaId,
                'fecha_desde' => $desde->toDateString(),
                'fecha_hasta' => $hasta->toDateString(),
                'dias_rango' => $diasRango,
                'dias_cobertura' => $diasCobertura,
                'lineas' => $lineas,
                'borrador_compra' => $borradorCompra,
                'borrador_sala' => $borradorSala,
                'puede_confirmar_compra' => $borradorCompra !== null && can('crear-requisicion', false),
                'puede_confirmar_sala' => $borradorSala !== null && can('crear-requisicion-sala', false),
                '_meta' => self::metaRoadmap($params, $multiplicadorEvento, $soloSabados, $leadTimeDias, $bufferDias),
            ],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private static function columnasTabla(): array
    {
        return [
            ['key' => 'sku', 'label' => 'SKU'],
            ['key' => 'nombre', 'label' => 'Artículo'],
            ['key' => 'consumo_periodo', 'label' => 'Consumo'],
            ['key' => 'consumo_diario', 'label' => 'Diario'],
            ['key' => 'stock', 'label' => 'Stock'],
            ['key' => 'pendiente', 'label' => 'Pend.'],
            ['key' => 'qty_sugerida', 'label' => 'Pedir'],
            ['key' => 'documento', 'label' => 'Doc'],
            ['key' => 'proveedor', 'label' => 'Proveedor'],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function metaRoadmap(
        array $params,
        float $multiplicadorEvento,
        bool $soloSabados,
        ?int $leadTimeDias,
        int $bufferDias,
    ): array {
        return [
            'consumo_base' => 'articulo_movimiento_salidas',
            'divisor' => 'dias_del_rango',
            'multiplicador_evento' => $multiplicadorEvento,
            'solo_sabados' => $soloSabados,
            'lead_time_dias' => $leadTimeDias,
            'buffer_dias' => $bufferDias,
            'cruzar_bom_gastronomia' => ! empty($params['cruzar_bom_gastronomia']),
            'hooks_pendientes' => [
                'evento_sabado_auto',
                'lead_time_desde_oc_recepcion',
                'bom_vs_am',
                'excluir_tm_interdeposito',
                'agente_hitl_stock_bajo',
            ],
        ];
    }

    /**
     * @return list<array{etiqueta: string, url: string}>
     */
    private static function linksBase(object $cc, object $deposito): array
    {
        $links = [];
        if (can('editar-centrocosto', false) || can('listar-centrocosto', false)) {
            try {
                $links[] = [
                    'etiqueta' => 'Centro de costo '.$cc->codigo,
                    'url' => route('editar_centrocosto', $cc->id),
                ];
            } catch (\Throwable) {
            }
        }
        if (can('editar-depositos', false) || can('listar-depositos', false)) {
            try {
                $links[] = [
                    'etiqueta' => 'Depósito '.($deposito->codigo ?? $deposito->id),
                    'url' => route('editar_depmae', $deposito->id),
                ];
            } catch (\Throwable) {
            }
        }

        return $links;
    }

    /** @param  array<string,mixed>  $params */
    private static function resolverCentrocosto(array $params): ?Centrocosto
    {
        $id = (int) ($params['centrocosto_id'] ?? 0);
        if ($id > 0) {
            return Centrocosto::query()->find($id);
        }

        $codigo = trim((string) ($params['centrocosto_codigo'] ?? $params['codigo'] ?? $params['valor'] ?? ''));
        if ($codigo === '') {
            return null;
        }

        $cc = Centrocosto::query()->where('codigo', $codigo)->first();
        if ($cc) {
            return $cc;
        }

        $codigoNorm = ltrim($codigo, '0');
        if ($codigoNorm !== '' && $codigoNorm !== $codigo) {
            return Centrocosto::query()->where('codigo', $codigoNorm)->first()
                ?? Centrocosto::query()->where('codigo', str_pad($codigoNorm, strlen($codigo), '0', STR_PAD_LEFT))->first();
        }

        return Centrocosto::query()->where('codigo', 'like', '%'.$codigo.'%')->orderBy('codigo')->first();
    }

    private static function resolverDeposito(mixed $idRaw, mixed $codigoRaw): ?Depmae
    {
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        if ($id > 0) {
            return Depmae::query()->find($id);
        }

        $codigo = trim((string) ($codigoRaw ?? ''));
        if ($codigo === '') {
            return null;
        }

        return Depmae::query()->where('codigo', $codigo)->first()
            ?? Depmae::query()->where('codigo', ltrim($codigo, '0'))->first();
    }

    /** @param  array<string,mixed>  $params */
    private static function resolverDepositoOrigen(array $params, int $empresaId, int $depositoConsumoId): ?Depmae
    {
        $explicit = self::resolverDeposito(
            $params['deposito_origen_id'] ?? null,
            $params['deposito_origen_codigo'] ?? null
        );
        if ($explicit && (int) $explicit->id !== $depositoConsumoId) {
            return $explicit;
        }

        if ($empresaId <= 0) {
            return null;
        }

        // Preferir depósito Normal / Formulas de la misma empresa (tipodeposito N o F).
        $candidato = Depmae::query()
            ->where('empresa_id', $empresaId)
            ->where('id', '!=', $depositoConsumoId)
            ->whereIn('tipodeposito', ['N', 'F'])
            ->orderByRaw("CASE tipodeposito WHEN 'F' THEN 0 WHEN 'N' THEN 1 ELSE 2 END")
            ->orderBy('codigo')
            ->first();

        return $candidato;
    }

    /**
     * @return array<int, float> articulo_id => consumo absoluto
     */
    private static function agregarConsumos(
        int $depositoId,
        string $desde,
        string $hasta,
        bool $soloInsumo,
        bool $soloSabados,
    ): array {
        $q = Articulo_Movimiento::query()
            ->from('articulo_movimiento as am')
            ->where('am.deposito_id', $depositoId)
            ->where('am.cantidad', '<', 0)
            ->whereBetween('am.fecha', [$desde, $hasta]);

        if ($soloInsumo) {
            $usoId = ArticuloUsoInsumoSupport::idUsoInsumo();
            if ($usoId) {
                $q->join('articulo as a', 'a.id', '=', 'am.articulo_id')
                    ->where('a.usoarticulo_id', $usoId);
            }
        }

        if ($soloSabados) {
            // Portable-ish: DAYOFWEEK MySQL (1=domingo … 7=sábado) → 7; SQLite strftime '%w' = 6.
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                $q->whereRaw("strftime('%w', am.fecha) = '6'");
            } else {
                $q->whereRaw('DAYOFWEEK(am.fecha) = 7');
            }
        }

        $rows = $q->groupBy('am.articulo_id')
            ->selectRaw('am.articulo_id as articulo_id, SUM(ABS(am.cantidad)) as consumo')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->articulo_id;
            $consumo = (float) $row->consumo;
            if ($id > 0 && $consumo > 0) {
                $out[$id] = $consumo;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, float>
     */
    private static function saldosPorArticulo(array $articuloIds, int $depositoId): array
    {
        if ($articuloIds === [] || $depositoId <= 0) {
            return [];
        }

        $rows = Articulo_Saldo_Deposito::query()
            ->where('deposito_id', $depositoId)
            ->whereIn('articulo_id', $articuloIds)
            ->selectRaw('articulo_id, SUM(cantidad) as saldo')
            ->groupBy('articulo_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->articulo_id] = (float) $row->saldo;
        }

        return $out;
    }

    /**
     * Pendientes de entrada: RQ/OC abiertas con destino al CC (best-effort).
     *
     * @param  list<int>  $articuloIds
     * @return array<int, float>
     */
    private static function pendientesEntrada(array $articuloIds, int $centrocostoId): array
    {
        $out = array_fill_keys($articuloIds, 0.0);
        if ($articuloIds === [] || $centrocostoId <= 0) {
            return $out;
        }

        $estadosRqCerrados = ['CUMPLIDA', 'ANULADA', 'CERRADA', 'RECHAZADA', 'SUSPENDIDA'];
        $rq = DB::table('requisicion_articulo as ra')
            ->join('requisicion as r', 'r.id', '=', 'ra.requisicion_id')
            ->whereIn('ra.articulo_id', $articuloIds)
            ->where(function ($q) use ($centrocostoId) {
                $q->where('ra.centrocostodestino_id', $centrocostoId)
                    ->orWhere('r.centrocosto_id', $centrocostoId);
            })
            ->whereNotIn(DB::raw('UPPER(TRIM(r.estado))'), $estadosRqCerrados)
            ->selectRaw('ra.articulo_id, SUM(GREATEST(COALESCE(ra.cantidad,0) - COALESCE(ra.cantidadentregada,0), 0)) as pend')
            ->groupBy('ra.articulo_id')
            ->get();

        foreach ($rq as $row) {
            $id = (int) $row->articulo_id;
            $out[$id] = ($out[$id] ?? 0) + (float) $row->pend;
        }

        $estadosOcAbiertos = [OrdencompraEstados::PENDIENTE, OrdencompraEstados::APROBADA];

        $oc = DB::table('ordencompra_articulo as oa')
            ->join('ordencompra as o', 'o.id', '=', 'oa.ordencompra_id')
            ->whereIn('oa.articulo_id', $articuloIds)
            ->where(function ($q) use ($centrocostoId) {
                $q->where('oa.centrocostodestino_id', $centrocostoId)
                    ->orWhere('o.centrocosto_id', $centrocostoId);
            })
            ->whereIn('o.estadoordencompra', $estadosOcAbiertos)
            ->selectRaw('oa.articulo_id, SUM(COALESCE(oa.cantidad,0)) as pend')
            ->groupBy('oa.articulo_id')
            ->get();

        foreach ($oc as $row) {
            $id = (int) $row->articulo_id;
            $out[$id] = ($out[$id] ?? 0) + (float) $row->pend;
        }

        return $out;
    }

    /** @return array{proveedor_id: int, codigo: string, nombre: string}|null */
    private static function proveedorSugerido(int $articuloId): ?array
    {
        $activos = ArticuloProveedorOperativoSupport::listarActivosPorArticulo($articuloId);
        $primero = $activos->first();
        if (! is_array($primero)) {
            return null;
        }

        $provId = (int) ($primero['proveedor_id'] ?? 0);
        if ($provId <= 0) {
            return null;
        }

        return [
            'proveedor_id' => $provId,
            'codigo' => (string) ($primero['proveedor_codigo'] ?? $primero['codigo_proveedor'] ?? ''),
            'nombre' => (string) ($primero['proveedor_nombre'] ?? $primero['nombre_proveedor'] ?? ''),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     * @return array<string,mixed>
     */
    private static function armarBorradorCompra(
        object $cc,
        object $deposito,
        int $empresaId,
        array $lineas,
        string $fechaEntrega,
    ): array {
        $articuloIds = [];
        $cantidades = [];
        $centrocostos = [];
        $proveedores = [];
        $detalles = [];

        foreach ($lineas as $l) {
            $articuloIds[] = (int) $l['articulo_id'];
            $cantidades[] = (float) $l['qty_sugerida'];
            $centrocostos[] = (int) $cc->id;
            $proveedores[] = $l['proveedor_id'] ?? '';
            $detalles[] = 'IA consumo · dep '.($deposito->codigo ?? $deposito->id);
        }

        return [
            'tipo' => self::DOCUMENTO_COMPRA,
            'fecha' => Carbon::today()->toDateString(),
            'fechaentrega' => $fechaEntrega,
            'empresa_id' => $empresaId > 0 ? $empresaId : (int) ($deposito->empresa_id ?? 0),
            'centrocosto_id' => (int) $cc->id,
            'comentario' => 'Sugerido por IA (pedido por consumo)',
            'detalle' => 'Depósito consumo '.($deposito->codigo ?? $deposito->id),
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'centrocostodestino_ids' => $centrocostos,
            'proveedor_ids' => $proveedores,
            'detalles' => $detalles,
            'colores_id' => array_fill(0, count($articuloIds), ''),
            'talles_id' => array_fill(0, count($articuloIds), ''),
            'lineas' => $lineas,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     * @return array<string,mixed>
     */
    private static function armarBorradorSala(
        object $cc,
        object $depositoConsumo,
        ?object $depositoOrigen,
        int $empresaId,
        array $lineas,
        string $fechaEntrega,
    ): array {
        $articuloIds = [];
        $cantidades = [];
        $detalles = [];

        foreach ($lineas as $l) {
            $articuloIds[] = (int) $l['articulo_id'];
            $cantidades[] = (float) $l['qty_sugerida'];
            $detalles[] = 'IA consumo · reposición interna';
        }

        return [
            'tipo' => self::DOCUMENTO_SALA,
            'fecha' => Carbon::today()->toDateString(),
            'fecha_entrega' => $fechaEntrega,
            'empresa_id' => $empresaId > 0 ? $empresaId : (int) ($depositoConsumo->empresa_id ?? 0),
            'centrocosto_id' => (int) $cc->id,
            // Destino = depósito que consume; origen implícito en stock origen.
            'deposito_id' => (int) $depositoConsumo->id,
            'deposito_origen_id' => $depositoOrigen ? (int) $depositoOrigen->id : null,
            'comentario' => 'Sugerido por IA (pedido interno por consumo)',
            'detalle' => 'Desde '.($depositoOrigen->codigo ?? '?').' → '.($depositoConsumo->codigo ?? ''),
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'detalles' => $detalles,
            'colores_id' => array_fill(0, count($articuloIds), ''),
            'talles_id' => array_fill(0, count($articuloIds), ''),
            'lineas' => $lineas,
        ];
    }

    private static function fechaODefault(mixed $raw, Carbon $default): Carbon
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($raw))) {
            return Carbon::parse(trim($raw))->startOfDay();
        }

        return $default->copy()->startOfDay();
    }

    private static function fmtNum(float $n, int $dec = 2): string
    {
        return number_format($n, $dec, ',', '.');
    }
}
