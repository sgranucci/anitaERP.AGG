<?php

declare(strict_types=1);

namespace App\Services\Caja\Remesa;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Remesa;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Caja\Remesa\RemesaSupport;
use App\Support\Caja\RemesaReporteFiltros;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\Efe\EfeAnitaBridgeReader;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Remesas por cuenta de caja (destino): ERP primero, Anita completa sin duplicar.
 */
class RemesaReporteService
{
    /** @var array<int, string> */
    private array $abrevMoneda = [];

    public function __construct(
        private readonly EfeAnitaBridgeReader $bridgeReader,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   total_movimientos: int,
     *   total_importe_origen: float,
     *   total_importe: float,
     *   subtitulo: string,
     *   advertencias: list<string>,
     *   nombreempresa: string
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaIds = array_values(array_map('intval', $filtros['empresa_ids'] ?? []));
        $empresas = Empresa::query()
            ->whereIn('id', $empresaIds)
            ->orderBy('id')
            ->get(['id', 'nombre'])
            ->keyBy('id');
        $nombresEmpresa = $empresas->pluck('nombre')->map(static fn ($n) => trim((string) $n))->filter()->values()->all();
        $advertencias = [];
        $movimientos = [];
        $fuente = (string) ($filtros['fuente'] ?? RemesaReporteFiltros::FUENTE_TODAS);
        $consolidar = ! empty($filtros['consolidar_empresas']);

        foreach ($empresaIds as $empresaId) {
            $nombreEmpresa = trim((string) ($empresas->get($empresaId)?->nombre ?? ('Empresa '.$empresaId)));
            $movEmpresa = [];
            if ($fuente !== RemesaReporteFiltros::FUENTE_ANITA) {
                $movEmpresa = array_merge(
                    $movEmpresa,
                    $this->movimientosErp($filtros, $empresaId, $nombreEmpresa, $advertencias)
                );
            }
            if ($fuente !== RemesaReporteFiltros::FUENTE_ERP) {
                $movEmpresa = array_merge(
                    $movEmpresa,
                    $this->movimientosAnita($filtros, $empresaId, $nombreEmpresa, $movEmpresa, $advertencias)
                );
            }
            $movimientos = array_merge($movimientos, $movEmpresa);
        }

        usort($movimientos, static function (array $a, array $b) use ($consolidar, $empresaIds): int {
            $multi = count($empresaIds) > 1;
            if ($multi && ! $consolidar) {
                $cmpEmp = ((int) $a['empresa_id']) <=> ((int) $b['empresa_id']);
                if ($cmpEmp !== 0) {
                    return $cmpEmp;
                }
            }

            $cmpCuenta = strnatcasecmp((string) $a['cuenta_codigo'], (string) $b['cuenta_codigo']);
            if ($cmpCuenta !== 0) {
                return $cmpCuenta;
            }

            if ($multi && $consolidar) {
                $cmpEmp = ((int) $a['empresa_id']) <=> ((int) $b['empresa_id']);
                if ($cmpEmp !== 0) {
                    return $cmpEmp;
                }
            }

            $cmpFecha = strcmp((string) $a['fecha_ymd'], (string) $b['fecha_ymd']);
            if ($cmpFecha !== 0) {
                return $cmpFecha;
            }

            return ((int) $a['remesa_nro']) <=> ((int) $b['remesa_nro']);
        });

        $filas = $this->aplanarPorCuenta($movimientos, $consolidar, count($empresaIds) > 1);
        $totalOrigen = 0.0;
        $totalImporte = 0.0;
        foreach ($movimientos as $mov) {
            $totalOrigen = round($totalOrigen + (float) $mov['importe_origen'], 2);
            $totalImporte = round($totalImporte + (float) $mov['importe'], 2);
        }

        $nombreLogo = count($nombresEmpresa) === 1 ? $nombresEmpresa[0] : '';

        return [
            'filas' => $filas,
            'total_movimientos' => count($movimientos),
            'total_importe_origen' => $totalOrigen,
            'total_importe' => $totalImporte,
            'subtitulo' => RemesaReporteFiltros::subtitulo($filtros, $nombresEmpresa),
            'advertencias' => $advertencias,
            'nombreempresa' => $nombreLogo,
        ];
    }

