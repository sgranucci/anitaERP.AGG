<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use Illuminate\Support\Facades\DB;

/**
 * Control diario: total rendgastro vs asientos ERP de facturación del cierre Waitry.
 * Asientos 1–2 (post-cierre + factura día), 3 (TOTEM ventas) y agregados suman al total;
 * asiento 4 (puente TOTEM) se audita en detalle pero no duplica importe (contrapartida interna).
 */
final class GastronomiaConciliacionRendgAsientosDiaSupport
{
    public const TIPO_POST_CIERRE = 'post_cierre';

    public const TIPO_FACTURA_DIA = 'factura_dia';

    public const TIPO_TOTEM_VENTAS = 'totem_ventas';

    public const TIPO_TOTEM_PUENTE = 'totem_puente';

    public const TIPO_AGREGADOS_CAEA = 'agregados_caea';

    /** @var list<string> Tipos que suman al total vs rendg / facturación del día. */
    private const TIPOS_FACTURACION_TOTAL = [
        self::TIPO_POST_CIERRE,
        self::TIPO_FACTURA_DIA,
        self::TIPO_TOTEM_VENTAS,
        self::TIPO_AGREGADOS_CAEA,
    ];

    /** @var list<string> Tipos incluidos en detalle del control (incl. puente TOTEM). */
    private const TIPOS_FACTURACION_DETALLE = [
        self::TIPO_POST_CIERRE,
        self::TIPO_FACTURA_DIA,
        self::TIPO_TOTEM_VENTAS,
        self::TIPO_TOTEM_PUENTE,
        self::TIPO_AGREGADOS_CAEA,
    ];

    /**
     * @param  array<string, float|null>  $totalesSalon
     * @param  array<string, mixed>  $postCierre
     * @param  array<string, mixed>  $agregados
     * @return array<string, mixed>
     */
    public function armarControl(
        int $empresaId,
        string $fechaJornada,
        array $totalesSalon,
        array $postCierre,
        array $agregados,
        bool $jornadaAbierta,
        float $tolerancia,
        float $notasCreditoRendg = 0.0,
    ): array {
        $rendgSalonBruto = ! $jornadaAbierta ? (float) ($totalesSalon['rendgastro_z'] ?? 0) : null;
        $ncRendg = ! $jornadaAbierta ? round($notasCreditoRendg, 2) : 0.0;
        $rendgSalonNeto = $rendgSalonBruto !== null ? round($rendgSalonBruto - $ncRendg, 2) : null;

        $rendgPost = ! $jornadaAbierta && ($postCierre['rendgastro_z'] ?? null) !== null
            ? (float) $postCierre['rendgastro_z']
            : 0.0;
        $rendgAgregados = ! $jornadaAbierta && ($agregados['rendgastro_z'] ?? null) !== null
            ? (float) $agregados['rendgastro_z']
            : 0.0;

        $rendgTotal = $rendgSalonNeto !== null
            ? round($rendgSalonNeto + $rendgPost + $rendgAgregados, 2)
            : null;

        $asientos = $this->auditarAsientosFacturacionJornada($empresaId, $fechaJornada);
        $asientosTotal = (float) ($asientos['total'] ?? 0);
        $asientosSalon = (float) ($asientos['factura_dia'] ?? 0);
        $asientosPost = (float) ($asientos['post_cierre'] ?? 0);
        $asientosAgregados = (float) ($asientos['agregados_caea'] ?? 0);
        $asientosTotem = (float) ($asientos['totem_ventas'] ?? 0);
        $asientosTotemPuente = (float) ($asientos['totem_puente'] ?? 0);

        $diff = $rendgTotal !== null ? round($rendgTotal - $asientosTotal, 2) : null;
        $estado = $this->resolverEstado(
            $jornadaAbierta,
            $rendgTotal,
            $asientosTotal,
            $asientos['cantidad'] ?? 0,
            $diff,
            $tolerancia,
        );

        $obs = [];
        if ($ncRendg > $tolerancia) {
            $obs[] = 'NC rendg descontadas del salón: $ '.number_format($ncRendg, 2, ',', '.');
        }
        if (($asientos['otros'] ?? []) !== []) {
            $obs[] = 'Otros asientos cierre (no facturación): '.count($asientos['otros']);
        }

        return [
            'tipo_fila' => 'control_rendg_asientos',
            'identificador_pc' => 'RENDG-ASIENTOS',
            'tipo_pv' => 'EMPRESA',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Control día: rendgastro vs asientos Waitry (1–2 + TOTEM 3–4 + agregados)',
            'rendg_salon' => $rendgSalonNeto,
            'rendg_salon_bruto' => $rendgSalonBruto,
            'notas_credito_rendg' => $ncRendg > 0.0001 ? $ncRendg : null,
            'rendg_post_cierre' => $rendgPost > 0.0001 ? $rendgPost : ($rendgSalonNeto !== null ? 0.0 : null),
            'rendg_agregados_caea' => $rendgAgregados > 0.0001 ? $rendgAgregados : null,
            'rendg_total' => $rendgTotal,
            'asientos_factura_dia' => $asientosSalon,
            'asientos_post_cierre' => $asientosPost,
            'asientos_agregados_caea' => $asientosAgregados,
            'asientos_totem' => $asientosTotem > 0.0001 ? $asientosTotem : null,
            'asientos_totem_puente' => $asientosTotemPuente > 0.0001 ? $asientosTotemPuente : null,
            'asientos_total' => $asientosTotal,
            'asientos_cantidad' => (int) ($asientos['cantidad'] ?? 0),
            'diff_rendg_asientos' => $diff,
            'estado' => $estado,
            'detalle_asientos' => $asientos['detalle'] ?? [],
            'observaciones' => implode(' | ', $obs),
            'es_control_rendg_asientos' => true,
        ];
    }

