<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Models\Contable\Asiento;
use App\Models\Contable\BienUso;
use App\Models\Contable\BienUsoAmortizacion;
use App\Models\Contable\BienUsoRevaluo;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generación de asiento de revalúo / amortizaciones (Anita p-asibie.c).
 *
 * Amortización: Debe = cuenta global gasto (impcont 301), Haber = cta_amort del bien,
 * importe = monto_mensual del período.
 * Revalúo: Debe = cta_origen, Haber = cta_contrap, importe = valor_actual − valor_comienzo.
 *
 * Módulo estándar ERP — sin gates Crown/AGG.
 */
final class BienUsoAsientoService
{
    public const TIPO_AMORTIZACION = 'amortizacion';

    public const TIPO_REVALUO = 'revaluo';

    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {
    }

    /**
     * @param  array{
     *     empresa_id: int,
     *     tipo_operacion: string,
     *     ejercicio: int,
     *     fecha_asiento: string,
     *     codigo_bien_desde?: int|null,
     *     codigo_bien_hasta?: int|null,
     *     bien_uso_rubro_id?: int|null,
     *     centrocosto_id?: int|null,
     * }  $filtros
     * @return array{
     *     lineas: list<array{cuentacontable_id: int, centrocosto_id: int|null, monto: float, d_h: string, codigo_cuenta?: string, nombre_cuenta?: string}>,
     *     total_debe: float,
     *     total_haber: float,
     *     bienes_procesados: int,
     *     meta: array<string, mixed>
     * }
     */
    public function preview(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $tipo = (string) ($filtros['tipo_operacion'] ?? self::TIPO_AMORTIZACION);
        $ejercicio = (int) ($filtros['ejercicio'] ?? 0);
        if ($empresaId <= 0 || $ejercicio <= 0) {
            throw new InvalidArgumentException('Empresa y ejercicio son obligatorios.');
        }
        if (! in_array($tipo, [self::TIPO_AMORTIZACION, self::TIPO_REVALUO], true)) {
            throw new InvalidArgumentException('Tipo de operación inválido.');
        }

        $bienes = $this->queryBienes($filtros)->get();
        /** @var array<string, array{cuentacontable_id: int, centrocosto_id: int|null, monto: float, d_h: string}> $acum */
        $acum = [];
        $procesados = 0;

        $cuentaGastoId = null;
        if ($tipo === self::TIPO_AMORTIZACION) {
            $cuentaGastoId = CuentaAutomaticaResolver::resolverId(
                $empresaId,
                CuentaAutomaticaClaves::BIEN_USO_GASTO_AMORTIZACION
            );
            if (! $cuentaGastoId) {
                throw new RuntimeException(
                    'Configure la cuenta global «Gasto de amortizaciones» (Contable → Cuentas automáticas → Bienes de uso).'
                );
            }
        }

        foreach ($bienes as $bien) {
            $importe = $this->importeOperacion($bien, $tipo, $ejercicio);
            if ($importe === null || abs($importe) < 0.005) {
                continue;
            }
            $procesados++;

            if ($tipo === self::TIPO_AMORTIZACION) {
                $cuenta1 = (int) $cuentaGastoId;
                $cuenta2 = (int) ($bien->cuentacontable_amort_id ?? 0);
                $ccosto1 = (int) ($bien->centrocosto_id ?? 0) ?: null;
            } else {
                $cuenta1 = (int) ($bien->cuentacontable_origen_id ?? 0);
                $cuenta2 = (int) ($bien->cuentacontable_contrap_id ?? 0);
                $ccosto1 = null;
            }

            if ($cuenta1 <= 0 || $cuenta2 <= 0) {
                continue;
            }

            $abs = abs($importe);
            if ($importe > 0) {
                $this->sumarLinea($acum, $cuenta1, $ccosto1, $abs, 'D');
                $this->sumarLinea($acum, $cuenta2, null, $abs, 'H');
            } else {
                $this->sumarLinea($acum, $cuenta1, $ccosto1, $abs, 'H');
                $this->sumarLinea($acum, $cuenta2, null, $abs, 'D');
            }
        }

        $lineas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($acum as $linea) {
            $montoSigned = $linea['d_h'] === 'D' ? $linea['monto'] : -$linea['monto'];
            $lineas[] = [
                'cuentacontable_id' => $linea['cuentacontable_id'],
                'centrocosto_id' => $linea['centrocosto_id'],
                'monto' => round($montoSigned, 2),
                'd_h' => $linea['d_h'],
                'importe' => round($linea['monto'], 2),
            ];
            if ($linea['d_h'] === 'D') {
                $totalDebe += $linea['monto'];
            } else {
                $totalHaber += $linea['monto'];
            }
        }

        usort($lineas, static fn ($a, $b) => [$a['d_h'] === 'H' ? 1 : 0, $a['cuentacontable_id']]
            <=> [$b['d_h'] === 'H' ? 1 : 0, $b['cuentacontable_id']]);

        return [
            'lineas' => $lineas,
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'bienes_procesados' => $procesados,
            'meta' => [
                'empresa_id' => $empresaId,
                'tipo_operacion' => $tipo,
                'ejercicio' => $ejercicio,
                'fecha_asiento' => $filtros['fecha_asiento'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function confirmar(array $filtros, int $usuarioId): Asiento
    {
        $preview = $this->preview($filtros);
        if ($preview['lineas'] === []) {
            throw new RuntimeException('No hay movimientos para generar el asiento.');
        }
        if (abs($preview['total_debe'] - $preview['total_haber']) > 0.02) {
            throw new RuntimeException(
                'El asiento no balancea. Debe '.$preview['total_debe'].' / Haber '.$preview['total_haber']
            );
        }

        $empresaId = (int) $filtros['empresa_id'];
        $fecha = (string) $filtros['fecha_asiento'];
        $tipo = (string) $filtros['tipo_operacion'];
        $abrev = $tipo === self::TIPO_AMORTIZACION ? 'AMO' : 'CONT';
        $tipoAsiento = $this->tipoasientoRepository->findPorAbreviatura($abrev);
        if (! $tipoAsiento) {
            throw new RuntimeException('No existe el tipo de asiento '.$abrev.'.');
        }

        $etiqueta = $tipo === self::TIPO_AMORTIZACION
            ? 'Amortización bienes de uso'
            : 'Revalúo contable bienes de uso';
        $obs = $etiqueta.' — ejercicio '.(int) $filtros['ejercicio']
            .' — '.Carbon::parse($fecha)->format('d/m/Y');

        return DB::transaction(function () use ($preview, $empresaId, $fecha, $tipoAsiento, $obs, $usuarioId) {
            $payloadMov = [
                'cuentacontable_ids' => [],
                'centrocosto_ids' => [],
                'moneda_ids' => [],
                'debes' => [],
                'haberes' => [],
                'cotizaciones' => [],
                'observaciones' => [],
            ];
            foreach ($preview['lineas'] as $lin) {
                $payloadMov['cuentacontable_ids'][] = $lin['cuentacontable_id'];
                $payloadMov['centrocosto_ids'][] = $lin['centrocosto_id'];
                $payloadMov['moneda_ids'][] = 1;
                $payloadMov['debes'][] = $lin['d_h'] === 'D' ? $lin['importe'] : '';
                $payloadMov['haberes'][] = $lin['d_h'] === 'H' ? $lin['importe'] : '';
                $payloadMov['cotizaciones'][] = 1;
                $payloadMov['observaciones'][] = $obs;
            }

            $payloadAsiento = array_merge($payloadMov, [
                'empresa_id' => $empresaId,
                'tipoasiento_id' => (int) $tipoAsiento->id,
                'fecha' => $fecha,
                'numeroasiento' => '',
                'observacion' => $obs,
                'usuario_id' => $usuarioId,
            ]);

            $asiento = $this->asientoRepository->create($payloadAsiento);
            if (! $asiento || $asiento === 'Error') {
                throw new RuntimeException('No se pudo crear el asiento de bienes de uso.');
            }

            $this->asientoMovimientoRepository->create($payloadMov, (int) $asiento->id);

            return $this->asientoRepository->find((int) $asiento->id);
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBienes(array $filtros): \Illuminate\Database\Eloquent\Builder
    {
        $q = BienUso::query()
            ->where('empresa_id', (int) $filtros['empresa_id'])
            ->where('estado', 'A')
            ->whereNotNull('codigo_bien');

        $desde = (int) ($filtros['codigo_bien_desde'] ?? 0);
        $hasta = (int) ($filtros['codigo_bien_hasta'] ?? 0);
        if ($desde > 0) {
            $q->where('codigo_bien', '>=', $desde);
        }
        if ($hasta > 0) {
            $q->where('codigo_bien', '<=', $hasta);
        }
        if (! empty($filtros['bien_uso_rubro_id'])) {
            $q->where('bien_uso_rubro_id', (int) $filtros['bien_uso_rubro_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $q->where('centrocosto_id', (int) $filtros['centrocosto_id']);
        }

        $fecha = (string) ($filtros['fecha_asiento'] ?? '');
        if ($fecha !== '') {
            $q->where(function ($w) use ($fecha) {
                $w->whereNull('fecha_alta')->orWhereDate('fecha_alta', '<=', $fecha);
            });
            $q->where(function ($w) use ($fecha) {
                $w->whereNull('fecha_baja')->orWhereDate('fecha_baja', '>', $fecha);
            });
        }

        return $q->orderBy('codigo_bien');
    }

    private function importeOperacion(BienUso $bien, string $tipo, int $ejercicio): ?float
    {
        if ($tipo === self::TIPO_AMORTIZACION) {
            $amor = BienUsoAmortizacion::query()
                ->where('bien_uso_id', $bien->id)
                ->where('periodo_anio', $ejercicio)
                ->first();

            return $amor ? (float) $amor->monto_mensual : null;
        }

        $rev = BienUsoRevaluo::query()
            ->where('bien_uso_id', $bien->id)
            ->where('periodo_anio', $ejercicio)
            ->first();
        if (! $rev) {
            return null;
        }

        return round((float) $rev->valor_actual - (float) $rev->valor_comienzo, 2);
    }

    /**
     * @param  array<string, array{cuentacontable_id: int, centrocosto_id: int|null, monto: float, d_h: string}>  $acum
     */
    private function sumarLinea(array &$acum, int $cuentaId, ?int $ccostoId, float $importe, string $dh): void
    {
        $key = $cuentaId.'|'.($ccostoId ?? 0).'|'.$dh;
        if (! isset($acum[$key])) {
            $acum[$key] = [
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $ccostoId,
                'monto' => 0.0,
                'd_h' => $dh,
            ];
        }
        $acum[$key]['monto'] = round($acum[$key]['monto'] + $importe, 2);
    }
}
