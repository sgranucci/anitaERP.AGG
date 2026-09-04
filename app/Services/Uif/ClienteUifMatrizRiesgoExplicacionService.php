<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Uif;
use App\Models\Uif\Inusualidad_Uif;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Factorriesgo_UifRepositoryInterface;
use App\Repositories\Uif\Frecuencia_UifRepositoryInterface;
use App\Repositories\Uif\Inusualidad_UifRepositoryInterface;
use App\Repositories\Uif\Monto_UifRepositoryInterface;
use App\Repositories\Uif\Puntaje_UifRepositoryInterface;
use App\Services\Configuracion\CotizacionService;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Explicación de la matriz de riesgo UIF (hoja Parámetros):
 * valor = puntaje × ponderación% / 100 → suma → BAJO / MEDIO / ALTO.
 */
class ClienteUifMatrizRiesgoExplicacionService
{
    /** @var array<string, string> */
    private const MESES = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ];

    public function __construct(
        private Cliente_UifRepositoryInterface $cliente_uifRepository,
        private Inusualidad_UifRepositoryInterface $inusualidad_uifRepository,
        private Monto_UifRepositoryInterface $monto_uifRepository,
        private Frecuencia_UifRepositoryInterface $frecuencia_uifRepository,
        private Factorriesgo_UifRepositoryInterface $factorriesgo_uifRepository,
        private Puntaje_UifRepositoryInterface $puntaje_uifRepository,
        private CotizacionService $cotizacionService,
    ) {
    }

    /**
     * @return array{
     *   cliente: Cliente_Uif,
     *   factores_ficha: list<array{factor: string, valor: string, puntaje: float|int, ponderacion: float|int, contribucion: float}>,
     *   umbrales: list<array{desde: float|int, hasta: float|int, riesgo: string}>,
     *   periodos: list<array<string, mixed>>,
     *   generado: string
     * }
     */
    public function explicarCliente(int $clienteUifId): array
    {
        $cliente = $this->cliente_uifRepository->find($clienteUifId);
        if ($cliente === null) {
            throw new InvalidArgumentException('Cliente UIF no encontrado.');
        }

        $factores = $this->factorriesgo_uifRepository->all();
        $factoresFicha = [
            ['factor' => 'Actividad', 'valor' => (string) ($cliente->actividades_uif->nombre ?? ''), 'puntaje' => (float) ($cliente->actividades_uif->puntaje ?? 0), 'id' => 1],
            ['factor' => 'Nacionalidad', 'valor' => (string) ($cliente->paises_uif->nombre ?? ''), 'puntaje' => (float) ($cliente->paises_uif->puntaje ?? 0), 'id' => 2],
            ['factor' => 'PEP', 'valor' => (string) ($cliente->peps_uif->nombre ?? ''), 'puntaje' => (float) ($cliente->peps_uif->puntaje ?? 0), 'id' => 3],
            ['factor' => 'Provincia', 'valor' => (string) ($cliente->provincias_uif->nombre ?? ''), 'puntaje' => (float) ($cliente->provincias_uif->puntaje ?? 0), 'id' => 4],
            ['factor' => 'Sujeto Obligado', 'valor' => (string) ($cliente->sos_uif->nombre ?? ''), 'puntaje' => (float) ($cliente->sos_uif->puntaje ?? 0), 'id' => 5],
        ];

        $fichaDetalle = [];
        foreach ($factoresFicha as $fila) {
            $pond = 0.0;
            foreach ($factores as $factor) {
                if ((int) $factor->id === (int) $fila['id']) {
                    $pond = (float) $factor->ponderacion;
                    break;
                }
            }
            $fichaDetalle[] = [
                'factor' => $fila['factor'],
                'valor' => $fila['valor'],
                'puntaje' => $fila['puntaje'],
                'ponderacion' => $pond,
                'contribucion' => round($pond * $fila['puntaje'] / 100.0, 4),
            ];
        }

        $umbrales = [];
        foreach ($this->puntaje_uifRepository->all()->sortBy('desdepuntaje') as $u) {
            $umbrales[] = [
                'desde' => $u->desdepuntaje,
                'hasta' => $u->hastapuntaje,
                'riesgo' => (string) $u->riesgo,
            ];
        }

        $periodos = [];
        $riesgos = $cliente->cliente_riesgos_uif
            ? $cliente->cliente_riesgos_uif->sortBy('periodo')->values()
            : collect();

        foreach ($riesgos as $riesgoFila) {
            $periodoYm = $this->normalizarPeriodoYm((string) ($riesgoFila->periodo ?? ''));
            $inusualidadId = (int) ($riesgoFila->inusualidad_uif_id ?? 0);
            if ($periodoYm === '' || $inusualidadId <= 0) {
                continue;
            }
            $calc = $this->calcularPeriodo($cliente, $periodoYm, $inusualidadId);
            $calc['riesgo_guardado'] = (string) ($riesgoFila->riesgo ?? '');
            $calc['cliente_riesgo_id'] = (int) $riesgoFila->id;
            $periodos[] = $calc;
        }

        return [
            'cliente' => $cliente,
            'factores_ficha' => $fichaDetalle,
            'umbrales' => $umbrales,
            'periodos' => $periodos,
            'generado' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array{
     *   periodo: string,
     *   periodo_etiqueta: string,
     *   inusualidad_id: int,
     *   inusualidad_nombre: string,
     *   inusualidad_puntaje: float|int,
     *   cantidad_premios: int,
     *   monto_operado: float,
     *   juego_nombre: string,
     *   juego_puntaje: float|int,
     *   lineas: list<array{factor: string, puntaje: float|int, ponderacion: float|int, contribucion: float}>,
     *   total: float,
     *   riesgo: string
     * }
     */
    public function calcularPeriodo(Cliente_Uif $cliente, string $periodoYm, int $inusualidadUifId): array
    {
        $periodoYm = $this->normalizarPeriodoYm($periodoYm);
        if ($periodoYm === '' || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodoYm)) {
            throw new InvalidArgumentException('Período inválido.');
        }

        [$anio, $mes] = explode('-', $periodoYm);
        $dias = cal_days_in_month(CAL_GREGORIAN, (int) $mes, (int) $anio);
        $desdeFecha = Carbon::createFromFormat('Y-m-d', $anio.'-'.$mes.'-01');
        $hastaFecha = Carbon::createFromFormat('Y-m-d', $anio.'-'.$mes.'-'.$dias);

        $montoOperadoMensual = 0.0;
        $cantidadVisita = 0;
        $puntaje = [
            1 => 0.0,
            2 => 0.0,
            3 => 0.0,
            4 => 0.0,
            5 => 0.0,
            6 => 0.0,
            7 => 0.0,
            8 => 0.0,
            9 => 0.0,
        ];
        $juegoNombre = '';

        foreach ($cliente->cliente_premios_uif as $premio) {
            if ($desdeFecha < $premio->fechaentrega && $hastaFecha > $premio->fechaentrega) {
                $cotizacion = $this->cotizacionService->leeCotizacionDiaria($premio->fechaentrega, $premio->moneda_id);
                $coeficienteConversion = calculaCoeficienteMoneda(
                    config('cotizacion.ID_MONEDA_DEFAULT'),
                    $premio->moneda_id,
                    $cotizacion
                );
                $montoOperadoMensual += ((float) $premio->monto * $coeficienteConversion);
                $puntaje[7] = (float) ($premio->juegos_uif->puntaje ?? 0);
                $juegoNombre = (string) ($premio->juegos_uif->nombre ?? '');
                $cantidadVisita++;
            }
        }

        $puntaje[1] = (float) ($cliente->actividades_uif->puntaje ?? 0);
        $puntaje[2] = (float) ($cliente->paises_uif->puntaje ?? 0);
        $puntaje[3] = (float) ($cliente->peps_uif->puntaje ?? 0);
        $puntaje[4] = (float) ($cliente->provincias_uif->puntaje ?? 0);
        $puntaje[5] = (float) ($cliente->sos_uif->puntaje ?? 0);

        /** @var Inusualidad_Uif|null $inusualidad */
        $inusualidad = $this->inusualidad_uifRepository->find($inusualidadUifId);
        $inusualidadNombre = $inusualidad ? (string) $inusualidad->nombre : '';
        $inusualidadPuntaje = $inusualidad ? (float) $inusualidad->puntaje : 0.0;
        $puntaje[8] = $inusualidadPuntaje;

        foreach ($this->monto_uifRepository->findPorMonto($montoOperadoMensual) as $monto) {
            $puntaje[9] += (float) $monto->puntaje;
        }
        foreach ($this->frecuencia_uifRepository->findPorFrecuencia($cantidadVisita) as $frecuencia) {
            $puntaje[6] += (float) $frecuencia->puntaje;
        }

        $lineas = [];
        $total = 0.0;
        foreach ($this->factorriesgo_uifRepository->all() as $factor) {
            $p = (float) ($puntaje[(int) $factor->id] ?? 0);
            $pond = (float) $factor->ponderacion;
            $contrib = round($pond * $p / 100.0, 4);
            $total += $contrib;
            $lineas[] = [
                'factor' => (string) $factor->nombre,
                'puntaje' => $p,
                'ponderacion' => $pond,
                'contribucion' => $contrib,
            ];
        }
        $total = round($total, 4);

        $puntajeUif = $this->puntaje_uifRepository->findPorPuntaje($total);
        $riesgo = $puntajeUif ? (string) $puntajeUif->riesgo : 'FALTAN DATOS';

        return [
            'periodo' => $periodoYm,
            'periodo_etiqueta' => ($this::MESES[$mes] ?? $mes).' '.$anio,
            'inusualidad_id' => $inusualidadUifId,
            'inusualidad_nombre' => $inusualidadNombre,
            'inusualidad_puntaje' => $inusualidadPuntaje,
            'cantidad_premios' => $cantidadVisita,
            'monto_operado' => round($montoOperadoMensual, 2),
            'juego_nombre' => $juegoNombre,
            'juego_puntaje' => $puntaje[7],
            'lineas' => $lineas,
            'total' => $total,
            'riesgo' => $riesgo,
        ];
    }

    /**
     * Período en formato MMYYYY (legacy del endpoint AJAX).
     */
    public function riesgoDesdePeriodoMmYyyy(int $clienteUifId, string $periodoMmYyyy, int $inusualidadUifId): string
    {
        if (strlen($periodoMmYyyy) === 5) {
            $periodoMmYyyy = '0'.$periodoMmYyyy;
        }
        $mes = substr($periodoMmYyyy, 0, 2);
        $anio = substr($periodoMmYyyy, 2, 4);
        $cliente = $this->cliente_uifRepository->find($clienteUifId);
        $calc = $this->calcularPeriodo($cliente, $anio.'-'.$mes, $inusualidadUifId);

        return $calc['riesgo'];
    }

    private function normalizarPeriodoYm(string $val): string
    {
        $val = trim($val);
        if ($val === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $val, $m)) {
            return $m[1].'-'.$m[2];
        }
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $val, $m)) {
            return $m[2].'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^(\d{2})(\d{4})$/', $val, $m)) {
            return $m[2].'-'.$m[1];
        }

        return '';
    }
}
