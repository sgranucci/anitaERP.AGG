<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Herramientas de diagnóstico y corrección de turnos / facturas huérfanas (solo uso administrativo).
 */
final class GastronomiaTurnoSaneamientoService
{
    public const PREFIJO_CONFIRMACION_CIERRE_CUENTAS = 'CERRAR-';

    public function __construct(
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
        private readonly GastronomiaCuentaService $cuentaService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostico(int $empresaId, ?string $identificadorPc = null): array
    {
        $jornada = $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null) {
            $jornada = JornadaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->orderByDesc('id')
                ->first();
        }

        if ($jornada === null) {
            return [
                'ok' => false,
                'error' => 'No hay jornada registrada para esta empresa.',
            ];
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $pcs = $this->terminalesParaEmpresa($empresaId, $identificadorPc);
        $terminales = [];

        foreach ($pcs as $pc) {
            $terminales[] = $this->diagnosticoTerminal($empresaId, $pc, $jornada, $fechaJornada);
        }

        $empresa = Empresa::query()->find($empresaId);

        return [
            'ok' => true,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresa?->nombre ?? '',
            'jornada' => [
                'id' => (int) $jornada->id,
                'abierta' => $jornada->cierre_en === null,
                'fecha_jornada' => $fechaJornada,
                'fecha_jornada_fmt' => $jornada->fecha_jornada?->format('d/m/Y') ?? $fechaJornada,
            ],
            'requiere_habilitacion_turno' => GastronomiaTurnoOperativoService::requiereHabilitacionTurno(),
            'terminales' => $terminales,
        ];
    }

    /**
     * Extiende el cierre del turno cerrado para incluir facturas huérfanas posteriores a su habilitación.
     *
     * @return array{mensaje:string, turno_operativo_id:int, cierre_en:string, facturas_cubiertas:int}
     */
    public function extenderCierreParaCubrirHuerfanas(int $turnoOperativoId): array
    {
        $turno = TurnoOperativoGastronomia::query()
            ->with(['turno', 'jornada'])
            ->findOrFail($turnoOperativoId);

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_CERRADO) {
            throw new InvalidArgumentException('Solo puede extender el cierre de un turno ya cerrado.');
        }

        if ($turno->habilitacion_en === null || $turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno no tiene fechas de habilitación/cierre.');
        }

        $pc = (string) $turno->identificador_pc;
        $empresaId = (int) $turno->empresa_id;
        $jornadaId = (int) $turno->jornada_gastronomia_id;
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        $huerfanas = $this->huerfanasAsignablesATurno($turno);
        if ($huerfanas === []) {
            throw new InvalidArgumentException(
                'No hay facturas huérfanas asignables a este turno (revise el diagnóstico).'
            );
        }

        $maxTs = collect($huerfanas)->max(fn (array $h) => $h['emitido_en']);
        $nuevoCierre = Carbon::parse((string) $maxTs);
        $siguiente = $this->siguienteTurnoCerradoOHabilitado($pc, $jornadaId, $turno->habilitacion_en);

        if ($siguiente !== null && $siguiente->habilitacion_en !== null) {
            $limite = $siguiente->habilitacion_en->copy()->subSecond();
            if ($nuevoCierre->gt($limite)) {
                $nuevoCierre = $limite;
            }
        }

        if ($nuevoCierre->lte($turno->habilitacion_en)) {
            throw new InvalidArgumentException('La nueva hora de cierre no puede ser anterior a la habilitación del turno.');
        }

        if ($nuevoCierre->lte($turno->cierre_en)) {
            throw new InvalidArgumentException('El cierre actual del turno ya cubre esas facturas.');
        }

        $cierreAnterior = $turno->cierre_en->copy();

        return DB::transaction(function () use (
            $turno,
            $nuevoCierre,
            $pc,
            $empresaId,
            $fechaJornada,
            $huerfanas,
            $cierreAnterior,
        ) {
            $nota = '[Saneamiento '.now()->format('Y-m-d H:i').' user '.(Auth::id() ?? 0)
                .'] Cierre extendido para cubrir '.count($huerfanas).' factura(s) huérfana(s).';
            $obs = trim((string) $turno->observacion_cierre);
            $obs = $obs === '' ? $nota : $obs."\n".$nota;

            $huerfanasRecienCubiertas = $this->filtrarFacturasEmitidasDespuesDe($huerfanas, $cierreAnterior);
            $montoIncremental = $this->sumarTotalFacturas($huerfanasRecienCubiertas);
            $montoTurno = round((float) ($turno->monto_facturacion_turno ?? 0) + $montoIncremental, 2);

            $turno->update([
                'cierre_en' => $nuevoCierre,
                'observacion_cierre' => mb_substr($obs, 0, 2000),
                'monto_facturacion_turno' => $montoTurno,
                'monto_facturacion_dia' => $this->calcularMontoFacturacionDia(
                    $pc,
                    $empresaId,
                    $fechaJornada,
                ),
            ]);

            $restantes = GastronomiaTurnoOperativoTotalesSupport::facturasHuerfanasDelDia(
                $pc,
                $empresaId,
                $fechaJornada,
                (int) $turno->jornada_gastronomia_id,
            );

            return [
                'mensaje' => 'Cierre del turno #'.$turno->id.' extendido a '.$nuevoCierre->format('d/m/Y H:i:s')
                    .'. Facturas cubiertas: '.count($huerfanas)
                    .($restantes['cantidad'] > 0 ? '. Aún quedan '.$restantes['cantidad'].' huérfana(s).' : '.'),
                'turno_operativo_id' => (int) $turno->id,
                'cierre_en' => $nuevoCierre->format('Y-m-d H:i:s'),
                'facturas_cubiertas' => count($huerfanas),
                'facturas_huerfanas_restantes' => $restantes['cantidad'],
            ];
        });
    }

