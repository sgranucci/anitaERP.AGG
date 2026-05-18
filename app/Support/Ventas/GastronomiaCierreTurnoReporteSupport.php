<?php

namespace App\Support\Ventas;

use App\Models\Ventas\CierreParcialTurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

        return $this->armarDatosComprobante(
            tipo: 'parcial',
            titulo: 'Cierre parcial de turno gastronomía',
            subtitulo: 'Comprobante Nº '.$parcial->numero_parcial.' — Turno operativo #'.$parcial->turno_operativo_gastronomia_id,
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
    ): array {
        $empresaNombre = $turno?->empresa?->nombre ?? '';
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre);

        return [
            'tipo' => $tipo,
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
