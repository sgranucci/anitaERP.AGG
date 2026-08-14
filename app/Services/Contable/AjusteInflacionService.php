<?php

namespace App\Services\Contable;

use App\Models\Contable\AjusteInflacionConfiguracion;
use App\Models\Contable\AjusteInflacionCorrida;
use App\Models\Contable\AjusteInflacionCorridaDetalle;
use App\Models\Contable\AjusteInflacionCuenta;
use App\Models\Contable\AjusteInflacionIndice;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AjusteInflacionService
{
    public const ESTADO_SIMULADA = 'simulada';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_ANULADA = 'anulada';

    private const CUENTA_RECPAM_CODIGO = '533030001';

    private const CENTROCOSTO_RECPAM_CODIGO = '97';

    private const TIPOS_EXCLUIDOS = [
        'AJ',
        'INF',
        'AJI',
        'AJU',
        'INFL',
        'CIR',
        'CIP',
        'CIJ',
        'CIE',
        'CER',
        'CIER',
    ];

    public function __construct(
        private AsientoRepositoryInterface $asientoRepository,
        private Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {
    }

    public function normalizarMes(string $periodo): string
    {
        $periodo = trim($periodo);
        if (preg_match('/^\d{4}-\d{2}$/', $periodo) === 1) {
            $periodo .= '-01';
        }

        $fecha = $this->fechaEstricta($periodo, 'mes');

        return $fecha->startOfMonth()->format('Y-m-d');
    }

    public function normalizarFecha(string $fecha): string
    {
        return $this->fechaEstricta(trim($fecha), 'fecha')->format('Y-m-d');
    }

    /**
     * Inicializa la configuración de la empresa y las cuentas detectadas en el último AJ.
     *
     * @return array<string, mixed>
     */
    public function inicializarConfiguracionDesdeUltimoAj(int $empresaId): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('La empresa indicada para inicializar el ajuste por inflación no es válida.');
        }

        $tipoAj = $this->tipoasientoRepository->findPorAbreviatura('AJ');
        if (! $tipoAj) {
            throw new RuntimeException('No existe el tipo de asiento AJ.');
        }

        $cuentaRecpam = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', self::CUENTA_RECPAM_CODIGO)
            ->first();
        if (! $cuentaRecpam) {
            throw new RuntimeException(
                'No existe la cuenta RECPAM '.self::CUENTA_RECPAM_CODIGO.' para la empresa indicada.'
            );
        }

        $centrocostoRecpam = Centrocosto::query()
            ->where('codigo', self::CENTROCOSTO_RECPAM_CODIGO)
            ->first();
        if (! $centrocostoRecpam) {
            throw new RuntimeException(
                'No existe el centro de costo '.self::CENTROCOSTO_RECPAM_CODIGO.' para RECPAM.'
            );
        }

        return DB::transaction(function () use ($empresaId, $tipoAj, $cuentaRecpam, $centrocostoRecpam) {
            $configuracion = AjusteInflacionConfiguracion::query()->updateOrCreate(
                ['empresa_id' => $empresaId],
                [
                    'cuentacontable_recpam_id' => (int) $cuentaRecpam->id,
                    'centrocosto_recpam_id' => (int) $centrocostoRecpam->id,
                    'tipoasiento_id' => (int) $tipoAj->id,
                    'activo' => true,
                ]
            );

            $ultimaFecha = DB::table('asiento')
                ->where('empresa_id', $empresaId)
                ->where('tipoasiento_id', (int) $tipoAj->id)
                ->whereNull('deleted_at')
                ->max('fecha');

            if ($ultimaFecha === null) {
                return [
                    'configuracion_id' => (int) $configuracion->id,
                    'asiento_id' => null,
                    'cuentas_detectadas' => 0,
                    'cuentas_creadas' => 0,
                    'cuentas_reactivadas' => 0,
                    'mensaje' => 'Se guardó la configuración, pero no se encontró ningún asiento AJ para importar cuentas.',
                ];
            }

            $asientos = DB::table('asiento')
                ->where('empresa_id', $empresaId)
                ->where('tipoasiento_id', (int) $tipoAj->id)
                ->whereDate('fecha', $ultimaFecha)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->get(['id']);

            $movimientos = DB::table('asiento_movimiento')
                ->whereIn('asiento_id', $asientos->pluck('id')->all())
                ->whereNull('deleted_at')
                ->get(['asiento_id', 'cuentacontable_id']);

            $cantidadPorAsiento = [];
            foreach ($movimientos as $movimiento) {
                $asientoId = (int) $movimiento->asiento_id;
                $cantidadPorAsiento[$asientoId] = ($cantidadPorAsiento[$asientoId] ?? 0) + 1;
            }

            $asientoElegidoId = null;
            $mayorCantidad = -1;
            foreach ($asientos as $asiento) {
                $cantidad = $cantidadPorAsiento[(int) $asiento->id] ?? 0;
                if ($cantidad > $mayorCantidad) {
                    $asientoElegidoId = (int) $asiento->id;
                    $mayorCantidad = $cantidad;
                }
            }

            if ($asientoElegidoId === null) {
                throw new RuntimeException('No se pudo determinar el último asiento AJ de la empresa.');
            }

            $cuentaIds = $movimientos
                ->where('asiento_id', $asientoElegidoId)
                ->pluck('cuentacontable_id')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn ($id) => $id > 0 && $id !== (int) $cuentaRecpam->id)
                ->unique()
                ->sort()
                ->values();

            $creadas = 0;
            $reactivadas = 0;
            foreach ($cuentaIds as $cuentaId) {
                $cuentaConfigurada = AjusteInflacionCuenta::query()->firstOrNew([
                    'empresa_id' => $empresaId,
                    'cuentacontable_id' => $cuentaId,
                ]);
                $eraNueva = ! $cuentaConfigurada->exists;
                $estabaInactiva = $cuentaConfigurada->exists && ! (bool) $cuentaConfigurada->activo;
                $cuentaConfigurada->activo = true;
                $cuentaConfigurada->metodo_anticuacion = 'movimientos_mensuales';
                $cuentaConfigurada->save();

                $creadas += $eraNueva ? 1 : 0;
                $reactivadas += $estabaInactiva ? 1 : 0;
            }

            return [
                'configuracion_id' => (int) $configuracion->id,
                'asiento_id' => $asientoElegidoId,
                'fecha_aj' => Carbon::parse($ultimaFecha)->format('Y-m-d'),
                'movimientos_aj' => max(0, $mayorCantidad),
                'cuentas_detectadas' => $cuentaIds->count(),
                'cuentas_creadas' => $creadas,
                'cuentas_reactivadas' => $reactivadas,
                'mensaje' => 'Configuración inicializada desde el AJ con más movimientos de la última fecha disponible.',
            ];
        });
    }

    public function simular(
        int $empresaId,
        string $periodoDesde,
        string $fechaCierre,
        int $usuarioId,
        ?string $observacion
    ): AjusteInflacionCorrida {
        if ($empresaId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('La empresa y el usuario son obligatorios para simular el ajuste por inflación.');
        }

        $desde = $this->normalizarMes($periodoDesde);
        $cierre = $this->normalizarFecha($fechaCierre);
        $this->assertRangoValido($desde, $cierre);
        $calculo = $this->calcular($empresaId, $desde, $cierre);

        return DB::transaction(function () use (
            $empresaId,
            $desde,
            $cierre,
            $usuarioId,
            $observacion,
            $calculo
        ) {
            $corrida = AjusteInflacionCorrida::query()->create([
                'empresa_id' => $empresaId,
                'periodo_desde' => $desde,
                'fecha_cierre' => $cierre,
                'indice_cierre_id' => $calculo['indice_cierre_id'],
                'estado' => self::ESTADO_SIMULADA,
                'usuario_id' => $usuarioId,
                'observacion' => $this->observacionNormalizada($observacion),
                'total_ajuste' => $calculo['total_ajuste'],
                'firma' => $calculo['firma'],
            ]);

            foreach ($calculo['detalles'] as $detalle) {
                $detalle['corrida_id'] = (int) $corrida->id;
                AjusteInflacionCorridaDetalle::query()->create($detalle);
            }

            return $corrida->fresh(['detalles']);
        });
    }

    public function confirmar(int $corridaId, int $usuarioId): AjusteInflacionCorrida
    {
        if ($corridaId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('La corrida y el usuario son obligatorios para confirmar.');
        }

        return DB::transaction(function () use ($corridaId, $usuarioId) {
            $corrida = AjusteInflacionCorrida::query()->lockForUpdate()->find($corridaId);
            if (! $corrida) {
                throw new InvalidArgumentException('La corrida de ajuste por inflación no existe.');
            }
            if ($corrida->estado !== self::ESTADO_SIMULADA) {
                throw new RuntimeException('Solo se puede confirmar una corrida en estado simulada.');
            }

            $empresaId = (int) $corrida->empresa_id;
            $fechaCierre = $this->normalizarFecha($corrida->fecha_cierre->format('Y-m-d'));
            $periodoDesde = $this->normalizarMes($corrida->periodo_desde->format('Y-m-d'));

            // Serializa las confirmaciones de una misma empresa, aun cuando sean corridas distintas.
            $configuracion = AjusteInflacionConfiguracion::query()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->first();
            if (! $configuracion || ! (bool) $configuracion->activo) {
                throw new RuntimeException('La configuración de ajuste por inflación está inactiva o no existe.');
            }

            $claveConfirmada = $empresaId.'|'.$fechaCierre;
            $yaConfirmada = AjusteInflacionCorrida::query()
                ->where('confirmada_clave', $claveConfirmada)
                ->where('estado', self::ESTADO_CONFIRMADA)
                ->exists();
            if ($yaConfirmada) {
                throw new RuntimeException('Ya existe una corrida confirmada para esa empresa y fecha de cierre.');
            }

            $calculo = $this->calcular($empresaId, $periodoDesde, $fechaCierre);
            if (! hash_equals((string) $corrida->firma, $calculo['firma'])) {
                throw new RuntimeException(
                    'La simulación quedó desactualizada porque cambiaron movimientos, índices o configuración. Simule nuevamente.'
                );
            }
            if ($calculo['detalles'] === []) {
                throw new RuntimeException('La corrida no contiene ajustes no nulos para contabilizar.');
            }

            PeriodoContableCierreSupport::assertOperacionPermitida(
                $empresaId,
                $fechaCierre,
                PeriodoContableCierreSupport::ALCANCE_CONTABLE,
                $usuarioId
            );

            $tipoAj = $this->tipoasientoRepository->findPorAbreviatura('AJ');
            if (! $tipoAj || (int) $configuracion->tipoasiento_id !== (int) $tipoAj->id) {
                throw new RuntimeException('La configuración debe utilizar el tipo de asiento AJ.');
            }

            $lineas = $this->consolidarParaAsiento($calculo['detalles']);
            $lineas[] = [
                'cuentacontable_id' => (int) $configuracion->cuentacontable_recpam_id,
                'centrocosto_id' => $this->idONull($configuracion->centrocosto_recpam_id),
                'monto' => round(-$calculo['total_ajuste'], 4),
            ];

            $descripcion = 'Ajuste por inflación RT 6 — cierre '
                .Carbon::parse($fechaCierre)->format('d/m/Y')
                .' — corrida #'.$corrida->id;
            $payloadMovimientos = $this->payloadMovimientos($lineas, $descripcion);
            $payloadAsiento = array_merge($payloadMovimientos, [
                'empresa_id' => $empresaId,
                'tipoasiento_id' => (int) $tipoAj->id,
                'fecha' => $fechaCierre,
                'numeroasiento' => '',
                'observacion' => $descripcion,
                'usuario_id' => $usuarioId,
                'omitir_anita' => true,
                'alcance_cierre_contable' => PeriodoContableCierreSupport::ALCANCE_CONTABLE,
            ]);

            $asiento = $this->asientoRepository->create($payloadAsiento);
            if (! $asiento || $asiento === 'Error') {
                throw new RuntimeException('No se pudo crear el asiento del ajuste por inflación.');
            }

            $this->asientoMovimientoRepository->create($payloadMovimientos, (int) $asiento->id);
            $asientoPersistido = $this->asientoRepository->find((int) $asiento->id);
            $asientoPersistido->load('asiento_movimientos');
            $saldoPersistido = round(
                (float) $asientoPersistido->asiento_movimientos->sum('monto'),
                4
            );
            if (abs($saldoPersistido) > 0.01) {
                throw new RuntimeException(
                    'El asiento persistido del ajuste por inflación no balancea. Diferencia: '
                    .number_format($saldoPersistido, 4, '.', '')
                );
            }

            $payloadAnita = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asientoPersistido);
            $payloadAnita['alcance_cierre_contable'] = PeriodoContableCierreSupport::ALCANCE_CONTABLE;
            $this->asientoRepository->sincronizarCtamovAnita($payloadAnita);

            $corrida->estado = self::ESTADO_CONFIRMADA;
            $corrida->asiento_id = (int) $asientoPersistido->id;
            $corrida->confirmada_clave = $claveConfirmada;
            $corrida->confirmado_por_id = $usuarioId;
            $corrida->confirmado_at = now();
            $corrida->save();

            return $corrida->fresh(['detalles', 'asiento']);
        });
    }

    public function anular(int $corridaId, int $usuarioId): AjusteInflacionCorrida
    {
        if ($corridaId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('La corrida y el usuario son obligatorios para anular.');
        }

        return DB::transaction(function () use ($corridaId) {
            $corrida = AjusteInflacionCorrida::query()->lockForUpdate()->find($corridaId);
            if (! $corrida) {
                throw new InvalidArgumentException('La corrida de ajuste por inflación no existe.');
            }
            if ($corrida->estado === self::ESTADO_CONFIRMADA) {
                throw new RuntimeException(
                    'Una corrida confirmada no puede anularse directamente: se requiere un flujo de reversión contable.'
                );
            }
            if ($corrida->estado !== self::ESTADO_SIMULADA) {
                throw new RuntimeException('Solo se puede anular una corrida en estado simulada.');
            }

            $corrida->estado = self::ESTADO_ANULADA;
            $corrida->save();

            return $corrida->fresh(['detalles']);
        });
    }

    /**
     * @return array{
     *   indice_cierre_id: int,
     *   total_ajuste: float,
     *   firma: string,
     *   detalles: list<array<string, mixed>>
     * }
     */
    private function calcular(int $empresaId, string $periodoDesde, string $fechaCierre): array
    {
        $configuracion = AjusteInflacionConfiguracion::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();
        if (! $configuracion) {
            throw new RuntimeException('No existe una configuración activa de ajuste por inflación para la empresa.');
        }

        $cuentaIds = AjusteInflacionCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('cuentacontable_id')
            ->pluck('cuentacontable_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
        if ($cuentaIds === []) {
            throw new RuntimeException('No hay cuentas activas configuradas para el ajuste por inflación.');
        }

        $filas = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->where('a.empresa_id', $empresaId)
            ->whereDate('a.fecha', '>=', $periodoDesde)
            ->whereDate('a.fecha', '<=', $fechaCierre)
            ->whereIn('am.cuentacontable_id', $cuentaIds)
            ->whereNotIn('ta.abreviatura', self::TIPOS_EXCLUIDOS)
            ->whereNull('a.deleted_at')
            ->whereNull('am.deleted_at')
            ->orderBy('am.cuentacontable_id')
            ->orderBy('am.centrocosto_id')
            ->orderBy('a.fecha')
            ->orderBy('am.id')
            ->get([
                'am.id',
                'am.cuentacontable_id',
                'am.centrocosto_id',
                'am.monto',
                'a.fecha',
                'ta.abreviatura as tipo_abreviatura',
            ]);

        $agregados = [];
        foreach ($filas as $fila) {
            $fecha = Carbon::parse($fila->fecha)->startOfDay();
            $esAperturaInicial = strtoupper(trim((string) $fila->tipo_abreviatura)) === 'APE'
                && $fecha->format('Y-m-d') === $periodoDesde;
            $periodoOrigen = $esAperturaInicial
                ? $fecha->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d')
                : $fecha->startOfMonth()->format('Y-m-d');
            $cuentaId = (int) $fila->cuentacontable_id;
            $centrocostoId = $this->idONull($fila->centrocosto_id);
            $clave = $cuentaId.'|'.($centrocostoId ?? 0).'|'.$periodoOrigen;

            if (! isset($agregados[$clave])) {
                $agregados[$clave] = [
                    'cuentacontable_id' => $cuentaId,
                    'centrocosto_id' => $centrocostoId,
                    'periodo_origen' => $periodoOrigen,
                    'saldo_origen' => 0.0,
                ];
            }
            $agregados[$clave]['saldo_origen'] += (float) $fila->monto;
        }

        $periodoCierre = Carbon::parse($fechaCierre)->startOfMonth()->format('Y-m-d');
        $periodosNecesarios = [$periodoCierre];
        foreach ($agregados as $agregado) {
            $periodosNecesarios[] = $agregado['periodo_origen'];
        }
        $periodosNecesarios = array_values(array_unique($periodosNecesarios));
        sort($periodosNecesarios, SORT_STRING);

        $indices = AjusteInflacionIndice::query()
            ->whereIn('periodo', $periodosNecesarios)
            ->get()
            ->keyBy(static fn (AjusteInflacionIndice $indice) => $indice->periodo->format('Y-m-d'));

        $faltantes = [];
        foreach ($periodosNecesarios as $periodo) {
            $indice = $indices->get($periodo);
            if (! $indice || (float) $indice->valor <= 0) {
                $faltantes[] = Carbon::parse($periodo)->format('m/Y');
            }
        }
        if ($faltantes !== []) {
            throw new RuntimeException(
                'Faltan índices de ajuste por inflación válidos para: '.implode(', ', $faltantes).'.'
            );
        }

        /** @var AjusteInflacionIndice $indiceCierre */
        $indiceCierre = $indices->get($periodoCierre);
        $detalles = [];
        foreach ($agregados as $agregado) {
            /** @var AjusteInflacionIndice $indiceOrigen */
            $indiceOrigen = $indices->get($agregado['periodo_origen']);
            $saldo = round((float) $agregado['saldo_origen'], 4);
            $coeficiente = (float) $indiceCierre->valor / (float) $indiceOrigen->valor;
            $reexpresado = round($saldo * $coeficiente, 4);
            $ajuste = round($reexpresado - $saldo, 4);
            if (abs($ajuste) < 0.0001) {
                continue;
            }

            $detalles[] = [
                'cuentacontable_id' => $agregado['cuentacontable_id'],
                'centrocosto_id' => $agregado['centrocosto_id'],
                'periodo_origen' => $agregado['periodo_origen'],
                'indice_origen_id' => (int) $indiceOrigen->id,
                'saldo_origen' => $saldo,
                'coeficiente' => round($coeficiente, 10),
                'importe_reexpresado' => $reexpresado,
                'ajuste' => $ajuste,
                'observacion' => null,
            ];
        }

        usort($detalles, static function (array $a, array $b): int {
            return [
                $a['cuentacontable_id'],
                $a['centrocosto_id'] ?? 0,
                $a['periodo_origen'],
            ] <=> [
                $b['cuentacontable_id'],
                $b['centrocosto_id'] ?? 0,
                $b['periodo_origen'],
            ];
        });

        $total = round(array_sum(array_column($detalles, 'ajuste')), 4);
        $firma = $this->firmarCalculo(
            $empresaId,
            $periodoDesde,
            $fechaCierre,
            $configuracion,
            $cuentaIds,
            $indiceCierre,
            $detalles
        );

        return [
            'indice_cierre_id' => (int) $indiceCierre->id,
            'total_ajuste' => $total,
            'firma' => $firma,
            'detalles' => $detalles,
        ];
    }

    /**
     * @param  list<int>  $cuentaIds
     * @param  list<array<string, mixed>>  $detalles
     */
    private function firmarCalculo(
        int $empresaId,
        string $periodoDesde,
        string $fechaCierre,
        AjusteInflacionConfiguracion $configuracion,
        array $cuentaIds,
        AjusteInflacionIndice $indiceCierre,
        array $detalles
    ): string {
        $detallesCanonicos = array_map(static fn (array $detalle) => [
            'cuenta' => (int) $detalle['cuentacontable_id'],
            'cc' => $detalle['centrocosto_id'] === null ? null : (int) $detalle['centrocosto_id'],
            'periodo' => (string) $detalle['periodo_origen'],
            'indice_id' => (int) $detalle['indice_origen_id'],
            'saldo' => number_format((float) $detalle['saldo_origen'], 4, '.', ''),
            'coeficiente' => number_format((float) $detalle['coeficiente'], 10, '.', ''),
            'reexpresado' => number_format((float) $detalle['importe_reexpresado'], 4, '.', ''),
            'ajuste' => number_format((float) $detalle['ajuste'], 4, '.', ''),
        ], $detalles);

        $canonico = [
            'empresa_id' => $empresaId,
            'periodo_desde' => $periodoDesde,
            'fecha_cierre' => $fechaCierre,
            'configuracion' => [
                'cuenta_recpam_id' => (int) $configuracion->cuentacontable_recpam_id,
                'centrocosto_recpam_id' => $this->idONull($configuracion->centrocosto_recpam_id),
                'tipoasiento_id' => (int) $configuracion->tipoasiento_id,
            ],
            'cuentas' => array_values($cuentaIds),
            'indice_cierre' => [
                'id' => (int) $indiceCierre->id,
                'valor' => number_format((float) $indiceCierre->valor, 8, '.', ''),
            ],
            'detalles' => $detallesCanonicos,
        ];

        $json = json_encode($canonico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo construir la firma de la simulación.');
        }

        return hash('sha256', $json);
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array{cuentacontable_id: int, centrocosto_id: int|null, monto: float}>
     */
    private function consolidarParaAsiento(array $detalles): array
    {
        $consolidados = [];
        foreach ($detalles as $detalle) {
            $cuentaId = (int) $detalle['cuentacontable_id'];
            $centrocostoId = $this->idONull($detalle['centrocosto_id']);
            $clave = $cuentaId.'|'.($centrocostoId ?? 0);
            if (! isset($consolidados[$clave])) {
                $consolidados[$clave] = [
                    'cuentacontable_id' => $cuentaId,
                    'centrocosto_id' => $centrocostoId,
                    'monto' => 0.0,
                ];
            }
            $consolidados[$clave]['monto'] += (float) $detalle['ajuste'];
        }

        $lineas = [];
        foreach ($consolidados as $linea) {
            $linea['monto'] = round($linea['monto'], 4);
            if (abs($linea['monto']) >= 0.0001) {
                $lineas[] = $linea;
            }
        }

        return $lineas;
    }

    /**
     * @param  list<array{cuentacontable_id: int, centrocosto_id: int|null, monto: float}>  $lineas
     * @return array<string, array<int, mixed>>
     */
    private function payloadMovimientos(array $lineas, string $descripcion): array
    {
        $payload = [
            'cuentacontable_ids' => [],
            'centrocosto_ids' => [],
            'moneda_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        foreach ($lineas as $linea) {
            $monto = round((float) $linea['monto'], 4);
            $payload['cuentacontable_ids'][] = (int) $linea['cuentacontable_id'];
            $payload['centrocosto_ids'][] = $linea['centrocosto_id'];
            $payload['moneda_ids'][] = 1;
            $payload['debes'][] = $monto > 0 ? $monto : '';
            $payload['haberes'][] = $monto < 0 ? abs($monto) : '';
            $payload['cotizaciones'][] = 1;
            $payload['observaciones'][] = $descripcion;
        }

        return $payload;
    }

    private function assertRangoValido(string $periodoDesde, string $fechaCierre): void
    {
        if (Carbon::parse($periodoDesde)->gt(Carbon::parse($fechaCierre))) {
            throw new InvalidArgumentException('El período desde no puede ser posterior a la fecha de cierre.');
        }
    }

    private function fechaEstricta(string $valor, string $etiqueta): Carbon
    {
        $fecha = Carbon::createFromFormat('!Y-m-d', $valor);
        $errores = Carbon::getLastErrors();
        $invalida = $fecha === false
            || (is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0))
            || ($fecha !== false && $fecha->format('Y-m-d') !== $valor);

        if ($invalida) {
            throw new InvalidArgumentException('La '.$etiqueta.' "'.$valor.'" no es válida.');
        }

        return $fecha;
    }

    private function idONull(mixed $id): ?int
    {
        return $id === null || $id === '' || (int) $id <= 0 ? null : (int) $id;
    }

    private function observacionNormalizada(?string $observacion): ?string
    {
        $observacion = trim((string) $observacion);

        return $observacion === '' ? null : mb_substr($observacion, 0, 2000);
    }
}
