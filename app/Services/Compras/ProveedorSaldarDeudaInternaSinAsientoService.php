<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Saldar deuda de un proveedor con NCI en CONTABILIZADO, sin asiento_id.
 * Una NCI por (empresa, moneda, cotización) para que la aplicación no genere DC.
 * Opcionalmente espeja compra/promov y aplmovp a Anita.
 */
final class ProveedorSaldarDeudaInternaSinAsientoService
{
    public const LEYENDA_NCI = 'NC interna saldado deuda (sin impacto contable)';

    public const LEYENDA_NCI_EXCEL = 'NC interna Excel Zitro BSA (sin impacto contable)';

    public const LEYENDA_NDI = 'ND interna absorbe crédito residual (sin impacto contable)';

    public const LEYENDA_NDI_PLAN = 'ND interna plan deuda Zitro BSA (sin impacto contable)';

    private const LETRA = 'X';

    private const SUCURSAL = 0;

    public function __construct(
        private readonly Proveedor_CuentacorrienteRepositoryInterface $cuentacorrienteRepository,
        private readonly ComprobanteProveedorAnitaSyncService $anitaComprobanteSync,
        private readonly ProveedorCuentacorrienteAplicacionAnitaSyncService $anitaAplicacionSync,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   dry_run: bool,
     *   proveedor_id: int,
     *   codigo: string,
     *   fecha: string,
     *   empresas: list<array<string, mixed>>
     * }
     */
    public function saldar(
        string $codigoProveedor,
        array $empresaIds,
        string $fecha,
        bool $ejecutar,
        int $usuarioId = 1,
    ): array {
        $fecha = substr($fecha, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new RuntimeException('Fecha inválida.');
        }

        $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds))));
        if ($empresaIds === []) {
            throw new RuntimeException('Indique al menos una empresa.');
        }

        $tipoNci = $this->tipoPorAbreviatura('NCI');
        $tipoNdi = $this->tipoPorAbreviatura('NDI');
        $conceptoId = $this->conceptoNoGravadoId();

        $empresas = [];
        foreach ($empresaIds as $empresaId) {
            $empresas[] = $this->saldarEmpresa(
                $codigoProveedor,
                $empresaId,
                $fecha,
                $ejecutar,
                $usuarioId,
                $tipoNci,
                $tipoNdi,
                $conceptoId,
            );
        }

        return [
            'dry_run' => ! $ejecutar,
            'proveedor_id' => (int) ($empresas[0]['proveedor_id'] ?? 0),
            'codigo' => $codigoProveedor,
            'fecha' => $fecha,
            'empresas' => $empresas,
        ];
    }

    /**
     * Aplica NCI solo a las CC de deuda indicadas (p. ej. selección del Excel).
     * No toca créditos residuales ni el resto de la deuda.
     *
     * @param  list<array{cc_id:int, monto?:float|null}>  $deudasSeleccionadas
     * @return array<string, mixed>
     */
    public function aplicarDeudasSeleccionadas(
        string $codigoProveedor,
        int $empresaId,
        array $deudasSeleccionadas,
        string $fecha,
        bool $ejecutar,
        bool $syncAnita = false,
        int $usuarioId = 1,
        string $leyendaPrefijo = self::LEYENDA_NCI_EXCEL,
    ): array {
        $fecha = substr($fecha, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new RuntimeException('Fecha inválida.');
        }
        if ($deudasSeleccionadas === []) {
            throw new RuntimeException('No hay deudas seleccionadas.');
        }

        $proveedor = Proveedor::query()
            ->where('codigo', $codigoProveedor)
            ->paraEmpresa($empresaId)
            ->first();
        if (! $proveedor) {
            throw new RuntimeException("Proveedor {$codigoProveedor} no encontrado para empresa {$empresaId}.");
        }

        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos((int) $proveedor->id, null);
        if ($cuit === '') {
            throw new RuntimeException("Proveedor {$codigoProveedor} sin CUIT (nroinscripcion).");
        }

        $yaHecho = Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('empresa_id', $empresaId)
            ->where('leyenda', 'like', $leyendaPrefijo.'%')
            ->exists();
        if ($yaHecho) {
            throw new RuntimeException(
                "Ya hay NCI ({$leyendaPrefijo}) para {$codigoProveedor} en empresa {$empresaId}."
            );
        }

        $ccIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['cc_id'],
            $deudasSeleccionadas
        )));
        $ccs = Proveedor_Cuentacorriente::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ccIds)
            ->get()
            ->keyBy('id');

        $aplicadoPorCc = Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereIn('proveedor_cuentacorriente_id', $ccIds)
            ->selectRaw('proveedor_cuentacorriente_id, COALESCE(SUM(total), 0) as aplicado')
            ->groupBy('proveedor_cuentacorriente_id')
            ->pluck('aplicado', 'proveedor_cuentacorriente_id');

        $items = [];
        foreach ($deudasSeleccionadas as $row) {
            $ccId = (int) $row['cc_id'];
            $cc = $ccs->get($ccId);
            if (! $cc) {
                throw new RuntimeException("CC {$ccId} no pertenece al proveedor/empresa.");
            }
            $aplicado = (float) ($aplicadoPorCc[$ccId] ?? 0);
            $saldo = round(ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                (float) $cc->total,
                $aplicado
            ), 4);
            if ($saldo < 0.0001) {
                throw new RuntimeException("CC {$ccId} no tiene saldo pendiente.");
            }
            $monto = isset($row['monto']) && $row['monto'] !== null
                ? round(abs((float) $row['monto']), 4)
                : $saldo;
            if ($monto > $saldo + 0.0001) {
                throw new RuntimeException(
                    "CC {$ccId}: monto {$monto} supera saldo {$saldo}."
                );
            }
            if ((float) $cc->total < 0) {
                throw new RuntimeException("CC {$ccId} es crédito, no deuda.");
            }
            $items[] = [
                'id' => $ccId,
                'saldo' => $monto,
                'moneda_id' => (int) $cc->moneda_id,
                'cotizacion' => round((float) $cc->cotizacion, 6),
            ];
        }

        $gruposNci = $this->agruparPorMonedaCotizacion($items);
        $tipoNci = $this->tipoPorAbreviatura('NCI');
        $conceptoId = $this->conceptoNoGravadoId();

        $resumen = [
            'empresa_id' => $empresaId,
            'proveedor_id' => (int) $proveedor->id,
            'proveedor' => (string) $proveedor->nombre,
            'seleccionadas' => count($items),
            'nci_a_crear' => count($gruposNci),
            'nci' => [],
            'totales_nci_por_moneda' => $this->totalesPorMoneda($gruposNci),
            'comprobantes_creados' => [],
            'comprobante_ids' => [],
            'aplicaciones_creadas' => 0,
            'aplicacion_ids' => [],
            'anita_sync' => $syncAnita,
            'anita_ok' => null,
            'anita_error' => null,
        ];
        foreach ($gruposNci as $grupo) {
            $resumen['nci'][] = [
                'moneda_id' => $grupo['moneda_id'],
                'cotizacion' => $grupo['cotizacion'],
                'total' => $grupo['total'],
                'cuotas' => count($grupo['items']),
            ];
        }

        if (! $ejecutar) {
            return $resumen;
        }

        $resumen = DB::transaction(function () use (
            $resumen,
            $proveedor,
            $empresaId,
            $fecha,
            $usuarioId,
            $cuit,
            $tipoNci,
            $conceptoId,
            $gruposNci,
            $leyendaPrefijo,
        ) {
            $aplicadas = 0;
            $appIds = [];

            foreach ($gruposNci as $grupo) {
                $cp = $this->crearComprobanteInterno(
                    $proveedor,
                    $empresaId,
                    $fecha,
                    $usuarioId,
                    $cuit,
                    $tipoNci,
                    $conceptoId,
                    (float) $grupo['total'],
                    (int) $grupo['moneda_id'],
                    (float) $grupo['cotizacion'],
                    $leyendaPrefijo.' · mon '.$grupo['moneda_id'].' cot '.$grupo['cotizacion'],
                    -1,
                );
                $ccCreditoId = (int) $cp['cc_id'];
                $resumen['comprobantes_creados'][] = $cp['etiqueta'];
                $resumen['comprobante_ids'][] = (int) $cp['comprobante_id'];

                $lineas = [];
                foreach ($grupo['items'] as $item) {
                    $lineas[] = [
                        'credito_id' => $ccCreditoId,
                        'deuda_id' => (int) $item['id'],
                        'monto' => (float) $item['saldo'],
                    ];
                }
                $persistidas = $this->persistirAplicacionesConIds($lineas, $fecha);
                $aplicadas += $persistidas['pares'];
                foreach ($persistidas['ids'] as $id) {
                    $appIds[] = $id;
                }
            }

            $resumen['aplicaciones_creadas'] = $aplicadas;
            $resumen['aplicacion_ids'] = $appIds;

            return $resumen;
        });

        if ($syncAnita) {
            try {
                foreach ($resumen['comprobante_ids'] as $cpId) {
                    $comp = Comprobante_Proveedor::query()->with([
                        'comprobante_proveedor_conceptos.concepto_ivacompras',
                        'comprobante_proveedor_cuotas',
                        'empresas', 'proveedores.condicionivas', 'proveedores.provincias',
                        'condicionpagos', 'tipotransaccion_compras', 'monedas',
                    ])->findOrFail($cpId);
                    $this->anitaComprobanteSync->syncCreate($comp);
                }
                $this->anitaAplicacionSync->syncPorIdsAplicacion($resumen['aplicacion_ids']);
                $resumen['anita_ok'] = true;
            } catch (\Throwable $e) {
                $resumen['anita_ok'] = false;
                $resumen['anita_error'] = $e->getMessage();
                throw new RuntimeException(
                    'ERP OK pero falló sync Anita: '.$e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $resumen;
    }

    /**
     * Crea una NDI CONTABILIZADA sin asiento, con N cuotas en moneda/cotización dadas
     * y una fila de CC por cuota. Opcionalmente sincroniza compra/promov a Anita.
     *
     * @param  list<array{numero_cuota:int, monto:float, fechavencimiento:string}>  $cuotas
     * @return array{
     *   comprobante_id:int,
     *   etiqueta:string,
     *   total:float,
     *   cuotas:int,
     *   cc_ids:list<int>,
     *   anita_nro_interno:?int,
     *   anita_ok:?bool,
     *   anita_error:?string
     * }
     */
    public function crearDebitoInternoPlanCuotas(
        string $codigoProveedor,
        int $empresaId,
        array $cuotas,
        string $fecha,
        int $monedaId,
        float $cotizacion,
        bool $syncAnita = false,
        int $usuarioId = 1,
        string $leyenda = self::LEYENDA_NDI_PLAN,
    ): array {
        $fecha = substr($fecha, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new RuntimeException('Fecha inválida.');
        }
        if ($cuotas === []) {
            throw new RuntimeException('Indique al menos una cuota.');
        }
        if ($monedaId <= 0 || $cotizacion <= 0) {
            throw new RuntimeException('Moneda/cotización inválida.');
        }

        $proveedor = Proveedor::query()
            ->where('codigo', $codigoProveedor)
            ->paraEmpresa($empresaId)
            ->first();
        if (! $proveedor) {
            throw new RuntimeException("Proveedor {$codigoProveedor} no encontrado para empresa {$empresaId}.");
        }
        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos((int) $proveedor->id, null);
        if ($cuit === '') {
            throw new RuntimeException("Proveedor {$codigoProveedor} sin CUIT.");
        }

        $ya = Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('empresa_id', $empresaId)
            ->where('leyenda', 'like', $leyenda.'%')
            ->exists();
        if ($ya) {
            throw new RuntimeException("Ya existe NDI plan ({$leyenda}) en empresa {$empresaId}.");
        }

        $normalizadas = [];
        $total = 0.0;
        $ultimoVto = $fecha;
        foreach ($cuotas as $c) {
            $nro = (int) ($c['numero_cuota'] ?? 0);
            $monto = round(abs((float) ($c['monto'] ?? 0)), 4);
            $vto = substr((string) ($c['fechavencimiento'] ?? ''), 0, 10);
            if ($nro <= 0 || $monto < 0.0001 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $vto)) {
                throw new RuntimeException('Cuota inválida: '.json_encode($c, JSON_UNESCAPED_UNICODE));
            }
            $normalizadas[] = [
                'numero_cuota' => $nro,
                'monto' => $monto,
                'fechavencimiento' => $vto,
            ];
            $total = round($total + $monto, 4);
            $ultimoVto = $vto;
        }
        usort($normalizadas, static fn (array $a, array $b): int => $a['numero_cuota'] <=> $b['numero_cuota']);

        $tipoNdi = $this->tipoPorAbreviatura('NDI');
        $conceptoId = $this->conceptoNoGravadoId();

        $creado = DB::transaction(function () use (
            $proveedor,
            $empresaId,
            $fecha,
            $usuarioId,
            $cuit,
            $tipoNdi,
            $conceptoId,
            $total,
            $monedaId,
            $cotizacion,
            $leyenda,
            $normalizadas,
            $ultimoVto,
        ) {
            $numero = $this->proximoNumero((int) $tipoNdi->id, $cuit);
            $datosCp = [
                'empresa_id' => $empresaId,
                'proveedor_id' => (int) $proveedor->id,
                'identificacion_proveedor_cuit' => $cuit,
                'tipotransaccion_compra_id' => (int) $tipoNdi->id,
                'letra' => self::LETRA,
                'sucursal' => self::SUCURSAL,
                'numerocomprobante' => $numero,
                'fechacomprobante' => $fecha,
                'fechaiva' => $fecha,
                'fechavencimiento' => $ultimoVto,
                'subtotal' => $total,
                'total' => $total,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'es_fce' => false,
                'leyenda' => mb_substr($leyenda.' · '.count($normalizadas).' cuotas DOL', 0, 255),
                'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
                'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
                'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
                'asiento_id' => null,
                'pararevisar' => false,
                'creousuario_id' => $usuarioId,
            ];
            if (Schema::hasColumn('comprobante_proveedor', 'provincia_destino_id')) {
                $datosCp['provincia_destino_id'] = (int) ($proveedor->provincia_id ?: 0) ?: null;
            }

            $comprobante = Comprobante_Proveedor::query()->create($datosCp);

            Comprobante_Proveedor_Concepto::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'concepto_ivacompra_id' => $conceptoId,
                'orden' => 1,
                'monto' => $total,
            ]);

            $ccIds = [];
            foreach ($normalizadas as $c) {
                $cuota = Comprobante_Proveedor_Cuota::query()->create([
                    'comprobante_proveedor_id' => $comprobante->id,
                    'numero_cuota' => $c['numero_cuota'],
                    'fechavencimiento' => $c['fechavencimiento'],
                    'monto' => $c['monto'],
                    'moneda_id' => $monedaId,
                    'cotizacion' => $cotizacion,
                    'formapago_id' => 1,
                    'total_pagado' => 0,
                ]);
                $cc = Proveedor_Cuentacorriente::query()->create([
                    'fecha' => $fecha,
                    'fechavencimiento' => $c['fechavencimiento'],
                    'proveedor_id' => (int) $proveedor->id,
                    'total' => $c['monto'],
                    'moneda_id' => $monedaId,
                    'cotizacion' => $cotizacion,
                    'empresa_id' => $empresaId,
                    'comprobante_proveedor_id' => $comprobante->id,
                    'comprobante_proveedor_cuota_id' => $cuota->id,
                    'leyenda' => mb_substr($leyenda.' cuota '.$c['numero_cuota'], 0, 255),
                ]);
                $cuota->proveedor_cuentacorriente_id = $cc->id;
                $cuota->save();
                $ccIds[] = (int) $cc->id;
            }

            Comprobante_Proveedor_Estado::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'fecha' => $fecha,
                'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
                'usuario_id' => $usuarioId,
                'observacion' => 'NDI plan multi-cuota sin asiento',
            ]);

            $etiqueta = sprintf(
                '%s %s-%04d-%d',
                $tipoNdi->abreviatura,
                self::LETRA,
                self::SUCURSAL,
                $numero
            );

            return [
                'comprobante_id' => (int) $comprobante->id,
                'etiqueta' => $etiqueta,
                'total' => $total,
                'cuotas' => count($normalizadas),
                'cc_ids' => $ccIds,
            ];
        });

        $creado['anita_nro_interno'] = null;
        $creado['anita_ok'] = null;
        $creado['anita_error'] = null;

        if ($syncAnita) {
            try {
                $comp = Comprobante_Proveedor::query()->with([
                    'comprobante_proveedor_conceptos.concepto_ivacompras',
                    'comprobante_proveedor_cuotas',
                    'empresas', 'proveedores.condicionivas', 'proveedores.provincias',
                    'condicionpagos', 'tipotransaccion_compras', 'monedas',
                ])->findOrFail($creado['comprobante_id']);
                $this->anitaComprobanteSync->syncCreate($comp);
                $creado['anita_nro_interno'] = (int) ($comp->fresh()->anita_nro_interno ?? 0) ?: null;
                $creado['anita_ok'] = true;
            } catch (\Throwable $e) {
                $creado['anita_ok'] = false;
                $creado['anita_error'] = $e->getMessage();
                throw new RuntimeException(
                    'NDI ERP OK pero falló sync Anita: '.$e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $creado;
    }

    /**
     * @return array<string, mixed>
     */
    private function saldarEmpresa(
        string $codigoProveedor,
        int $empresaId,
        string $fecha,
        bool $ejecutar,
        int $usuarioId,
        Tipotransaccion_Compra $tipoNci,
        Tipotransaccion_Compra $tipoNdi,
        int $conceptoId,
    ): array {
        $proveedor = Proveedor::query()
            ->where('codigo', $codigoProveedor)
            ->paraEmpresa($empresaId)
            ->first();
        if (! $proveedor) {
            throw new RuntimeException("Proveedor {$codigoProveedor} no encontrado para empresa {$empresaId}.");
        }

        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos((int) $proveedor->id, null);
        if ($cuit === '') {
            throw new RuntimeException("Proveedor {$codigoProveedor} sin CUIT (nroinscripcion).");
        }

        $yaHecho = Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('empresa_id', $empresaId)
            ->where('tipotransaccion_compra_id', $tipoNci->id)
            ->where('leyenda', 'like', self::LEYENDA_NCI.'%')
            ->exists();
        if ($yaHecho) {
            throw new RuntimeException(
                "Ya hay NCI de saldado para {$codigoProveedor} en empresa {$empresaId}. No se vuelve a ejecutar."
            );
        }

        $deudas = $this->cuentacorrienteRepository
            ->listarPendientesAplicacion((int) $proveedor->id, 'deuda', $empresaId);
        $creditos = $this->cuentacorrienteRepository
            ->listarPendientesAplicacion((int) $proveedor->id, 'credito', $empresaId);

        $planCreditosSobreDeuda = $this->planAplicarCreditosSobreDeuda($deudas, $creditos);
        $deudasRestantes = $this->simularSaldosTrasPlan($deudas, $planCreditosSobreDeuda, 'deuda');
        $creditosRestantes = $this->simularSaldosTrasPlan($creditos, $planCreditosSobreDeuda, 'credito');

        $gruposNci = $this->agruparPorMonedaCotizacion($deudasRestantes);
        $gruposNdi = $this->agruparPorMonedaCotizacion($creditosRestantes);

        $resumen = [
            'empresa_id' => $empresaId,
            'proveedor_id' => (int) $proveedor->id,
            'proveedor' => (string) $proveedor->nombre,
            'deudas_abiertas' => $deudas->count(),
            'creditos_abiertos' => $creditos->count(),
            'aplicaciones_credito_existente' => count($planCreditosSobreDeuda),
            'nci_a_crear' => count($gruposNci),
            'ndi_a_crear' => count($gruposNdi),
            'nci' => [],
            'ndi' => [],
            'totales_nci_por_moneda' => $this->totalesPorMoneda($gruposNci),
            'totales_ndi_por_moneda' => $this->totalesPorMoneda($gruposNdi),
            'comprobantes_creados' => [],
            'aplicaciones_creadas' => 0,
        ];

        foreach ($gruposNci as $grupo) {
            $resumen['nci'][] = [
                'moneda_id' => $grupo['moneda_id'],
                'cotizacion' => $grupo['cotizacion'],
                'total' => $grupo['total'],
                'cuotas' => count($grupo['items']),
            ];
        }
        foreach ($gruposNdi as $grupo) {
            $resumen['ndi'][] = [
                'moneda_id' => $grupo['moneda_id'],
                'cotizacion' => $grupo['cotizacion'],
                'total' => $grupo['total'],
                'creditos' => count($grupo['items']),
            ];
        }

        if (! $ejecutar) {
            return $resumen;
        }

        return DB::transaction(function () use (
            $resumen,
            $proveedor,
            $empresaId,
            $fecha,
            $usuarioId,
            $cuit,
            $tipoNci,
            $tipoNdi,
            $conceptoId,
            $planCreditosSobreDeuda,
            $gruposNci,
            $gruposNdi,
        ) {
            $aplicadas = 0;

            $aplicadas += $this->persistirAplicacionesConIds($planCreditosSobreDeuda, $fecha)['pares'];

            foreach ($gruposNci as $grupo) {
                $cp = $this->crearComprobanteInterno(
                    $proveedor,
                    $empresaId,
                    $fecha,
                    $usuarioId,
                    $cuit,
                    $tipoNci,
                    $conceptoId,
                    (float) $grupo['total'],
                    (int) $grupo['moneda_id'],
                    (float) $grupo['cotizacion'],
                    self::LEYENDA_NCI.' · mon '.$grupo['moneda_id'].' cot '.$grupo['cotizacion'],
                    -1,
                );
                $ccCreditoId = (int) $cp['cc_id'];
                $resumen['comprobantes_creados'][] = $cp['etiqueta'];

                $lineas = [];
                foreach ($grupo['items'] as $item) {
                    $lineas[] = [
                        'credito_id' => $ccCreditoId,
                        'deuda_id' => (int) $item['id'],
                        'monto' => (float) $item['saldo'],
                    ];
                }
                $aplicadas += $this->persistirAplicacionesConIds($lineas, $fecha)['pares'];
            }

            foreach ($gruposNdi as $grupo) {
                $cp = $this->crearComprobanteInterno(
                    $proveedor,
                    $empresaId,
                    $fecha,
                    $usuarioId,
                    $cuit,
                    $tipoNdi,
                    $conceptoId,
                    (float) $grupo['total'],
                    (int) $grupo['moneda_id'],
                    (float) $grupo['cotizacion'],
                    self::LEYENDA_NDI.' · mon '.$grupo['moneda_id'].' cot '.$grupo['cotizacion'],
                    1,
                );
                $ccDeudaId = (int) $cp['cc_id'];
                $resumen['comprobantes_creados'][] = $cp['etiqueta'];

                $lineas = [];
                foreach ($grupo['items'] as $item) {
                    $lineas[] = [
                        'credito_id' => (int) $item['id'],
                        'deuda_id' => $ccDeudaId,
                        'monto' => (float) $item['saldo'],
                    ];
                }
                $aplicadas += $this->persistirAplicacionesConIds($lineas, $fecha)['pares'];
            }

            $resumen['aplicaciones_creadas'] = $aplicadas;

            $deudaFinal = $this->cuentacorrienteRepository
                ->listarPendientesAplicacion((int) $proveedor->id, 'deuda', $empresaId);
            $creditoFinal = $this->cuentacorrienteRepository
                ->listarPendientesAplicacion((int) $proveedor->id, 'credito', $empresaId);
            $resumen['deudas_restantes'] = $deudaFinal->count();
            $resumen['creditos_restantes'] = $creditoFinal->count();
            $resumen['total_deuda_final'] = $this->cuentacorrienteRepository
                ->calcularTotalDeudaProveedor((int) $proveedor->id, ['empresa_id' => $empresaId]);

            if ($deudaFinal->count() > 0 || $creditoFinal->count() > 0) {
                throw new RuntimeException(
                    "Tras saldar, empresa {$empresaId} quedó con "
                    .$deudaFinal->count().' deudas y '.$creditoFinal->count().' créditos pendientes.'
                );
            }

            return $resumen;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente>  $deudas
     * @param  \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente>  $creditos
     * @return list<array{credito_id:int,deuda_id:int,monto:float}>
     */
    private function planAplicarCreditosSobreDeuda($deudas, $creditos): array
    {
        $disponibles = [];
        foreach ($deudas as $d) {
            $saldo = $this->saldoAbs($d);
            if ($saldo < 0.0001) {
                continue;
            }
            $key = $this->claveMonCot($d);
            $disponibles[$key][] = [
                'id' => (int) $d->id,
                'saldo' => $saldo,
            ];
        }

        $lineas = [];
        foreach ($creditos as $c) {
            $saldoCredito = $this->saldoAbs($c);
            if ($saldoCredito < 0.0001) {
                continue;
            }
            $key = $this->claveMonCot($c);
            if (! isset($disponibles[$key])) {
                continue;
            }
            foreach ($disponibles[$key] as $i => &$deudaDisp) {
                if ($saldoCredito < 0.0001) {
                    break;
                }
                if ($deudaDisp['saldo'] < 0.0001) {
                    continue;
                }
                $monto = round(min($saldoCredito, $deudaDisp['saldo']), 4);
                if ($monto < 0.0001) {
                    continue;
                }
                $lineas[] = [
                    'credito_id' => (int) $c->id,
                    'deuda_id' => (int) $deudaDisp['id'],
                    'monto' => $monto,
                ];
                $deudaDisp['saldo'] = round($deudaDisp['saldo'] - $monto, 4);
                $saldoCredito = round($saldoCredito - $monto, 4);
                $disponibles[$key][$i] = $deudaDisp;
            }
            unset($deudaDisp);
        }

        return $lineas;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente>  $filas
     * @param  list<array{credito_id:int,deuda_id:int,monto:float}>  $plan
     * @return list<array{id:int,saldo:float,moneda_id:int,cotizacion:float}>
     */
    private function simularSaldosTrasPlan($filas, array $plan, string $lado): array
    {
        $saldos = [];
        foreach ($filas as $f) {
            $saldos[(int) $f->id] = [
                'id' => (int) $f->id,
                'saldo' => $this->saldoAbs($f),
                'moneda_id' => (int) $f->moneda_id,
                'cotizacion' => round((float) $f->cotizacion, 6),
            ];
        }
        foreach ($plan as $linea) {
            $id = $lado === 'deuda' ? (int) $linea['deuda_id'] : (int) $linea['credito_id'];
            if (! isset($saldos[$id])) {
                continue;
            }
            $saldos[$id]['saldo'] = round($saldos[$id]['saldo'] - (float) $linea['monto'], 4);
        }

        return array_values(array_filter(
            $saldos,
            static fn (array $row): bool => $row['saldo'] >= 0.0001
        ));
    }

    /**
     * @param  list<array{id:int,saldo:float,moneda_id:int,cotizacion:float}>  $items
     * @return list<array{moneda_id:int,cotizacion:float,total:float,items:list<array{id:int,saldo:float,moneda_id:int,cotizacion:float}>}>
     */
    private function agruparPorMonedaCotizacion(array $items): array
    {
        $grupos = [];
        foreach ($items as $item) {
            $key = $item['moneda_id'].'|'.$item['cotizacion'];
            if (! isset($grupos[$key])) {
                $grupos[$key] = [
                    'moneda_id' => (int) $item['moneda_id'],
                    'cotizacion' => (float) $item['cotizacion'],
                    'total' => 0.0,
                    'items' => [],
                ];
            }
            $grupos[$key]['items'][] = $item;
            $grupos[$key]['total'] = round($grupos[$key]['total'] + (float) $item['saldo'], 4);
        }

        return array_values($grupos);
    }

    /**
     * @param  list<array{moneda_id:int,cotizacion:float,total:float,items:list<mixed>}>  $grupos
     * @return array<int, float>
     */
    private function totalesPorMoneda(array $grupos): array
    {
        $out = [];
        foreach ($grupos as $g) {
            $mid = (int) $g['moneda_id'];
            $out[$mid] = round(($out[$mid] ?? 0) + (float) $g['total'], 4);
        }

        return $out;
    }

    /**
     * @return array{cc_id:int,etiqueta:string,comprobante_id:int}
     */
    private function crearComprobanteInterno(
        Proveedor $proveedor,
        int $empresaId,
        string $fecha,
        int $usuarioId,
        string $cuit,
        Tipotransaccion_Compra $tipo,
        int $conceptoId,
        float $total,
        int $monedaId,
        float $cotizacion,
        string $leyenda,
        int $signoCc,
    ): array {
        $total = round(abs($total), 4);
        if ($total < 0.0001) {
            throw new RuntimeException('Total interno en cero.');
        }

        $numero = $this->proximoNumero((int) $tipo->id, $cuit);

        $datosCp = [
            'empresa_id' => $empresaId,
            'proveedor_id' => (int) $proveedor->id,
            'identificacion_proveedor_cuit' => $cuit,
            'tipotransaccion_compra_id' => (int) $tipo->id,
            'letra' => self::LETRA,
            'sucursal' => self::SUCURSAL,
            'numerocomprobante' => $numero,
            'fechacomprobante' => $fecha,
            'fechaiva' => $fecha,
            'fechavencimiento' => $fecha,
            'subtotal' => $total,
            'total' => $total,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'es_fce' => false,
            'leyenda' => mb_substr($leyenda, 0, 255),
            'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
            'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
            'asiento_id' => null,
            'pararevisar' => false,
            'creousuario_id' => $usuarioId,
        ];
        if (Schema::hasColumn('comprobante_proveedor', 'provincia_destino_id')) {
            $datosCp['provincia_destino_id'] = (int) ($proveedor->provincia_id ?: 0) ?: null;
        }

        $comprobante = Comprobante_Proveedor::query()->create($datosCp);

        Comprobante_Proveedor_Concepto::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'concepto_ivacompra_id' => $conceptoId,
            'orden' => 1,
            'monto' => $total,
        ]);

        $cuota = Comprobante_Proveedor_Cuota::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'numero_cuota' => 1,
            'fechavencimiento' => $fecha,
            'monto' => $total,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'formapago_id' => 1,
            'total_pagado' => 0,
        ]);

        $cc = Proveedor_Cuentacorriente::query()->create([
            'fecha' => $fecha,
            'fechavencimiento' => $fecha,
            'proveedor_id' => (int) $proveedor->id,
            'total' => round($total * $signoCc, 4),
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'empresa_id' => $empresaId,
            'comprobante_proveedor_id' => $comprobante->id,
            'comprobante_proveedor_cuota_id' => $cuota->id,
            'leyenda' => mb_substr($leyenda, 0, 255),
        ]);

        $cuota->proveedor_cuentacorriente_id = $cc->id;
        $cuota->save();

        Comprobante_Proveedor_Estado::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'fecha' => $fecha,
            'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
            'usuario_id' => $usuarioId,
            'observacion' => 'Saldado interno sin asiento ni sync Anita',
        ]);

        $etiqueta = sprintf(
            '%s %s-%04d-%d',
            $tipo->abreviatura,
            self::LETRA,
            self::SUCURSAL,
            $numero
        );

        return [
            'cc_id' => (int) $cc->id,
            'etiqueta' => $etiqueta,
            'comprobante_id' => (int) $comprobante->id,
        ];
    }

    /**
     * Inserta pares de aplicación sin asiento. Devuelve ids del lado deuda (total < 0).
     *
     * @param  list<array{credito_id:int,deuda_id:int,monto:float}>  $lineas
     * @return array{pares:int, ids:list<int>}
     */
    private function persistirAplicacionesConIds(array $lineas, string $fecha): array
    {
        if ($lineas === []) {
            return ['pares' => 0, 'ids' => []];
        }

        $ids = [];
        foreach ($lineas as $linea) {
            $ids[] = (int) $linea['credito_id'];
            $ids[] = (int) $linea['deuda_id'];
        }
        $ids = array_values(array_unique(array_filter($ids)));

        $ccs = Proveedor_Cuentacorriente::query()
            ->with([
                'comprobante_proveedores.tipotransaccion_compras',
                'pagoproveedores',
            ])
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $aplicadoPorCc = Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereIn('proveedor_cuentacorriente_id', $ids)
            ->selectRaw('proveedor_cuentacorriente_id, COALESCE(SUM(total), 0) as aplicado')
            ->groupBy('proveedor_cuentacorriente_id')
            ->pluck('aplicado', 'proveedor_cuentacorriente_id');

        $saldoRestante = [];
        foreach ($ccs as $cc) {
            $aplicado = (float) ($aplicadoPorCc[$cc->id] ?? 0);
            $saldoRestante[(int) $cc->id] = round(
                ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $cc->total, $aplicado),
                4
            );
        }

        $creadas = 0;
        $ids = [];
        foreach ($lineas as $linea) {
            $creditoId = (int) $linea['credito_id'];
            $deudaId = (int) $linea['deuda_id'];
            $credito = $ccs->get($creditoId);
            $deuda = $ccs->get($deudaId);
            if (! $credito || ! $deuda) {
                throw new RuntimeException('CC no encontrada al aplicar saldado interno.');
            }
            if ((int) $credito->empresa_id !== (int) $deuda->empresa_id) {
                throw new RuntimeException('Empresa distinta al aplicar.');
            }
            if ((int) $credito->moneda_id !== (int) $deuda->moneda_id
                || abs((float) $credito->cotizacion - (float) $deuda->cotizacion) > 0.000001) {
                throw new RuntimeException(
                    'Moneda/cotización distinta al aplicar (evitaría DC). '
                    .'crédito '.$credito->id.' vs deuda '.$deuda->id
                );
            }

            $monto = round(abs((float) $linea['monto']), 4);
            if ($monto < 0.0001) {
                continue;
            }
            if (($saldoRestante[$creditoId] ?? 0) + 0.00005 < $monto
                || ($saldoRestante[$deudaId] ?? 0) + 0.00005 < $monto) {
                throw new RuntimeException(
                    "Saldo insuficiente al aplicar crédito {$creditoId} vs deuda {$deudaId} "
                    ."(monto {$monto}, saldo crédito ".($saldoRestante[$creditoId] ?? 0)
                    .', saldo deuda '.($saldoRestante[$deudaId] ?? 0).').'
                );
            }

            $etiquetaCredito = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
                $credito,
                ProveedorCuentacorrienteAplicacionFilaSupport::tipo($credito, 'credito')
            );
            $etiquetaDeuda = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
                $deuda,
                ProveedorCuentacorrienteAplicacionFilaSupport::tipo($deuda, 'deuda')
            );

            $aplDeuda = Proveedor_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $fecha,
                'proveedor_cuentacorriente_id' => $deuda->id,
                'total' => -$monto,
                'moneda_id' => $deuda->moneda_id,
                'cotizacion' => $deuda->cotizacion,
                'diferencia_cambio' => 0,
                'asiento_id' => null,
                'comprobanteaplicado' => $etiquetaCredito,
                'comprobante_proveedor_aplicado_id' => $credito->comprobante_proveedor_id,
                'empresa_id' => $deuda->empresa_id,
                'proveedor_cuentacorriente_aplicado_id' => $credito->id,
            ]);
            Proveedor_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $fecha,
                'proveedor_cuentacorriente_id' => $credito->id,
                'total' => $monto,
                'moneda_id' => $credito->moneda_id,
                'cotizacion' => $credito->cotizacion,
                'diferencia_cambio' => 0,
                'asiento_id' => null,
                'comprobanteaplicado' => $etiquetaDeuda,
                'comprobante_proveedor_aplicado_id' => $deuda->comprobante_proveedor_id,
                'empresa_id' => $credito->empresa_id,
                'proveedor_cuentacorriente_aplicado_id' => $deuda->id,
            ]);

            $saldoRestante[$creditoId] = round($saldoRestante[$creditoId] - $monto, 4);
            $saldoRestante[$deudaId] = round($saldoRestante[$deudaId] - $monto, 4);
            $ids[] = (int) $aplDeuda->id;
            $creadas++;
        }

        return ['pares' => $creadas, 'ids' => $ids];
    }

    private function saldoAbs(Proveedor_Cuentacorriente $fila): float
    {
        $aplicado = isset($fila->aplicado) ? (float) $fila->aplicado : null;

        return round(ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
            (float) $fila->total,
            $aplicado
        ), 4);
    }

    private function claveMonCot(Proveedor_Cuentacorriente $fila): string
    {
        return (int) $fila->moneda_id.'|'.round((float) $fila->cotizacion, 6);
    }

    private function tipoPorAbreviatura(string $abrev): Tipotransaccion_Compra
    {
        $tipo = Tipotransaccion_Compra::query()
            ->where('abreviatura', $abrev)
            ->first();
        if (! $tipo) {
            throw new RuntimeException("No existe tipotransaccion_compra {$abrev}.");
        }

        return $tipo;
    }

    private function conceptoNoGravadoId(): int
    {
        $id = (int) (Concepto_Ivacompra::query()
            ->where('codigo', '1')
            ->where('nombre', 'like', 'No gravado%')
            ->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) (Concepto_Ivacompra::query()->whereKey(2)->value('id') ?? 0);
        }
        if ($id <= 0) {
            throw new RuntimeException('No se encontró concepto IVA compra "No gravado".');
        }

        return $id;
    }

    /**
     * Numeración de todo el grupo: en Anita aplmovp y promov identifican el comprobante por
     * proveedor + tipo + letra + sucursal + número, sin empresa.
     */
    private function proximoNumero(int $tipoId, string $cuit): int
    {
        $max = (int) Comprobante_Proveedor::query()
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('letra', self::LETRA)
            ->where('sucursal', self::SUCURSAL)
            ->max('numerocomprobante');

        $numero = max(1, $max + 1);
        while (Comprobante_Proveedor::query()
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('letra', self::LETRA)
            ->where('sucursal', self::SUCURSAL)
            ->where('numerocomprobante', $numero)
            ->where('identificacion_proveedor_cuit', $cuit)
            ->exists()) {
            $numero++;
        }

        return $numero;
    }
}