    /**
     * @return array{
     *   factura_dia: float,
     *   post_cierre: float,
     *   totem_ventas: float,
     *   totem_puente: float,
     *   agregados_caea: float,
     *   total: float,
     *   cantidad: int,
     *   detalle: list<array<string, mixed>>,
     *   otros: list<array<string, mixed>>
     * }
     */
    public function auditarAsientosFacturacionJornada(int $empresaId, string $fechaJornada): array
    {
        $prefijo = 'Cierre Waitry jornada '.$fechaJornada.' — ';
        $mapaGrabados = CierreJornadaProcesoAsientosGrabacionSupport::mapaAsientosGrabadosPorEmpresaJornada(
            $empresaId,
            $fechaJornada,
        );
        $idsSnapshot = array_keys($mapaGrabados);

        $query = DB::table('asiento')
            ->where('empresa_id', $empresaId);

        if ($idsSnapshot !== []) {
            $query->whereIn('id', $idsSnapshot);
        } else {
            $query->where(function ($q) use ($prefijo) {
                $q->where('observacion', 'like', $prefijo.'%')
                    ->orWhere(
                        'observacion',
                        CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
                    );
            });
        }

        $rows = $query
            ->orderBy('id')
            ->get(['id', 'numeroasiento', 'observacion']);

        $totales = [
            self::TIPO_FACTURA_DIA => 0.0,
            self::TIPO_POST_CIERRE => 0.0,
            self::TIPO_TOTEM_VENTAS => 0.0,
            self::TIPO_TOTEM_PUENTE => 0.0,
            self::TIPO_AGREGADOS_CAEA => 0.0,
        ];
        $detalle = [];
        $otros = [];

        foreach ($rows as $row) {
            $asientoId = (int) ($row->id ?? 0);
            $meta = $mapaGrabados[$asientoId] ?? null;
            $tipo = self::clasificarAsientoCierreWaitry(
                is_array($meta) ? ($meta['codigo'] ?? null) : null,
                is_array($meta) ? ($meta['titulo'] ?? null) : null,
                (string) ($row->observacion ?? ''),
            );
            $total = self::totalDebeAsiento((int) ($row->id ?? 0));
            $item = [
                'asiento_id' => (int) ($row->id ?? 0),
                'numeroasiento' => (string) ($row->numeroasiento ?? ''),
                'observacion' => (string) ($row->observacion ?? ''),
                'tipo' => $tipo,
                'total_debe' => $total,
            ];

            if ($tipo !== null && in_array($tipo, self::TIPOS_FACTURACION_DETALLE, true)) {
                $totales[$tipo] += $total;
                $detalle[] = $item;
            } else {
                $otros[] = $item;
            }
        }

        foreach ($totales as $k => $v) {
            $totales[$k] = round($v, 2);
        }

        $totalFacturacion = 0.0;
        foreach (self::TIPOS_FACTURACION_TOTAL as $tipoTotal) {
            $totalFacturacion += $totales[$tipoTotal];
        }

        return [
            'factura_dia' => $totales[self::TIPO_FACTURA_DIA],
            'post_cierre' => $totales[self::TIPO_POST_CIERRE],
            'totem_ventas' => $totales[self::TIPO_TOTEM_VENTAS],
            'totem_puente' => $totales[self::TIPO_TOTEM_PUENTE],
            'agregados_caea' => $totales[self::TIPO_AGREGADOS_CAEA],
            'total' => round($totalFacturacion, 2),
            'cantidad' => count($detalle),
            'detalle' => $detalle,
            'otros' => $otros,
        ];
    }

