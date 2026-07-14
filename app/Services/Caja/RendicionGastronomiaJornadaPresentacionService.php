<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemJornadaService;
use App\Support\Ventas\GastronomiaJornadaNumeracionComprobanteSupport;
use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryInformeZTransmisionFaltanteSupport;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Presentación a caja de una jornada cerrada: cierre Waitry/tótem (Z) y marcadores de auditoría.
 * No replica en Anita (bridge).
 */
final class RendicionGastronomiaJornadaPresentacionService
{
    public function __construct(
        private readonly GastronomiaCierreTotemJornadaService $cierreTotemJornadaService,
    ) {
    }

    /**
     * @return array{
     *   waitry_order_id_hasta: int,
     *   cierre_totem_jornada_gastronomia_id: ?int,
     *   numeracion_comprobantes_json: array<string, mixed>
     * }
     */
    public function resolverMarcadoresAuditoria(JornadaGastronomia $jornada): array
    {
        $jornada->loadMissing(['cierreTotem', 'empresa']);

        // Solo lectura: no invocar registrarAlCerrarJornada (consulta Waitry, puede tardar minutos).
        // El cierre tótem debe existir desde Ventas → Jornada al cerrar la jornada.
        $cierreTotem = $jornada->cierreTotem;

        $waitryHasta = 0;
        if ($cierreTotem instanceof CierreTotemJornadaGastronomia) {
            $waitryHasta = max(0, (int) ($cierreTotem->waitry_order_id_hasta ?? 0));
        }

        $numeracion = GastronomiaJornadaNumeracionComprobanteSupport::paraJornada($jornada);

        $totemHabilitado = $this->cierreTotemJornadaService->habilitado();
        $sinCierreTotem = $totemHabilitado && $cierreTotem === null;

        return [
            'waitry_order_id_hasta' => $waitryHasta,
            'cierre_totem_jornada_gastronomia_id' => $cierreTotem?->id,
            'cierre_totem_habilitado' => $totemHabilitado,
            'sin_cierre_totem_jornada' => $sinCierreTotem,
            'aviso_cierre_totem' => $sinCierreTotem
                ? 'No hay cierre Waitry/tótem guardado para esta jornada. Cierre la jornada desde Ventas → Gastronomía antes de rendir en caja.'
                : null,
            'numeracion_comprobantes_json' => [
                'jornada_id' => (int) $jornada->id,
                'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
                'apertura_en' => $jornada->apertura_en?->format('Y-m-d H:i:s'),
                'cierre_en' => $jornada->cierre_en?->format('Y-m-d H:i:s'),
                'waitry_order_id_hasta' => $waitryHasta,
                'proximo_waitry_order_id' => $waitryHasta > 0 ? $waitryHasta + 1 : 1,
                'resumen_numeracion' => $numeracion['resumen_etiqueta'] ?? '',
                'por_puntoventa' => $numeracion['filas'] ?? [],
                'registrado_en' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Marcadores Waitry, numeración PV e Informe Z (solo lectura) para el formulario de rendición en caja.
     *
     * @return array<string, mixed>
     */
    public function datosAuditoriaJornadaParaCaja(JornadaGastronomia $jornada): array
    {
        $marcadores = $this->resolverMarcadoresAuditoria($jornada);

        $cierreTotem = CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();

        $waitryAnterior = 0;
        $waitryDesde = null;
        $waitryHasta = (int) ($marcadores['waitry_order_id_hasta'] ?? 0);
        $rangoEtiqueta = '';

        if ($cierreTotem instanceof CierreTotemJornadaGastronomia) {
            $waitryAnterior = (int) ($cierreTotem->waitry_order_id_anterior ?? 0);
            $waitryDesde = $cierreTotem->waitry_order_id_desde !== null
                ? (int) $cierreTotem->waitry_order_id_desde
                : null;
            $waitryHasta = max(0, (int) ($cierreTotem->waitry_order_id_hasta ?? 0));
            $rangoEtiqueta = $this->cierreTotemJornadaService->etiquetaRango(
                $waitryAnterior,
                $waitryDesde,
                $waitryHasta > 0 ? $waitryHasta : null,
            );
        } elseif ($waitryHasta > 0) {
            $rangoEtiqueta = 'Último comprobante Waitry cerrado: #'.$waitryHasta;
        }

        $informeZ = null;
        $conciliacion = null;
        $informeZCargado = false;
        $plantilla = null;

        $totemTotalGeneral = null;
        $tramoTotem = null;
        $transmisionFaltante = WaitryInformeZTransmisionFaltanteSupport::paraVista([]);

        if ($cierreTotem instanceof CierreTotemJornadaGastronomia) {
            $detalle = is_array($cierreTotem->detalle_json) ? $cierreTotem->detalle_json : [];
            $resumenOperativo = $detalle['resumen_totems'] ?? ['por_totem' => [], 'total_general' => []];
            $totemTotalGeneral = is_array($resumenOperativo['total_general'] ?? null)
                ? $resumenOperativo['total_general']
                : null;
            $tramoTotem = is_array($resumenOperativo['tramo'] ?? null) ? $resumenOperativo['tramo'] : null;
            $informeZ = is_array($cierreTotem->informe_z_json) ? $cierreTotem->informe_z_json : null;
            $presentacion = WaitryInformeZConciliacionSupport::conciliacionPresentacionDesdeCierre($cierreTotem);
            if ($presentacion !== null) {
                $plantilla = $presentacion['plantilla'];
                $conciliacion = $presentacion['conciliacion'];
                $informeZCargado = true;
            }
            $transmisionFaltante = WaitryInformeZTransmisionFaltanteSupport::paraVista(
                is_array($detalle[WaitryInformeZTransmisionFaltanteSupport::CLAVE_DETALLE] ?? null)
                    ? $detalle[WaitryInformeZTransmisionFaltanteSupport::CLAVE_DETALLE]
                    : [],
            );
        }

        $informeZPlantilla = $plantilla ?? null;

        return array_merge($marcadores, [
            'waitry_order_id_anterior' => $waitryAnterior,
            'waitry_order_id_desde' => $waitryDesde,
            'waitry_rango_etiqueta' => $rangoEtiqueta,
            'proximo_waitry_order_id' => $waitryHasta > 0 ? $waitryHasta + 1 : 1,
            'numeracion_resumen' => (string) (($marcadores['numeracion_comprobantes_json']['resumen_numeracion'] ?? '') ?: ''),
            'numeracion_por_puntoventa' => $marcadores['numeracion_comprobantes_json']['por_puntoventa'] ?? [],
            'informe_z_cargado' => $informeZCargado,
            'informe_z_en' => $informeZ['informe_z_en'] ?? null,
            'usuario_informe_z' => $informeZ['usuario_nombre'] ?? null,
            'informe_z_plantilla' => $informeZPlantilla,
            'informe_z_precarga_automatica' => (bool) ($informeZ['precarga_automatica'] ?? false),
            'informe_z_ajustado_en_caja' => (bool) ($informeZ['ajustado_en_caja'] ?? false),
            'informe_z_ajuste_caja_en' => $informeZ['ajuste_caja_en'] ?? null,
            'informe_z_ajuste_caja_usuario' => $informeZ['ajuste_caja_usuario_nombre'] ?? null,
            'conciliacion_informe_z' => $conciliacion,
            'tolerancia_informe_z' => WaitryInformeZConciliacionSupport::toleranciaMonto(),
            'transmision_faltante_z' => $transmisionFaltante,
            'sin_cierre_totem_jornada' => (bool) ($marcadores['sin_cierre_totem_jornada'] ?? false),
            'aviso_cierre_totem' => $marcadores['aviso_cierre_totem'] ?? null,
            'totem_total_general' => $totemTotalGeneral,
            'totem_tramo' => $tramoTotem,
            'tramo_ultimo_ticket_origen' => is_array($tramoTotem) ? ($tramoTotem['tramo_ultimo_ticket_origen'] ?? null) : null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeRendir(JornadaGastronomia $jornada, ?int $exceptoRendicionId = null): array
    {
        $errores = [];

        if ((int) $jornada->empresa_id <= 0) {
            $errores[] = 'Jornada sin empresa válida.';

            return $errores;
        }

        if ($jornada->estado !== JornadaGastronomia::ESTADO_CERRADA || $jornada->cierre_en === null) {
            $errores[] = 'La jornada debe estar cerrada antes de presentarla en caja.';
        }

        if ($this->jornadaYaRendida((int) $jornada->id, $exceptoRendicionId)) {
            $errores[] = 'La jornada #'.$jornada->id.' ya tiene una rendición registrada en caja.';
        }

        $turnosAbiertos = TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->whereIn('estado', [
                TurnoOperativoGastronomia::ESTADO_HABILITADO,
            ])
            ->count();

        if ($turnosAbiertos > 0) {
            $errores[] = 'Hay '.$turnosAbiertos.' turno(s) operativo(s) aún habilitado(s) en esta jornada. Ciérrelos antes de rendir la jornada.';
        }

        $turnosSinCierre = TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->where('estado', '!=', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->whereNotNull('habilitacion_en')
            ->count();

        if ($turnosSinCierre > 0) {
            $errores[] = 'Hay turnos operativos sin cierre definitivo en esta jornada.';
        }

        $cierresTurnoSinRendir = $this->turnosCerradosSinRendirEnJornada((int) $jornada->id, $exceptoRendicionId);
        if ($cierresTurnoSinRendir->isNotEmpty()) {
            $errores[] = $this->mensajeCierresTurnoPendientesEnCaja($cierresTurnoSinRendir);
        }

        return $errores;
    }

    /**
     * Cierres de turno cerrados en la jornada que aún no tienen rendición de caja (tipo turno).
     *
     * @return Collection<int, TurnoOperativoGastronomia>
     */
    public function turnosCerradosSinRendirEnJornada(int $jornadaId, ?int $exceptoRendicionId = null): Collection
    {
        if ($jornadaId <= 0) {
            return collect();
        }

        $rendidos = RendicionGastronomiaCaja::query()
            ->whereNotNull('turno_operativo_gastronomia_id')
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->pluck('turno_operativo_gastronomia_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->values()
            ->all();

        return TurnoOperativoGastronomia::query()
            ->with(['turno:id,nombre'])
            ->where('jornada_gastronomia_id', $jornadaId)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->whereNotNull('cierre_en')
            ->whereNotIn('id', $rendidos)
            ->orderBy('identificador_pc')
            ->orderBy('cierre_en')
            ->get();
    }

    public function jornadaListaParaRendirEnCaja(int $jornadaId, ?int $exceptoRendicionId = null): bool
    {
        return $this->turnosCerradosSinRendirEnJornada($jornadaId, $exceptoRendicionId)->isEmpty();
    }

    /**
     * @param  Collection<int, TurnoOperativoGastronomia>  $pendientes
     */
    private function mensajeCierresTurnoPendientesEnCaja(Collection $pendientes): string
    {
        $cantidad = $pendientes->count();
        $detalle = $pendientes
            ->take(8)
            ->map(function (TurnoOperativoGastronomia $t) {
                $pc = trim((string) ($t->identificador_pc ?? ''));
                $turno = trim((string) ($t->turno?->nombre ?? ''));
                $cierre = $t->cierre_en?->format('d/m/Y H:i') ?? '';

                return '#'.$t->id
                    .($pc !== '' ? ' '.$pc : '')
                    .($turno !== '' ? ' ('.$turno.')' : '')
                    .($cierre !== '' ? ' cierre '.$cierre : '');
            })
            ->implode('; ');

        $sufijo = $cantidad > 8 ? '…' : '';

        return 'Hay '.$cantidad.' cierre(s) de turno sin rendir en caja para esta jornada'
            .($detalle !== '' ? ': '.$detalle.$sufijo : '.')
            .' Registre primero las rendiciones de turno (Caja → Rendiciones gastronomía, alcance turno); después podrá presentar la jornada.';
    }

    public function jornadaYaRendida(int $jornadaId, ?int $exceptoRendicionId = null): bool
    {
        return RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
            ->where('jornada_gastronomia_id', $jornadaId)
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->exists();
    }

    public function jornadaPresentadaBloqueaRendicionesTurno(int $jornadaId): bool
    {
        return $jornadaId > 0 && $this->jornadaYaRendida($jornadaId);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function exigirRendicionTurnoModificable(int $jornadaId): void
    {
        if (! $this->jornadaPresentadaBloqueaRendicionesTurno($jornadaId)) {
            return;
        }

        throw new InvalidArgumentException(
            'La jornada ya fue presentada en caja. No puede crear, modificar ni eliminar rendiciones de turno. '
            .'Para corregir datos, edite o anule la presentación de jornada (alcance jornada en esta pantalla).'
        );
    }

    public function proponerCodigoInterno(int $empresaId, int $jornadaId): string
    {
        if ($empresaId <= 0 || $jornadaId <= 0) {
            throw new InvalidArgumentException('Empresa o jornada inválida para el código de rendición.');
        }

        $max = (int) RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
            ->where('empresa_id', $empresaId)
            ->count();

        return sprintf('RGJ-%d-%d-%04d', $empresaId, $jornadaId, $max + 1);
    }
}
