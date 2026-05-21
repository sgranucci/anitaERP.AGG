<?php

namespace App\Support\Ventas;

use App\Models\Ventas\CierreParcialTurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Datos para listados y comprobantes PDF/Excel de cierres de turno gastronomía.
 */
final class GastronomiaCierreTurnoReporteSupport
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * Ventana temporal de facturas incluidas en un cierre parcial o definitivo.
     *
     * @return array{
     *   identificador_pc: string,
     *   empresa_id: int,
     *   fecha_jornada: string,
     *   desde: Carbon,
     *   hasta: Carbon,
     *   titulo: string,
     *   subtitulo: string
     * }
     */
    public function alcanceComprobantesRegistro(string $tipo, int $id): array
    {
        if ($tipo === 'parcial') {
            $parcial = CierreParcialTurnoGastronomia::query()
                ->with(['turnoOperativo.jornada', 'turnoOperativo.turno'])
                ->findOrFail($id);

            $turno = $parcial->turnoOperativo;
            if ($turno === null || $turno->habilitacion_en === null) {
                throw new InvalidArgumentException('Turno operativo no encontrado para el cierre parcial.');
            }

            $hasta = $parcial->created_at ?? now();
            $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
                ?? $hasta->format('Y-m-d');

            return [
                'identificador_pc' => (string) $parcial->identificador_pc,
                'empresa_id' => (int) $turno->empresa_id,
                'fecha_jornada' => $fechaJornada,
                'desde' => Carbon::parse($turno->habilitacion_en),
                'hasta' => Carbon::parse($hasta),
                'titulo' => 'Comprobantes del cierre parcial #'.$parcial->numero_parcial,
                'subtitulo' => ($turno->turno?->nombre ?? 'Turno')
                    .' · Op. #'.$turno->id
                    .' · '.$turno->habilitacion_en->format('d/m/Y H:i')
                    .' — '.$hasta->format('d/m/Y H:i'),
            ];
        }

        if ($tipo === 'cierre') {
            $turno = TurnoOperativoGastronomia::query()
                ->with(['jornada', 'turno'])
                ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
                ->findOrFail($id);

            if ($turno->habilitacion_en === null || $turno->cierre_en === null) {
                throw new InvalidArgumentException('El turno cerrado no tiene fechas de habilitación o cierre.');
            }

            $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
                ?? $turno->cierre_en->format('Y-m-d');

            return [
                'identificador_pc' => (string) $turno->identificador_pc,
                'empresa_id' => (int) $turno->empresa_id,
                'fecha_jornada' => $fechaJornada,
                'desde' => Carbon::parse($turno->habilitacion_en),
                'hasta' => Carbon::parse($turno->cierre_en),
                'titulo' => 'Comprobantes del cierre definitivo',
                'subtitulo' => ($turno->turno?->nombre ?? 'Turno')
                    .' · Op. #'.$turno->id
                    .' · '.$turno->habilitacion_en->format('d/m/Y H:i')
                    .' — '.$turno->cierre_en->format('d/m/Y H:i'),
            ];
        }

        throw new InvalidArgumentException('Tipo de registro de cierre inválido.');
    }

    /**
     * @return array<string, mixed>
     */
    public function datosComprobanteParcial(CierreParcialTurnoGastronomia $parcial): array
    {
        $parcial->loadMissing([
            'turnoOperativo.turno',
            'turnoOperativo.jornada',
            'turnoOperativo.empresa',
            'turnoOperativo.usuarioHabilitado',
            'turnoOperativo.usuarioHabilitacion',
            'usuario',
        ]);

        $turno = $parcial->turnoOperativo;
        $totales = is_array($parcial->totales_json) ? $parcial->totales_json : [];

        $soloMozo = ! empty($totales['solo_totales_mozo']);

        return $this->armarDatosComprobante(
            tipo: 'parcial',
            titulo: $soloMozo
                ? 'Informe por mozo (sin cierre de turno)'
                : 'Cierre parcial de turno gastronomía',
            subtitulo: $soloMozo
                ? 'Solo totales por mozo — el turno permanece habilitado'
                : 'Comprobante Nº '.$parcial->numero_parcial.' — Turno operativo #'.$parcial->turno_operativo_gastronomia_id,
            turno: $turno,
            totalesTurno: $totales,
            totalesDia: null,
            fechaEmision: $parcial->created_at ?? now(),
            usuarioRegistro: $parcial->usuario?->nombre,
            montoHabilitacion: $turno ? (float) $turno->monto_habilitacion : null,
            redondeoInvitaciones: null,
            redondeoTurno: null,
            sobranteFaltante: null,
            observacionCierre: null,
            soloTotalesMozo: $soloMozo,
        );
    }

    /**
     * PDF informativo: totales por mozo sin registrar cierre parcial.
     *
     * @return array<string, mixed>
     */
    public function datosInformeSoloMozo(TurnoOperativoGastronomia $turno, string $identificadorPc): array
    {
        $turno->loadMissing([
            'turno',
            'jornada',
            'empresa',
            'usuarioHabilitado',
            'usuarioHabilitacion',
        ]);

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $totales = GastronomiaTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
        );
        $totales['solo_totales_mozo'] = true;

        return $this->armarDatosComprobante(
            tipo: 'informe_mozo',
            titulo: 'Informe por mozo — turno en curso',
            subtitulo: 'NO es cierre parcial ni cierre definitivo — solo consulta de totales',
            turno: $turno,
            totalesTurno: $totales,
            totalesDia: null,
            fechaEmision: now(),
            usuarioRegistro: null,
            montoHabilitacion: (float) $turno->monto_habilitacion,
            redondeoInvitaciones: null,
            redondeoTurno: null,
            sobranteFaltante: null,
            observacionCierre: null,
            soloTotalesMozo: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function datosComprobanteCierreDefinitivo(TurnoOperativoGastronomia $turno): array
    {
        $turno->loadMissing([
            'turno',
            'jornada',
            'empresa',
            'usuarioHabilitado',
            'usuarioHabilitacion',
            'usuarioCierre',
            'cierresParciales',
        ]);

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
        );

        $totalesDia = GastronomiaTurnoOperativoTotalesSupport::calcular(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            null,
        );

        return $this->armarDatosComprobante(
            tipo: 'cierre',
            titulo: 'Cierre de turno gastronomía',
            subtitulo: 'Turno operativo #'.$turno->id,
            turno: $turno,
            totalesTurno: $totalesTurno,
            totalesDia: $totalesDia,
            fechaEmision: $turno->cierre_en ?? now(),
            usuarioRegistro: $turno->usuarioCierre?->nombre,
            montoHabilitacion: (float) $turno->monto_habilitacion,
            redondeoInvitaciones: $turno->redondeo_invitaciones !== null ? (float) $turno->redondeo_invitaciones : null,
            redondeoTurno: $turno->redondeo_turno !== null ? (float) $turno->redondeo_turno : null,
            sobranteFaltante: $turno->sobrante_faltante !== null ? (float) $turno->sobrante_faltante : null,
            observacionCierre: $turno->observacion_cierre,
            cantidadParciales: $turno->cierresParciales->count(),
        );
    }

    /**
     * @return Collection<int, object>
     */
    public function listadoDesdeRequest(Request $request): Collection
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        $pc = trim((string) $request->input('identificador_pc', ''));
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        $tipo = trim((string) $request->input('tipo', ''));

        $empresasAsignadas = $this->empresaRepository->traeEmpresasAsignadas();

        $filas = collect();

        if ($tipo === '' || $tipo === 'parcial') {
            $qPar = CierreParcialTurnoGastronomia::query()
                ->with([
                    'turnoOperativo.turno',
                    'turnoOperativo.jornada',
                    'turnoOperativo.empresa',
                    'usuario',
                ]);

            if ($empresaId > 0) {
                $qPar->whereHas('turnoOperativo', fn ($t) => $t->where('empresa_id', $empresaId));
            } elseif (count($empresasAsignadas) > 1) {
                $qPar->whereHas('turnoOperativo', fn ($t) => $t->whereIn('empresa_id', $empresasAsignadas));
            }

            if ($pc !== '') {
                $qPar->where('identificador_pc', $pc);
            }

            if ($fechaDesde !== '') {
                $qPar->whereDate('created_at', '>=', $fechaDesde);
            }
            if ($fechaHasta !== '') {
                $qPar->whereDate('created_at', '<=', $fechaHasta);
            }

            foreach ($qPar->orderByDesc('created_at')->limit(500)->get() as $p) {
                $filas->push($this->filaListadoDesdeParcial($p));
            }
        }

        if ($tipo === '' || $tipo === 'cierre') {
            $qCer = TurnoOperativoGastronomia::query()
                ->with(['turno', 'jornada', 'empresa', 'usuarioCierre', 'usuarioHabilitado'])
                ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO);

            if ($empresaId > 0) {
                $qCer->where('empresa_id', $empresaId);
            } elseif (count($empresasAsignadas) > 1) {
                $qCer->whereIn('empresa_id', $empresasAsignadas);
            }

            if ($pc !== '') {
                $qCer->where('identificador_pc', $pc);
            }

            if ($fechaDesde !== '') {
                $qCer->whereDate('cierre_en', '>=', $fechaDesde);
            }
            if ($fechaHasta !== '') {
                $qCer->whereDate('cierre_en', '<=', $fechaHasta);
            }

            foreach ($qCer->orderByDesc('cierre_en')->limit(500)->get() as $t) {
                $filas->push($this->filaListadoDesdeCierre($t));
            }
        }

        return $filas->sortByDesc(fn ($r) => $r->fecha_hora_raw)->values();
    }

    private function filaListadoDesdeParcial(CierreParcialTurnoGastronomia $p): object
    {
        $turno = $p->turnoOperativo;
        $empresaNombre = $turno?->empresa?->nombre ?? '';

        return (object) [
            'tipo' => 'parcial',
            'tipo_etiqueta' => 'Cierre parcial',
            'id' => (int) $p->id,
            'referencia' => '#'.$p->numero_parcial.' / Op. '.$p->turno_operativo_gastronomia_id,
            'fecha_hora' => $p->created_at?->format('d/m/Y H:i') ?? '',
            'fecha_hora_raw' => $p->created_at?->format('Y-m-d H:i:s') ?? '',
            'empresa_id' => (int) ($turno?->empresa_id ?? 0),
            'nombreempresa' => $empresaNombre,
            'identificador_pc' => (string) $p->identificador_pc,
            'turno_nombre' => $turno?->turno?->nombre ?? '',
            'fecha_jornada' => $turno?->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'usuario' => $p->usuario?->nombre ?? '',
            'total' => (float) $p->total_facturacion_turno,
            'monto_habilitacion' => $turno ? (float) $turno->monto_habilitacion : null,
        ];
    }

    private function filaListadoDesdeCierre(TurnoOperativoGastronomia $t): object
    {
        return (object) [
            'tipo' => 'cierre',
            'tipo_etiqueta' => 'Cierre definitivo',
            'id' => (int) $t->id,
            'referencia' => 'Op. #'.$t->id,
            'fecha_hora' => $t->cierre_en?->format('d/m/Y H:i') ?? '',
            'fecha_hora_raw' => $t->cierre_en?->format('Y-m-d H:i:s') ?? '',
            'empresa_id' => (int) $t->empresa_id,
            'nombreempresa' => $t->empresa?->nombre ?? '',
            'identificador_pc' => (string) $t->identificador_pc,
            'turno_nombre' => $t->turno?->nombre ?? '',
            'fecha_jornada' => $t->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'usuario' => $t->usuarioCierre?->nombre ?? '',
            'total' => (float) ($t->monto_facturacion_turno ?? 0),
            'monto_habilitacion' => (float) $t->monto_habilitacion,
            'monto_facturacion_dia' => (float) ($t->monto_facturacion_dia ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $totalesTurno
     * @param  array<string, mixed>|null  $totalesDia
     * @return array<string, mixed>
     */
    private function armarDatosComprobante(
        string $tipo,
        string $titulo,
        string $subtitulo,
        ?TurnoOperativoGastronomia $turno,
        ?array $totalesTurno,
        ?array $totalesDia,
        mixed $fechaEmision,
        ?string $usuarioRegistro,
        ?float $montoHabilitacion,
        ?float $redondeoInvitaciones,
        ?float $redondeoTurno,
        ?float $sobranteFaltante,
        ?string $observacionCierre,
        int $cantidadParciales = 0,
        bool $soloTotalesMozo = false,
    ): array {
        $empresaNombre = $turno?->empresa?->nombre ?? '';
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre);

        return [
            'tipo' => $tipo,
            'solo_totales_mozo' => $soloTotalesMozo,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'logo' => $logo,
            'empresa_nombre' => $empresaNombre,
            'identificador_pc' => $turno?->identificador_pc ?? '',
            'turno_catalogo' => $turno?->turno?->nombre ?? '',
            'turno_horario' => $turno?->turno?->etiquetaHorario() ?? '',
            'fecha_jornada' => $turno?->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'habilitacion_en' => $turno?->habilitacion_en?->format('d/m/Y H:i') ?? '',
            'cierre_en' => $turno?->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_habilita' => $turno?->usuarioHabilitacion?->nombre ?? '',
            'usuario_habilitado' => $turno?->usuarioHabilitado?->nombre ?? '',
            'usuario_registro' => $usuarioRegistro ?? '',
            'fecha_emision_comprobante' => Carbon::parse($fechaEmision)->format('d/m/Y H:i'),
            'monto_habilitacion' => $montoHabilitacion,
            'totales_turno' => $totalesTurno ?? [],
            'totales_dia' => $totalesDia,
            'redondeo_invitaciones' => $redondeoInvitaciones,
            'redondeo_turno' => $redondeoTurno,
            'sobrante_faltante' => $sobranteFaltante,
            'observacion_habilitacion' => $turno?->observacion_habilitacion,
            'observacion_cierre' => $observacionCierre,
            'cantidad_parciales' => $cantidadParciales,
        ];
    }
}