    /**
     * Cuentas destino de remesa para las empresas indicadas (filtro del reporte).
     *
     * @param  list<int>  $empresaIds
     * @return Collection<int, Cuentacaja>
     */
    public function cuentasDestino(array $empresaIds): Collection
    {
        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds), static fn (int $id) => $id > 0));
        if ($empresaIds === []) {
            return collect();
        }

        $query = Cuentacaja::query()
            ->whereHas('usocuentacajas', static function ($q) {
                $q->where('usocuentacaja.nombre', RemesaSupport::USO_DESTINO);
            })
            ->with(['monedas:id,abreviatura']);

        if (count($empresaIds) === 1) {
            $query->paraEmpresa($empresaIds[0]);
        } else {
            $query->where(static function ($q) use ($empresaIds) {
                $q->whereIn('empresa_id', $empresaIds)->orWhereNull('empresa_id');
            });
        }

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'moneda_id', 'empresa_id']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $advertencias
     * @return list<array<string, mixed>>
     */
    private function movimientosErp(array $filtros, int $empresaId, string $nombreEmpresa, array &$advertencias): array
    {
        $query = Remesa::query()
            ->with(['lineasDestino.cuentacaja.monedas', 'lineasOrigen.cuentacaja.monedas', 'empresa:id,nombre'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->whereIn('estado', [RemesaSupport::ESTADO_CONFIRMADA, RemesaSupport::ESTADO_REVERTIDA]);

        $tipo = (string) ($filtros['tipo'] ?? RemesaSupport::TIPO_EXTERNA);
        if ($tipo !== RemesaReporteFiltros::TIPO_TODAS) {
            $query->where('tipo', $tipo);
        }

        $cuentaFiltro = (int) ($filtros['cuentacaja_id'] ?? 0);
        if ($cuentaFiltro > 0) {
            $query->whereHas('lineasDestino', static function ($q) use ($cuentaFiltro) {
                $q->where('cuentacaja_id', $cuentaFiltro);
            });
        }

        $out = [];
        foreach ($query->orderBy('fecha')->orderBy('numero')->get() as $remesa) {
            $lineas = $remesa->lineasDestino;
            if ($lineas->isEmpty() && $remesa->tipo === RemesaSupport::TIPO_INTERNA) {
                $lineas = $remesa->lineasOrigen;
            }
            foreach ($lineas as $linea) {
                $cuenta = $linea->cuentacaja;
                $monto = round((float) ($linea->monto ?? 0), 2);
                if ($cuenta === null || $monto <= 0) {
                    continue;
                }
                if ($cuentaFiltro > 0 && (int) $cuenta->id !== $cuentaFiltro) {
                    continue;
                }

                $fecha = $remesa->fecha?->format('Y-m-d') ?? '';
                $monedaId = (int) ($cuenta->moneda_id ?: 1);
                $cotizacion = $this->cotizacion($fecha, $monedaId, $empresaId, $advertencias, 'ERP '.$remesa->numero);
                $importe = round($monto * $cotizacion, 2);

                $out[] = $this->filaMovimiento(
                    $cuenta,
                    $fecha,
                    (int) $remesa->id,
                    (int) $remesa->numero,
                    $monedaId,
                    $cotizacion,
                    $monto,
                    $importe,
                    $this->etiquetaEstadoErp((string) $remesa->estado),
                    $empresaId,
                    'ERP',
                    $nombreEmpresa,
                    $this->firmaErp($fecha, $cuenta, $monedaId, $monto),
                );
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $yaErp
     * @param  list<string>  $advertencias
     * @return list<array<string, mixed>>
     */
    private function movimientosAnita(
        array $filtros,
        int $empresaId,
        string $nombreEmpresa,
        array $yaErp,
        array &$advertencias,
    ): array {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desde = (int) str_replace('-', '', (string) $filtros['fecha_desde']);
        $hasta = (int) str_replace('-', '', (string) $filtros['fecha_hasta']);
        $tipoFiltro = (string) ($filtros['tipo'] ?? RemesaSupport::TIPO_EXTERNA);
        $cuentaFiltro = (int) ($filtros['cuentacaja_id'] ?? 0);
        $firmasErp = [];
        foreach ($yaErp as $mov) {
            $firma = (string) ($mov['firma'] ?? '');
            if ($firma !== '') {
                $firmasErp[$firma] = ($firmasErp[$firma] ?? 0) + 1;
            }
        }

        try {
            $filasAnita = $this->bridgeReader->listarRememaeDetalle($empresaAnita, $desde, $hasta);
        } catch (\Throwable $e) {
            Log::warning('Remesa reporte Anita: '.$e->getMessage(), ['empresa_id' => $empresaId]);
            $advertencias[] = 'No se pudieron leer remesas de Anita: '.$e->getMessage();

            return [];
        }

        $cuentasPorCodigo = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->get(['id', 'codigo', 'nombre', 'moneda_id', 'empresa_id'])
            ->keyBy(static fn (Cuentacaja $c) => strtoupper(trim((string) $c->codigo)));

        $out = [];
        foreach ($filasAnita as $fila) {
            $fila = is_object($fila) ? $fila : (object) $fila;
            $tipoAnita = strtoupper(trim((string) ($fila->remem_tipo_remesa ?? '')));
            if ($tipoFiltro !== RemesaReporteFiltros::TIPO_TODAS && $tipoAnita !== $tipoFiltro) {
                continue;
            }

            $importeAnita = round((float) ($fila->remem_importe ?? 0), 2);
            $fechaYmdInt = (int) ($fila->remem_fecha ?? 0);
            if ($importeAnita <= 0 || $fechaYmdInt < 10000101) {
                continue;
            }
            $fecha = sprintf(
                '%04d-%02d-%02d',
                intdiv($fechaYmdInt, 10000),
                intdiv($fechaYmdInt % 10000, 100),
                $fechaYmdInt % 100
            );

            $monedaAnita = (int) ($fila->remem_cod_mon ?? 1);
            if ($monedaAnita <= 0) {
                $monedaAnita = 1;
            }

            $codigoAnita = trim((string) ($fila->remem_cuenta ?? ''));
            $codigoErp = RemesaSupport::normalizarCodigoErp($codigoAnita);
            $cuenta = $codigoErp !== '' ? ($cuentasPorCodigo[strtoupper($codigoErp)] ?? null) : null;
            if ($cuenta === null && $codigoAnita !== '') {
                $cuenta = $cuentasPorCodigo[strtoupper($codigoAnita)] ?? null;
            }
            if ($cuenta === null) {
                $cuenta = $this->cuentaPorDestinoAnita(
                    $cuentasPorCodigo,
                    (string) ($fila->remem_destino ?? ''),
                    $monedaAnita
                );
            }
            $monedaId = $cuenta !== null ? (int) ($cuenta->moneda_id ?: $monedaAnita) : $monedaAnita;

            if ($cuentaFiltro > 0) {
                if ($cuenta === null || (int) $cuenta->id !== $cuentaFiltro) {
                    continue;
                }
            }

            // rememae: en ME el importe viene en pesos; origen = importe / cotización (l-remesa).
            $cotizacionAnita = $monedaAnita === 1 ? 1.0 : (float) ($fila->remem_cotizacion ?? 0);
            if ($monedaAnita > 1 && $cotizacionAnita > 0) {
                $importeOrigen = round($importeAnita / $cotizacionAnita, 2);
                $importe = $importeAnita;
            } else {
                if ($cotizacionAnita <= 0) {
                    $cotizacionAnita = 1.0;
                }
                $importeOrigen = $importeAnita;
                $importe = $importeAnita;
            }

            $destinoFirma = $this->destinoFirma($cuenta, (string) ($fila->remem_destino ?? ''), $codigoErp);
            $firma = implode('|', [
                $fechaYmdInt,
                $destinoFirma,
                $monedaAnita,
                number_format($importeOrigen, 2, '.', ''),
            ]);
            if (($firmasErp[$firma] ?? 0) > 0) {
                $firmasErp[$firma]--;

                continue;
            }

            if ($cuenta === null) {
                $cuenta = new Cuentacaja([
                    'codigo' => $codigoErp !== '' ? $codigoErp : ($codigoAnita !== '' ? $codigoAnita : trim((string) ($fila->remem_destino ?? 'ANITA'))),
                    'nombre' => trim((string) ($fila->remem_destino ?? 'Anita')),
                    'moneda_id' => $monedaId,
                ]);
                $cuenta->id = 0;
            }

            $out[] = $this->filaMovimiento(
                $cuenta,
                $fecha,
                0,
                (int) ($fila->remem_nro_remesa ?? 0),
                $monedaId,
                $cotizacionAnita,
                $importeOrigen,
                $importe,
                $this->etiquetaEstadoAnita((string) ($fila->remem_estado ?? '')),
                $empresaId,
                'ANITA',
                $nombreEmpresa,
                $firma,
            );
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    private function aplanarPorCuenta(array $movimientos, bool $consolidar, bool $multiEmpresa): array
    {
        $filas = [];
        $totalGeneralOrigen = 0.0;
        $totalGeneralImporte = 0.0;
        $grupo = null;
        $grupoEtiqueta = '';
        $acumOrigen = 0.0;
        $acumImporte = 0.0;

        $flushGrupo = function () use (&$filas, &$grupo, &$grupoEtiqueta, &$acumOrigen, &$acumImporte): void {
            if ($grupo === null) {
                return;
            }
            $filas[] = [
                'tipo_fila' => 'total_cuenta',
                'cuenta_etiqueta' => 'Total '.$grupoEtiqueta,
                'importe_origen' => $acumOrigen,
                'importe' => $acumImporte,
                'nombreempresa' => '',
            ];
        };

        foreach ($movimientos as $mov) {
            // Varias empresas: no mezclar cuentas homónimas; sin consolidar también separa por empresa.
            $clave = ($multiEmpresa || ! $consolidar ? ((int) $mov['empresa_id']).'|' : '')
                .$mov['cuenta_codigo'].'|'.$mov['cuenta_nombre'];
            if ($grupo !== $clave) {
                $flushGrupo();
                $grupo = $clave;
                $grupoEtiqueta = $this->etiquetaCuenta($mov['cuenta_codigo'], $mov['cuenta_nombre']);
                if (($multiEmpresa || ! $consolidar) && trim((string) ($mov['nombreempresa'] ?? '')) !== '') {
                    $grupoEtiqueta = trim((string) $mov['nombreempresa']).' — '.$grupoEtiqueta;
                }
                $acumOrigen = 0.0;
                $acumImporte = 0.0;
                $filas[] = [
                    'tipo_fila' => 'grupo',
                    'cuenta_etiqueta' => $grupoEtiqueta,
                    'nombreempresa' => $mov['nombreempresa'],
                ];
            }
            $filas[] = $mov + ['tipo_fila' => 'dato'];
            $acumOrigen = round($acumOrigen + (float) $mov['importe_origen'], 2);
            $acumImporte = round($acumImporte + (float) $mov['importe'], 2);
            $totalGeneralOrigen = round($totalGeneralOrigen + (float) $mov['importe_origen'], 2);
            $totalGeneralImporte = round($totalGeneralImporte + (float) $mov['importe'], 2);
        }
        $flushGrupo();

        if ($movimientos !== []) {
            $filas[] = [
                'tipo_fila' => 'total_general',
                'cuenta_etiqueta' => 'Total general',
                'importe_origen' => $totalGeneralOrigen,
                'importe' => $totalGeneralImporte,
                'nombreempresa' => $movimientos[0]['nombreempresa'] ?? '',
            ];
        }

        return $filas;
    }

    /**
     * @param  list<string>  $advertencias
     */
    private function cotizacion(string $fecha, int $monedaId, int $empresaId, array &$advertencias, string $ref): float
    {
        if ($monedaId <= 1) {
            return 1.0;
        }
        $tasa = CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($fecha, $monedaId, $empresaId);
        if ($tasa !== null && $tasa > 0) {
            return round($tasa, 6);
        }
        $tasa = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        if ($tasa > 0) {
            return round($tasa, 6);
        }
        $advertencias[] = 'Sin cotización vigente para '.$ref.' (moneda '.$monedaId.'). El importe en pesos quedó en 0.';

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaMovimiento(
        Cuentacaja $cuenta,
        string $fecha,
        int $remesaId,
        int $remesaNro,
        int $monedaId,
        float $cotizacion,
        float $importeOrigen,
        float $importe,
        string $estado,
        int $empresaId,
        string $fuente,
        string $nombreEmpresa,
        string $firma,
    ): array {
        $abrev = $this->abreviaturaMoneda($monedaId);

        return [
            'cuenta_id' => (int) ($cuenta->id ?? 0),
            'cuenta_codigo' => (string) $cuenta->codigo,
            'cuenta_nombre' => (string) $cuenta->nombre,
            'cuenta_etiqueta' => $this->etiquetaCuenta((string) $cuenta->codigo, (string) $cuenta->nombre),
            'fecha_ymd' => $fecha,
            'fecha' => $this->fechaDmy($fecha),
            'remesa_id' => $remesaId,
            'remesa_nro' => $remesaNro,
            'moneda' => $abrev,
            'cotizacion' => $cotizacion,
            'importe_origen' => $importeOrigen,
            'importe' => $importe,
            'estado' => $estado,
            'empresa_id' => $empresaId,
            'fuente' => $fuente,
            'nombreempresa' => $nombreEmpresa,
            'firma' => $firma,
        ];
    }

    private function firmaErp(string $fecha, Cuentacaja $cuenta, int $monedaId, float $importe): string
    {
        $fechaYmd = (int) str_replace('-', '', $fecha);
        $codigoAnita = CotizacionTesoreriaConsultaSupport::codigoAnitaDesdeMonedaId($monedaId) ?? $monedaId;
        $destino = $this->destinoFirma($cuenta, '', (string) $cuenta->codigo);

        return implode('|', [
            $fechaYmd,
            $destino,
            $codigoAnita,
            number_format($importe, 2, '.', ''),
        ]);
    }

    /**
     * @param  Collection<string, Cuentacaja>  $cuentasPorCodigo
     */
    private function cuentaPorDestinoAnita(Collection $cuentasPorCodigo, string $destinoAnita, int $monedaAnita): ?Cuentacaja
    {
        $destino = trim($destinoAnita);
        $needles = match ($destino) {
            '1' => ['MACRO'],
            '2' => ['FRANCES', 'BBVA'],
            '3' => ['PROVINCIA'],
            '4' => ['MACO', 'GREEN ARMOR'],
            '5' => ['SEGURIDAD'],
            '6' => ['PAGO FACIL'],
            default => [],
        };
        if ($needles === []) {
            return null;
        }

        foreach ($cuentasPorCodigo as $cuenta) {
            $texto = mb_strtoupper(trim((string) $cuenta->codigo).' '.trim((string) $cuenta->nombre));
            $monedaOk = (int) ($cuenta->moneda_id ?: 1) === $monedaAnita;
            foreach ($needles as $needle) {
                if (str_contains($texto, $needle) && $monedaOk) {
                    return $cuenta;
                }
            }
        }

        return null;
    }

    private function destinoFirma(?Cuentacaja $cuenta, string $destinoAnita, string $codigoErp): string
    {
        $texto = '';
        if ($cuenta !== null) {
            $texto = mb_strtoupper(trim((string) $cuenta->codigo).' '.trim((string) $cuenta->nombre));
        }
        if ($texto === '') {
            $texto = mb_strtoupper(trim($destinoAnita.' '.$codigoErp));
        }

        if ($cuenta === null && preg_match('/^[1-6]$/', trim($destinoAnita))) {
            return trim($destinoAnita);
        }

        return match (true) {
            str_contains($texto, 'PAGO FACIL') => '6',
            str_contains($texto, 'SEGURIDAD') => '5',
            str_contains($texto, 'PROVINCIA') => '3',
            str_contains($texto, 'FRANCES'), str_contains($texto, 'BBVA') => '2',
            str_contains($texto, 'MACRO') => '1',
            str_contains($texto, 'MACO'), str_contains($texto, 'GREEN ARMOR') => '4',
            default => $codigoErp !== '' ? 'erp:'.$codigoErp : ('anita:'.trim($destinoAnita)),
        };
    }

    private function etiquetaCuenta(string $codigo, string $nombre): string
    {
        $codigo = trim($codigo);
        $nombre = trim($nombre);
        if ($codigo === '') {
            return $nombre;
        }

        return '('.$codigo.') '.$nombre;
    }

    private function etiquetaEstadoErp(string $estado): string
    {
        return match ($estado) {
            RemesaSupport::ESTADO_REVERTIDA => 'Revertida',
            RemesaSupport::ESTADO_ANULADA => 'Anulada',
            default => 'Cerrada',
        };
    }

    private function etiquetaEstadoAnita(string $estado): string
    {
        $estado = strtoupper(trim($estado));
        if (in_array($estado, ['A', 'ANULADA', 'X'], true)) {
            return 'Anulada';
        }

        return 'Cerrada';
    }

    private function abreviaturaMoneda(int $monedaId): string
    {
        if (! isset($this->abrevMoneda[$monedaId])) {
            $abrev = strtoupper(trim((string) (Moneda::query()->whereKey($monedaId)->value('abreviatura') ?? '')));
            if ($abrev === '') {
                $abrev = $monedaId === 2 ? 'DOL' : ($monedaId === 3 ? 'EUR' : 'PES');
            }
            $this->abrevMoneda[$monedaId] = $abrev;
        }

        return $this->abrevMoneda[$monedaId];
    }

    private function fechaDmy(string $ymd): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return $ymd;
        }

        return (int) $m[3].'/'.(int) $m[2].'/'.$m[1];
    }
}