    public static function clasificarObservacionCierreWaitry(string $observacion): ?string
    {
        return self::clasificarAsientoCierreWaitry(null, null, $observacion);
    }

    public static function clasificarAsientoCierreWaitry(
        ?string $codigo,
        ?string $titulo,
        string $observacion,
    ): ?string {
        $titulo = trim((string) $titulo);
        if ($titulo !== '' && preg_match('/agregados CAEA migrados/i', $titulo) === 1) {
            return self::TIPO_AGREGADOS_CAEA;
        }

        $obs = trim($observacion);
        if ($obs !== '' && preg_match('/agregados CAEA migrados/i', $obs) === 1) {
            return self::TIPO_AGREGADOS_CAEA;
        }

        $codigo = trim((string) $codigo);
        if ($codigo !== '') {
            return match ($codigo) {
                'sin_facturar_qr' => self::TIPO_POST_CIERRE,
                'ventas_medio_real' => self::TIPO_FACTURA_DIA,
                'totem_ventas_iva' => self::TIPO_TOTEM_VENTAS,
                'totem_puente' => self::TIPO_TOTEM_PUENTE,
                default => null,
            };
        }

        if ($obs === '' || ! str_contains($obs, 'Cierre Waitry jornada')) {
            return null;
        }

        if (preg_match('/agregados CAEA migrados/i', $obs) === 1) {
            return self::TIPO_AGREGADOS_CAEA;
        }

        if (preg_match('/Waitry sin facturar/i', $obs) === 1) {
            return self::TIPO_POST_CIERRE;
        }

        if (preg_match('/Facturación Anita jornada/i', $obs) === 1) {
            return self::TIPO_FACTURA_DIA;
        }

        if (preg_match('/Puente TOTEM/i', $obs) === 1) {
            return self::TIPO_TOTEM_PUENTE;
        }

        if (preg_match('/TOTEM → ventas/i', $obs) === 1) {
            return self::TIPO_TOTEM_VENTAS;
        }

        if (preg_match('/cobro TOTEM/i', $obs) === 1 && preg_match('/puente/i', $obs) !== 1) {
            return self::TIPO_TOTEM_VENTAS;
        }

        return null;
    }

    public static function totalDebeAsiento(int $asientoId): float
    {
        if ($asientoId <= 0) {
            return 0.0;
        }

        $total = DB::table('asiento_movimiento')
            ->where('asiento_id', $asientoId)
            ->where('monto', '>', 0)
            ->sum('monto');

        return round((float) ($total ?? 0), 2);
    }

    private function resolverEstado(
        bool $jornadaAbierta,
        ?float $rendgTotal,
        float $asientosTotal,
        int $cantidadAsientos,
        ?float $diff,
        float $tolerancia,
    ): string {
        if ($jornadaAbierta || $rendgTotal === null) {
            return '—';
        }

        if ($cantidadAsientos === 0 && abs($rendgTotal) <= $tolerancia) {
            return '—';
        }

        if ($cantidadAsientos === 0 && abs($rendgTotal) > $tolerancia) {
            return 'DIF';
        }

        if ($diff === null || abs($diff) > $tolerancia) {
            return 'DIF';
        }

        return 'OK';
    }
}
