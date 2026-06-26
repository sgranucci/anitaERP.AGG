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
        $idsCubiertas = [];

        foreach ($pcs as $pc) {
            $term = $this->diagnosticoTerminal($empresaId, $pc, $jornada, $fechaJornada);
            $terminales[] = $term;
            foreach ($term['cuentas_pendientes_detalle'] as $det) {
                $idsCubiertas[(int) $det['id']] = true;
            }
        }

        // Si se filtró por una sola terminal, no agregamos el bucket de huérfanas globales
        // (sería ruido fuera del scope del filtro).
        $mostrarHuerfanasDeTerminal = ($identificadorPc === null || $identificadorPc === '');
        if ($mostrarHuerfanasDeTerminal) {
            $bucketHuerfano = $this->diagnosticoCuentasNoCubiertas(
                $empresaId,
                array_keys($idsCubiertas),
            );
            if ($bucketHuerfano !== null) {
                $terminales[] = $bucketHuerfano;
            }
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
            'turnos_habilitados_remoto' => $this->turnoOperativoService->listarTurnosHabilitadosParaCierreRemoto(
                $empresaId,
                $jornada,
            ),
            'terminales' => $terminales,
        ];
    }

    /**
     * Cierra un turno habilitado en otra terminal (PC inoperativa, cierre de jornada, etc.).
     *
     * @return array{mensaje:string, turno_operativo_id:int, url_comprobante_pdf:string}
     */
    public function cerrarTurnoRemoto(
        int $turnoOperativoId,
        string $pcOperador,
        ?string $observacion = null,
        ?float $redondeoInvitaciones = null,
        ?float $redondeoTurno = null,
        ?float $sobranteFaltante = null,
        mixed $mediosContado = null,
        bool $usarAjustesAutomaticos = false,
    ): array {
        if (! GastronomiaTurnoOperativoService::requiereHabilitacionTurno()) {
            throw new InvalidArgumentException('La habilitación de turno no está activa en configuración.');
        }

        $pcOperador = trim($pcOperador);
        if ($pcOperador === '') {
            throw new InvalidArgumentException('Indique la terminal desde la que opera el cierre remoto.');
        }

        $turno = TurnoOperativoGastronomia::query()
            ->with(['jornada', 'turno'])
            ->find($turnoOperativoId);

        if ($turno === null) {
            throw new InvalidArgumentException('Turno operativo no encontrado.');
        }

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_HABILITADO) {
            throw new InvalidArgumentException('Solo puede cerrar remotamente un turno en estado habilitado.');
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $totalesPreview = GastronomiaTurnoOperativoTotalesSupport::calcular(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
        );

        $ajustes = $usarAjustesAutomaticos
            ? GastronomiaTurnoOperativoTotalesSupport::resolverAjustesCierreConSobranteFaltanteResidual(
                $totalesPreview,
                $redondeoInvitaciones,
                $redondeoTurno,
            )
            : [
                'redondeo_invitaciones' => $redondeoInvitaciones !== null
                    ? round($redondeoInvitaciones, 2)
                    : round((float) ($totalesPreview['redondeo_invitaciones_sugerido'] ?? 0), 2),
                'redondeo_turno' => round($redondeoTurno ?? 0.0, 2),
                'sobrante_faltante' => round($sobranteFaltante ?? 0.0, 2),
                'sobrante_faltante_auto' => false,
            ];

        $datosCierre = [
            'redondeo_invitaciones' => $ajustes['redondeo_invitaciones'],
            'redondeo_turno' => $ajustes['redondeo_turno'],
            'sobrante_faltante' => $ajustes['sobrante_faltante'],
            'observacion_cierre' => $observacion,
            'medios_contado' => $mediosContado,
        ];

        $cerrado = $this->turnoOperativoService->cerrar(
            $turno,
            (string) $turno->identificador_pc,
            $datosCierre,
            [
                'cierre_remoto' => true,
                'pc_operador' => $pcOperador,
                'omitir_validacion_jornada_posterior' => true,
            ],
        );

        $mensaje = 'Turno cerrado remotamente en terminal '.$turno->identificador_pc
            .' (desde '.$pcOperador.').';
        if ($ajustes['sobrante_faltante_auto']) {
            $sf = (float) $ajustes['sobrante_faltante'];
            $tipo = $sf >= 0 ? 'sobrante' : 'faltante';
            $mensaje .= ' Diferencia de conciliación imputada a '.$tipo.' ($ '
                .number_format(abs($sf), 2, ',', '.').'); puede corregirse anulando el cierre.';
        }

        return [
            'mensaje' => $mensaje,
            'turno_operativo_id' => (int) $cerrado->id,
            'numero_cierre' => (int) ($cerrado->numero_cierre ?? 0),
            'url_comprobante_pdf' => route('gastronomia_cierre_turno_comprobante_cierre', [
                'id' => $cerrado->id,
                'inline' => 1,
            ]),
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

        $abiertas = $this->turnoOperativoService->listarCuentasAbiertasEnTerminal($turno);

        return $this->procesarCierreCuentas(
            $abiertas,
            $confirmacion,
            $motivo,
            fn () => $this->turnoOperativoService->contarCuentasCerradasSinFacturarEnTerminal($turno),
        );
    }

    /**
     * Cierra sin facturar las cuentas abiertas de la terminal cuando NO hay un turno
     * operativo habilitado (típico cuando todos los turnos del día están cerrados pero
     * quedaron cuentas abiertas que bloquean el cierre de jornada). Identifica la terminal
     * por (empresa_id, identificador_pc) en vez de turno_operativo_id.
     *
     * @return array{
     *   mensaje:string,
     *   cerradas_abiertas:int,
     *   siguen_cerradas_sin_facturar:int,
     *   detalle:list<array{id:int, etiqueta:string, estado:string, accion:string}>
     * }
     */
    public function cerrarCuentasPendientesPorTerminal(
        int $empresaId,
        string $identificadorPc,
        string $confirmacion,
        ?string $motivo = null,
    ): array {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }
        if (trim($identificadorPc) === '') {
            throw new InvalidArgumentException('Indique el identificador de PC de la terminal.');
        }

        // Si justo hay un turno habilitado en esa terminal, delegamos al flujo "con turno"
        // (mantiene consistencia con auditoría / contadores).
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($identificadorPc);
        if ($activo !== null && (int) $activo->empresa_id === $empresaId) {
            return $this->cerrarCuentasPendientesEnTerminal(
                (int) $activo->id,
                $confirmacion,
                $motivo,
            );
        }

        $cfgId = $this->cfgIdParaTerminal($empresaId, $identificadorPc);
        $abiertas = $this->turnoOperativoService->listarCuentasAbiertasParaPuntoventa(
            $empresaId,
            $cfgId,
            $identificadorPc,
        );

        return $this->procesarCierreCuentas(
            $abiertas,
            $confirmacion,
            $motivo,
            fn () => $this->turnoOperativoService->listarCuentasSinFacturarParaPuntoventa(
                $empresaId,
                $cfgId,
                $identificadorPc,
            )->where('estado', CuentaGastronomia::ESTADO_CERRADA)->count(),
        );
    }

    /**
     * Cierre administrativo de cuentas identificadas por id. Usado por el bucket huérfano
     * cuando las cuentas no tienen `identificador_pc` válido para individualizarlas
     * (típico mesas abiertas vía `abrirMesa` con PV borrada/deshabilitada).
     *
     * @param  list<int>  $cuentaIds
     * @return array{
     *   mensaje:string,
     *   cerradas_abiertas:int,
     *   siguen_cerradas_sin_facturar:int,
     *   detalle:list<array{id:int, etiqueta:string, estado:string, accion:string}>
     * }
     */
    public function cerrarCuentasPendientesPorIds(
        int $empresaId,
        array $cuentaIds,
        string $confirmacion,
        ?string $motivo = null,
    ): array {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $cuentaIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            throw new InvalidArgumentException('Indique al menos una cuenta a cerrar (cuenta_ids).');
        }

        $abiertas = CuentaGastronomia::query()
            ->with(['mesa', 'mozo', 'lineas'])
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereIn('id', $ids)
            ->get();

        return $this->procesarCierreCuentas(
            $abiertas,
            $confirmacion,
            $motivo,
            fn () => CuentaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->where('estado', CuentaGastronomia::ESTADO_CERRADA)
                ->whereIn('id', $ids)
                ->count(),
        );
    }

    /**
     * Lógica común para cerrar cuentas abiertas (con/sin turno activo).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>  $abiertas
     * @param  callable():int  $contarCerradasHistoricas
     * @return array{
     *   mensaje:string,
     *   cerradas_abiertas:int,
     *   siguen_cerradas_sin_facturar:int,
     *   detalle:list<array{id:int, etiqueta:string, estado:string, accion:string}>
     * }
     */
    private function procesarCierreCuentas(
        $abiertas,
        string $confirmacion,
        ?string $motivo,
        callable $contarCerradasHistoricas,
    ): array {
        $abiertas->load('lineas');

        $conItems = $abiertas->filter(fn (CuentaGastronomia $c) => $c->lineas->count() > 0)->values();
        $vacias = $abiertas->filter(fn (CuentaGastronomia $c) => $c->lineas->count() === 0)->values();

        if ($conItems->isEmpty() && $vacias->isEmpty()) {
            throw new InvalidArgumentException(
                'No hay cuentas ABIERTAS para cerrar en esta terminal. '
                .'Las cuentas en estado "cerrada sin facturar" son estado terminal y no bloquean el cierre.'
            );
        }

        if ($conItems->isNotEmpty()) {
            $esperado = self::textoConfirmacionCierreCuentas($conItems->count());
            if (trim($confirmacion) !== $esperado) {
                throw new InvalidArgumentException(
                    'Confirmación incorrecta. Debe escribir exactamente: '.$esperado
                );
            }
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('Usuario no autenticado.');
        }

        $motivoTxt = trim((string) $motivo);
        $notaSaneamiento = '[Saneamiento '.now()->format('Y-m-d H:i').' user '.Auth::id().']'
            .($motivoTxt !== '' ? ' '.$motivoTxt : ' Cierre administrativo sin facturar.');

        return DB::transaction(function () use ($conItems, $vacias, $notaSaneamiento, $contarCerradasHistoricas) {
            $detalle = [];
            $cerradasConItems = 0;
            $cerradasVacias = 0;

            foreach ($conItems as $cuenta) {
                $this->cuentaService->cerrarSinFacturar($cuenta);
                $cerradasConItems++;
                $detalle[] = [
                    'id' => (int) $cuenta->id,
                    'etiqueta' => $this->etiquetaCuenta($cuenta),
                    'estado' => CuentaGastronomia::ESTADO_CERRADA,
                    'accion' => 'Cerrada sin facturar (tenía consumos)',
                ];
            }

            foreach ($vacias as $cuenta) {
                $this->cuentaService->cerrarSinFacturar($cuenta);
                $cerradasVacias++;
                $detalle[] = [
                    'id' => (int) $cuenta->id,
                    'etiqueta' => $this->etiquetaCuenta($cuenta),
                    'estado' => CuentaGastronomia::ESTADO_CERRADA,
                    'accion' => 'Descartada (sin ítems)',
                ];
            }

            $totalCerradas = $cerradasConItems + $cerradasVacias;
            $cerradasHistoricas = (int) $contarCerradasHistoricas();

            $mensaje = 'Se cerraron '.$totalCerradas.' cuenta(s) abierta(s) en esta terminal'
                .' ('.$cerradasConItems.' con consumos, '.$cerradasVacias.' sin ítems descartadas). '
                .'No quedan cuentas abiertas en esta terminal: '
                .'el cierre del turno y de la jornada ya no se bloquea por estas cuentas.';

            if ($cerradasHistoricas > 0) {
                $mensaje .= ' Quedan '.$cerradasHistoricas.' cuenta(s) en estado "cerrada sin facturar" '
                    .'(estado terminal, no bloquean el cierre; visibles aquí para auditoría).';
            }

            $mensaje .= ' '.$notaSaneamiento;

            return [
                'mensaje' => $mensaje,
                'cerradas_abiertas' => $totalCerradas,
                'cerradas_con_items' => $cerradasConItems,
                'descartadas_vacias' => $cerradasVacias,
                'siguen_cerradas_sin_facturar' => $cerradasHistoricas,
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

        return $this->terminalesConfiguradasParaEmpresa($empresaId);
    }

    /**
     * @return list<string>
     */
    private function terminalesConfiguradasParaEmpresa(int $empresaId): array
    {
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

        // Listamos cuentas pendientes de la terminal SIEMPRE (haya o no turno activo).
        // Antes solo se cargaban si existía $activo, ocultando cuentas abiertas cuando
        // todos los turnos del día ya estaban cerrados → bloqueaba el cierre de jornada
        // sin dejar al usuario saneamiento donde gestionarlas.
        $cfgId = $this->cfgIdParaTerminal($empresaId, $pc);
        $cuentas = $this->turnoOperativoService->listarCuentasSinFacturarParaPuntoventa(
            $empresaId,
            $cfgId,
            $pc,
        );

        $cuentasPendientes = $cuentas->count();
        $abiertas = $cuentas->where('estado', CuentaGastronomia::ESTADO_ABIERTA);
        $cuentasAbiertas = $abiertas->count();
        $cuentasAbiertasConItems = $abiertas->filter(fn (CuentaGastronomia $c) => $c->lineas->count() > 0)->count();
        $cuentasAbiertasVacias = $cuentasAbiertas - $cuentasAbiertasConItems;
        $cuentasCerradasSinFacturar = $cuentas->where('estado', CuentaGastronomia::ESTADO_CERRADA)->count();
        $cuentasDetalle = $cuentas->map(fn (CuentaGastronomia $c) => $this->mapearCuentaPendiente($c))->all();
        $confirmacionCierre = $cuentasAbiertasConItems > 0
            ? self::textoConfirmacionCierreCuentas($cuentasAbiertasConItems)
            : null;

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

        if ($cuentasAbiertasConItems > 0) {
            $detalleSugerencia = 'Cerrar sin facturar las '.$cuentasAbiertasConItems
                .' cuenta(s) abierta(s) con consumos de la terminal.';
            if ($cuentasAbiertasVacias > 0) {
                $detalleSugerencia .= ' (Hay '.$cuentasAbiertasVacias.' cuenta(s) abierta(s) sin ítems que se '
                    .'descartarán automáticamente al cerrar el último turno del día o la jornada.)';
            }
            if ($cuentasCerradasSinFacturar > 0) {
                $detalleSugerencia .= ' (Además hay '.$cuentasCerradasSinFacturar
                    .' cerrada(s) sin facturar — estado terminal, no bloquean el cierre.)';
            }
            if ($activo === null) {
                $detalleSugerencia .= ' No hay turno habilitado en esta terminal: el cierre administrativo '
                    .'se hará igualmente para que la jornada pueda cerrarse.';
            }

            $sugerencia = [
                'accion' => $activo !== null ? 'cerrar_cuentas' : 'cerrar_cuentas_sin_turno_activo',
                'cantidad' => $cuentasAbiertasConItems,
                'cantidad_abiertas' => $cuentasAbiertas,
                'cantidad_abiertas_con_items' => $cuentasAbiertasConItems,
                'cantidad_abiertas_vacias' => $cuentasAbiertasVacias,
                'cantidad_cerradas_sin_facturar' => $cuentasCerradasSinFacturar,
                'confirmacion' => $confirmacionCierre,
                'detalle' => $detalleSugerencia,
            ];
            if ($activo !== null) {
                $sugerencia['turno_operativo_id'] = (int) $activo->id;
                $sugerencias[] = [
                    'accion' => 'cerrar_turno_remoto',
                    'turno_operativo_id' => (int) $activo->id,
                    'identificador_pc' => $pc,
                    'turno_nombre' => $activo->turno?->nombre ?? '',
                    'detalle' => 'Cerrar el turno habilitado #'.$activo->id.' ('.($activo->turno?->nombre ?? '').') '
                        .'desde otra terminal si esta PC no responde.',
                ];
            } else {
                $sugerencia['empresa_id'] = $empresaId;
                $sugerencia['identificador_pc'] = $pc;
            }
            $sugerencias[] = $sugerencia;
        }

        return [
            'identificador_pc' => $pc,
            'turno_habilitado' => $activo !== null,
            'turno_operativo_activo_id' => $activo?->id,
            'facturas_huerfanas' => $huerfanas,
            'cantidad_huerfanas' => count($huerfanas),
            'cuentas_pendientes' => $cuentasPendientes,
            'cuentas_abiertas' => $cuentasAbiertas,
            'cuentas_abiertas_con_items' => $cuentasAbiertasConItems,
            'cuentas_abiertas_vacias' => $cuentasAbiertasVacias,
            'cuentas_cerradas_sin_facturar' => $cuentasCerradasSinFacturar,
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

    /**
     * Diagnóstico de cuentas que no quedaron cubiertas por ninguna terminal configurada
     * del loop anterior. Se muestran como una "terminal" pseudo (`es_bucket_huerfano = true`)
     * para que sigan siendo visibles en saneamiento y se puedan cerrar.
     *
     * Garantiza que `contarCuentasAbiertasConItemsPorEmpresa` (usado por cierre de jornada)
     * coincida con lo listado en saneamiento. Si no hay cuentas sobrantes, devuelve null.
     *
     * @param  list<int>  $idsCubiertas
     * @return array<string, mixed>|null
     */
    private function diagnosticoCuentasNoCubiertas(int $empresaId, array $idsCubiertas): ?array
    {
        $cuentas = $this->turnoOperativoService->listarCuentasNoFacturadasNoCubiertas(
            $empresaId,
            $idsCubiertas,
        );

        if ($cuentas->isEmpty()) {
            return null;
        }

        $abiertas = $cuentas->where('estado', CuentaGastronomia::ESTADO_ABIERTA);
        $cuentasAbiertasConItems = $abiertas->filter(fn (CuentaGastronomia $c) => $c->lineas->count() > 0)->count();
        $cuentasAbiertasVacias = $abiertas->count() - $cuentasAbiertasConItems;
        $cuentasCerradasSinFacturar = $cuentas->where('estado', CuentaGastronomia::ESTADO_CERRADA)->count();
        $cuentasDetalle = $cuentas->map(fn (CuentaGastronomia $c) => $this->mapearCuentaPendiente($c))->all();
        $confirmacionCierre = $cuentasAbiertasConItems > 0
            ? self::textoConfirmacionCierreCuentas($cuentasAbiertasConItems)
            : null;

        // Agrupamos por identificador_pc real para que el operador pueda cerrarlas por PC
        // cuando exista. Para cuentas con identificador_pc NULL/vacío (típico mesas
        // abiertas vía `abrirMesa` con PV ahora inexistente) emitimos una sugerencia que
        // identifica las cuentas por id, ya que (empresa_id, identificador_pc='') no
        // alcanza para individualizarlas.
        $sugerencias = [];
        $porPc = $cuentas->groupBy(fn (CuentaGastronomia $c) => (string) $c->identificador_pc);
        foreach ($porPc as $pcReal => $grupo) {
            $abiertasGrupo = $grupo->where('estado', CuentaGastronomia::ESTADO_ABIERTA);
            $conItemsGrupo = $abiertasGrupo->filter(fn (CuentaGastronomia $c) => $c->lineas->count() > 0);
            $cantConItems = $conItemsGrupo->count();
            if ($cantConItems <= 0) {
                continue;
            }
            $sugerencia = [
                'accion' => 'cerrar_cuentas_sin_turno_activo',
                'cantidad' => $cantConItems,
                'cantidad_abiertas_con_items' => $cantConItems,
                'cantidad_abiertas_vacias' => $abiertasGrupo->count() - $cantConItems,
                'confirmacion' => self::textoConfirmacionCierreCuentas($cantConItems),
                'empresa_id' => $empresaId,
                'identificador_pc' => (string) $pcReal,
            ];

            if ((string) $pcReal === '') {
                // Identificamos las cuentas por id (incluye vacías del grupo para que el
                // cierre las descarte también, igual que en el flujo por terminal).
                $sugerencia['cuenta_ids'] = $grupo->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $sugerencia['detalle'] = 'Cerrar sin facturar '.$cantConItems
                    .' cuenta(s) con consumos sin terminal identificable '
                    .'(probable PV borrada/deshabilitada; se cerrarán por id de cuenta).';
            } else {
                $sugerencia['detalle'] = 'Cerrar sin facturar '.$cantConItems.' cuenta(s) con consumos del PC "'
                    .$pcReal.'" (no coincide con ninguna PV configurada de la empresa).';
            }

            $sugerencias[] = $sugerencia;
        }

        return [
            'identificador_pc' => '— sin PV configurada —',
            'es_bucket_huerfano' => true,
            'turno_habilitado' => false,
            'turno_operativo_activo_id' => null,
            'facturas_huerfanas' => [],
            'cantidad_huerfanas' => 0,
            'cuentas_pendientes' => $cuentas->count(),
            'cuentas_abiertas' => $abiertas->count(),
            'cuentas_abiertas_con_items' => $cuentasAbiertasConItems,
            'cuentas_abiertas_vacias' => $cuentasAbiertasVacias,
            'cuentas_cerradas_sin_facturar' => $cuentasCerradasSinFacturar,
            'cuentas_pendientes_detalle' => $cuentasDetalle,
            'confirmacion_cierre_cuentas' => $confirmacionCierre,
            'turnos' => [],
            'sugerencias' => $sugerencias,
            'puede_habilitar_turno' => false,
        ];
    }

    private function cfgIdParaTerminal(int $empresaId, string $pc): int
    {
        if ($pc === '') {
            return 0;
        }

        return (int) ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('identificador_pc', $pc)
            ->value('id');
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
