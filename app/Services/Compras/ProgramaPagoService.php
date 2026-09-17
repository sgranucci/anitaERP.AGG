<?php

namespace App\Services\Compras;

use App\Models\Compras\ProgramaPago;
use App\Models\Compras\ProgramaPagoAsignacion;
use App\Models\Compras\ProgramaPagoLinea;
use App\Repositories\Compras\ProgramaPagoRepositoryInterface;
use App\Support\Caja\ProgramaPagoChequesCarteraSupport;
use App\Support\Compras\ProgramaPagoDeudaSupport;
use App\Support\Compras\ProgramaPagoMesesSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProgramaPagoService
{
    public function __construct(
        private ProgramaPagoRepositoryInterface $repository,
    ) {
    }

    /**
     * @param  array{
     *   empresa_id:int,
     *   titulo?:?string,
     *   fecha_base:string,
     *   anio_mes_inicio:string,
     *   cantidad_meses?:int,
     *   incluye_transf?:bool,
     *   detalle?:?string,
     *   sembrar_deuda?:bool
     * }  $datos
     */
    public function crear(array $datos): ProgramaPago
    {
        $empresaId = (int) ($datos['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe indicar la empresa.');
        }

        $fechaBase = (string) ($datos['fecha_base'] ?? '');
        $anioMes = (string) ($datos['anio_mes_inicio'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaBase)) {
            throw new InvalidArgumentException('Fecha base inválida.');
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $anioMes)) {
            throw new InvalidArgumentException('Mes de inicio inválido (YYYY-MM).');
        }

        $cantidad = max(1, min(12, (int) ($datos['cantidad_meses'] ?? 4)));
        $incluyeTransf = (bool) ($datos['incluye_transf'] ?? true);
        $sembrar = (bool) ($datos['sembrar_deuda'] ?? true);

        return DB::transaction(function () use ($datos, $empresaId, $fechaBase, $anioMes, $cantidad, $incluyeTransf, $sembrar) {
            $programa = $this->repository->create([
                'empresa_id' => $empresaId,
                'titulo' => trim((string) ($datos['titulo'] ?? '')) ?: null,
                'fecha_base' => $fechaBase,
                'anio_mes_inicio' => $anioMes,
                'cantidad_meses' => $cantidad,
                'incluye_transf' => $incluyeTransf,
                'estado' => ProgramaPago::ESTADO_BORRADOR,
                'detalle' => trim((string) ($datos['detalle'] ?? '')) ?: null,
                'usuario_id' => Auth::id(),
            ]);

            if ($sembrar) {
                $this->sembrarDesdeDeuda($programa);
            }

            return $this->repository->find($programa->id);
        });
    }

    public function sembrarDesdeDeuda(ProgramaPago $programa): int
    {
        $this->assertEditable($programa);

        $saldos = ProgramaPagoDeudaSupport::saldosPorProveedor(
            (int) $programa->empresa_id,
            $programa->fecha_base->format('Y-m-d')
        );

        $existentes = ProgramaPagoLinea::query()
            ->where('programa_pago_id', $programa->id)
            ->pluck('id', 'proveedor_id');

        $orden = (int) (ProgramaPagoLinea::query()->where('programa_pago_id', $programa->id)->max('orden') ?? 0);
        $creadas = 0;

        foreach ($saldos as $fila) {
            if ($fila->saldo <= 0.009) {
                continue;
            }
            $proveedorId = (int) $fila->proveedor_id;
            if ($existentes->has($proveedorId)) {
                ProgramaPagoLinea::query()
                    ->where('id', (int) $existentes[$proveedorId])
                    ->update(['saldo_adeudado' => $fila->saldo]);
                continue;
            }
            $orden++;
            ProgramaPagoLinea::query()->create([
                'programa_pago_id' => $programa->id,
                'proveedor_id' => $proveedorId,
                'saldo_adeudado' => $fila->saldo,
                'observacion' => null,
                'orden' => $orden,
            ]);
            $creadas++;
        }

        return $creadas;
    }

    public function refrescarSaldos(ProgramaPago $programa): int
    {
        $this->assertEditable($programa);

        $fecha = $programa->fecha_base->format('Y-m-d');
        $actualizados = 0;
        foreach ($programa->lineas as $linea) {
            $saldo = ProgramaPagoDeudaSupport::saldoProveedor(
                (int) $programa->empresa_id,
                (int) $linea->proveedor_id,
                $fecha
            );
            if (abs((float) $linea->saldo_adeudado - $saldo) > 0.009) {
                $linea->update(['saldo_adeudado' => $saldo]);
                $actualizados++;
            }
        }

        return $actualizados;
    }

    /**
     * @param  array{
     *   titulo?:?string,
     *   detalle?:?string,
     *   estado?:string,
     *   lineas?: list<array{
     *     id?:int,
     *     proveedor_id?:int,
     *     saldo_adeudado?:float|string,
     *     observacion?:?string,
     *     asignaciones?: array<string, float|string>
     *   }>
     * }  $datos
     */
    public function actualizar(ProgramaPago $programa, array $datos): ProgramaPago
    {
        $this->assertEditable($programa);

        $columnas = ProgramaPagoMesesSupport::columnas(
            (string) $programa->anio_mes_inicio,
            (int) $programa->cantidad_meses,
            (bool) $programa->incluye_transf
        );
        $clavesValidas = array_flip(ProgramaPagoMesesSupport::claves($columnas));

        return DB::transaction(function () use ($programa, $datos, $clavesValidas) {
            $cab = [];
            if (array_key_exists('titulo', $datos)) {
                $cab['titulo'] = trim((string) ($datos['titulo'] ?? '')) ?: null;
            }
            if (array_key_exists('detalle', $datos)) {
                $cab['detalle'] = trim((string) ($datos['detalle'] ?? '')) ?: null;
            }
            if (! empty($datos['estado']) && in_array($datos['estado'], [
                ProgramaPago::ESTADO_BORRADOR,
                ProgramaPago::ESTADO_CERRADO,
            ], true)) {
                $cab['estado'] = $datos['estado'];
            }
            if ($cab !== []) {
                $this->repository->update($cab, $programa->id);
            }

            $lineasPayload = $datos['lineas'] ?? null;
            if (is_array($lineasPayload)) {
                $idsConservar = [];
                $orden = 0;
                foreach ($lineasPayload as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $lineaId = (int) ($item['id'] ?? 0);
                    $proveedorId = (int) ($item['proveedor_id'] ?? 0);
                    $orden++;

                    if ($lineaId > 0) {
                        $linea = ProgramaPagoLinea::query()
                            ->where('programa_pago_id', $programa->id)
                            ->where('id', $lineaId)
                            ->first();
                        if ($linea === null) {
                            continue;
                        }
                        $linea->update([
                            'observacion' => trim((string) ($item['observacion'] ?? '')) ?: null,
                            'orden' => $orden,
                            'saldo_adeudado' => round((float) ($item['saldo_adeudado'] ?? $linea->saldo_adeudado), 2),
                        ]);
                    } else {
                        if ($proveedorId <= 0) {
                            continue;
                        }
                        $existe = ProgramaPagoLinea::query()
                            ->where('programa_pago_id', $programa->id)
                            ->where('proveedor_id', $proveedorId)
                            ->exists();
                        if ($existe) {
                            throw new InvalidArgumentException('El proveedor ya está en el programa.');
                        }
                        $saldo = array_key_exists('saldo_adeudado', $item)
                            ? round((float) $item['saldo_adeudado'], 2)
                            : ProgramaPagoDeudaSupport::saldoProveedor(
                                (int) $programa->empresa_id,
                                $proveedorId,
                                $programa->fecha_base->format('Y-m-d')
                            );
                        $linea = ProgramaPagoLinea::query()->create([
                            'programa_pago_id' => $programa->id,
                            'proveedor_id' => $proveedorId,
                            'saldo_adeudado' => $saldo,
                            'observacion' => trim((string) ($item['observacion'] ?? '')) ?: null,
                            'orden' => $orden,
                        ]);
                    }

                    $idsConservar[] = (int) $linea->id;
                    $this->sincronizarAsignaciones($linea, $item['asignaciones'] ?? [], $clavesValidas);
                }

                EloquentAuditDeleteSupport::each(
                    ProgramaPagoLinea::query()
                        ->where('programa_pago_id', $programa->id)
                        ->when($idsConservar !== [], fn ($q) => $q->whereNotIn('id', $idsConservar))
                        ->when($idsConservar === [], fn ($q) => $q)
                );
            }

            return $this->repository->find($programa->id);
        });
    }

    public function agregarProveedor(ProgramaPago $programa, int $proveedorId, ?string $observacion = null): ProgramaPagoLinea
    {
        $this->assertEditable($programa);
        if ($proveedorId <= 0) {
            throw new InvalidArgumentException('Proveedor inválido.');
        }
        if (ProgramaPagoLinea::query()
            ->where('programa_pago_id', $programa->id)
            ->where('proveedor_id', $proveedorId)
            ->exists()) {
            throw new InvalidArgumentException('El proveedor ya está en el programa.');
        }

        $orden = (int) (ProgramaPagoLinea::query()->where('programa_pago_id', $programa->id)->max('orden') ?? 0) + 1;
        $saldo = ProgramaPagoDeudaSupport::saldoProveedor(
            (int) $programa->empresa_id,
            $proveedorId,
            $programa->fecha_base->format('Y-m-d')
        );

        return ProgramaPagoLinea::query()->create([
            'programa_pago_id' => $programa->id,
            'proveedor_id' => $proveedorId,
            'saldo_adeudado' => $saldo,
            'observacion' => $observacion ? trim($observacion) : null,
            'orden' => $orden,
        ]);
    }

    public function eliminarLinea(ProgramaPago $programa, int $lineaId): void
    {
        $this->assertEditable($programa);
        $linea = ProgramaPagoLinea::query()
            ->where('programa_pago_id', $programa->id)
            ->where('id', $lineaId)
            ->firstOrFail();
        $linea->delete();
    }

    public function cerrar(ProgramaPago $programa): ProgramaPago
    {
        $this->assertEditable($programa);
        $this->repository->update(['estado' => ProgramaPago::ESTADO_CERRADO], $programa->id);

        return $this->repository->find($programa->id);
    }

    public function reabrir(ProgramaPago $programa): ProgramaPago
    {
        if ($programa->estado !== ProgramaPago::ESTADO_CERRADO) {
            throw new RuntimeException('Solo se puede reabrir un programa cerrado.');
        }
        $this->repository->update(['estado' => ProgramaPago::ESTADO_BORRADOR], $programa->id);

        return $this->repository->find($programa->id);
    }

    public function eliminar(ProgramaPago $programa): void
    {
        if ($programa->estado !== ProgramaPago::ESTADO_BORRADOR) {
            throw new RuntimeException('Solo se pueden borrar programas en borrador.');
        }
        $this->repository->delete($programa->id);
    }

    /**
     * @return array{
     *   columnas: list<array{clave:string,etiqueta:string,anio_mes:?string}>,
     *   filas: list<array<string,mixed>>,
     *   totales_asignacion: array<string,float>,
     *   total_saldo: float,
     *   total_programa: float,
     *   cheques: array{por_clave: array<string,float>, total: float},
     *   diferencia: array<string,float>
     * }
     */
    public function armarVistaMatriz(ProgramaPago $programa): array
    {
        $columnas = ProgramaPagoMesesSupport::columnas(
            (string) $programa->anio_mes_inicio,
            (int) $programa->cantidad_meses,
            (bool) $programa->incluye_transf
        );
        $claves = ProgramaPagoMesesSupport::claves($columnas);

        $totales = array_fill_keys($claves, 0.0);
        $filas = [];
        $totalSaldo = 0.0;
        $totalPrograma = 0.0;

        foreach ($programa->lineas as $linea) {
            $mapa = [];
            foreach ($linea->asignaciones as $asig) {
                $mapa[(string) $asig->clave] = round((float) $asig->monto, 2);
            }
            $asignaciones = [];
            $sumaFila = 0.0;
            foreach ($claves as $clave) {
                $monto = round((float) ($mapa[$clave] ?? 0), 2);
                $asignaciones[$clave] = $monto;
                $totales[$clave] += $monto;
                $sumaFila += $monto;
            }
            $saldo = round((float) $linea->saldo_adeudado, 2);
            $totalSaldo += $saldo;
            $totalPrograma += $sumaFila;
            $filas[] = [
                'id' => (int) $linea->id,
                'proveedor_id' => (int) $linea->proveedor_id,
                'codigo' => (string) ($linea->proveedores->codigo ?? ''),
                'nombre' => (string) ($linea->proveedores->nombre ?? ''),
                'saldo_adeudado' => $saldo,
                'observacion' => (string) ($linea->observacion ?? ''),
                'asignaciones' => $asignaciones,
                'total_fila' => round($sumaFila, 2),
            ];
        }

        foreach ($totales as $k => $v) {
            $totales[$k] = round($v, 2);
        }

        $cheques = ProgramaPagoChequesCarteraSupport::montosPorColumna(
            (int) $programa->empresa_id,
            $columnas
        );

        $diferencia = [];
        foreach ($claves as $clave) {
            if ($clave === ProgramaPagoMesesSupport::CLAVE_TRANSF) {
                $diferencia[$clave] = round(0 - $totales[$clave], 2);
                continue;
            }
            $cht = (float) ($cheques['por_clave'][$clave] ?? 0);
            $diferencia[$clave] = round($cht - $totales[$clave], 2);
        }

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'totales_asignacion' => $totales,
            'total_saldo' => round($totalSaldo, 2),
            'total_programa' => round($totalPrograma, 2),
            'cheques' => $cheques,
            'diferencia' => $diferencia,
        ];
    }

    /**
     * @param  array<string, float|string>  $asignaciones
     * @param  array<string, int>  $clavesValidas
     */
    private function sincronizarAsignaciones(ProgramaPagoLinea $linea, array $asignaciones, array $clavesValidas): void
    {
        $clavesConservar = [];
        foreach ($asignaciones as $clave => $montoRaw) {
            $clave = (string) $clave;
            if (! isset($clavesValidas[$clave])) {
                continue;
            }
            $monto = round((float) $montoRaw, 2);
            if (abs($monto) < 0.005) {
                continue;
            }
            $clavesConservar[] = $clave;
            ProgramaPagoAsignacion::query()->updateOrCreate(
                [
                    'programa_pago_linea_id' => $linea->id,
                    'clave' => $clave,
                ],
                ['monto' => $monto]
            );
        }

        EloquentAuditDeleteSupport::each(
            ProgramaPagoAsignacion::query()
                ->where('programa_pago_linea_id', $linea->id)
                ->when($clavesConservar !== [], fn ($q) => $q->whereNotIn('clave', $clavesConservar))
        );
    }

    private function assertEditable(ProgramaPago $programa): void
    {
        if (! $programa->esEditable()) {
            throw new RuntimeException('El programa está cerrado y no se puede modificar.');
        }
    }
}
