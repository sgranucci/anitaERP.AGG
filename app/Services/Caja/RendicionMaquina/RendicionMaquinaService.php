<?php

namespace App\Services\Caja\RendicionMaquina;

use App\Models\Caja\AperturaGasto;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\RendicionMaquina;
use App\Models\Caja\RendicionMaquinaAjusteWigos;
use App\Models\Caja\Usocuentacaja;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaAjusteWigosSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaCompletoDelDiaSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaContextoBuilder;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaPreviasSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaResultadoCalculo;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaValorQrPrecargaSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaValoresCuentacajaSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaVariables;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaWigosLeeOnlineSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class RendicionMaquinaService
{
    public function __construct(
        private readonly RendicionMaquinaCalculoService $calculoService,
        private readonly UsuarioRepositoryInterface $usuarioRepository,
        private readonly RendicionMaquinaAnitaSyncService $anitaSyncService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function calcularDesdePayload(array $payload): RendicionMaquinaResultadoCalculo
    {
        $payload = $this->enriquecerPayloadConPrevias($payload);
        $payload = $this->enriquecerPayloadValoresCuentacaja($payload);
        $contexto = RendicionMaquinaContextoBuilder::desdePayload($payload);

        return $this->calculoService->calcular($contexto);
    }

    /**
     * @param  list<array<string, mixed>>  $lineasValor
     * @return array<string, float>
     */
    public function armarValoresTotales(array $lineasValor): array
    {
        return RendicionMaquinaContextoBuilder::armarValoresTotales($lineasValor);
    }

    /**
     * @param  list<array<string, mixed>>  $lineasGasto
     */
    public function armarGastosTotal(array $lineasGasto): float
    {
        return RendicionMaquinaContextoBuilder::armarGastosTotal($lineasGasto);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function guardar(array $payload, ?int $id, int $usuarioId): RendicionMaquina
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $fecha = (string) ($payload['fecha'] ?? '');
        $turno = RendicionMaquinaTurno::normalizar((string) ($payload['turno'] ?? RendicionMaquinaTurno::MANIANA));

        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe indicar la empresa.');
        }
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }

        $this->assertUnicoEmpresaFechaTurno($empresaId, $fecha, $turno, $id);

        $payload = $this->enriquecerPayloadValoresCuentacaja($payload);
        $resultado = $this->calcularDesdePayload($payload);
        $totales = $resultado->totalesCierre();

        $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : [];
        // Paridad Anita: deposito se calcula (D25), no se tipea
        $inputs['deposito'] = $resultado->get('calc.deposito');
        $wigosJson = is_array($payload['wigos_json'] ?? null) ? $payload['wigos_json'] : null;
        $lineasValor = is_array($payload['valores'] ?? null) ? $payload['valores'] : [];
        $lineasGasto = is_array($payload['gastos'] ?? null) ? $payload['gastos'] : [];
        $ajustes = is_array($payload['ajustes'] ?? null) ? $payload['ajustes'] : [];

        return DB::transaction(function () use (
            $payload,
            $id,
            $usuarioId,
            $empresaId,
            $fecha,
            $turno,
            $inputs,
            $wigosJson,
            $lineasValor,
            $lineasGasto,
            $ajustes,
            $resultado,
            $totales,
        ) {
            $cabecera = [
                'empresa_id' => $empresaId,
                'fecha' => $fecha,
                'turno' => $turno,
                'estado' => (string) ($payload['estado'] ?? RendicionMaquina::ESTADO_CONFIRMADA),
                'supervisor_usuario_id' => $this->nullableInt($payload['supervisor_usuario_id'] ?? null),
                'auxiliar_usuario_id' => $this->nullableInt($payload['auxiliar_usuario_id'] ?? null),
                'cajero_usuario_id' => $this->nullableInt($payload['cajero_usuario_id'] ?? null),
                'observacion' => trim((string) ($payload['observacion'] ?? '')) ?: null,
                'inputs_json' => $this->normalizarInputsParaPersistencia($inputs),
                'wigos_json' => $wigosJson,
                'calc_json' => [
                    'variables' => $resultado->variables,
                    'rastro' => $resultado->rastro,
                    'modo_wigos' => $resultado->modoWigos,
                ],
                'total_ingreso' => $totales['total_ingreso'],
                'total_salida' => $totales['total_salida'],
                'resultado_turno' => $totales['resultado_turno'],
                'transferencia' => $totales['transferencia'],
                'fondo_cierre' => $totales['fondo_cierre'],
                'fondo_inicial' => $totales['fondo_inicial'],
                'dif_caja' => $totales['dif_caja'],
            ];

            if ($id !== null && $id > 0) {
                $rendicion = RendicionMaquina::query()->findOrFail($id);
                $rendicion->update($cabecera);
            } else {
                $cabecera['creousuario_id'] = $usuarioId;
                $cabecera['codigo'] = $this->generarCodigo($empresaId, $fecha, $turno);
                $rendicion = RendicionMaquina::query()->create($cabecera);
            }

            $this->reemplazarDetalle($rendicion, $lineasValor, $lineasGasto, $empresaId);
            $this->registrarAjustesWigos($rendicion, $ajustes, $usuarioId);

            $this->sincronizarDespuesDeGuardar($rendicion);

            return $rendicion->fresh(['valores.cuentacaja', 'gastos.aperturaGasto', 'empresa']);
        });
    }

    public function eliminar(int $id): void
    {
        $rendicion = RendicionMaquina::query()->findOrFail($id);
        $rendicion->estado = RendicionMaquina::ESTADO_ANULADA;
        $rendicion->save();

        $this->anitaSyncService->sincronizarDespuesDeEliminar($rendicion);

        $rendicion->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function datosPantalla(int $empresaId, ?string $fecha, ?string $turno, ?int $id): array
    {
        $fechaYmd = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
            ? $fecha
            : date('Y-m-d');

        $turnoNorm = RendicionMaquinaTurno::normalizar($turno ?? RendicionMaquinaTurno::MANIANA);

        $rendicion = null;
        if ($id !== null && $id > 0) {
            $rendicion = RendicionMaquina::query()
                ->with([
                    'valores.cuentacaja',
                    'gastos.aperturaGasto',
                    'supervisorUsuario:id,nombre,usuario',
                    'auxiliarUsuario:id,nombre,usuario',
                    'cajeroUsuario:id,nombre,usuario',
                ])
                ->findOrFail($id);
            $empresaId = (int) $rendicion->empresa_id;
            $fechaYmd = $rendicion->fecha?->format('Y-m-d') ?? $fechaYmd;
            $turnoNorm = (string) $rendicion->turno;
        }

        $cuentasValor = $this->listarCuentasValor($empresaId, $rendicion, $fechaYmd);
        $gastos = $this->listarGastos($empresaId, $rendicion);

        $inputs = is_array($rendicion?->inputs_json) ? $rendicion->inputs_json : [];
        $previas = RendicionMaquinaPreviasSupport::resolver(
            $empresaId,
            $fechaYmd,
            $turnoNorm,
            $id
        );

        if ($rendicion === null) {
            if (! isset($inputs['fondo_inicial']) || (float) $inputs['fondo_inicial'] == 0.0) {
                $inputs['fondo_inicial'] = $previas['fondo_inicial'];
            }
        }

        $calcOrquestador = [
            'comprobante' => (float) ($rendicion?->calc_json['variables']['calc.comprobante']
                ?? $previas['comprobante']),
            'vale_rep_fondo' => (float) ($rendicion?->calc_json['variables']['calc.vale_rep_fondo']
                ?? $previas['vale_rep_fondo']),
        ];

        $payloadDemo = [
            'empresa_id' => $empresaId,
            'fecha' => $fechaYmd,
            'turno' => $turnoNorm,
            'inputs' => $inputs,
            'valores' => $cuentasValor,
            'gastos' => $gastos,
            'calc_orquestador' => $calcOrquestador,
            'rendicion_id' => $id,
            'previas' => $previas,
        ];

        $calculo = null;
        $totales = [];
        // Alta: pie en cero hasta Traer WIGOS / editar (Ctrl+R no debe dejar totales viejos).
        // Edición: sí precalcular con lo grabado.
        if ($empresaId > 0 && $rendicion !== null) {
            try {
                $calculo = $this->calcularDesdePayload($payloadDemo);
                $totales = $calculo->totalesCierre();
            } catch (\Throwable) {
                $totales = [];
            }
        }

        return [
            'rendicion' => $rendicion,
            'empresa_id' => $empresaId,
            'fecha' => $fechaYmd,
            'turno' => $turnoNorm,
            'cuentas_valor' => $cuentasValor,
            'gastos' => $gastos,
            'inputs' => $inputs,
            'wigos_json' => $rendicion?->wigos_json ?? [],
            'calc_orquestador' => $calcOrquestador,
            'totales' => $totales,
            'calculo' => $calculo,
            'turnos' => $this->enumTurnos(),
            'estados' => RendicionMaquina::$enumEstado,
            'usuarios' => $this->usuarioRepository->listadoOperativoParaSelector($empresaId > 0 ? $empresaId : null),
            'campos_wigos' => RendicionMaquinaAjusteWigosSupport::CAMPOS_WIGOS,
            'campos_impuestos' => RendicionMaquinaAjusteWigosSupport::CAMPOS_IMPUESTOS,
            'campos_wigos_ajustables' => RendicionMaquinaAjusteWigosSupport::camposAjustables(),
            'campos_manuales' => $this->camposManualesInputs(),
            'previas' => $previas,
        ];
    }

    /**
     * Completa fondo/comprobante/previas si el cliente no los mandó.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enriquecerPayloadConPrevias(array $payload): array
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $fecha = (string) ($payload['fecha'] ?? '');
        $turno = (string) ($payload['turno'] ?? RendicionMaquinaTurno::MANIANA);
        $exceptoId = (int) ($payload['rendicion_id'] ?? $payload['id'] ?? 0) ?: null;

        if ($empresaId <= 0 || $fecha === '') {
            return $payload;
        }

        $previas = RendicionMaquinaPreviasSupport::resolver($empresaId, $fecha, $turno, $exceptoId);
        $payload['previas'] = $previas;

        $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : [];
        $orq = is_array($payload['calc_orquestador'] ?? null) ? $payload['calc_orquestador'] : [];

        // 0 / ausente = no cargado: completar desde previas (ERP o Anita)
        $fondoActual = (float) ($inputs['fondo_inicial'] ?? $inputs['inputs.fondo_inicial'] ?? 0);
        if (abs($fondoActual) < 0.00001 && abs((float) ($previas['fondo_inicial'] ?? 0)) > 0.00001) {
            $inputs['fondo_inicial'] = $previas['fondo_inicial'];
        }

        // Impuesto drop: no pisar 0 manual (antes 0 = “vacío” y volvía la previa del C).
        // Solo completar si el cliente no envió la clave, y solo en mañana.
        $turnoPayload = RendicionMaquinaTurno::normalizar($turno);
        $tieneImpDrop = array_key_exists('impuesto_drop', $inputs)
            || array_key_exists('inputs.impuesto_drop', $inputs);
        if (! $tieneImpDrop
            && RendicionMaquinaTurno::esManiana($turnoPayload)
            && abs((float) ($previas['impuesto_drop'] ?? 0)) > 0.00001) {
            $inputs['impuesto_drop'] = $previas['impuesto_drop'];
        }

        // Si vienen en 0 (cambio de fecha blanqueó inputs), completar desde previas.
        // Completo: apertura = misma semilla que M (fondo + comprobante); vale = 0.
        if (RendicionMaquinaTurno::esCompleto($turnoPayload)) {
            $orq['vale_rep_fondo'] = 0.0;
            if (abs((float) ($inputs['fondo_inicial'] ?? $inputs['inputs.fondo_inicial'] ?? 0)) < 0.00001
                && abs((float) ($previas['fondo_inicial'] ?? 0)) > 0.00001) {
                $inputs['fondo_inicial'] = round((float) $previas['fondo_inicial'], 2);
            }
            if (abs((float) ($orq['comprobante'] ?? 0)) < 0.00001
                && abs((float) ($previas['comprobante'] ?? 0)) > 0.00001) {
                $orq['comprobante'] = round((float) $previas['comprobante'], 2);
            }
            $faltaCierre = abs((float) ($orq['fondo_cierre'] ?? 0)) < 0.00001
                || abs((float) ($orq['resultado_turno'] ?? 0)) < 0.00001
                || ! array_key_exists('transferencia', $orq);
            if ($faltaCierre) {
                $completo = RendicionMaquinaCompletoDelDiaSupport::consolidar(
                    $empresaId,
                    $fecha,
                    $exceptoId
                );
                $orq['fondo_cierre'] = round((float) ($completo['orquestador']['fondo_cierre'] ?? 0), 2);
                $orq['resultado_turno'] = round((float) ($completo['orquestador']['resultado_turno'] ?? 0), 2);
                $orq['transferencia'] = round((float) ($completo['orquestador']['transferencia'] ?? 0), 2);
                if (abs((float) ($orq['comprobante'] ?? 0)) < 0.00001) {
                    $orq['comprobante'] = round((float) ($completo['orquestador']['comprobante'] ?? 0), 2);
                }
                if (abs((float) ($inputs['fondo_inicial'] ?? 0)) < 0.00001) {
                    $inputs['fondo_inicial'] = round((float) ($completo['inputs']['fondo_inicial'] ?? 0), 2);
                }
            }
        } else {
            if (abs((float) ($orq['comprobante'] ?? 0)) < 0.00001
                && abs((float) ($previas['comprobante'] ?? 0)) > 0.00001) {
                $orq['comprobante'] = round((float) $previas['comprobante'], 2);
            }
            if (abs((float) ($orq['vale_rep_fondo'] ?? 0)) < 0.00001
                && abs((float) ($previas['vale_rep_fondo'] ?? 0)) > 0.00001) {
                $orq['vale_rep_fondo'] = round((float) $previas['vale_rep_fondo'], 2);
            }
        }

        $payload['inputs'] = $inputs;
        $payload['calc_orquestador'] = $orq;

        return $payload;
    }

    /**
     * Completa moneda_id desde cuentacaja y cotización vigente de tesorería.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enriquecerPayloadValoresCuentacaja(array $payload): array
    {
        $payload['valores'] = RendicionMaquinaValoresCuentacajaSupport::enriquecerLineas(
            is_array($payload['valores'] ?? null) ? $payload['valores'] : [],
            (string) ($payload['fecha'] ?? date('Y-m-d')),
            (int) ($payload['empresa_id'] ?? 0)
        );

        return $payload;
    }

    /**
     * Trae drop/tito/venta/QR desde WIGOS (RENDM_lee_on_line / calc_datos_wigos).
     * En turno Completo consolida M/T/N (lee_rendiciones_del_dia) y valores/gastos.
     *
     * @param  array<string, float|int|string>|null  $inputsActuales
     * @return array<string, mixed>
     */
    public function traerWigos(
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?array $inputsActuales = null,
        ?int $exceptoId = null
    ): array {
        $turnoNorm = RendicionMaquinaTurno::normalizar($turno);
        $esCompleto = RendicionMaquinaTurno::esCompleto($turnoNorm);

        try {
            $resultado = RendicionMaquinaWigosLeeOnlineSupport::traer(
                $empresaId,
                $fechaYmd,
                $turnoNorm,
                $exceptoId
            );
        } catch (\Throwable $e) {
            Log::warning('RendicionMaquina Traer WIGOS falló', [
                'empresa_id' => $empresaId,
                'fecha' => $fechaYmd,
                'turno' => $turnoNorm,
                'error' => $e->getMessage(),
            ]);

            $inputs = [];
            foreach (RendicionMaquinaVariables::INPUTS as $ruta) {
                $inputs[$this->claveInputCorta($ruta)] = 0.0;
            }
            $previasStub = RendicionMaquinaPreviasSupport::resolver($empresaId, $fechaYmd, $turnoNorm, $exceptoId);
            $inputs['fondo_inicial'] = round((float) ($previasStub['fondo_inicial'] ?? 0), 2);
            // Solo M trae impuesto drop del C anterior; T/N = 0 (Anita).
            $inputs['impuesto_drop'] = RendicionMaquinaTurno::esManiana($turnoNorm)
                ? round((float) ($previasStub['impuesto_drop'] ?? 0), 2)
                : 0.0;
            $inputs['drop_billete_bruto'] = 0.0;

            $stub = [
                'inputs' => $inputs,
                'wigos_json' => $inputs,
                'previas' => $previasStub,
                'calc_orquestador' => [
                    'comprobante' => round((float) ($previasStub['comprobante'] ?? 0), 2),
                    'vale_rep_fondo' => $esCompleto
                        ? 0.0
                        : round((float) ($previasStub['vale_rep_fondo'] ?? 0), 2),
                ],
                'meta' => [
                    'modo_wigos' => RendicionMaquinaTurno::modoWigos($turnoNorm),
                    'turno_wigos' => RendicionMaquinaTurno::letraWigos($turnoNorm),
                    'stub' => true,
                    'error' => $e->getMessage(),
                    'origen_fondo' => $previasStub['origen_fondo'],
                    'origen_vale_rep_fondo' => $previasStub['origen_vale_rep_fondo'],
                    'mensaje' => 'No se pudo leer WIGOS: '.$e->getMessage()
                        .'. Se dejaron ceros; complete manualmente o reintente.',
                ],
            ];

            if ($esCompleto) {
                $completo = RendicionMaquinaCompletoDelDiaSupport::consolidar(
                    $empresaId,
                    $fechaYmd,
                    $exceptoId
                );
                foreach ($completo['inputs'] as $clave => $valor) {
                    $stub['inputs'][$clave] = round((float) $valor, 2);
                }
                $stub['wigos_json'] = $stub['inputs'];
                $stub['valores'] = $completo['valores'];
                $stub['gastos'] = $completo['gastos'];
                $stub['calc_orquestador'] = $completo['orquestador'];
                $stub['meta']['completo_del_dia'] = $completo['meta'];
                $stub['meta']['origen_fondo'] = (string) ($completo['meta']['origen_fondo'] ?? $previasStub['origen_fondo']);
                $stub['meta']['origen_comprobante'] = (string) ($completo['meta']['origen_comprobante'] ?? 'ninguno');
            }

            return $stub;
        }

        $inputs = $resultado['inputs'];

        // Manuales: en Completo vienen del consolidado M/T/N; no conservar payload cliente.
        if (! $esCompleto) {
            $conservar = [
                'sobrantes',
                'variacion_ff',
                'pago_diferido',
                'vale_anterior',
                'ticket_prom',
                'vta_ant_gastro',
            ];
            if (is_array($inputsActuales)) {
                foreach ($conservar as $clave) {
                    if (array_key_exists($clave, $inputsActuales)) {
                        $inputs[$clave] = is_numeric($inputsActuales[$clave])
                            ? (float) $inputsActuales[$clave]
                            : 0.0;
                    } elseif (array_key_exists('inputs.'.$clave, $inputsActuales)) {
                        $inputs[$clave] = (float) $inputsActuales['inputs.'.$clave];
                    }
                }
            }
        }

        $previas = RendicionMaquinaPreviasSupport::resolver($empresaId, $fechaYmd, $turnoNorm, $exceptoId);

        // Completo ya trae fondo/comprobante del consolidado (semilla = M).
        // Parciales: fondo desde previas; impuesto drop del C anterior solo en M.
        if (! $esCompleto) {
            $inputs['fondo_inicial'] = round((float) ($previas['fondo_inicial'] ?? 0), 2);
            if (RendicionMaquinaTurno::esManiana($turnoNorm)) {
                $inputs['impuesto_drop'] = round((float) ($previas['impuesto_drop'] ?? 0), 2);
            } else {
                $inputs['impuesto_drop'] = 0.0;
            }
        } elseif (abs((float) ($inputs['fondo_inicial'] ?? 0)) < 0.00001) {
            $inputs['fondo_inicial'] = round((float) ($previas['fondo_inicial'] ?? 0), 2);
        }

        // Pantalla: drop_billete = neto (como Anita dr_bill_rod); bruto aparte.
        // En C el impuesto es el del M de D+1 (desfase jornada).
        $bruto = round((float) ($inputs['drop_billete'] ?? 0), 2);
        $imp = round((float) ($inputs['impuesto_drop'] ?? 0), 2);
        $inputs['drop_billete_bruto'] = $bruto;
        $inputs['drop_billete'] = round($bruto - $imp, 2);

        $resultado['inputs'] = $inputs;
        $resultado['wigos_json'] = $inputs;
        $resultado['previas'] = $previas;
        if ($esCompleto) {
            // Apertura = M; cierre/resultado = Noche; transfer = suma M+T+N; vale = 0
            $orqCompleto = is_array($resultado['calc_orquestador'] ?? null)
                ? $resultado['calc_orquestador']
                : [];
            $resultado['calc_orquestador'] = [
                'comprobante' => round((float) ($orqCompleto['comprobante'] ?? $previas['comprobante'] ?? 0), 2),
                'vale_rep_fondo' => 0.0,
                'fondo_cierre' => round((float) ($orqCompleto['fondo_cierre'] ?? 0), 2),
                'resultado_turno' => round((float) ($orqCompleto['resultado_turno'] ?? 0), 2),
                'transferencia' => round((float) ($orqCompleto['transferencia'] ?? 0), 2),
            ];
        } else {
            $resultado['calc_orquestador'] = [
                'comprobante' => round((float) ($previas['comprobante'] ?? 0), 2),
                'vale_rep_fondo' => round((float) ($previas['vale_rep_fondo'] ?? 0), 2),
            ];
        }
        $resultado['meta']['origen_fondo'] = $esCompleto
            ? (string) ($resultado['crudo']['completo_del_dia']['origen_fondo'] ?? $previas['origen_fondo'])
            : $previas['origen_fondo'];
        $resultado['meta']['origen_comprobante'] = $esCompleto
            ? (string) ($resultado['crudo']['completo_del_dia']['origen_comprobante'] ?? $previas['origen_comprobante'] ?? 'ninguno')
            : ($previas['origen_comprobante'] ?? 'ninguno');
        $resultado['meta']['origen_impuesto_drop'] = $esCompleto
            ? (string) ($resultado['crudo']['completo_del_dia']['origen_impuesto_drop'] ?? 'ninguno')
            : $previas['origen_impuesto_drop'];
        $resultado['meta']['origen_vale_rep_fondo'] = $esCompleto
            ? 'completo_sin_vale'
            : $previas['origen_vale_rep_fondo'];
        $resultado['meta']['origen_drop_ant_completo'] = $esCompleto
            ? (string) ($resultado['crudo']['drop_ant_origen'] ?? $previas['origen_drop_ant_completo'] ?? 'ninguno')
            : ($previas['origen_drop_ant_completo'] ?? 'ninguno');
        $resultado['meta']['origen_noche'] = $esCompleto
            ? (string) ($resultado['crudo']['completo_del_dia']['origen_noche'] ?? 'ninguno')
            : 'n/a';

        $resultado['precarga_valores'] = $this->precargaValorQrManiana(
            $empresaId,
            $fechaYmd,
            $turnoNorm,
            $inputs,
            (bool) ($resultado['meta']['stub'] ?? false),
            $esCompleto
        );

        return $resultado;
    }

    /** @deprecated usar traerWigos */
    public function stubTraerWigos(int $empresaId, string $fechaYmd, string $turno): array
    {
        return $this->traerWigos($empresaId, $fechaYmd, $turno);
    }

    public function sincronizarDespuesDeGuardar(RendicionMaquina $rendicion): void
    {
        try {
            $this->anitaSyncService->sincronizarDespuesDeGuardar($rendicion);
        } catch (\Throwable $e) {
            Log::error('RendicionMaquina Anita sync falló', [
                'id' => $rendicion->id,
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private function assertUnicoEmpresaFechaTurno(int $empresaId, string $fecha, string $turno, ?int $exceptId): void
    {
        $query = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fecha)
            ->where('turno', $turno);

        if ($exceptId !== null && $exceptId > 0) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Ya existe una rendición para esa empresa, fecha y turno.');
        }
    }

    private function generarCodigo(int $empresaId, string $fecha, string $turno): string
    {
        $base = sprintf('RM-%d-%s-%s', $empresaId, str_replace('-', '', $fecha), $turno);
        if (! RendicionMaquina::withTrashed()->where('codigo', $base)->exists()) {
            return $base;
        }

        return $base.'-'.time();
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, float|int|string>
     */
    private function normalizarInputsParaPersistencia(array $inputs): array
    {
        $out = [];
        foreach ($inputs as $clave => $valor) {
            $key = str_starts_with((string) $clave, 'inputs.')
                ? substr((string) $clave, 7)
                : (string) $clave;
            $out[$key] = is_numeric($valor) ? (float) $valor : $valor;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lineasValor
     * @param  list<array<string, mixed>>  $lineasGasto
     */
    private function reemplazarDetalle(RendicionMaquina $rendicion, array $lineasValor, array $lineasGasto, int $empresaId): void
    {
        $rendicion->valores()->delete();
        $rendicion->gastos()->delete();

        $fechaYmd = $rendicion->fecha?->format('Y-m-d') ?? date('Y-m-d');
        $lineasValor = RendicionMaquinaValoresCuentacajaSupport::enriquecerLineas(
            $lineasValor,
            $fechaYmd,
            $empresaId
        );

        $ordenValor = 0;
        foreach ($lineasValor as $linea) {
            $cuentacajaId = (int) ($linea['cuentacaja_id'] ?? 0);
            $monto = round((float) ($linea['monto'] ?? 0), 2);
            if ($cuentacajaId <= 0) {
                continue;
            }
            if (! Cuentacaja::existeParaEmpresa($cuentacajaId, $empresaId)) {
                throw new InvalidArgumentException("Cuenta de caja {$cuentacajaId} no válida para la empresa.");
            }

            $rendicion->valores()->create([
                'cuentacaja_id' => $cuentacajaId,
                'codigo_valormae' => $this->nullableInt($linea['codigo_valormae'] ?? null),
                'monto' => $monto,
                'cotizacion' => round((float) ($linea['cotizacion'] ?? 1), 6),
                'orden' => $ordenValor++,
            ]);
        }

        $ordenGasto = 0;
        foreach ($lineasGasto as $linea) {
            $aperturaGastoId = (int) ($linea['apertura_gasto_id'] ?? 0);
            $monto = round((float) ($linea['monto'] ?? 0), 2);
            if ($aperturaGastoId <= 0 || abs($monto) < 0.00001) {
                continue;
            }

            $gasto = AperturaGasto::query()
                ->whereKey($aperturaGastoId)
                ->where('estado', AperturaGasto::ESTADO_ACTIVO)
                ->whereHas('empresas', fn ($q) => $q->where('empresa_id', $empresaId))
                ->first();
            if ($gasto === null) {
                throw new InvalidArgumentException("Apertura de gasto {$aperturaGastoId} no válida.");
            }

            $rendicion->gastos()->create([
                'apertura_gasto_id' => $aperturaGastoId,
                'monto' => $monto,
                'orden' => $ordenGasto++,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $ajustes
     */
    private function registrarAjustesWigos(RendicionMaquina $rendicion, array $ajustes, int $usuarioId): void
    {
        if ($ajustes === []) {
            return;
        }

        if (! RendicionMaquinaAjusteWigosSupport::usuarioPuedeAjustar()) {
            throw new InvalidArgumentException('No tiene permiso para registrar ajustes WIGOS.');
        }

        foreach ($ajustes as $ajuste) {
            $registro = RendicionMaquinaAjusteWigosSupport::registrar([
                'rendicion_maquina_id' => (int) $rendicion->id,
                'empresa_id' => (int) $rendicion->empresa_id,
                'fecha' => $rendicion->fecha?->format('Y-m-d') ?? date('Y-m-d'),
                'turno' => (string) $rendicion->turno,
                'campo' => (string) ($ajuste['campo'] ?? ''),
                'valor_wigos' => (float) ($ajuste['valor_wigos'] ?? 0),
                'valor_ajustado' => (float) ($ajuste['valor_ajustado'] ?? 0),
                'motivo' => $ajuste['motivo'] ?? null,
                'usuario_id' => $usuarioId,
            ]);

            if ($registro !== null && (int) ($registro->rendicion_maquina_id ?? 0) !== (int) $rendicion->id) {
                $registro->update(['rendicion_maquina_id' => (int) $rendicion->id]);
            }
        }

        RendicionMaquinaAjusteWigos::query()
            ->where('empresa_id', $rendicion->empresa_id)
            ->whereDate('fecha', $rendicion->fecha)
            ->where('turno', $rendicion->turno)
            ->whereNull('rendicion_maquina_id')
            ->update(['rendicion_maquina_id' => (int) $rendicion->id]);
    }

    /**
     * Turno mañana: precarga TotalCoin QR Máquinas = drop QR rodillo + impuesto QR (WIGOS).
     * No pisa el consolidado del Completo ni una lectura stub (WIGOS falló).
     *
     * @param  array<string, float|int|string>  $inputs
     * @return list<array{cuentacaja_id: int, monto: float}>|null
     */
    private function precargaValorQrManiana(
        int $empresaId,
        string $fechaYmd,
        string $turno,
        array $inputs,
        bool $esStub,
        bool $esCompleto
    ): ?array {
        if ($esStub || $esCompleto || ! RendicionMaquinaTurno::esManiana($turno)) {
            return null;
        }

        $catalogo = $this->listarCuentasValor($empresaId, null, $fechaYmd);
        $lineas = RendicionMaquinaValorQrPrecargaSupport::lineasPrecarga($inputs, $catalogo);

        return $lineas === [] ? null : $lineas;
    }

    /**
     * Líneas de valores (cuentacaja) y gastos para una empresa (montos en cero).
     * Usado al cambiar empresa en alta.
     *
     * @return array{cuentas_valor: list<array<string, mixed>>, gastos: list<array<string, mixed>>}
     */
    public function lineasValorYGastoParaEmpresa(int $empresaId, ?string $fecha = null): array
    {
        return [
            'cuentas_valor' => $this->listarCuentasValor($empresaId, null, $fecha),
            'gastos' => $this->listarGastos($empresaId, null),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarCuentasValor(int $empresaId, ?RendicionMaquina $rendicion, ?string $fecha = null): array
    {
        $usoId = (int) (Usocuentacaja::query()
            ->where('nombre', RendicionMaquinaVariables::USO_CUENTACAJA_NOMBRE)
            ->value('id') ?? 0);

        $montosGuardados = [];
        if ($rendicion !== null) {
            foreach ($rendicion->valores as $valor) {
                $montosGuardados[(int) $valor->cuentacaja_id] = [
                    'monto' => (float) $valor->monto,
                    'cotizacion' => $valor->cotizacion !== null ? (float) $valor->cotizacion : null,
                    'codigo_valormae' => $valor->codigo_valormae,
                ];
            }
        }

        if ($usoId <= 0 || $empresaId <= 0) {
            return [];
        }

        $fechaYmd = $fecha
            ?? ($rendicion?->fecha?->format('Y-m-d'))
            ?? date('Y-m-d');

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($q) => $q->where('usocuentacaja.id', $usoId))
            ->get(['id', 'codigo', 'nombre', 'descripcion_operaciones', 'moneda_id']);

        $lineas = [];
        foreach ($cuentas as $cuenta) {
            $saved = $montosGuardados[(int) $cuenta->id] ?? null;
            $etiqueta = $cuenta->etiquetaOperaciones();
            $monedaId = (int) ($cuenta->moneda_id ?? 1);
            $lineas[] = [
                'cuentacaja_id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => $etiqueta,
                'descripcion_operaciones' => trim((string) ($cuenta->descripcion_operaciones ?? '')),
                'nombre_maestro' => (string) $cuenta->nombre,
                'monto' => $saved['monto'] ?? 0.0,
                'cotizacion' => $saved['cotizacion'] ?? null,
                'codigo_valormae' => $saved['codigo_valormae'] ?? null,
                'moneda_id' => $monedaId,
            ];
        }

        return RendicionMaquinaValoresCuentacajaSupport::enriquecerLineas(
            $this->ordenarLineasPorCodigoNumerico($lineas),
            $fechaYmd,
            $empresaId
        );
    }

    /**
     * Códigos numéricos primero (25, 100, 121…); alfanuméricos al final (M0QR).
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function ordenarLineasPorCodigoNumerico(array $lineas): array
    {
        usort($lineas, static function (array $a, array $b): int {
            $ca = trim((string) ($a['codigo'] ?? ''));
            $cb = trim((string) ($b['codigo'] ?? ''));
            $na = ctype_digit($ca) ? (int) $ca : null;
            $nb = ctype_digit($cb) ? (int) $cb : null;

            if ($na !== null && $nb !== null && $na !== $nb) {
                return $na <=> $nb;
            }
            if ($na !== null && $nb === null) {
                return -1;
            }
            if ($na === null && $nb !== null) {
                return 1;
            }

            return strnatcasecmp($ca, $cb);
        });

        return $lineas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarGastos(int $empresaId, ?RendicionMaquina $rendicion): array
    {
        $montosGuardados = [];
        if ($rendicion !== null) {
            foreach ($rendicion->gastos as $gasto) {
                $montosGuardados[(int) $gasto->apertura_gasto_id] = (float) $gasto->monto;
            }
        }

        if ($empresaId <= 0) {
            return [];
        }

        $conceptos = AperturaGasto::query()
            ->where('estado', AperturaGasto::ESTADO_ACTIVO)
            ->whereHas('empresas', fn ($q) => $q->where('empresa_id', $empresaId))
            ->get(['id', 'codigo', 'nombre']);

        $lineas = [];
        foreach ($conceptos as $concepto) {
            $lineas[] = [
                'apertura_gasto_id' => (int) $concepto->id,
                'codigo' => (int) $concepto->codigo,
                'nombre' => (string) $concepto->nombre,
                'monto' => $montosGuardados[(int) $concepto->id] ?? 0.0,
            ];
        }

        return $this->ordenarLineasPorCodigoNumerico($lineas);
    }

    /**
     * @return list<array{valor: string, nombre: string}>
     */
    private function enumTurnos(): array
    {
        return [
            ['valor' => RendicionMaquinaTurno::MANIANA, 'nombre' => 'Mañana (M)'],
            ['valor' => RendicionMaquinaTurno::TARDE, 'nombre' => 'Tarde (T)'],
            ['valor' => RendicionMaquinaTurno::NOCHE, 'nombre' => 'Noche (N)'],
            ['valor' => RendicionMaquinaTurno::COMPLETO, 'nombre' => 'Cierre jornada (C)'],
        ];
    }

    /**
     * @return list<string>
     */
    private function camposManualesInputs(): array
    {
        // Manuales visibles (abajo del bloque WIGOS/impuestos).
        // vta_ant_gastro sigue en fórmulas (D25) pero no en pantalla Anita ventana2.
        // Vales/reintegros salen de pantalla (van por Gastos). ticket_prom: solo dato/asiento.
        return [
            'fondo_inicial',
            'variacion_ff',
            'pago_diferido',
            'sobrantes',
            'ticket_prom',
        ];
    }

    private function claveInputCorta(string $ruta): string
    {
        return str_starts_with($ruta, 'inputs.')
            ? substr($ruta, 7)
            : $ruta;
    }

    private function nullableInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
