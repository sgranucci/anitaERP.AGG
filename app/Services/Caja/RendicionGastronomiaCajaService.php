<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Caja\RendicionGastronomiaMovimientoCaja;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RendicionGastronomiaCajaService
{
    private const TOLERANCIA = 0.02;

    public function listar(
        ?string $busqueda,
        bool $paginar = true,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
    ): LengthAwarePaginator|Collection {
        $q = RendicionGastronomiaCaja::query()
            ->with([
                'empresa:id,nombre',
                'caja:id,nombre',
                'turnoOperativo.turno:id,nombre',
                'turnoOperativo.jornada:id,fecha_jornada',
                'creousuario:id,nombre',
            ])
            ->orderByDesc('fecharendicion')
            ->orderByDesc('id');

        if ($busqueda !== null && trim($busqueda) !== '') {
            $b = trim($busqueda);
            $q->where(function ($w) use ($b) {
                $w->where('codigo', 'like', '%'.$b.'%')
                    ->orWhere('id', $b)
                    ->orWhere('turno_operativo_gastronomia_id', $b)
                    ->orWhereHas('empresa', fn ($e) => $e->where('nombre', 'like', '%'.$b.'%'))
                    ->orWhereHas('caja', fn ($c) => $c->where('nombre', 'like', '%'.$b.'%'));
            });
        }

        [$desde, $hasta] = $this->normalizarRangoFechasListado($fechaDesde, $fechaHasta);
        if ($desde !== '' || $hasta !== '') {
            $q->where(function ($w) use ($desde, $hasta) {
                $w->where(function ($r) use ($desde, $hasta) {
                    if ($desde !== '') {
                        $r->whereDate('fecharendicion', '>=', $desde);
                    }
                    if ($hasta !== '') {
                        $r->whereDate('fecharendicion', '<=', $hasta);
                    }
                })->orWhereHas('turnoOperativo.jornada', function ($j) use ($desde, $hasta) {
                    if ($desde !== '') {
                        $j->whereDate('fecha_jornada', '>=', $desde);
                    }
                    if ($hasta !== '') {
                        $j->whereDate('fecha_jornada', '<=', $hasta);
                    }
                });
            });
        }

        return $paginar ? $q->paginate(10) : $q->get();
    }

    /**
     * Si solo se indica una fecha, filtra ese día (desde = hasta).
     *
     * @return array{0: string, 1: string}
     */
    private function normalizarRangoFechasListado(?string $fechaDesde, ?string $fechaHasta): array
    {
        $desde = trim((string) ($fechaDesde ?? ''));
        $hasta = trim((string) ($fechaHasta ?? ''));

        if ($desde !== '' && $hasta === '') {
            $hasta = $desde;
        } elseif ($hasta !== '' && $desde === '') {
            $desde = $hasta;
        }

        return [$desde, $hasta];
    }

    /**
     * Turnos cerrados aún no rendidos en caja.
     *
     * @return Collection<int, TurnoOperativoGastronomia>
     */
    public function turnosPendientes(?int $exceptoRendicionId = null, ?int $empresaId = null): Collection
    {
        $rendidos = RendicionGastronomiaCaja::query()
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->pluck('turno_operativo_gastronomia_id');

        return TurnoOperativoGastronomia::query()
            ->with(['turno:id,nombre', 'jornada:id,fecha_jornada', 'empresa:id,nombre'])
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->whereNotNull('cierre_en')
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotIn('id', $rendidos)
            ->orderByDesc('cierre_en')
            ->limit(200)
            ->get();
    }

    /**
     * @return array{data: string}
     */
    public function consultaCierresTurno(
        string $consulta,
        int $empresaId,
        ?int $exceptoRendicionId = null,
        bool $puedeVerComprobante = false,
        bool $puedeVerDetalleCierre = false,
    ): array {
        if ($empresaId <= 0) {
            return ['data' => '<tr><td colspan="6">Seleccione una empresa.</td></tr>'];
        }

        $consulta = trim($consulta);

        if ($consulta !== '' && ctype_digit($consulta)) {
            try {
                $encontrado = $this->findTurnoPendientePorNumero((int) $consulta, $empresaId, $exceptoRendicionId);
                $turno = TurnoOperativoGastronomia::query()
                    ->with(['turno:id,nombre', 'jornada:id,fecha_jornada', 'empresa:id,nombre'])
                    ->find($encontrado['id']);

                if ($turno === null) {
                    return ['data' => '<tr><td colspan="6">Cierre no encontrado.</td></tr>'];
                }

                return ['data' => $this->renderTurnosPendientesHtml(collect([$turno]), $puedeVerComprobante, $puedeVerDetalleCierre)];
            } catch (InvalidArgumentException $e) {
                return ['data' => '<tr><td colspan="6">'.e($e->getMessage()).'</td></tr>'];
            }
        }

        $turnos = $this->turnosPendientes($exceptoRendicionId, $empresaId);

        if ($consulta !== '') {
            $needle = mb_strtoupper($consulta);
            $turnos = $turnos->filter(function (TurnoOperativoGastronomia $t) use ($needle) {
                $haystack = mb_strtoupper(implode(' ', [
                    (string) $t->id,
                    (string) ($t->turno?->nombre ?? ''),
                    (string) ($t->identificador_pc ?? ''),
                    (string) ($t->cierre_en?->format('d/m/Y H:i') ?? ''),
                    (string) ($t->jornada?->fecha_jornada?->format('d/m/Y') ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        $turnos = $turnos->take(200);

        if ($turnos->isEmpty()) {
            return ['data' => '<tr><td colspan="6">Sin cierres pendientes de rendir.</td></tr>'];
        }

        return ['data' => $this->renderTurnosPendientesHtml($turnos, $puedeVerComprobante, $puedeVerDetalleCierre)];
    }

    /**
     * @param  Collection<int, TurnoOperativoGastronomia>  $turnos
     */
    private function renderTurnosPendientesHtml(
        Collection $turnos,
        bool $puedeVerComprobante = false,
        bool $puedeVerDetalleCierre = false,
    ): string {
        $html = '';
        foreach ($turnos as $t) {
            $html .= '<tr>';
            $html .= '<td class="id">'.e((string) $t->id).'</td>';
            $html .= '<td class="turno_nombre">'.e((string) ($t->turno?->nombre ?? '')).'</td>';
            $html .= '<td class="identificador_pc">'.e((string) ($t->identificador_pc ?? '')).'</td>';
            $html .= '<td class="cierre_en">'.e((string) ($t->cierre_en?->format('d/m/Y H:i') ?? '')).'</td>';
            $html .= '<td class="fecha_jornada">'.e((string) ($t->jornada?->fecha_jornada?->format('d/m/Y') ?? '')).'</td>';
            $html .= '<td class="text-nowrap">';
            if ($puedeVerDetalleCierre) {
                $urlVer = route('gastronomia_cierre_turno_ver', [
                    'id' => $t->id,
                    'vista' => 'consulta',
                    'origen' => 'modal_consulta',
                ]);
                $html .= '<button type="button" class="btn btn-outline-info btn-sm js-ver-cierre-turno-detalle mr-1" ';
                $html .= 'data-url="'.e($urlVer).'" title="Visualizar cierre (modo consulta)">';
                $html .= '<i class="fa fa-eye"></i></button>';
            }
            if ($puedeVerComprobante) {
                $urlPdf = route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $t->id, 'inline' => 1]);
                $tituloPdf = 'Op. #'.$t->id.' — '.($t->turno?->nombre ?? '');
                $html .= '<button type="button" class="btn btn-outline-danger btn-sm js-ver-comprobante-cierre-modal mr-1" ';
                $html .= 'data-url="'.e($urlPdf).'" data-titulo="'.e($tituloPdf).'" title="Ver comprobante (solapa Comprobante)">';
                $html .= '<i class="fa fa-file-pdf-o"></i></button>';
            }
            $html .= '<a class="btn btn-warning btn-sm eligeconsultacierre">Elegir</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * @return array{id:int, etiqueta:string}
     *
     * @throws InvalidArgumentException
     */
    public function findTurnoPendientePorNumero(int $numero, int $empresaId, ?int $exceptoRendicionId = null): array
    {
        if ($numero <= 0 || $empresaId <= 0) {
            throw new InvalidArgumentException('Indique un número de cierre de turno válido.');
        }

        $turno = TurnoOperativoGastronomia::query()
            ->with(['turno:id,nombre', 'jornada:id,fecha_jornada'])
            ->where('id', $numero)
            ->first();

        if ($turno === null) {
            throw new InvalidArgumentException('No existe el turno operativo #'.$numero.'.');
        }

        if ((int) $turno->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El turno #'.$numero.' pertenece a otra empresa.');
        }

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_CERRADO || $turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno #'.$numero.' no tiene cierre definitivo.');
        }

        if ($this->turnoYaRendido($numero, $exceptoRendicionId)) {
            throw new InvalidArgumentException('El turno #'.$numero.' ya fue rendido en caja.');
        }

        return [
            'id' => (int) $turno->id,
            'etiqueta' => $this->etiquetaTurnoPendiente($turno),
        ];
    }

    public function proponerCodigoAnita(int $empresaId): string
    {
        // TODO: consultar último código en Anita vía ApiAnita cuando esté definida la tabla remota.
        $ultimo = RendicionGastronomiaCaja::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('id')
            ->value('codigo');

        if ($ultimo !== null && preg_match('/^(\d+)$/', trim((string) $ultimo), $m)) {
            return (string) ((int) $m[1] + 1);
        }

        return '1';
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDesdeTurno(int $turnoId, ?int $exceptoRendicionId = null): array
    {
        if ($this->turnoYaRendido($turnoId, $exceptoRendicionId)) {
            throw new InvalidArgumentException('El turno operativo #'.$turnoId.' ya tiene una rendición registrada en caja.');
        }

        $turno = TurnoOperativoGastronomia::query()
            ->with([
                'turno',
                'jornada',
                'empresa',
                'configuracionPuntoventa.puntoventaCae',
                'configuracionPuntoventa.puntoventaCaea',
                'usuarioCierre',
                'usuarioHabilitacion',
                'usuarioHabilitado',
            ])
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->findOrFail($turnoId);

        if ($turno->habilitacion_en === null || $turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno no tiene fechas de habilitación o cierre.');
        }

        $cfg = $turno->configuracionPuntoventa;
        if ($cfg === null) {
            throw new InvalidArgumentException('El turno no tiene configuración de punto de venta.');
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? $turno->cierre_en->format('Y-m-d');

        $totales = GastronomiaTurnoOperativoTotalesSupport::calcular(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            Carbon::parse($turno->habilitacion_en),
            Carbon::parse($turno->cierre_en),
        );

        $movimientos = $this->movimientosDesdeTotales($totales);

        return [
            'turno_operativo_gastronomia_id' => (int) $turno->id,
            'empresa_id' => (int) $turno->empresa_id,
            'empresa_nombre' => (string) ($turno->empresa?->nombre ?? ''),
            'puntoventa_cae_id' => (int) $cfg->puntoventa_cae_id,
            'puntoventa_caea_id' => (int) $cfg->puntoventa_caea_id,
            'puntoventa_cae_label' => $this->etiquetaPuntoventa($cfg->puntoventaCae),
            'puntoventa_caea_label' => $this->etiquetaPuntoventa($cfg->puntoventaCaea),
            'identificador_pc' => (string) $turno->identificador_pc,
            'turno_nombre' => (string) ($turno->turno?->nombre ?? ''),
            'fecha_jornada' => $turno->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'habilitacion_en' => $turno->habilitacion_en->format('d/m/Y H:i'),
            'cierre_en' => $turno->cierre_en->format('d/m/Y H:i'),
            'usuario_habilita' => (string) ($turno->usuarioHabilitacion?->nombre ?? ''),
            'usuario_habilitado' => (string) ($turno->usuarioHabilitado?->nombre ?? ''),
            'usuario_cierre' => (string) ($turno->usuarioCierre?->nombre ?? ''),
            'monto_habilitacion' => round((float) $turno->monto_habilitacion, 2),
            'iniciodelfondo' => round((float) $turno->monto_habilitacion, 2),
            'totalfactura' => round((float) ($totales['total_ventas'] ?? 0), 2),
            'totalcobrado' => round((float) ($totales['total_cobrado'] ?? 0), 2),
            'totalinvitacion' => round((float) ($totales['total_invitaciones'] ?? 0), 2),
            'totalnotacredito' => round(abs((float) ($totales['total_notas_credito'] ?? 0)), 2),
            'totalredondeo' => round((float) ($turno->redondeo_turno ?? 0), 2),
            'totalredondeoinvitacion' => round((float) ($turno->redondeo_invitaciones ?? 0), 2),
            'sobrantefaltante' => round((float) ($turno->sobrante_faltante ?? 0), 2),
            'observacion_turno' => (string) ($turno->observacion_cierre ?? ''),
            'totales_turno' => $totales,
            'movimientos' => $movimientos,
            'url_comprobante_cierre' => route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $turno->id, 'inline' => 1]),
            'resumen_diferencias' => $this->armarResumenDiferencias(
                round((float) ($totales['total_cobrado'] ?? 0), 2),
                $movimientos,
                round((float) ($turno->redondeo_turno ?? 0), 2),
                round((float) ($turno->redondeo_invitaciones ?? 0), 2),
                round((float) ($turno->sobrante_faltante ?? 0), 2),
            ),
        ];
    }

    public function findConDetalle(int $id): RendicionGastronomiaCaja
    {
        return RendicionGastronomiaCaja::query()
            ->with([
                'empresa',
                'caja',
                'puntoventaCae',
                'puntoventaCaea',
                'turnoOperativo.turno',
                'turnoOperativo.jornada',
                'turnoOperativo.usuarioCierre',
                'turnoOperativo.usuarioHabilitacion',
                'turnoOperativo.usuarioHabilitado',
                'movimientos.cuentacaja',
                'creousuario',
            ])
            ->findOrFail($id);
    }

    /**
     * @return array{datos: array<string, mixed>}
     */
    public function datosParaImpresion(int $id): array
    {
        $data = $this->findConDetalle($id);
        $data->loadMissing([
            'turnoOperativo.usuarioHabilitacion',
            'turnoOperativo.usuarioHabilitado',
        ]);

        $totalesTurno = null;
        try {
            $datosTurno = $this->datosDesdeTurno((int) $data->turno_operativo_gastronomia_id, $id);
            $totalesTurno = $datosTurno['totales_turno'] ?? null;
        } catch (InvalidArgumentException) {
            // Se usan totales persistidos en la rendición.
        }

        $lineas = $this->lineasMediosParaImpresion($data, $totalesTurno);

        $resumen = $this->armarResumenDiferencias(
            round((float) $data->totalcobrado, 2),
            array_map(fn (array $l) => ['monto' => $l['monto']], $lineas),
            round((float) $data->totalredondeo, 2),
            round((float) $data->totalredondeoinvitacion, 2),
            round((float) $data->sobrantefaltante, 2),
        );

        return [
            'datos' => $this->armarDatosComprobanteRendicion($data, $totalesTurno, $lineas, $resumen),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $totalesTurno
     * @param  list<array{nombre: string, codigo: string, monto: float, cotizacion: float, es_nota_credito: bool}>  $lineas
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    private function armarDatosComprobanteRendicion(
        RendicionGastronomiaCaja $r,
        ?array $totalesTurno,
        array $lineas,
        array $resumen,
    ): array {
        $turno = $r->turnoOperativo;
        $empresaNombre = (string) ($r->empresa?->nombre ?? '');
        $totalesTurno = is_array($totalesTurno) && $totalesTurno !== []
            ? $totalesTurno
            : $this->totalesTurnoDesdeCabeceraRendicion($r, $resumen);

        return [
            'tipo' => 'rendicion',
            'titulo' => 'Rendición gastronomía — caja',
            'subtitulo' => 'Ticket '.$r->codigo.' — Turno operativo #'.$r->turno_operativo_gastronomia_id,
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre),
            'empresa_nombre' => $empresaNombre,
            'identificador_pc' => (string) ($turno?->identificador_pc ?? ''),
            'turno_catalogo' => (string) ($turno?->turno?->nombre ?? ''),
            'turno_horario' => $turno?->turno?->etiquetaHorario() ?? '',
            'fecha_jornada' => $turno?->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'habilitacion_en' => $turno?->habilitacion_en?->format('d/m/Y H:i') ?? '',
            'cierre_en' => $turno?->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_habilita' => (string) ($turno?->usuarioHabilitacion?->nombre ?? ''),
            'usuario_habilitado' => (string) ($turno?->usuarioHabilitado?->nombre ?? ''),
            'usuario_registro' => (string) ($r->creousuario?->nombre ?? ''),
            'usuario_cierre' => (string) ($turno?->usuarioCierre?->nombre ?? ''),
            'fecha_emision_comprobante' => ($r->fecharendicion ?? now())->format('d/m/Y H:i'),
            'monto_habilitacion' => (float) ($turno?->monto_habilitacion ?? 0),
            'observacion_habilitacion' => $turno?->observacion_habilitacion,
            'totales_turno' => $totalesTurno,
            'totales_dia' => null,
            'rendicion_id' => (int) $r->id,
            'turno_operativo_id' => (int) $r->turno_operativo_gastronomia_id,
            'codigo_anita' => (string) $r->codigo,
            'caja_nombre' => (string) ($r->caja?->nombre ?? ''),
            'fecha_rendicion' => $r->fecharendicion?->format('d/m/Y H:i') ?? '',
            'pv_cae_label' => $this->etiquetaPuntoventa($r->puntoventaCae),
            'pv_caea_label' => $this->etiquetaPuntoventa($r->puntoventaCaea),
            'iniciodelfondo' => (float) $r->iniciodelfondo,
            'lineas_medios' => $lineas,
            'resumen_rendicion' => $resumen,
            'redondeo_rendicion' => (float) $r->totalredondeo,
            'redondeo_invitaciones' => (float) $r->totalredondeoinvitacion,
            'sobrante_faltante' => (float) $r->sobrantefaltante,
            'observacion' => (string) ($r->observacion ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    private function totalesTurnoDesdeCabeceraRendicion(RendicionGastronomiaCaja $r, array $resumen): array
    {
        $nc = round((float) $r->totalnotacredito, 2);

        return [
            'total_ventas' => round((float) $r->totalfactura, 2),
            'total_cobrado' => round((float) $r->totalcobrado, 2),
            'total_invitaciones' => round((float) $r->totalinvitacion, 2),
            'total_notas_credito' => $nc > 0 ? -$nc : 0.0,
            'cantidad_notas_credito' => 0,
            'conciliacion_ok' => ! empty($resumen['cuadra']),
            'diferencia_cobranza' => (float) ($resumen['diferencia'] ?? 0),
            'por_mozo' => [],
            'por_medio_pago' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $totalesTurno
     * @return list<array{nombre: string, codigo: string, monto: float, cotizacion: float, es_nota_credito: bool}>
     */
    private function lineasMediosParaImpresion(RendicionGastronomiaCaja $data, ?array $totalesTurno): array
    {
        $lineas = [];
        foreach ($data->movimientos as $m) {
            $lineas[] = [
                'nombre' => (string) ($m->cuentacaja?->nombre ?? ''),
                'codigo' => (string) ($m->cuentacaja?->codigo ?? ''),
                'monto' => round((float) $m->monto, 2),
                'cotizacion' => round((float) $m->cotizacion, 2),
                'es_nota_credito' => false,
            ];
        }

        $totalesTurno = is_array($totalesTurno) ? $totalesTurno : [];
        $ncCant = (int) ($totalesTurno['cantidad_notas_credito'] ?? 0);
        $ncMonto = array_key_exists('total_notas_credito', $totalesTurno)
            ? round((float) $totalesTurno['total_notas_credito'], 2)
            : (round((float) $data->totalnotacredito, 2) > 0
                ? -round((float) $data->totalnotacredito, 2)
                : 0.0);

        $yaTieneNc = false;
        foreach ($lineas as $l) {
            if (str_contains(mb_strtolower($l['nombre']), 'notas de crédito')) {
                $yaTieneNc = true;
                break;
            }
        }

        if (! $yaTieneNc && ($ncCant > 0 || abs($ncMonto) >= 0.005)) {
            $lineas[] = [
                'nombre' => 'Notas de crédito ('.$ncCant.' comp.)',
                'codigo' => '',
                'monto' => $ncMonto,
                'cotizacion' => 1.0,
                'es_nota_credito' => true,
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    public function guardar(array $cabecera, array $movimientos): RendicionGastronomiaCaja
    {
        return DB::transaction(function () use ($cabecera, $movimientos) {
            $turnoId = (int) $cabecera['turno_operativo_gastronomia_id'];
            if ($this->turnoYaRendido($turnoId)) {
                throw new InvalidArgumentException('El turno operativo #'.$turnoId.' ya fue rendido.');
            }

            if (trim((string) ($cabecera['codigo'] ?? '')) === '') {
                $cabecera['codigo'] = $this->proponerCodigoAnita((int) $cabecera['empresa_id']);
            }

            $cabecera['creousuario_id'] = (int) Auth::id();
            $rendicion = RendicionGastronomiaCaja::create($cabecera);
            $this->persistirMovimientos($rendicion, $movimientos);

            return $rendicion->fresh(['movimientos.cuentacaja']);
        });
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    public function actualizar(int $id, array $cabecera, array $movimientos): RendicionGastronomiaCaja
    {
        return DB::transaction(function () use ($id, $cabecera, $movimientos) {
            $rendicion = RendicionGastronomiaCaja::findOrFail($id);
            $turnoId = (int) $cabecera['turno_operativo_gastronomia_id'];

            if ($this->turnoYaRendido($turnoId, $id)) {
                throw new InvalidArgumentException('Otra rendición ya utiliza el turno operativo #'.$turnoId.'.');
            }

            $rendicion->update($cabecera);
            RendicionGastronomiaMovimientoCaja::query()
                ->where('rendicion_gastronomia_caja_id', $rendicion->id)
                ->delete();
            $this->persistirMovimientos($rendicion, $movimientos);

            return $rendicion->fresh(['movimientos.cuentacaja']);
        });
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id) {
            $rendicion = RendicionGastronomiaCaja::findOrFail($id);
            RendicionGastronomiaMovimientoCaja::query()
                ->where('rendicion_gastronomia_caja_id', $rendicion->id)
                ->delete();
            $rendicion->delete();
        });
    }

    /**
     * @param  list<array{cuentacaja_id?:int, monto?:float, cotizacion?:float}>  $raw
     * @return list<array{cuentacaja_id:int, monto:float, cotizacion:float}>
     */
    public function normalizarMovimientosRequest(array $raw): array
    {
        $out = [];
        foreach ($raw as $row) {
            $cuentaId = (int) ($row['cuentacaja_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $monto = round((float) ($row['monto'] ?? 0), 2);
            $cotizacion = round((float) ($row['cotizacion'] ?? 1), 2);
            if ($cotizacion <= 0) {
                $cotizacion = 1.0;
            }
            $out[] = [
                'cuentacaja_id' => $cuentaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function cabeceraDesdeRequest(array $validated): array
    {
        return [
            'codigo' => trim((string) $validated['codigo']),
            'empresa_id' => (int) $validated['empresa_id'],
            'puntoventa_cae_id' => (int) $validated['puntoventa_cae_id'],
            'puntoventa_caea_id' => (int) $validated['puntoventa_caea_id'],
            'caja_id' => (int) $validated['caja_id'],
            'fecharendicion' => Carbon::parse($validated['fecharendicion'])->format('Y-m-d H:i:s'),
            'iniciodelfondo' => round((float) $validated['iniciodelfondo'], 2),
            'totalfactura' => round((float) $validated['totalfactura'], 2),
            'totalcobrado' => round((float) $validated['totalcobrado'], 2),
            'totalinvitacion' => round((float) $validated['totalinvitacion'], 2),
            'totalnotacredito' => round((float) $validated['totalnotacredito'], 2),
            'totalredondeo' => round((float) $validated['totalredondeo'], 2),
            'totalredondeoinvitacion' => round((float) $validated['totalredondeoinvitacion'], 2),
            'sobrantefaltante' => round((float) $validated['sobrantefaltante'], 2),
            'turno_operativo_gastronomia_id' => (int) $validated['turno_operativo_gastronomia_id'],
            'observacion' => isset($validated['observacion']) ? trim((string) $validated['observacion']) : null,
        ];
    }

    private function turnoYaRendido(int $turnoId, ?int $exceptoRendicionId = null): bool
    {
        return RendicionGastronomiaCaja::query()
            ->where('turno_operativo_gastronomia_id', $turnoId)
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->exists();
    }

    /**
     * Medios de cobro del turno + fila virtual de notas de crédito (como cierre de turno).
     *
     * @param  array<string, mixed>  $totales  Resultado de GastronomiaTurnoOperativoTotalesSupport::calcular()
     * @return list<array{cuentacaja_id:int, codigo:string, nombre:string, monto:float, cotizacion:float, es_nota_credito?:bool}>
     */
    private function movimientosDesdeTotales(array $totales): array
    {
        $movimientos = [];
        foreach ($totales['por_medio_pago'] ?? [] as $p) {
            $cuentaId = (int) ($p['cuentacaja_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $movimientos[] = [
                'cuentacaja_id' => $cuentaId,
                'codigo' => (string) ($p['codigo'] ?? ''),
                'nombre' => (string) ($p['nombre'] ?? $p['codigo'] ?? ''),
                'monto' => round((float) ($p['total'] ?? 0), 2),
                'cotizacion' => 1.0,
            ];
        }

        $ncTotal = round((float) ($totales['total_notas_credito'] ?? 0), 2);
        $ncCant = (int) ($totales['cantidad_notas_credito'] ?? 0);
        if ($ncCant > 0 || abs($ncTotal) >= 0.005) {
            $movimientos[] = [
                'cuentacaja_id' => 0,
                'codigo' => '',
                'nombre' => 'Notas de crédito ('.$ncCant.' comp.)',
                'monto' => $ncTotal,
                'cotizacion' => 1.0,
                'es_nota_credito' => true,
            ];
        }

        return $movimientos;
    }

    /**
     * @param  list<array{monto:float, cotizacion?:float}>  $movimientos
     * @return array{total_grilla: float, total_ajustado: float, diferencia: float, cuadra: bool, mensaje: string}
     */
    private function armarResumenDiferencias(
        float $totalCobradoTurno,
        array $movimientos,
        float $redondeo,
        float $redondeoInvitacion,
        float $sobranteFaltante,
    ): array {
        $totalGrilla = 0.0;
        foreach ($movimientos as $m) {
            $totalGrilla += round((float) ($m['monto'] ?? 0), 2);
        }
        $totalGrilla = round($totalGrilla, 2);
        $totalAjustado = round($totalGrilla + $redondeo + $redondeoInvitacion + $sobranteFaltante, 2);
        $diferencia = round($totalAjustado - $totalCobradoTurno, 2);
        $cuadra = abs($diferencia) <= self::TOLERANCIA;

        $mensaje = $cuadra
            ? 'La rendición cuadra con el total cobrado del cierre de turno.'
            : ($diferencia > 0
                ? 'Sobra $'.number_format(abs($diferencia), 2, ',', '.').' respecto al cobrado del turno.'
                : 'Falta $'.number_format(abs($diferencia), 2, ',', '.').' respecto al cobrado del turno.');

        return [
            'total_grilla' => $totalGrilla,
            'total_ajustado' => $totalAjustado,
            'diferencia' => $diferencia,
            'cuadra' => $cuadra,
            'mensaje' => $mensaje,
        ];
    }

    private function etiquetaTurnoPendiente(TurnoOperativoGastronomia $turno): string
    {
        $n = (int) ($turno->numero_cierre ?? 0);
        $ref = $n > 0 ? 'Cierre #'.$n : 'Op. #'.$turno->id;

        return $ref
            .' — '.($turno->turno?->nombre ?? '')
            .' — '.($turno->identificador_pc ?? '')
            .' — cierre '.($turno->cierre_en?->format('d/m/Y H:i') ?? '');
    }

    private function etiquetaPuntoventa($pv): string
    {
        if ($pv === null) {
            return '—';
        }

        $codigo = trim((string) ($pv->codigo ?? ''));
        $nombre = trim((string) ($pv->nombre ?? ''));

        if ($codigo !== '' && $nombre !== '') {
            return $codigo.' — '.$nombre;
        }

        return $codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '—');
    }

    /**
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    private function persistirMovimientos(RendicionGastronomiaCaja $rendicion, array $movimientos): void
    {
        foreach ($movimientos as $row) {
            RendicionGastronomiaMovimientoCaja::create([
                'rendicion_gastronomia_caja_id' => $rendicion->id,
                'cuentacaja_id' => $row['cuentacaja_id'],
                'monto' => $row['monto'],
                'cotizacion' => $row['cotizacion'],
            ]);
        }
    }
}
