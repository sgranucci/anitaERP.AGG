<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Caja\Cobranza;
use App\Models\Contable\Asiento;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Venta;
use App\Repositories\Contable\AsientoRepository;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoRendicionReparacionService;
use App\Support\Caja\AnitaSync\RendicionAnitaFechaAlfaSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Corrige post-cierre Waitry Rebisco (PV 00030): renumerar 54047–54053 → 54194–54200,
 * jornada 23/06, asientos sin_facturar_qr y rendgastro CIERRE-WAITRY.
 */
final class GastronomiaCorregirPostCierreWaitryRebiscoSupport
{
    public const EMPRESA_ID = 3;

    public const FECHA_JORNADA = '2026-06-23';

    public const FECHA_ENTERA = 20260623;

    /** @var array<int, int> venta_id => numerocomprobante nuevo */
    private const RENUMERACION = [
        11728 => 54194,
        11729 => 54195,
        11730 => 54196,
        11748 => 54197,
        11749 => 54198,
        11750 => 54199,
        11751 => 54200,
    ];

    /** @var list<int> */
    private const ASIENTO_IDS = [37830, 37833];

    /** @var array<int, string> */
    private const OBSERVACION_AGREGADOS = [
        37830 => 'Cierre Waitry jornada 2026-06-23 — agregados CAEA migrados (ex 10/06) — Waitry sin facturar (QR / Mercado Pago tras redistribución)',
        37833 => 'Cierre Waitry jornada 2026-06-23 — agregados CAEA migrados (ex 11/06) — Waitry sin facturar (QR / Mercado Pago tras redistribución)',
    ];

    /** @var list<int> */
    private const JORNADA_IDS_POST_CIERRE = [23, 24];

    public function __construct(
        private readonly AsientoRepository $asientoRepository,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaCierreJornadaProcesoRendicionReparacionService $rendicionReparacionService,
        private readonly GastronomiaConciliacionRebiscoAgregadosCaeaSupport $agregadosCaeaSupport,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $ventas = $this->cargarVentas();
        $asientos = Asiento::query()->whereIn('id', self::ASIENTO_IDS)->get(['id', 'numeroasiento', 'fecha', 'observacion']);
        $rendg = [];
        foreach (self::JORNADA_IDS_POST_CIERRE as $jornadaId) {
            $cabs = $this->rendgastroSupport->listarCabecerasPostCierrePorJornada(self::EMPRESA_ID, $jornadaId);
            $rendg[$jornadaId] = array_map(static fn (object $c): array => [
                'nro_oper' => (int) ($c->rendg_nro_oper ?? 0),
                'fecha' => (int) ($c->rendg_fecha ?? 0),
                'total_x' => (float) ($c->rendg_total_x ?? 0),
            ], $cabs);
        }

        return [
            'fecha_destino' => self::FECHA_JORNADA,
            'renumeracion' => array_map(static fn (Venta $v): array => [
                'venta_id' => (int) $v->id,
                'fc_actual' => (int) $v->numerocomprobante,
                'fc_nuevo' => self::RENUMERACION[(int) $v->id],
                'jornada_actual' => (string) $v->fechajornada,
                'total' => round((float) $v->total, 2),
            ], $ventas->all()),
            'asientos' => $asientos->map(static fn (Asiento $a): array => [
                'id' => (int) $a->id,
                'numeroasiento' => (string) $a->numeroasiento,
                'fecha_actual' => substr((string) $a->fecha, 0, 10),
            ])->values()->all(),
            'rendgastro_post_cierre' => $rendg,
        ];
    }