    /**
     * Crea un turno operativo ya cerrado que cubre el rango de facturas huérfanas (sin turno previo).
     *
     * @return array{mensaje:string, turno_operativo_id:int}
     */
    public function crearTurnoRetroactivoCerrado(
        ConfiguracionPuntoventaGastronomia $cfg,
        string $identificadorPc,
        int $turnoGastronomiaId,
        float $montoHabilitacion = 0.,
        ?string $observacion = null,
    ): array {
        if (! GastronomiaTurnoOperativoService::requiereHabilitacionTurno()) {
            throw new InvalidArgumentException('La habilitación de turno no está activa en configuración.');
        }

        $empresaId = (int) $cfg->empresa_id;
        $jornada = $this->jornadaService->jornadaAbierta($empresaId)
            ?? JornadaGastronomia::query()->where('empresa_id', $empresaId)->orderByDesc('id')->first();

        if ($jornada === null) {
            throw new InvalidArgumentException('No hay jornada para esta empresa.');
        }

        if ($this->turnoOperativoService->turnoHabilitadoEnPc($identificadorPc) !== null) {
            throw new InvalidArgumentException('Hay un turno habilitado en esta terminal. Ciérrelo antes de crear uno retroactivo.');
        }

        $turnoMaestro = TurnoGastronomia::query()
            ->where('id', $turnoGastronomiaId)
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if ($turnoMaestro === null) {
            throw new InvalidArgumentException('Turno maestro inválido.');
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $huerfanas = GastronomiaTurnoOperativoTotalesSupport::listarFacturasHuerfanasDelDia(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            (int) $jornada->id,
        );

        if ($huerfanas === []) {
            throw new InvalidArgumentException('No hay facturas huérfanas en esta terminal para la jornada.');
        }

        $minTs = collect($huerfanas)->min(fn (array $h) => $h['emitido_en']);
        $maxTs = collect($huerfanas)->max(fn (array $h) => $h['emitido_en']);
        $habilitacion = Carbon::parse((string) $minTs)->subMinute();
        $cierre = Carbon::parse((string) $maxTs);

        if (Auth::id() === null) {
            throw new InvalidArgumentException('Usuario no autenticado.');
        }

        return DB::transaction(function () use (
            $cfg,
            $identificadorPc,
            $jornada,
            $turnoMaestro,
            $montoHabilitacion,
            $observacion,
            $habilitacion,
            $cierre,
            $empresaId,
            $fechaJornada,
            $huerfanas,
        ) {
            $nota = '[Saneamiento retroactivo '.now()->format('Y-m-d H:i').'] Cubre '
                .count($huerfanas).' factura(s) huérfana(s).';
            $obs = trim((string) $observacion);
            $obs = $obs === '' ? $nota : $obs."\n".$nota;

            $turno = TurnoOperativoGastronomia::query()->create([
                'empresa_id' => $empresaId,
                'jornada_gastronomia_id' => (int) $jornada->id,
                'turno_gastronomia_id' => (int) $turnoMaestro->id,
                'configuracion_puntoventa_gastronomia_id' => (int) $cfg->id,
                'identificador_pc' => $identificadorPc,
                'estado' => TurnoOperativoGastronomia::ESTADO_CERRADO,
                'usuario_habilitacion_id' => (int) Auth::id(),
                'usuario_habilitado_id' => (int) Auth::id(),
                'monto_habilitacion' => round(max(0, $montoHabilitacion), 2),
                'observacion_habilitacion' => mb_substr($obs, 0, 2000),
                'habilitacion_en' => $habilitacion,
                'usuario_cierre_id' => (int) Auth::id(),
                'cierre_en' => $cierre,
                'observacion_cierre' => mb_substr($obs, 0, 2000),
            ]);

            $this->recalcularMontosTurnoCerrado($turno->fresh(['jornada']));

            $restantes = GastronomiaTurnoOperativoTotalesSupport::facturasHuerfanasDelDia(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                (int) $jornada->id,
            );

            return [
                'mensaje' => 'Turno retroactivo #'.$turno->id.' creado ('
                    .$habilitacion->format('d/m/Y H:i').' – '.$cierre->format('d/m/Y H:i').').'
                    .($restantes['cantidad'] > 0 ? ' Quedan '.$restantes['cantidad'].' huérfana(s).' : ''),
                'turno_operativo_id' => (int) $turno->id,
                'facturas_huerfanas_restantes' => $restantes['cantidad'],
            ];
        });
    }

    /**
     * @return array{mensaje:string}
     */
    public function recalcularMontosTurnoCerrado(TurnoOperativoGastronomia $turno): array
    {
        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_CERRADO) {
            throw new InvalidArgumentException('Solo aplica a turnos cerrados.');
        }

        $turno->loadMissing('jornada');
        $pc = (string) $turno->identificador_pc;
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
            $pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
            $turno->cierre_en,
        );

