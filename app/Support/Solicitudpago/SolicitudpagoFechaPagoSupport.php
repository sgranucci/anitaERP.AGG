<?php

declare(strict_types=1);

namespace App\Support\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de la fecha en la que se va a pagar una solicitud autorizada.
 * Esa fecha es la que usa la posición bancaria diaria.
 */
final class SolicitudpagoFechaPagoSupport
{
    public const LIMITE = 1500;

    public const ESTADO_AUTORIZADAS = 'autorizadas';

    public const ESTADO_PAGADAS = 'pagadas';

    public const ESTADO_AMBAS = 'ambas';

    public const ASIGNACION_TODAS = 'todas';

    public const ASIGNACION_SIN = 'sin_fecha';

    public const ASIGNACION_CON = 'con_fecha';

    public static function normalizarFecha(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        $valor = substr($valor, 0, 10);
        $dt = DateTime::createFromFormat('!Y-m-d', $valor);

        return ($dt instanceof DateTime && $dt->format('Y-m-d') === $valor) ? $valor : null;
    }

    /**
     * @param  list<int>  $empresaIds  vacío = sin restricción de empresa
     * @return array{filas: Collection<int, Solicitudpago>, truncado: bool}
     */
    public function listar(
        string $desde,
        string $hasta,
        array $empresaIds,
        ?int $empresaId,
        string $estadoFiltro,
        string $asignacion,
        bool $incluirVencidas,
    ): array {
        if (! Schema::hasTable('solicitudpago') || ! Schema::hasColumn('solicitudpago', 'fecha_pago')) {
            return ['filas' => collect(), 'truncado' => false];
        }

        $q = Solicitudpago::query()
            ->with([
                'empresas:id,nombre',
                'conceptos:id,nombre',
                'proveedores:id,nombre,codigo',
                'monedas:id,abreviatura',
                'formapagosol:id,nombre',
            ])
            ->whereDoesntHave('hijas')
            ->whereIn('estado', $this->estados($estadoFiltro))
            ->whereNotNull('fecha_vencimiento');

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        } elseif ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }

        $q->where(function ($w) use ($desde, $hasta, $asignacion, $incluirVencidas) {
            $w->where(function ($rango) use ($desde, $hasta, $asignacion) {
                $rango->whereDate('fecha_vencimiento', '>=', $desde)
                    ->whereDate('fecha_vencimiento', '<=', $hasta);
                $this->aplicarAsignacion($rango, $asignacion);
            });
            if ($incluirVencidas && $asignacion !== self::ASIGNACION_CON) {
                $w->orWhere(function ($vencidas) use ($desde) {
                    $vencidas->whereDate('fecha_vencimiento', '<', $desde)
                        ->whereNull('fecha_pago');
                });
            }
        });

        $filas = $q->orderBy('fecha_vencimiento')
            ->orderBy('codigo')
            ->limit(self::LIMITE + 1)
            ->get();

        $truncado = $filas->count() > self::LIMITE;
        if ($truncado) {
            $filas = $filas->take(self::LIMITE)->values();
        }

        return ['filas' => $filas, 'truncado' => $truncado];
    }

    /**
     * @param  array<int|string, mixed>  $fechasPorId
     * @param  list<int>  $empresaIds
     * @return array{asignadas: int, limpiadas: int, omitidas: int}
     */
    public function grabar(array $fechasPorId, array $empresaIds): array
    {
        $normalizadas = [];
        $omitidas = 0;
        foreach ($fechasPorId as $id => $raw) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $texto = trim((string) $raw);
            if ($texto === '') {
                $normalizadas[$id] = null;

                continue;
            }
            $fecha = self::normalizarFecha($texto);
            if ($fecha === null) {
                $omitidas++;

                continue;
            }
            $normalizadas[$id] = $fecha;
        }

        if ($normalizadas === [] || ! Schema::hasColumn('solicitudpago', 'fecha_pago')) {
            return ['asignadas' => 0, 'limpiadas' => 0, 'omitidas' => $omitidas];
        }

        $q = Solicitudpago::query()
            ->whereIn('id', array_keys($normalizadas))
            ->whereDoesntHave('hijas')
            ->whereIn('estado', [SolicitudpagoEstados::AUTORIZADA, SolicitudpagoEstados::PAGADA]);
        if ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }
        $encontradas = $q->get()->keyBy(fn (Solicitudpago $sp) => (int) $sp->id);
        $omitidas += count($normalizadas) - $encontradas->count();

        $asignadas = 0;
        $limpiadas = 0;
        DB::transaction(function () use ($encontradas, $normalizadas, &$asignadas, &$limpiadas) {
            foreach ($encontradas as $id => $sp) {
                $nueva = $normalizadas[(int) $id] ?? null;
                $actual = $sp->fecha_pago?->toDateString();
                if ($actual === $nueva) {
                    continue;
                }
                $sp->fecha_pago = $nueva;
                $sp->save();
                if ($nueva === null) {
                    $limpiadas++;
                } else {
                    $asignadas++;
                }
            }
        });

        return [
            'asignadas' => $asignadas,
            'limpiadas' => $limpiadas,
            'omitidas' => $omitidas,
        ];
    }

    /** @return list<string> */
    private function estados(string $estadoFiltro): array
    {
        return match ($estadoFiltro) {
            self::ESTADO_PAGADAS => [SolicitudpagoEstados::PAGADA],
            self::ESTADO_AMBAS => [SolicitudpagoEstados::AUTORIZADA, SolicitudpagoEstados::PAGADA],
            default => [SolicitudpagoEstados::AUTORIZADA],
        };
    }

    private function aplicarAsignacion($query, string $asignacion): void
    {
        if ($asignacion === self::ASIGNACION_SIN) {
            $query->whereNull('fecha_pago');
        } elseif ($asignacion === self::ASIGNACION_CON) {
            $query->whereNotNull('fecha_pago');
        }
    }

    /**
     * Impagas con fecha de pago, agrupadas por empresa y ordenadas por esa fecha.
     * El formato de cada fila es el del informe de solicitudes de pago.
     *
     * @param  list<int>  $empresaIds
     * @return array{filas: Collection<int, object>, totales: array{registros: int, monto: float}}
     */
    public function informeImpagas(array $empresaIds, ?int $empresaId): array
    {
        if (! Schema::hasTable('solicitudpago') || ! Schema::hasColumn('solicitudpago', 'fecha_pago')) {
            return ['filas' => collect(), 'totales' => ['registros' => 0, 'monto' => 0.0]];
        }

        $q = Solicitudpago::query()
            ->with([
                'empresas:id,nombre,codigo',
                'proveedores:id,codigo,nombre',
                'conceptos:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'monedas:id,abreviatura,nombre',
                'sectores:id,codigo,nombre',
                'madre:id,codigo,estado,monto,fecha',
                'cuotas',
                'estados',
            ])
            ->whereNotNull('fecha_pago')
            ->whereDoesntHave('hijas')
            ->whereNotIn('estado', [
                SolicitudpagoEstados::PAGADA,
                SolicitudpagoEstados::SUSPENDIDA,
                SolicitudpagoEstados::RECHAZADA,
                SolicitudpagoEstados::TERMINADA,
            ]);

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        } elseif ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }

        SolicitudpagoVisibilidadSupport::aplicarFiltroListado($q, 'empresa_id', 'centrocosto_id');

        $solicitudes = $q->get()->sort(function (Solicitudpago $a, Solicitudpago $b) {
            $empresa = strcasecmp(
                (string) (optional($a->empresas)->nombre ?? ''),
                (string) (optional($b->empresas)->nombre ?? '')
            );
            if ($empresa !== 0) {
                return $empresa;
            }
            $fecha = strcmp(
                (string) ($a->fecha_pago?->toDateString() ?? ''),
                (string) ($b->fecha_pago?->toDateString() ?? '')
            );
            if ($fecha !== 0) {
                return $fecha;
            }

            return ((int) $a->codigo) <=> ((int) $b->codigo);
        })->values();

        $enriquecidas = SolicitudpagoMaeReporteConsulta::enriquecerColeccion($solicitudes, [
            'filtro_tratamiento' => 'NORMAL',
            'incluir_conciliacion_mayor' => false,
        ]);

        $filas = collect();
        $empresaActual = null;
        $buffer = collect();
        $montoEmpresa = 0.0;
        $cerrarEmpresa = function () use (&$filas, &$buffer, &$montoEmpresa, &$empresaActual) {
            if ($empresaActual === null) {
                return;
            }
            foreach ($buffer as $fila) {
                $filas->push($fila);
            }
            $filas->push($this->filaTotal('Total '.$empresaActual, $montoEmpresa, $buffer->count()));
            $buffer = collect();
            $montoEmpresa = 0.0;
        };

        foreach ($solicitudes as $indice => $solicitud) {
            $fila = $enriquecidas[$indice];
            $fila->fecha_pago = $solicitud->fecha_pago;
            $fila->es_total = false;
            $nombre = (string) ($fila->nombreempresa ?? '');
            if ($empresaActual !== null && $nombre !== $empresaActual) {
                $cerrarEmpresa();
            }
            $empresaActual = $nombre;
            $buffer->push($fila);
            $montoEmpresa += (float) $fila->monto;
        }
        $cerrarEmpresa();

        $registros = $solicitudes->count();
        $monto = (float) $solicitudes->sum(fn (Solicitudpago $sp) => (float) $sp->monto);
        if ($registros > 0) {
            $filas->push($this->filaTotal('Total general', $monto, $registros));
        }

        return [
            'filas' => $filas->values(),
            'totales' => ['registros' => $registros, 'monto' => $monto],
        ];
    }

    private function filaTotal(string $etiqueta, float $monto, int $cantidad): object
    {
        return (object) [
            'es_total' => true,
            'nombreempresa' => $etiqueta,
            'codigo' => '',
            'es_madre_plan' => false,
            'es_hija' => false,
            'fecha' => null,
            'fecha_vencimiento' => null,
            'fecha_pago' => null,
            'tratamiento_label' => '',
            'sector_codigo' => '',
            'sector_nombre' => '',
            'concepto_nombre' => $cantidad === 1 ? '1 solicitud' : $cantidad.' solicitudes',
            'forma_pago_nombre' => '',
            'proveedor_codigo' => '',
            'proveedor_nombre' => '',
            'moneda' => '',
            'monto' => $monto,
            'estado_label' => '',
            'referencia' => '',
            'observacion' => '',
        ];
    }
};
