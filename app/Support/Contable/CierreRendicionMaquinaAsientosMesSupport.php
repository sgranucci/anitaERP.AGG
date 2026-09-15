<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquina;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Tipoasiento;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Listado de asientos de máquinas (tipo MAQ) en ERP para un mes calendario.
 *
 * Fuente: tabla asiento (tipo abreviatura MAQ) de la empresa.
 * Complementa con jornada desde rendiciones cerradas cuando hay vínculo.
 */
final class CierreRendicionMaquinaAsientosMesSupport
{
    /**
     * @return array<string, mixed>
     */
    public function generar(int $empresaId, int $mes, int $anio): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Indique empresa.');
        }
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('Mes inválido.');
        }
        if ($anio < 2000 || $anio > 2100) {
            throw new InvalidArgumentException('Año inválido.');
        }

        $empresa = Empresa::query()->find($empresaId);
        if ($empresa === null) {
            throw new InvalidArgumentException('Empresa inexistente.');
        }

        $tipoAsientoId = $this->resolverTipoAsientoMaqId();
        if ($tipoAsientoId <= 0) {
            throw new InvalidArgumentException(
                'No está configurado el tipo de asiento MAQ (Máquinas) en ERP.',
            );
        }

        $desde = Carbon::create($anio, $mes, 1)->startOfDay()->toDateString();
        $hasta = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

        $jornadaPorAsiento = $this->mapaJornadaPorAsiento($empresaId);

        $asientos = Asiento::query()
            ->where('empresa_id', $empresaId)
            ->where('tipoasiento_id', $tipoAsientoId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->with([
                'tipoasientos',
                'asiento_movimientos.cuentacontables',
                'asiento_movimientos.centrocostos',
                'asiento_movimientos.monedas',
            ])
            ->orderBy('fecha')
            ->orderBy('numeroasiento')
            ->orderBy('id')
            ->get();

        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $cantidadCierreErp = 0;
        $cantidadOtros = 0;

        foreach ($asientos as $asiento) {
            $fila = $this->filaDesdeAsiento(
                $asiento,
                $jornadaPorAsiento[(int) $asiento->id] ?? null,
            );
            $filas[] = $fila;
            $totalDebe += (float) $fila['total_debe'];
            $totalHaber += (float) $fila['total_haber'];
            if (! empty($fila['es_cierre_erp'])) {
                $cantidadCierreErp++;
            } else {
                $cantidadOtros++;
            }
        }

        $mesesNombres = $this->mesesNombres();

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'empresa_codigo' => (string) ($empresa->codigo ?? ''),
            'mes' => $mes,
            'anio' => $anio,
            'mes_nombre' => $mesesNombres[$mes] ?? (string) $mes,
            'periodo_label' => ($mesesNombres[$mes] ?? (string) $mes).' / '.$anio,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'cantidad_asientos' => count($filas),
            'cantidad_cierre_erp' => $cantidadCierreErp,
            'cantidad_otros' => $cantidadOtros,
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'filas' => $filas,
        ];
    }

    private function resolverTipoAsientoMaqId(): int
    {
        $abrev = (string) config(
            'rendicion_maquina_anita.cierre_rendicion_contable.tipoasiento_abreviatura',
            'MAQ',
        );
        if ($abrev === '') {
            $abrev = 'MAQ';
        }

        return (int) (Tipoasiento::query()
            ->where('abreviatura', $abrev)
            ->value('id') ?? 0);
    }

    /**
     * @return array<int, string>
     */
    private function mapaJornadaPorAsiento(int $empresaId): array
    {
        $rendiciones = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->where('turno', RendicionMaquinaTurno::COMPLETO)
            ->where(function ($q) {
                $q->whereNotNull('asientos_cierre_ids_json')
                    ->orWhere('asiento_id', '>', 0);
            })
            ->get(['id', 'fecha', 'asiento_id', 'asientos_cierre_ids_json']);

        $jornadaPorAsiento = [];
        foreach ($rendiciones as $rendicion) {
            $jornada = $rendicion->fecha?->format('Y-m-d');
            if ($jornada === null || $jornada === '') {
                continue;
            }
            foreach ($this->asientoIdsDeRendicion($rendicion) as $asientoId) {
                if (! isset($jornadaPorAsiento[$asientoId])) {
                    $jornadaPorAsiento[$asientoId] = $jornada;
                }
            }
        }

        return $jornadaPorAsiento;
    }

    /**
     * @return list<int>
     */
    private function asientoIdsDeRendicion(RendicionMaquina $row): array
    {
        $ids = [];
        $json = $row->asientos_cierre_ids_json;
        if (is_array($json)) {
            foreach ($json as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
        $principal = (int) ($row->asiento_id ?? 0);
        if ($principal > 0) {
            $ids[$principal] = $principal;
        }

        return array_values($ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDesdeAsiento(Asiento $asiento, ?string $jornada): array
    {
        $movimientos = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($asiento->asiento_movimientos as $mov) {
            $monto = (float) ($mov->monto ?? 0);
            $debe = $monto >= 0 ? $monto : 0.0;
            $haber = $monto < 0 ? abs($monto) : 0.0;
            $totalDebe += $debe;
            $totalHaber += $haber;

            $movimientos[] = [
                'cuenta_codigo' => (string) ($mov->cuentacontables?->codigo ?? ''),
                'cuenta_nombre' => (string) ($mov->cuentacontables?->nombre ?? ''),
                'centrocosto' => (string) ($mov->centrocostos?->nombre ?? ''),
                'observacion' => (string) ($mov->observacion ?? ''),
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
                'moneda' => (string) ($mov->monedas?->nombre ?? ''),
                'cotizacion' => (float) ($mov->cotizacion ?? 0),
            ];
        }

        $fecha = $asiento->fecha;
        $fechaStr = $fecha instanceof \DateTimeInterface
            ? $fecha->format('Y-m-d')
            : (string) $fecha;

        $observacion = (string) ($asiento->observacion ?? '');
        $esCierreErp = str_starts_with($observacion, 'Cierre rendición máquinas');
        $origen = $esCierreErp
            ? 'Cierre ERP'
            : ((string) ($asiento->anita_origen ?? '') !== '' ? 'Anita/ERP' : 'ERP');

        if ($jornada === null || $jornada === '') {
            $jornada = $fechaStr !== '' ? $fechaStr : null;
        }

        return [
            'id' => (int) $asiento->id,
            'numero' => (string) ($asiento->numeroasiento ?? ''),
            'fecha' => $fechaStr,
            'fecha_fmt' => $fechaStr !== '' ? Carbon::parse($fechaStr)->format('d/m/Y') : '—',
            'tipo' => (string) ($asiento->tipoasientos?->nombre ?? $asiento->tipoasientos?->abreviatura ?? 'MAQ'),
            'tipo_abreviatura' => (string) ($asiento->tipoasientos?->abreviatura ?? ''),
            'observacion' => $observacion,
            'origen' => $origen,
            'es_cierre_erp' => $esCierreErp,
            'jornada' => $jornada,
            'jornada_fmt' => $jornada !== null && $jornada !== ''
                ? Carbon::parse($jornada)->format('d/m/Y')
                : '—',
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'movimientos' => $movimientos,
            'cantidad_movimientos' => count($movimientos),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function mesesNombres(): array
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
    }
}