        $turno->update([
            'monto_facturacion_turno' => $totalesTurno['total_general'],
            'monto_facturacion_dia' => $this->calcularMontoFacturacionDia(
                $pc,
                (int) $turno->empresa_id,
                $fechaJornada,
            ),
        ]);

        return [
            'mensaje' => 'Totales del turno #'.$turno->id.' recalculados ($ '
                .number_format($totalesTurno['total_general'], 2, ',', '.').').',
        ];
    }

    public static function textoConfirmacionCierreCuentas(int $cantidad): string
    {
        return self::PREFIJO_CONFIRMACION_CIERRE_CUENTAS.$cantidad;
    }

    /**
     * Cierra sin facturar las cuentas abiertas de la terminal (requiere frase de confirmación).
     *
     * @return array{
     *   mensaje:string,
     *   cerradas_abiertas:int,
     *   siguen_cerradas_sin_facturar:int,
     *   detalle:list<array{id:int, etiqueta:string, estado:string, accion:string}>
     * }
     */
    public function cerrarCuentasPendientesEnTerminal(
        int $turnoOperativoId,
        string $confirmacion,
        ?string $motivo = null,
    ): array {
        $turno = TurnoOperativoGastronomia::query()
            ->with(['turno', 'jornada'])
            ->findOrFail($turnoOperativoId);

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_HABILITADO) {
            throw new InvalidArgumentException('El turno operativo debe estar habilitado en la terminal.');
        }

        $cuentas = $this->turnoOperativoService->listarCuentasSinFacturarEnTerminal($turno);
        if ($cuentas->isEmpty()) {
            throw new InvalidArgumentException('No hay cuentas pendientes en esta terminal.');
        }

        $esperado = self::textoConfirmacionCierreCuentas($cuentas->count());
        if (trim($confirmacion) !== $esperado) {
            throw new InvalidArgumentException(
                'Confirmación incorrecta. Debe escribir exactamente: '.$esperado
            );
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('Usuario no autenticado.');
        }

        $motivoTxt = trim((string) $motivo);
        $notaSaneamiento = '[Saneamiento '.now()->format('Y-m-d H:i').' user '.Auth::id().']'
            .($motivoTxt !== '' ? ' '.$motivoTxt : ' Cierre administrativo sin facturar.');

        return DB::transaction(function () use ($cuentas, $notaSaneamiento) {
            $detalle = [];
            $cerradas = 0;

            foreach ($cuentas as $cuenta) {
                $etiqueta = $this->etiquetaCuenta($cuenta);
                if ($cuenta->estado === CuentaGastronomia::ESTADO_ABIERTA) {
                    $this->cuentaService->cerrarSinFacturar($cuenta);
                    $cerradas++;
                    $detalle[] = [
                        'id' => (int) $cuenta->id,
                        'etiqueta' => $etiqueta,
                        'estado' => 'cerrada',
                        'accion' => 'Cerrada sin facturar',
                    ];
                } else {
                    $detalle[] = [
                        'id' => (int) $cuenta->id,
                        'etiqueta' => $etiqueta,
                        'estado' => $cuenta->estado,
                        'accion' => 'Ya estaba cerrada; requiere facturación o gestión manual',
                    ];
                }
            }

            $siguen = (int) CuentaGastronomia::query()
                ->whereIn('id', $cuentas->pluck('id'))
                ->where('estado', CuentaGastronomia::ESTADO_CERRADA)
                ->count();

            return [
                'mensaje' => 'Se cerraron '.$cerradas.' cuenta(s) abierta(s). '
                    .($siguen > 0
                        ? 'Quedan '.$siguen.' en estado cerrada sin facturar (bloquean cierre del último turno).'
                        : 'No quedan cuentas pendientes en esta terminal.')
                    .' '.$notaSaneamiento,
                'cerradas_abiertas' => $cerradas,
                'siguen_cerradas_sin_facturar' => $siguen,
                'detalle' => $detalle,
            ];
        });
    }

    /**
     * @return list<string>
     */
    private function terminalesParaEmpresa(int $empresaId, ?string $filtroPc): array
    {
        if ($filtroPc !== null && $filtroPc !== '') {
            return [$filtroPc];
        }

        return ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('identificador_pc')
            ->pluck('identificador_pc')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosticoTerminal(
        int $empresaId,
        string $pc,
        JornadaGastronomia $jornada,
        string $fechaJornada,
    ): array {
        $jornadaId = (int) $jornada->id;
        $huerfanas = GastronomiaTurnoOperativoTotalesSupport::listarFacturasHuerfanasDelDia(
            $pc,
            $empresaId,
            $fechaJornada,
            $jornadaId,
        );

        $turnos = TurnoOperativoGastronomia::query()
            ->with('turno')
            ->where('identificador_pc', $pc)
            ->where('jornada_gastronomia_id', $jornadaId)
            ->orderBy('habilitacion_en')
            ->get();

        $activo = $turnos->firstWhere('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO);
        $cerrados = $turnos->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)->values();

        $cuentasDetalle = [];
        $cuentasPendientes = 0;
        $confirmacionCierre = null;
        if ($activo !== null) {
            $cuentas = $this->turnoOperativoService->listarCuentasSinFacturarEnTerminal($activo);
            $cuentasPendientes = $cuentas->count();
            $cuentasDetalle = $cuentas->map(fn (CuentaGastronomia $c) => $this->mapearCuentaPendiente($c))->all();
            if ($cuentasPendientes > 0) {
                $confirmacionCierre = self::textoConfirmacionCierreCuentas($cuentasPendientes);
            }
        }

        $sugerencias = [];
        foreach ($cerrados as $t) {
            $asignables = $this->huerfanasAsignablesATurno($t);
            if ($asignables !== []) {
                $sugerencias[] = [
                    'accion' => 'extender_cierre',
                    'turno_operativo_id' => (int) $t->id,
                    'turno_nombre' => $t->turno?->nombre,
                    'facturas' => count($asignables),
                    'detalle' => 'Extender cierre del turno #'.$t->id.' para cubrir '.count($asignables).' factura(s).',
                ];
            }
        }

        if ($huerfanas !== [] && $sugerencias === [] && $activo === null) {
            $sugerencias[] = [
                'accion' => 'crear_retroactivo',
                'detalle' => 'Crear un turno cerrado retroactivo que abarque todas las huérfanas.',
            ];
        }

        if ($activo !== null && $cuentasPendientes > 0) {
            $abiertas = collect($cuentasDetalle)->where('estado', CuentaGastronomia::ESTADO_ABIERTA)->count();
            $sugerencias[] = [
                'accion' => 'cerrar_cuentas',
                'turno_operativo_id' => (int) $activo->id,
                'cantidad' => $cuentasPendientes,
                'cantidad_abiertas' => $abiertas,
                'confirmacion' => $confirmacionCierre,
                'detalle' => 'Cerrar sin facturar las cuentas abiertas de la terminal ('
                    .$abiertas.' abierta(s), '
                    .($cuentasPendientes - $abiertas).' ya cerrada(s) sin facturar).',
            ];
        }

        return [
            'identificador_pc' => $pc,
            'turno_habilitado' => $activo !== null,
            'turno_operativo_activo_id' => $activo?->id,
            'facturas_huerfanas' => $huerfanas,
            'cantidad_huerfanas' => count($huerfanas),
            'cuentas_pendientes' => $cuentasPendientes,
            'cuentas_pendientes_detalle' => $cuentasDetalle,
            'confirmacion_cierre_cuentas' => $confirmacionCierre,
            'turnos' => $cerrados->map(fn (TurnoOperativoGastronomia $t) => [
                'id' => (int) $t->id,
                'turno_nombre' => $t->turno?->nombre,
                'estado' => $t->estado,
                'habilitacion_en' => $t->habilitacion_en?->format('Y-m-d H:i:s'),
                'cierre_en' => $t->cierre_en?->format('Y-m-d H:i:s'),
                'monto_facturacion_turno' => (float) $t->monto_facturacion_turno,
            ])->all(),
            'sugerencias' => $sugerencias,
            'puede_habilitar_turno' => $this->puedeHabilitarTurnoEnTerminal($empresaId, $pc, $jornada, $activo),
        ];
    }

    private function calcularMontoFacturacionDia(string $pc, int $empresaId, string $fechaJornada): float
    {
        return GastronomiaTurnoOperativoTotalesSupport::calcular(
            $pc,
            $empresaId,
            $fechaJornada,
            null,
        )['total_general'];
    }

    /**
     * @param  list<array{venta_id:int, codigo:string, hora:string, emitido_en:string, total:float, cliente:string}>  $facturas
     * @return list<array{venta_id:int, codigo:string, hora:string, emitido_en:string, total:float, cliente:string}>
     */
    private function filtrarFacturasEmitidasDespuesDe(array $facturas, Carbon $desde): array
    {
        return array_values(array_filter(
            $facturas,
            fn (array $h) => Carbon::parse((string) $h['emitido_en'])->gt($desde),
        ));
    }

    /**
     * @param  list<array{venta_id:int, codigo:string, hora:string, emitido_en:string, total:float, cliente:string}>  $facturas
     */
    private function sumarTotalFacturas(array $facturas): float
    {
        return round(collect($facturas)->sum(fn (array $h) => (float) ($h['total'] ?? 0)), 2);
    }

    /**
     * Huérfanas emitidas después de la habilitación del turno y antes del siguiente turno (si existe).
     *
     * @return list<array{venta_id:int, codigo:string, hora:string, emitido_en:string, total:float, cliente:string}>
     */
    private function huerfanasAsignablesATurno(TurnoOperativoGastronomia $turno): array
    {
        $turno->loadMissing('jornada');
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $todas = GastronomiaTurnoOperativoTotalesSupport::listarFacturasHuerfanasDelDia(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            (int) $turno->jornada_gastronomia_id,
        );

        $siguiente = $this->siguienteTurnoCerradoOHabilitado(
            (string) $turno->identificador_pc,
            (int) $turno->jornada_gastronomia_id,
            $turno->habilitacion_en,
        );
        $limiteSuperior = $siguiente?->habilitacion_en;

        $out = [];
        foreach ($todas as $h) {
            $ts = Carbon::parse($h['emitido_en']);
            if ($ts->lt($turno->habilitacion_en)) {
                continue;
            }
            if ($limiteSuperior !== null && $ts->gte($limiteSuperior)) {
                continue;
            }
            $out[] = $h;
        }

        return $out;
    }

    private function puedeHabilitarTurnoEnTerminal(
        int $empresaId,
        string $pc,
        JornadaGastronomia $jornada,
        ?TurnoOperativoGastronomia $activo,
    ): bool {
        if ($activo !== null || ! GastronomiaTurnoOperativoService::requiereHabilitacionTurno()) {
            return false;
        }

        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('identificador_pc', $pc)
            ->first();

        if ($cfg === null) {
            return false;
        }

        return $this->turnoOperativoService->erroresAntesDeHabilitar($cfg, $pc, $jornada) === []
            && $this->turnoOperativoService->hayTurnoMaestroPendienteDeHabilitar(
                $empresaId,
                (int) $jornada->id,
                $pc,
            );
    }

    /**
     * @return array{
     *   id:int,
     *   tipo:string,
     *   etiqueta:string,
     *   estado:string,
     *   estado_etiqueta:string,
     *   apertura_en:?string,
     *   apertura_en_fmt:string,
     *   mozo:?string,
     *   lineas:int,
     *   tiene_items:bool,
     *   subtotal:float,
     *   acceso_facturador:string
     * }
     */
    private function mapearCuentaPendiente(CuentaGastronomia $cuenta): array
    {
        $subtotal = 0.0;
        foreach ($cuenta->lineas as $linea) {
            $subtotal += (float) $linea->cantidad * (float) $linea->precio_unitario;
        }

        $lineas = $cuenta->lineas->count();

        return [
            'id' => (int) $cuenta->id,
            'tipo' => (string) $cuenta->tipo,
            'etiqueta' => $this->etiquetaCuenta($cuenta),
            'estado' => (string) $cuenta->estado,
            'estado_etiqueta' => $this->etiquetaEstadoCuentaPendiente($cuenta),
            'apertura_en' => $cuenta->created_at?->format('Y-m-d H:i:s'),
            'apertura_en_fmt' => $cuenta->created_at?->format('d/m/Y H:i') ?? '—',
            'mozo' => $cuenta->mozo?->nombre,
            'lineas' => $lineas,
            'tiene_items' => $lineas > 0,
            'subtotal' => round($subtotal, 2),
            'acceso_facturador' => $this->notaAccesoFacturadorCuentaPendiente($cuenta),
        ];
    }

    private function etiquetaEstadoCuentaPendiente(CuentaGastronomia $cuenta): string
    {
        return match ($cuenta->estado) {
            CuentaGastronomia::ESTADO_ABIERTA => 'Abierta',
            CuentaGastronomia::ESTADO_CERRADA => 'Cerrada sin facturar',
            default => (string) $cuenta->estado,
        };
    }

    private function notaAccesoFacturadorCuentaPendiente(CuentaGastronomia $cuenta): string
    {
        return match ($cuenta->estado) {
            CuentaGastronomia::ESTADO_ABIERTA => 'Visible en mesas/cuentas del facturador.',
            CuentaGastronomia::ESTADO_CERRADA => 'No aparece en el facturador (mesa no ocupada). Gestionar desde saneamiento.',
            default => '',
        };
    }

    private function etiquetaCuenta(CuentaGastronomia $cuenta): string
    {
        if ($cuenta->tipo === CuentaGastronomia::TIPO_MESA && $cuenta->mesa) {
            $mesaLbl = trim((string) ($cuenta->mesa->codigo ?? $cuenta->mesa->numeromesa ?? $cuenta->mesa->nombre ?? ''));

            return 'Mesa '.($mesaLbl !== '' ? $mesaLbl : '#'.$cuenta->mesa_gastronomia_id).' (cuenta #'.$cuenta->id.')';
        }

        return 'Cuenta libre #'.$cuenta->id
            .($cuenta->identificador_pc ? ' ('.$cuenta->identificador_pc.')' : '');
    }

    private function siguienteTurnoCerradoOHabilitado(
        string $pc,
        int $jornadaId,
        Carbon $habilitacionActual,
    ): ?TurnoOperativoGastronomia {
        return TurnoOperativoGastronomia::query()
            ->where('identificador_pc', $pc)
            ->where('jornada_gastronomia_id', $jornadaId)
            ->where('habilitacion_en', '>', $habilitacionActual)
            ->orderBy('habilitacion_en')
            ->first();
    }
}