    /**
     * Reasigna observación jornada 23/06 en asientos agregados (idempotente).
     *
     * @return array<string, mixed>
     */
    public function realinearObservacionAsientosAgregados(bool $dryRun = true): array
    {
        $pasos = [];
        foreach (self::ASIENTO_IDS as $asientoId) {
            $asiento = Asiento::query()->find($asientoId);
            if ($asiento === null) {
                continue;
            }
            $nueva = self::OBSERVACION_AGREGADOS[$asientoId] ?? '';
            $pasos[] = [
                'asiento_id' => $asientoId,
                'observacion_actual' => (string) $asiento->observacion,
                'observacion_nueva' => $nueva,
            ];
            if (! $dryRun && $nueva !== '' && trim((string) $asiento->observacion) !== $nueva) {
                $this->actualizarObservacionAsientoAgregados($asientoId);
            }
        }

        return ['dry_run' => $dryRun, 'pasos' => $pasos];
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(bool $dryRun = true, bool $regrabarRendgJornada24 = true): array
    {
        $preview = $this->preview();
        $resultado = ['dry_run' => $dryRun, 'pasos' => []];

        if ($dryRun) {
            $resultado['preview'] = $preview;
            $resultado['mensaje'] = 'Simulación: use --aplicar para ejecutar.';

            return $resultado;
        }

        $ventas = $this->cargarVentas();

        DB::transaction(function () use ($ventas, $regrabarRendgJornada24, &$resultado): void {
            if ($regrabarRendgJornada24) {
                $j24 = JornadaGastronomia::query()->findOrFail(24);
                $cabs24 = $this->rendgastroSupport->listarCabecerasPostCierrePorJornada(self::EMPRESA_ID, 24);
                if ($cabs24 === []) {
                    $regrab = $this->rendicionReparacionService->regrabarJornada($j24, false);
                    $resultado['pasos'][] = ['regrabar_jornada_24' => $regrab['accion'] ?? '?'];
                }
            }

            foreach (self::JORNADA_IDS_POST_CIERRE as $jornadaId) {
                foreach ($this->rendgastroSupport->listarCabecerasPostCierrePorJornada(self::EMPRESA_ID, $jornadaId) as $cab) {
                    $nroOper = (int) ($cab->rendg_nro_oper ?? 0);
                    if ($nroOper <= 0) {
                        continue;
                    }
                    $this->actualizarFechaRendgastro($nroOper);
                    $resultado['pasos'][] = [
                        'rendgastro' => $nroOper,
                        'jornada_id' => $jornadaId,
                        'fecha_nueva' => self::FECHA_ENTERA,
                    ];
                }
            }

            foreach (self::ASIENTO_IDS as $asientoId) {
                $this->moverAsientoAFecha($asientoId);
                $this->actualizarObservacionAsientoAgregados($asientoId);
                $resultado['pasos'][] = [
                    'asiento_id' => $asientoId,
                    'fecha_nueva' => self::FECHA_JORNADA,
                    'observacion' => self::OBSERVACION_AGREGADOS[$asientoId] ?? '',
                ];
            }

            foreach ($ventas as $venta) {
                $nuevo = self::RENUMERACION[(int) $venta->id] ?? 0;
                if ($nuevo <= 0) {
                    throw new InvalidArgumentException('Sin numeración destino para venta #'.$venta->id);
                }
                $codigoNuevo = VentaNumeracionEmpresaSupport::formatearCodigoVenta('FAC', 'B', '00030', $nuevo);
                Venta::query()->whereKey($venta->id)->update([
                    'numerocomprobante' => $nuevo,
                    'codigo' => $codigoNuevo,
                    'fechajornada' => self::FECHA_JORNADA,
                    'fecha' => self::FECHA_JORNADA,
                ]);
                Cobranza::query()->where('venta_id', $venta->id)->update(['fecha' => self::FECHA_JORNADA]);
                $resultado['pasos'][] = [
                    'venta_id' => (int) $venta->id,
                    'fc' => (int) $venta->numerocomprobante.'→'.$nuevo,
                    'jornada' => self::FECHA_JORNADA,
                ];
            }
        });

        $resultado['mensaje'] = 'Corrección post-cierre Waitry aplicada.';
        $resultado['rendgastro'] = $this->agregadosCaeaSupport->sincronizarRendgastroAnita();

        return $resultado;
    }

    /** @return \Illuminate\Support\Collection<int, Venta> */
    private function cargarVentas()
    {
        $ventas = Venta::query()
            ->whereIn('id', array_keys(self::RENUMERACION))
            ->orderBy('numerocomprobante')
            ->get();

        if ($ventas->count() !== count(self::RENUMERACION)) {
            throw new InvalidArgumentException('Faltan ventas post-cierre Waitry en ERP.');
        }

        return $ventas;
    }

    private function moverAsientoAFecha(int $asientoId): void
    {
        $asiento = Asiento::query()->with(['asiento_movimientos.monedas'])->findOrFail($asientoId);
        $fechaActual = substr((string) $asiento->fecha, 0, 10);
        if ($fechaActual === self::FECHA_JORNADA) {
            return;
        }

        $asiento->fecha = self::FECHA_JORNADA;
        $asiento->save();

        $payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
        $this->asientoRepository->sincronizarCtamovAnita($payload);
    }

    private function actualizarObservacionAsientoAgregados(int $asientoId): void
    {
        $nueva = self::OBSERVACION_AGREGADOS[$asientoId] ?? null;
        if ($nueva === null) {
            return;
        }

        $asiento = Asiento::query()->findOrFail($asientoId);
        if (trim((string) $asiento->observacion) === $nueva) {
            return;
        }

        $asiento->observacion = $nueva;
        $asiento->save();
    }

    private function actualizarFechaRendgastro(int $nroOper): void
    {
        $fechaEntera = self::FECHA_ENTERA;
        $alfa = RendicionAnitaFechaAlfaSupport::desdeFechaEntera($fechaEntera);
        $tipoOper = (string) config('rendicion_gastronomia_anita.tipo_oper', 'F');
        $api = new ApiAnita();

        $respuesta = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_cabecera', 'rendgastro'),
            'valores' => " rendg_fecha = '".$fechaEntera."', rendg_fecha_alfa = '".$alfa."' ",
            'whereArmado' => " WHERE rendg_empresa = '".self::EMPRESA_ID."' AND rendg_nro_oper = '".$nroOper."' ",
        ], 'rendgastro update fecha post-cierre');

        if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuesta)) {
            throw new RuntimeException(
                'No se pudo actualizar rendgastro #'.$nroOper.': '.(ApiAnita::extraerMensajeError($respuesta) ?? $respuesta),
            );
        }

        $respValor = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_valor', 'rendvalor'),
            'valores' => ' rendv_fecha = '.$fechaEntera.' ',
            'whereArmado' => " WHERE rendv_nro_oper = '".$nroOper."' AND rendv_tipo_oper = '".$tipoOper."' ",
        ], 'rendvalor update fecha post-cierre');

        if (! ApiAnita::respuestaBridgeEscrituraExitosa($respValor)) {
            throw new RuntimeException(
                'No se pudo actualizar rendvalor #'.$nroOper.': '.(ApiAnita::extraerMensajeError($respValor) ?? $respValor),
            );
        }
    }
}
