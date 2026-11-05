<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\PropuestaPago;
use App\Models\Compras\PropuestaPagoLinea;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Database\SqlDialectSupport;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programa fecha de pago y marca de urgentes sobre la deuda abierta
 * (misma fuente que la proyección) para armar lotes separados.
 */
final class ProyeccionPagosProgramacionSupport
{
    public const LIMITE = 1500;

    public const LIMITE_INFORME = 8000;

    public const ASIGNACION_TODAS = 'todas';

    public const ASIGNACION_SIN = 'sin_fecha';

    public const ASIGNACION_CON = 'con_fecha';

    public const ASIGNACION_URGENTES = 'urgentes';

    public const ASIGNACION_VENCIDOS = 'vencidos';

    public const MEDIO_TODOS = '';

    public const MEDIO_TRF = 'TRF';

    public const MEDIO_CHEQUE = 'CHEQUE';

    public const INFORME_URGENTES = 'urgentes';

    public const INFORME_PROGRAMADOS = 'programados';

    /** @var list<string> */
    private const ESTADOS_ABIERTOS = ['BORRADOR', 'EN_APROBACION', 'AUTORIZADA', 'EJECUTADA_PARCIAL'];

    public static function columnasDisponibles(): bool
    {
        return Schema::hasTable('proveedor_cuentacorriente')
            && Schema::hasColumn('proveedor_cuentacorriente', 'fecha_pago')
            && Schema::hasColumn('proveedor_cuentacorriente', 'urgente_pago');
    }

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
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, truncado: bool}
     */
    public function listar(array $filtros, array $empresaIds, int $limite = self::LIMITE): array
    {
        if (! self::columnasDisponibles()) {
            return ['filas' => [], 'truncado' => false];
        }

        $modelos = $this->consultar($filtros, $empresaIds, max($limite, 1) + 1);
        $truncado = $modelos->count() > $limite;
        if ($truncado) {
            $modelos = $modelos->take($limite)->values();
        }

        $ocupados = self::mapaOcupados($modelos->pluck('id')->map(fn ($id) => (int) $id)->all());

        $filas = [];
        foreach ($modelos as $cc) {
            $fila = $this->fila($cc);
            if ($fila === null) {
                continue;
            }
            $ccId = (int) $cc->id;
            $fila['propuesta_abierta_id'] = $ocupados[$ccId] ?? 0;
            $filas[] = $fila;
        }

        return ['filas' => $filas, 'truncado' => $truncado];
    }

    /**
     * @param  array<int|string, mixed>  $fechasPorId
     * @param  array<int|string, mixed>  $urgentesPorId
     * @param  list<int>  $empresaIds
     * @return array{asignadas: int, limpiadas: int, urgentes: int, omitidas: int}
     */
    public function grabar(array $fechasPorId, array $urgentesPorId, array $empresaIds): array
    {
        $vacios = ['asignadas' => 0, 'limpiadas' => 0, 'urgentes' => 0, 'omitidas' => 0];
        if (! self::columnasDisponibles() || $fechasPorId === []) {
            return $vacios;
        }

        $pedidos = [];
        $omitidas = 0;
        foreach ($fechasPorId as $id => $rawFecha) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $texto = trim((string) $rawFecha);
            $fecha = null;
            if ($texto !== '') {
                $fecha = self::normalizarFecha($texto);
                if ($fecha === null) {
                    $omitidas++;

                    continue;
                }
            }
            $pedidos[$id] = [
                'fecha' => $fecha,
                'urgente' => ! empty($urgentesPorId[$id]) || ! empty($urgentesPorId[(string) $id]),
            ];
        }

        if ($pedidos === []) {
            return ['asignadas' => 0, 'limpiadas' => 0, 'urgentes' => 0, 'omitidas' => $omitidas];
        }

        $q = Proveedor_Cuentacorriente::query()
            ->with([
                'comprobante_proveedor_cuotas.formapagos',
                'comprobante_proveedor_cuotas.ordencompra_comprobante_cuotas.formapagos',
                'comprobante_proveedor_cuotas.ordencompra_comprobante_cuotas.ordencompra_comprobantes',
            ])
            ->whereIn('id', array_keys($pedidos))
            ->whereNotNull('comprobante_proveedor_id');
        if ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }
        $encontradas = $q->get()->keyBy(fn (Proveedor_Cuentacorriente $cc) => (int) $cc->id);
        $omitidas += count($pedidos) - $encontradas->count();

        $ocupados = self::mapaOcupados($encontradas->keys()->map(fn ($id) => (int) $id)->all());

        $asignadas = 0;
        $limpiadas = 0;
        $urgentes = 0;

        DB::transaction(function () use ($encontradas, $pedidos, $ocupados, &$asignadas, &$limpiadas, &$urgentes, &$omitidas) {
            foreach ($encontradas as $id => $cc) {
                $id = (int) $id;
                if (isset($ocupados[$id])) {
                    $omitidas++;

                    continue;
                }
                $pedido = $pedidos[$id];
                $medio = PropuestaPagoLineaPresentacionSupport::resolverMedioDesdeCuentacorriente($cc);
                $esCheque = PropuestaPagoTipoLote::esCheque($medio['medio_pago']);
                $esTransf = PropuestaPagoTipoLote::esTransferencia($medio['medio_pago']);

                $fecha = $pedido['fecha'];
                $urgente = (bool) $pedido['urgente'];
                if ($esCheque) {
                    $urgente = false;
                } elseif ($urgente && $esTransf) {
                    $fecha = null;
                } elseif (! $esTransf) {
                    $urgente = false;
                }

                $fechaActual = $cc->fecha_pago?->toDateString();
                $urgenteActual = (bool) $cc->urgente_pago;
                if ($fechaActual === $fecha && $urgenteActual === $urgente) {
                    continue;
                }

                $cc->fecha_pago = $fecha;
                $cc->urgente_pago = $urgente;
                $cc->save();

                if ($urgente) {
                    $urgentes++;
                } elseif ($fecha !== null) {
                    $asignadas++;
                } else {
                    $limpiadas++;
                }
            }
        });

        return [
            'asignadas' => $asignadas,
            'limpiadas' => $limpiadas,
            'urgentes' => $urgentes,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, truncado: bool}
     */
    public function informe(array $filtros, array $empresaIds, string $modo): array
    {
        $filtros['asignacion'] = $modo === self::INFORME_URGENTES
            ? self::ASIGNACION_URGENTES
            : self::ASIGNACION_CON;
        $filtros['medio'] = $modo === self::INFORME_URGENTES ? self::MEDIO_TRF : self::MEDIO_TODOS;
        $filtros['incluir_vencidas'] = true;
        if ($modo === self::INFORME_PROGRAMADOS) {
            $filtros['vencimiento_desde'] = '2000-01-01';
            $filtros['vencimiento_hasta'] = '2099-12-31';
        }

        return $this->listar($filtros, $empresaIds, self::LIMITE_INFORME);
    }

    /**
     * Deudas del mismo tipo de lote que todavía no están en la propuesta.
     *
     * @return list<array<string, mixed>>
     */
    public function candidatos(PropuestaPago $propuesta, string $texto = ''): array
    {
        if (! self::columnasDisponibles()) {
            return [];
        }

        $tipo = PropuestaPagoTipoLote::normalizar((string) $propuesta->tipo_lote);
        $filtros = [
            'empresa_id' => (int) $propuesta->empresa_id,
            'vencimiento_desde' => '2000-01-01',
            'vencimiento_hasta' => '2099-12-31',
            'asignacion' => self::ASIGNACION_TODAS,
            'medio' => self::MEDIO_TODOS,
            'incluir_vencidas' => true,
            'texto' => trim($texto),
        ];

        if ($tipo === PropuestaPagoTipoLote::URGENTE_TRF) {
            $filtros['asignacion'] = self::ASIGNACION_URGENTES;
            $filtros['medio'] = self::MEDIO_TRF;
        } elseif ($tipo === PropuestaPagoTipoLote::CHEQUE) {
            $fecha = $propuesta->fecha?->toDateString() ?: date('Y-m-d');
            $filtros['fecha_pago'] = $fecha;
            $filtros['medio'] = self::MEDIO_CHEQUE;
        } else {
            $desde = $propuesta->fecha_vencimiento_desde?->toDateString();
            $hasta = $propuesta->fecha_vencimiento_hasta?->toDateString();
            if ($desde) {
                $filtros['vencimiento_desde'] = $desde;
            }
            if ($hasta) {
                $filtros['vencimiento_hasta'] = $hasta;
            }
        }

        $ya = PropuestaPagoLinea::query()
            ->where('propuesta_pago_id', (int) $propuesta->id)
            ->pluck('proveedor_cuentacorriente_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $listado = $this->listar($filtros, [(int) $propuesta->empresa_id], 200);
        $salida = [];
        foreach ($listado['filas'] as $fila) {
            if (in_array((int) $fila['id'], $ya, true)) {
                continue;
            }
            if ((int) ($fila['propuesta_abierta_id'] ?? 0) > 0) {
                continue;
            }
            if (! PropuestaPagoTipoLote::admite($tipo, (string) $fila['medio_pago'])) {
                continue;
            }
            $salida[] = $fila;
            if (count($salida) >= 80) {
                break;
            }
        }

        return $salida;
    }

    /**
     * @param  list<int>  $ccIds
     * @return array<int, int> cuentacorriente_id => propuesta_pago_id
     */
    public static function mapaOcupados(array $ccIds, ?int $exceptoPropuestaId = null): array
    {
        $ccIds = array_values(array_filter(array_map('intval', $ccIds), fn (int $id) => $id > 0));
        if ($ccIds === []) {
            return [];
        }

        $q = PropuestaPagoLinea::query()
            ->select(['proveedor_cuentacorriente_id', 'propuesta_pago_id'])
            ->whereIn('proveedor_cuentacorriente_id', $ccIds)
            ->where('incluido', true)
            ->where(function ($w) {
                $w->whereNull('pagoproveedor_id')->orWhere('pagoproveedor_id', 0);
            })
            ->whereHas('propuesta_pagos', function ($p) use ($exceptoPropuestaId) {
                $p->whereIn('estado', self::ESTADOS_ABIERTOS);
                if ($exceptoPropuestaId) {
                    $p->where('id', '!=', $exceptoPropuestaId);
                }
            });

        $mapa = [];
        foreach ($q->get() as $linea) {
            $mapa[(int) $linea->proveedor_cuentacorriente_id] = (int) $linea->propuesta_pago_id;
        }

        return $mapa;
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente>
     */
    private function consultar(array $filtros, array $empresaIds, int $tope)
    {
        $desde = self::normalizarFecha((string) ($filtros['vencimiento_desde'] ?? '')) ?? date('Y-m-d');
        $hasta = self::normalizarFecha((string) ($filtros['vencimiento_hasta'] ?? '')) ?? date('Y-m-d', strtotime('+30 days'));
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $query = $this->queryDeuda($empresaIds, (int) ($filtros['empresa_id'] ?? 0));
        $asignacion = (string) ($filtros['asignacion'] ?? self::ASIGNACION_TODAS);
        $incluirVencidas = (bool) ($filtros['incluir_vencidas'] ?? true);

        $query->where(function ($w) use ($desde, $hasta, $asignacion, $incluirVencidas) {
            $w->where(function ($rango) use ($desde, $hasta, $asignacion) {
                $rango->where(function ($fechas) use ($desde, $hasta) {
                    $fechas->whereNull('proveedor_cuentacorriente.fechavencimiento')
                        ->orWhere(function ($entre) use ($desde, $hasta) {
                            $entre->whereDate('proveedor_cuentacorriente.fechavencimiento', '>=', $desde)
                                ->whereDate('proveedor_cuentacorriente.fechavencimiento', '<=', $hasta);
                        });
                });
                $this->aplicarAsignacion($rango, $asignacion);
            });
            if ($incluirVencidas && $asignacion !== self::ASIGNACION_CON) {
                $w->orWhere(function ($vencidas) use ($desde, $asignacion) {
                    $vencidas->whereDate('proveedor_cuentacorriente.fechavencimiento', '<', $desde);
                    if ($asignacion === self::ASIGNACION_URGENTES) {
                        $vencidas->where('proveedor_cuentacorriente.urgente_pago', true);
                    } elseif ($asignacion !== self::ASIGNACION_TODAS && $asignacion !== self::ASIGNACION_VENCIDOS) {
                        $vencidas->whereNull('proveedor_cuentacorriente.fecha_pago')
                            ->where(function ($u) {
                                $u->where('proveedor_cuentacorriente.urgente_pago', false)
                                    ->orWhereNull('proveedor_cuentacorriente.urgente_pago');
                            });
                    }
                });
            }
        });

        if ($asignacion === self::ASIGNACION_VENCIDOS) {
            $query->whereDate('proveedor_cuentacorriente.fechavencimiento', '<', date('Y-m-d'));
        }

        $fechaPago = self::normalizarFecha((string) ($filtros['fecha_pago'] ?? ''));
        if ($fechaPago !== null) {
            $query->whereDate('proveedor_cuentacorriente.fecha_pago', $fechaPago);
        }

        $texto = trim((string) ($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $query->where(function ($w) use ($like) {
                $w->whereHas('proveedores', function ($p) use ($like) {
                    $p->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like);
                })->orWhereHas('comprobante_proveedores', function ($c) use ($like) {
                    $c->where('numerocomprobante', 'like', $like)->orWhere('leyenda', 'like', $like);
                });
            });
        }

        $medio = (string) ($filtros['medio'] ?? self::MEDIO_TODOS);
        $modelos = $query
            ->orderBy('proveedor_cuentacorriente.fechavencimiento')
            ->orderBy('proveedor_cuentacorriente.id')
            ->limit($medio === self::MEDIO_TODOS ? $tope : min(4000, $tope * 4))
            ->get();

        if ($medio === self::MEDIO_TODOS) {
            return $modelos->take($tope)->values();
        }

        return $modelos->filter(function (Proveedor_Cuentacorriente $cc) use ($medio) {
            $resuelto = PropuestaPagoLineaPresentacionSupport::resolverMedioDesdeCuentacorriente($cc);

            return PropuestaPagoTipoLote::familia($resuelto['medio_pago']) === $medio;
        })->take($tope)->values();
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function queryDeuda(array $empresaIds, int $empresaId): Builder
    {
        $query = Proveedor_Cuentacorriente::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'empresas:id,nombre',
                'monedas:id,abreviatura',
                'comprobante_proveedores.tipotransaccion_compras',
                'comprobante_proveedores.condicionpagos',
                'comprobante_proveedores.conceptogastos',
                'comprobante_proveedor_cuotas.formapagos',
                'comprobante_proveedor_cuotas.ordencompra_comprobante_cuotas.formapagos',
                'comprobante_proveedor_cuotas.ordencompra_comprobante_cuotas.ordencompra_comprobantes',
            ])
            ->select('proveedor_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('comprobante_proveedor as cp_bloq')
                    ->whereColumn('cp_bloq.id', 'proveedor_cuentacorriente.comprobante_proveedor_id')
                    ->where('cp_bloq.bloqueado_pago', true);
            })
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteProveedorCc());

        if ($empresaId > 0) {
            $query->where('proveedor_cuentacorriente.empresa_id', $empresaId);
        } elseif ($empresaIds !== []) {
            $query->whereIn('proveedor_cuentacorriente.empresa_id', $empresaIds);
        }

        return $query;
    }

    private function aplicarAsignacion(Builder $query, string $asignacion): void
    {
        if ($asignacion === self::ASIGNACION_SIN) {
            $query->whereNull('proveedor_cuentacorriente.fecha_pago')
                ->where(function ($u) {
                    $u->where('proveedor_cuentacorriente.urgente_pago', false)
                        ->orWhereNull('proveedor_cuentacorriente.urgente_pago');
                });
        } elseif ($asignacion === self::ASIGNACION_CON) {
            $query->whereNotNull('proveedor_cuentacorriente.fecha_pago');
        } elseif ($asignacion === self::ASIGNACION_URGENTES) {
            $query->where('proveedor_cuentacorriente.urgente_pago', true);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fila(Proveedor_Cuentacorriente $cc): ?array
    {
        $aplicado = (float) ($cc->aplicado ?? 0);
        $saldo = abs((float) $cc->total + $aplicado);
        if ($saldo <= 0.0001) {
            return null;
        }

        $medio = PropuestaPagoLineaPresentacionSupport::resolverMedioDesdeCuentacorriente($cc);
        $comp = $cc->comprobante_proveedores;
        $tipo = '';
        $comprobante = 'CC#'.$cc->id;
        if ($comp) {
            $tt = $comp->tipotransaccion_compras;
            $tipo = (string) ($tt->abreviatura ?? $tt->nombre ?? '');
            $comprobante = sprintf(
                '%s-%04d-%s',
                $comp->letra ?? 'A',
                (int) ($comp->sucursal ?? 0),
                $comp->numerocomprobante ?? ''
            );
        }

        $hoy = date('Y-m-d');
        $vto = $cc->fechavencimiento?->toDateString();
        $dias = null;
        if ($vto) {
            $dias = (int) floor((strtotime($hoy) - strtotime($vto)) / 86400);
        }

        return [
            'id' => (int) $cc->id,
            'empresa' => (string) ($cc->empresas->nombre ?? ''),
            'nombreempresa' => (string) ($cc->empresas->nombre ?? ''),
            'proveedor_codigo' => (string) ($cc->proveedores->codigo ?? ''),
            'proveedor_nombre' => (string) ($cc->proveedores->nombre ?? ''),
            'tipo' => mb_substr($tipo, 0, 4),
            'comprobante' => $comprobante,
            'fecha_comprobante' => $comp?->fechacomprobante?->format('d/m/Y') ?? '',
            'fecha_iva' => $comp?->fechaiva?->format('d/m/Y') ?? '',
            'fecha_vto' => $cc->fechavencimiento?->format('d/m/Y') ?? '',
            'fecha_vto_iso' => $vto ?? '',
            'dias' => $dias,
            'condicion_pago' => (string) ($comp?->condicionpagos->nombre ?? ''),
            'nro_refer' => $medio['nro_refer'],
            'medio_pago' => $medio['medio_pago'],
            'familia' => PropuestaPagoTipoLote::familia($medio['medio_pago']),
            'detalle_pago' => $medio['detalle_pago'],
            'moneda' => (string) ($cc->monedas->abreviatura ?? ''),
            'total_adeudado' => round($saldo, 2),
            'leyenda' => (string) ($comp?->leyenda ?? $cc->leyenda ?? ''),
            'detalle_concepto' => (string) ($comp?->conceptogastos->nombre ?? ''),
            'fecha_pago' => $cc->fecha_pago?->toDateString() ?? '',
            'urgente' => (bool) $cc->urgente_pago,
            'comprobante_proveedor_id' => (int) ($cc->comprobante_proveedor_id ?? 0),
        ];
    }
}
