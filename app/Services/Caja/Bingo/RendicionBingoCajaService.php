<?php

declare(strict_types=1);

namespace App\Services\Caja\Bingo;

use App\Models\Caja\Bingo\BingoCarton;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Caja\Bingo\TurnoOperativoBingo;
use App\Support\Caja\Bingo\BingoIdentificadorPc;
use App\Support\Caja\Bingo\BingoRendicionCalculoSupport;
use App\Support\Caja\Bingo\RendicionBingoCajaListadoFiltros;
use App\Support\Caja\AnitaSync\RendicionBingoCabeceraAnitaMapper;
use App\Support\Ventas\GastronomiaTurnoMediosContadoCierreSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RendicionBingoCajaService
{
    public function __construct(
        private readonly BingoTurnoOperativoService $turnoOperativoService,
        private readonly RendicionBingoAnitaSyncService $anitaSyncService,
    ) {}

    /**
     * @param  list<array{carton_id?: int, cantidad?: int, precio_unitario?: float, anulado?: bool}>  $lineasCartones
     * @param  array<int, float>  $montosManuales
     * @return array<string, mixed>
     */
    public function calcular(int $empresaId, array $lineasCartones, array $montosManuales = []): array
    {
        $lineas = $this->normalizarLineasCartones($empresaId, $lineasCartones);
        $conceptos = $this->conceptosActivos($empresaId);

        return BingoRendicionCalculoSupport::calcular($lineas, $conceptos, $montosManuales);
    }

    /**
     * Cierre de turno en terminal: cartones + conceptos. No presenta en caja.
     *
     * @param  array<string, mixed>  $payload
     */
    public function guardarCierreTerminal(string $identificadorPc, array $payload): TurnoOperativoBingo
    {
        $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($identificadorPc);
        if ($turno === null) {
            throw new InvalidArgumentException('No hay turno habilitado en esta terminal.');
        }

        if ($turno->estado === TurnoOperativoBingo::ESTADO_CERRADO) {
            throw new InvalidArgumentException('El turno ya está cerrado.');
        }

        $empresaId = (int) $turno->empresa_id;
        $lineasRaw = is_array($payload['cartones'] ?? null) ? $payload['cartones'] : [];
        $montosManuales = is_array($payload['montos_manuales'] ?? null) ? $payload['montos_manuales'] : [];
        $lineas = $this->normalizarLineasCartones($empresaId, $lineasRaw);
        $calculo = BingoRendicionCalculoSupport::calcular(
            $lineas,
            $this->conceptosActivos($empresaId),
            $montosManuales,
        );

        $montoRendicion = round((float) $calculo['saldo_final'], 2);
        $deposito = $montoRendicion;
        $observacion = trim((string) ($payload['observacion'] ?? ''));

        $turno->load(['jornada', 'configuracionPuntoventa.cuentacaja']);
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        $totalesTurno = \App\Support\Caja\Bingo\BingoTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $turno->habilitacion_en,
        );

        $mediosContado = $this->resolverMediosContado($payload, $totalesTurno, $empresaId, $montoRendicion);
        $lineasGuardar = $this->sanitizarLineasCartonesParaGuardar($empresaId, $lineasRaw);

        return DB::transaction(function () use (
            $turno,
            $identificadorPc,
            $lineasGuardar,
            $calculo,
            $montosManuales,
            $montoRendicion,
            $deposito,
            $observacion,
            $mediosContado,
        ) {
            $this->turnoOperativoService->cerrar($turno->fresh(['jornada', 'turno']), $identificadorPc, [
                'deposito' => $deposito,
                'monto_rendicion' => $montoRendicion,
                'medios_contado' => $mediosContado,
                'cartones' => $lineasGuardar,
                'conceptos' => $this->armarPayloadConceptos($calculo['lineas_concepto'], $montosManuales),
                'montos_manuales' => $montosManuales,
                'observacion_cierre' => $observacion,
            ]);

            return $turno->fresh(['turno', 'jornada', 'usuarioHabilitado']);
        });
    }

    /**
     * Guarda la planilla (cartones / premios) sin cerrar el turno ni la jornada.
     *
     * @param  array<string, mixed>  $payload
     */
    public function guardarBorradorTerminal(string $identificadorPc, array $payload): TurnoOperativoBingo
    {
        $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($identificadorPc);
        if ($turno === null) {
            throw new InvalidArgumentException('No hay turno habilitado en esta terminal.');
        }

        if ($turno->estado !== TurnoOperativoBingo::ESTADO_HABILITADO) {
            throw new InvalidArgumentException('El turno ya está cerrado. Use la edición de rendición.');
        }

        $empresaId = (int) $turno->empresa_id;
        $lineasRaw = is_array($payload['cartones'] ?? null) ? $payload['cartones'] : [];
        $montosManuales = is_array($payload['montos_manuales'] ?? null) ? $payload['montos_manuales'] : [];
        $lineas = $this->normalizarLineasCartones($empresaId, $lineasRaw);
        $calculo = BingoRendicionCalculoSupport::calcular(
            $lineas,
            $this->conceptosActivos($empresaId),
            $montosManuales,
        );
        $observacion = trim((string) ($payload['observacion'] ?? ''));
        $lineasGuardar = $this->sanitizarLineasCartonesParaGuardar($empresaId, $lineasRaw);

        return $this->turnoOperativoService->guardarBorradorRendicion($turno, [
            'cartones' => $lineasGuardar,
            'conceptos' => $this->armarPayloadConceptos($calculo['lineas_concepto'], $montosManuales),
            'montos_manuales' => $montosManuales,
            'observacion_cierre' => $observacion,
        ]);
    }

    /**
     * Actualiza cartones/conceptos de un turno ya cerrado, pendiente de presentar en caja.
     *
     * @param  array<string, mixed>  $payload
     */
    public function guardarEdicionTurnoCerrado(int $turnoId, array $payload): TurnoOperativoBingo
    {
        $turno = TurnoOperativoBingo::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado'])
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->findOrFail($turnoId);

        if ($turno->rendicion_presentada) {
            throw new InvalidArgumentException('La rendición ya fue presentada en caja y no puede modificarse.');
        }

        if ($turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno no tiene cierre definitivo.');
        }

        $empresaId = (int) $turno->empresa_id;
        $lineasRaw = is_array($payload['cartones'] ?? null) ? $payload['cartones'] : [];
        $montosManuales = is_array($payload['montos_manuales'] ?? null) ? $payload['montos_manuales'] : [];
        $lineasCalculo = $this->normalizarLineasCartones($empresaId, $lineasRaw);
        $calculo = BingoRendicionCalculoSupport::calcular(
            $lineasCalculo,
            $this->conceptosActivos($empresaId),
            $montosManuales,
        );

        $montoRendicion = round((float) $calculo['saldo_final'], 2);
        $deposito = $montoRendicion;
        $observacion = trim((string) ($payload['observacion'] ?? ''));

        $this->turnoOperativoService->actualizarRendicion($turno, [
            'deposito' => $deposito,
            'monto_rendicion' => $montoRendicion,
            'medios_contado' => $this->resolverMediosContado($payload, [], $empresaId, $montoRendicion),
            'cartones' => $this->sanitizarLineasCartonesParaGuardar($empresaId, $lineasRaw),
            'conceptos' => $this->armarPayloadConceptos($calculo['lineas_concepto'], $montosManuales),
            'montos_manuales' => $montosManuales,
            'observacion_cierre' => $observacion,
        ]);

        return $turno->fresh(['turno', 'jornada', 'usuarioHabilitado']);
    }

    /**
     * Presentación en caja de un turno ya cerrado en terminal.
     *
     * @param  array<string, mixed>  $cabecera
     */
    public function guardarPresentacionCaja(array $cabecera): RendicionBingoCaja
    {
        $turnoId = (int) ($cabecera['turno_operativo_bingo_id'] ?? 0);
        if ($turnoId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar un cierre de turno pendiente.');
        }

        if ($this->turnoYaPresentado($turnoId)) {
            throw new InvalidArgumentException('El turno operativo #'.$turnoId.' ya fue presentado en caja.');
        }

        $turno = TurnoOperativoBingo::query()
            ->with(['jornada', 'turno', 'configuracionPuntoventa.cuentacaja', 'usuarioHabilitado'])
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->findOrFail($turnoId);

        if ($turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno #'.$turnoId.' no tiene cierre definitivo.');
        }

        $empresaId = (int) ($cabecera['empresa_id'] ?? $turno->empresa_id);
        if ($empresaId !== (int) $turno->empresa_id) {
            throw new InvalidArgumentException('La empresa no coincide con el turno seleccionado.');
        }

        $datos = $this->armarDatosDesdeTurnoCerrado($turno);
        $codigo = trim((string) ($cabecera['codigo'] ?? ''));
        $propuesta = null;
        if ($codigo === '') {
            $propuesta = $this->anitaSyncService->proponerSiguienteNroOper($empresaId);
            $codigo = $propuesta['codigo'];
        }

        $fecharendicion = now();
        $observacion = trim((string) ($cabecera['observacion'] ?? ''));

        return DB::transaction(function () use ($turno, $datos, $codigo, $propuesta, $fecharendicion, $observacion) {
            $rendicion = RendicionBingoCaja::query()->create([
                'codigo' => $codigo,
                'nro_oper_anita' => $propuesta['nro_oper'] ?? RendicionBingoCabeceraAnitaMapper::nroOperDesdeCodigo($codigo),
                'fuente_nro_oper' => $propuesta['fuente'] ?? null,
                'empresa_id' => (int) $turno->empresa_id,
                'cuentacaja_id' => $turno->configuracionPuntoventa?->cuentacaja_id,
                'turno_operativo_bingo_id' => (int) $turno->id,
                'jornada_bingo_id' => (int) $turno->jornada_bingo_id,
                'creousuario_id' => (int) Auth::id(),
                'fecharendicion' => $fecharendicion,
                'fecha_jornada' => $datos['fecha_jornada'],
                'cant_cartones' => (int) $datos['calculo']['cant_cartones'],
                'total_cartones' => (float) $datos['calculo']['total_cartones'],
                'deposito' => round((float) ($turno->deposito ?? $datos['calculo']['saldo_final']), 2),
                'saldo_final' => (float) $datos['calculo']['saldo_final'],
                'sobrante_faltante' => (float) ($turno->sobrante_faltante ?? 0),
                'vales' => (float) ($turno->vales ?? 0),
                'redondeo' => (float) ($turno->redondeo ?? 0),
                'cartones_json' => $datos['cartones'],
                'conceptos_json' => $datos['calculo']['lineas_concepto'],
                'medios_contado_json' => $turno->medios_contado_cierre_json,
                'observacion' => $observacion !== '' ? $observacion : ($turno->observacion_cierre ?? null),
                'cerro_turno' => false,
            ]);

            $this->anitaSyncService->sincronizarDespuesDeGuardar($rendicion);
            $turno->update(['rendicion_presentada' => true]);

            return $rendicion->fresh(['empresa', 'turnoOperativo.turno', 'jornada']);
        });
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id) {
            $rendicion = RendicionBingoCaja::query()->findOrFail($id);
            $turnoId = (int) ($rendicion->turno_operativo_bingo_id ?? 0);

            $this->anitaSyncService->sincronizarDespuesDeEliminar($rendicion);

            $rendicion->delete();

            if ($turnoId > 0) {
                TurnoOperativoBingo::query()
                    ->whereKey($turnoId)
                    ->update(['rendicion_presentada' => false]);
            }
        });
    }

    /**
     * @deprecated Use guardarCierreTerminal o guardarPresentacionCaja
     *
     * @param  array<string, mixed>  $payload
     */
    public function guardarDesdeTurnoActivo(string $identificadorPc, array $payload): RendicionBingoCaja|TurnoOperativoBingo
    {
        return $this->guardarCierreTerminal($identificadorPc, $payload);
    }

    public function turnoYaPresentado(int $turnoId, ?int $exceptoRendicionId = null): bool
    {
        if ($turnoId <= 0) {
            return false;
        }

        return RendicionBingoCaja::query()
            ->where('turno_operativo_bingo_id', $turnoId)
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->exists();
    }

    /**
     * @return Collection<int, TurnoOperativoBingo>
     */
    public function turnosPendientes(?int $exceptoRendicionId = null, ?int $empresaId = null): Collection
    {
        $presentados = RendicionBingoCaja::query()
            ->whereNotNull('turno_operativo_bingo_id')
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->pluck('turno_operativo_bingo_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->values()
            ->all();

        return TurnoOperativoBingo::query()
            ->with(['turno:id,nombre,codigo', 'jornada:id,fecha_jornada', 'empresa:id,nombre', 'usuarioHabilitado:id,nombre'])
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->whereNotNull('cierre_en')
            ->where('rendicion_presentada', false)
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotIn('id', $presentados)
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
    ): array {
        if ($empresaId <= 0) {
            return ['data' => '<tr><td colspan="7">Seleccione una empresa.</td></tr>'];
        }

        $consulta = trim($consulta);

        if ($consulta !== '' && ctype_digit($consulta)) {
            try {
                $encontrado = $this->findTurnoPendientePorNumero((int) $consulta, $empresaId, $exceptoRendicionId);
                $turno = TurnoOperativoBingo::query()
                    ->with(['turno:id,nombre', 'jornada:id,fecha_jornada', 'empresa:id,nombre', 'usuarioHabilitado:id,nombre'])
                    ->find($encontrado['id']);

                if ($turno === null) {
                    return ['data' => '<tr><td colspan="7">Cierre no encontrado.</td></tr>'];
                }

                return ['data' => $this->renderTurnosPendientesHtml(collect([$turno]), $puedeVerComprobante)];
            } catch (InvalidArgumentException $e) {
                return ['data' => '<tr><td colspan="7">'.e($e->getMessage()).'</td></tr>'];
            }
        }

        $turnos = $this->turnosPendientes($exceptoRendicionId, $empresaId);

        if ($consulta !== '') {
            $needle = mb_strtoupper($consulta);
            $turnos = $turnos->filter(function (TurnoOperativoBingo $t) use ($needle) {
                $haystack = mb_strtoupper(implode(' ', [
                    (string) $t->id,
                    (string) ($t->turno?->nombre ?? ''),
                    (string) ($t->identificador_pc ?? ''),
                    (string) ($t->cierre_en?->format('d/m/Y H:i') ?? ''),
                    (string) ($t->jornada?->fecha_jornada?->format('d/m/Y') ?? ''),
                    (string) ($t->usuarioHabilitado?->nombre ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        if ($turnos->isEmpty()) {
            return ['data' => '<tr><td colspan="7">Sin cierres pendientes de presentar en caja.</td></tr>'];
        }

        return ['data' => $this->renderTurnosPendientesHtml($turnos, $puedeVerComprobante)];
    }

    /**
     * @return array{id: int, etiqueta: string}
     */
    public function findTurnoPendientePorNumero(int $numero, int $empresaId, ?int $exceptoRendicionId = null): array
    {
        if ($numero <= 0 || $empresaId <= 0) {
            throw new InvalidArgumentException('Indique un número de cierre de turno válido.');
        }

        $turno = TurnoOperativoBingo::query()
            ->with(['turno:id,nombre', 'jornada:id,fecha_jornada', 'usuarioHabilitado:id,nombre'])
            ->where('id', $numero)
            ->first();

        if ($turno === null) {
            throw new InvalidArgumentException('No existe el turno operativo #'.$numero.'.');
        }

        if ((int) $turno->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El turno #'.$numero.' pertenece a otra empresa.');
        }

        if ($turno->estado !== TurnoOperativoBingo::ESTADO_CERRADO || $turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno #'.$numero.' no tiene cierre definitivo.');
        }

        if ($this->turnoYaPresentado($numero, $exceptoRendicionId) || $turno->rendicion_presentada) {
            throw new InvalidArgumentException('El turno #'.$numero.' ya fue presentado en caja.');
        }

        return [
            'id' => (int) $turno->id,
            'etiqueta' => $this->etiquetaTurnoPendiente($turno),
        ];
    }

    public function proponerCodigoAnita(int $empresaId): string
    {
        return $this->anitaSyncService->proponerSiguienteNroOper($empresaId)['codigo'];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDesdeTurno(int $turnoId, ?int $exceptoRendicionId = null): array
    {
        if ($this->turnoYaPresentado($turnoId, $exceptoRendicionId)) {
            throw new InvalidArgumentException('El turno operativo #'.$turnoId.' ya fue presentado en caja.');
        }

        $turno = TurnoOperativoBingo::query()
            ->with(['turno', 'jornada', 'empresa', 'usuarioHabilitado', 'configuracionPuntoventa'])
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->findOrFail($turnoId);

        if ($turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno no tiene cierre definitivo.');
        }

        $datos = $this->armarDatosDesdeTurnoCerrado($turno);
        $datos['turno_operativo_bingo_id'] = (int) $turno->id;
        $datos['codigo_sugerido'] = $this->proponerCodigoAnita((int) $turno->empresa_id);
        $datos['etiqueta_turno'] = $this->etiquetaTurnoPendiente($turno);
        $datos['comprobante_url'] = route('bingo_cierre_turno_comprobante_cierre', ['id' => $turno->id, 'inline' => 1]);

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarDatosDesdeTurnoCerrado(TurnoOperativoBingo $turno): array
    {
        $empresaId = (int) $turno->empresa_id;
        $lineasGuardadas = collect($turno->cartones_rendicion_json ?? []);
        $montosManuales = $this->extraerMontosManuales($turno);

        $lineas = $this->normalizarLineasCartones($empresaId, $lineasGuardadas->all());
        $conceptosPayload = $turno->conceptos_rendicion_json ?? [];
        if ($lineas === [] && is_array($conceptosPayload['lineas'] ?? null)) {
            $calculoGuardado = [
                'cant_cartones' => 0,
                'total_cartones' => round((float) ($turno->monto_rendicion_turno ?? 0), 2),
                'lineas_concepto' => $conceptosPayload['lineas'],
                'saldo_final' => round((float) ($turno->monto_rendicion_turno ?? 0), 2),
            ];
        } else {
            $calculoGuardado = BingoRendicionCalculoSupport::calcular(
                $lineas,
                $this->conceptosActivos($empresaId),
                $montosManuales,
            );
        }

        return [
            'turno' => $turno,
            'fecha_jornada' => $turno->jornada?->fecha_jornada?->format('Y-m-d') ?? $turno->cierre_en?->format('Y-m-d'),
            'fecha_jornada_fmt' => $turno->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'cartones' => $lineas,
            'calculo' => $calculoGuardado,
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($turno->empresa?->nombre ?? ''),
            'identificador_pc' => (string) ($turno->identificador_pc ?? ''),
            'usuario_habilitado' => (string) ($turno->usuarioHabilitado?->nombre ?? ''),
            'deposito' => round((float) ($turno->deposito ?? 0), 2),
            'redondeo' => round((float) ($turno->redondeo ?? 0), 2),
            'sobrante_faltante' => round((float) ($turno->sobrante_faltante ?? 0), 2),
            'vales' => round((float) ($turno->vales ?? 0), 2),
            'medios_contado' => $turno->medios_contado_cierre_json ?? [],
        ];
    }

    /**
     * @param  Collection<int, TurnoOperativoBingo>  $turnos
     */
    private function renderTurnosPendientesHtml(Collection $turnos, bool $puedeVerComprobante = false): string
    {
        $html = '';
        foreach ($turnos as $t) {
            $recaudacion = 0.0;
            $cartones = $t->cartones_rendicion_json ?? [];
            if (is_array($cartones) && $cartones !== []) {
                foreach ($cartones as $linea) {
                    if (! empty($linea['anulado'])) {
                        continue;
                    }
                    $recaudacion += (int) ($linea['cantidad'] ?? 0) * (float) ($linea['precio_unitario'] ?? 0);
                }
            } elseif ((float) ($t->monto_rendicion_turno ?? 0) > 0) {
                $recaudacion = (float) $t->monto_rendicion_turno;
            }

            $html .= '<tr>';
            $html .= '<td class="id">'.e((string) $t->id).'</td>';
            $html .= '<td class="turno_nombre">'.e((string) ($t->turno?->nombre ?? '')).'</td>';
            $html .= '<td class="identificador_pc">'.e((string) ($t->identificador_pc ?? '')).'</td>';
            $html .= '<td class="cierre_en">'.e((string) ($t->cierre_en?->format('d/m/Y H:i') ?? '')).'</td>';
            $html .= '<td class="fecha_jornada">'.e((string) ($t->jornada?->fecha_jornada?->format('d/m/Y') ?? '')).'</td>';
            $html .= '<td class="recaudacion text-right">$'.e(number_format($recaudacion, 2, ',', '.')).'</td>';
            $html .= '<td class="text-nowrap">';
            if ($puedeVerComprobante) {
                $urlPdf = route('bingo_cierre_turno_comprobante_cierre', ['id' => $t->id, 'inline' => 1]);
                $tituloPdf = 'Op. #'.$t->id.' — '.($t->turno?->nombre ?? '');
                $html .= '<button type="button" class="btn btn-outline-danger btn-sm js-ver-comprobante-cierre-modal mr-1" ';
                $html .= 'data-url="'.e($urlPdf).'" data-titulo="'.e($tituloPdf).'" title="Ver comprobante">';
                $html .= '<i class="fa fa-file-pdf-o"></i></button>';
            }
            $html .= '<a class="btn btn-warning btn-sm eligeconsultacierre">Elegir</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    private function etiquetaTurnoPendiente(TurnoOperativoBingo $turno): string
    {
        return 'Op. #'.$turno->id.' — '.($turno->turno?->nombre ?? '')
            .' — '.($turno->identificador_pc ?? '')
            .' — cierre '.($turno->cierre_en?->format('d/m/Y H:i') ?? '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, RendicionBingoCaja>|Collection<int, RendicionBingoCaja>
     */
    public function listar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $q = RendicionBingoCaja::query()
            ->with([
                'empresa:id,nombre,codigo',
                'turnoOperativo:id,turno_bingo_id,identificador_pc',
                'turnoOperativo.turno:id,nombre,codigo',
                'jornada:id,fecha_jornada',
                'creousuario:id,nombre',
            ])
            ->orderByDesc('fecharendicion')
            ->orderByDesc('id');

        RendicionBingoCajaListadoFiltros::aplicarScopeEmpresasAsignadas($q, $filtros);

        if (RendicionBingoCajaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RendicionBingoCajaListadoFiltros::aplicar($q, $filtros);
        }

        return $paginar ? $q->paginate(10) : $q->get();
    }

    public function datosPantallaCarga(int $empresaId, string $identificadorPc): array
    {
        $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($identificadorPc, $empresaId);
        if ($turno === null) {
            throw new InvalidArgumentException('No hay turno habilitado para cargar la rendición.');
        }

        $turno->load(['turno', 'jornada', 'usuarioHabilitado']);

        return $this->armarDatosPantallaRendicion($turno, $identificadorPc, false);
    }

    public function datosPantallaEdicion(int $turnoId): array
    {
        $turno = TurnoOperativoBingo::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado'])
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->findOrFail($turnoId);

        if ($turno->rendicion_presentada) {
            throw new InvalidArgumentException('La rendición ya fue presentada en caja.');
        }

        if ($turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno no tiene cierre definitivo.');
        }

        return $this->armarDatosPantallaRendicion($turno, (string) $turno->identificador_pc, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function armarDatosPantallaRendicion(TurnoOperativoBingo $turno, string $identificadorPc, bool $modoEdicion): array
    {
        $empresaId = (int) $turno->empresa_id;

        $cartones = BingoCarton::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', BingoCarton::ESTADO_ACTIVO)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();

        $lineasGuardadas = collect($turno->cartones_rendicion_json ?? []);
        $montosManuales = $this->extraerMontosManuales($turno);

        $lineasIniciales = [];
        foreach ($cartones as $carton) {
            $guardada = $lineasGuardadas->first(
                fn ($g) => (int) ($g['carton_id'] ?? 0) === (int) $carton->id
            );
            $lineasIniciales[] = [
                'carton_id' => (int) $carton->id,
                'codigo' => (string) $carton->codigo,
                'nombre' => (string) $carton->nombre,
                'precio_unitario' => (float) $carton->precio_unitario,
                'lineas' => (int) $carton->lineas,
                'es_azar' => (bool) $carton->es_azar,
                'cantidad' => (int) ($guardada['cantidad'] ?? 0),
                'anulado' => (bool) ($guardada['anulado'] ?? false),
            ];
        }

        $conceptos = $this->conceptosActivos($empresaId)->map(fn (BingoConceptoRendicion $c) => [
            'id' => (int) $c->id,
            'codigo' => (string) $c->codigo,
            'detalle' => (string) $c->detalle,
            'signo' => (string) $c->signo,
            'porcentaje' => $c->porcentaje !== null ? (float) $c->porcentaje : null,
            'base_calculo' => (string) $c->base_calculo,
            'monto_fijo' => $c->monto_fijo !== null ? (float) $c->monto_fijo : null,
            'es_saldo_rendicion' => (bool) $c->es_saldo_rendicion,
            'monto_manual' => (float) ($montosManuales[(int) $c->id] ?? 0),
        ]);

        $calculo = BingoRendicionCalculoSupport::calcular(
            $this->normalizarLineasCartones($empresaId, $lineasIniciales),
            $this->conceptosActivos($empresaId),
            $montosManuales,
        );

        $tieneBorrador = ! $modoEdicion
            && $turno->estado === TurnoOperativoBingo::ESTADO_HABILITADO
            && (
                ! empty($turno->cartones_rendicion_json)
                || ! empty($turno->conceptos_rendicion_json)
            );

        return [
            'turno' => $turno,
            'identificador_pc' => $identificadorPc,
            'cartones' => $lineasIniciales,
            'conceptos' => $conceptos,
            'calculo' => $calculo,
            'rendicion_presentada' => (bool) $turno->rendicion_presentada,
            'modo_edicion' => $modoEdicion,
            'tiene_borrador' => $tieneBorrador,
            'observacion_cierre' => (string) ($turno->observacion_cierre ?? ''),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function extraerMontosManuales(TurnoOperativoBingo $turno): array
    {
        $payload = $turno->conceptos_rendicion_json ?? [];
        $montos = [];

        if (is_array($payload['montos_manuales'] ?? null)) {
            foreach ($payload['montos_manuales'] as $id => $monto) {
                $conceptoId = (int) $id;
                if ($conceptoId > 0) {
                    $montos[$conceptoId] = (float) $monto;
                }
            }
            if ($montos !== []) {
                return $montos;
            }
        }

        $lineas = [];
        if (is_array($payload['lineas'] ?? null)) {
            $lineas = $payload['lineas'];
        } elseif (is_array($payload) && array_is_list($payload)) {
            $lineas = $payload;
        }

        if ($lineas === []) {
            return [];
        }

        $conceptosManuales = BingoConceptoRendicion::query()
            ->where('empresa_id', (int) $turno->empresa_id)
            ->where('estado', BingoConceptoRendicion::ESTADO_ACTIVO)
            ->where('base_calculo', BingoConceptoRendicion::BASE_MANUAL)
            ->where('es_saldo_rendicion', false)
            ->get(['id']);

        foreach ($conceptosManuales as $concepto) {
            $conceptoId = (int) $concepto->id;
            foreach ($lineas as $linea) {
                if (! is_array($linea)) {
                    continue;
                }
                if ((int) ($linea['concepto_id'] ?? 0) !== $conceptoId) {
                    continue;
                }
                if (! empty($linea['es_saldo_rendicion'])) {
                    continue;
                }
                $montos[$conceptoId] = (float) ($linea['monto'] ?? 0);
                break;
            }
        }

        return $montos;
    }

    /**
     * @param  list<array<string, mixed>>  $lineasConcepto
     * @param  array<int, float>  $montosManuales
     * @return array{lineas: list<array<string, mixed>>, montos_manuales: array<int, float>}
     */
    private function armarPayloadConceptos(array $lineasConcepto, array $montosManuales): array
    {
        return [
            'lineas' => $lineasConcepto,
            'montos_manuales' => $montosManuales,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $totalesTurno
     * @return list<array<string, mixed>>
     */
    private function resolverMediosContado(array $payload, array $totalesTurno, int $empresaId, float $montoRendicion): array
    {
        $mediosContado = GastronomiaTurnoMediosContadoCierreSupport::normalizarParaGuardar(
            $payload['medios_contado'] ?? null,
            $totalesTurno,
            $empresaId,
        );

        if ($mediosContado === null || $mediosContado === []) {
            $rawMedios = is_array($payload['medios_contado'] ?? null) ? $payload['medios_contado'] : [];
            if ($rawMedios !== []) {
                return $rawMedios;
            }

            if ($montoRendicion > 0) {
                return [[
                    'medio' => 'Efectivo',
                    'monto' => $montoRendicion,
                ]];
            }
        }

        return is_array($mediosContado) ? $mediosContado : [];
    }

    /**
     * @param  list<array<string, mixed>>  $lineasRaw
     * @return list<array<string, mixed>>
     */
    private function sanitizarLineasCartonesParaGuardar(int $empresaId, array $lineasRaw): array
    {
        $out = [];
        foreach ($lineasRaw as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $cartonId = (int) ($linea['carton_id'] ?? 0);
            if ($cartonId <= 0) {
                continue;
            }

            $carton = BingoCarton::query()
                ->where('id', $cartonId)
                ->where('empresa_id', $empresaId)
                ->first();
            if ($carton === null) {
                continue;
            }

            $anulado = ! empty($linea['anulado']);
            $cantidad = max(0, (int) ($linea['cantidad'] ?? 0));
            $out[] = [
                'carton_id' => $cartonId,
                'codigo' => (string) ($linea['codigo'] ?? $carton->codigo),
                'nombre' => (string) ($linea['nombre'] ?? $carton->nombre),
                'precio_unitario' => (float) ($linea['precio_unitario'] ?? $carton->precio_unitario),
                'cantidad' => $anulado ? 0 : $cantidad,
                'anulado' => $anulado,
            ];
        }

        return $out;
    }

    /**
     * @return Collection<int, BingoConceptoRendicion>
     */
    private function conceptosActivos(int $empresaId): Collection
    {
        return BingoConceptoRendicion::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', BingoConceptoRendicion::ESTADO_ACTIVO)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCartones
     * @return list<array{carton_id: int, cantidad: int, precio_unitario: float, codigo?: string, nombre?: string, anulado?: bool}>
     */
    private function normalizarLineasCartones(int $empresaId, array $lineasCartones): array
    {
        $out = [];
        foreach ($lineasCartones as $linea) {
            if (! empty($linea['anulado'])) {
                continue;
            }
            $cartonId = (int) ($linea['carton_id'] ?? 0);
            $cantidad = max(0, (int) ($linea['cantidad'] ?? 0));
            if ($cartonId <= 0 || $cantidad <= 0) {
                continue;
            }

            $precio = (float) ($linea['precio_unitario'] ?? 0);
            if ($precio <= 0) {
                $carton = BingoCarton::query()
                    ->where('id', $cartonId)
                    ->where('empresa_id', $empresaId)
                    ->first();
                if ($carton === null) {
                    continue;
                }
                $precio = (float) $carton->precio_unitario;
            }

            $out[] = [
                'carton_id' => $cartonId,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'codigo' => (string) ($linea['codigo'] ?? ''),
                'nombre' => (string) ($linea['nombre'] ?? ''),
            ];
        }

        return $out;
    }
}
