<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Retencionganancia;
use App\Models\Seguridad\Usuario;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\PagosSabanaAnitaBridgeReader;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;

/**
 * Importa retenciones de Ganancias (retmov Anita) a pagoproveedor_retencion
 * para alimentar el acumulado mensual RG 830 en ERP.
 */
class RetencionGananciasImportarAcumuladoAnitaService
{
    /** @var array<string, int|null> */
    private array $cacheProveedor = [];

    /** @var array<string, int|null> */
    private array $cacheRegimen = [];

    /** @var array<string, int|null> */
    private array $cacheTipoCaja = [];

    /** @var array<int, float> anita_nro_interno => ratio retenible ganancias */
    private array $cacheRatioNeto = [];

    public function __construct(
        private readonly PagosSabanaAnitaBridgeReader $reader = new PagosSabanaAnitaBridgeReader,
        private readonly ApiAnita $api = new ApiAnita,
    ) {
    }

    /**
     * @return array{
     *   en_anita: int,
     *   creados: int,
     *   actualizados: int,
     *   omitidos: int,
     *   pagos_creados: int,
     *   sin_proveedor: int,
     *   errores: list<string>,
     *   errores_bridge: list<string>
     * }
     */
    public function importar(
        string $desdeIso,
        string $hastaIso,
        bool $dryRun = true,
        ?int $usuarioId = null,
        array $empresasAnita = [1, 2, 3],
    ): array {
        $stats = [
            'en_anita' => 0,
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'pagos_creados' => 0,
            'sin_proveedor' => 0,
            'errores' => [],
            'errores_bridge' => [],
        ];

        $uid = $usuarioId ?? (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        $desde = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso);
        $hasta = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso);
        if ($desde <= 0 || $hasta <= 0) {
            $stats['errores'][] = 'Rango de fechas inválido';

            return $stats;
        }

        $filas = $this->listarRetmov($desde, $hasta, $empresasAnita, $stats['errores_bridge']);
        $stats['en_anita'] = count($filas);

        $auxpagPorOp = $this->indexarAuxpagFacturas($empresasAnita, $desde, $hasta, $stats['errores_bridge']);

        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $empresaId = (int) ($fila['retv_empresa'] ?? 0);
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($fila['retv_tipo'] ?? 'OPP'));
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($fila['retv_letra'] ?? 'A'));
            $sucursal = (int) ($fila['retv_sucursal'] ?? 0);
            $numero = (int) ($fila['retv_nro'] ?? 0);
            $importe = round(abs((float) ($fila['retv_retencion'] ?? 0)), 2);
            $pagoActual = round(abs((float) ($fila['retv_pago_actual'] ?? 0)), 2);
            $nroCert = trim((string) ($fila['retv_nro_retencion'] ?? ''));
            $codigoRet = trim((string) ($fila['retv_codigo_ret'] ?? ''));
            $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($fila['retv_fecha'] ?? '');
            $provCodigo = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita(
                (string) ($fila['retv_proveedor'] ?? '')
            );

            if ($empresaId <= 0 || $numero <= 0 || $importe <= 0 || $fecha === '') {
                $stats['omitidos']++;

                continue;
            }

            $proveedorId = $this->resolverProveedorId($provCodigo);
            if (! $proveedorId) {
                $stats['sin_proveedor']++;
                if (count($stats['errores']) < 25) {
                    $stats['errores'][] = "Proveedor {$provCodigo} ausente en ERP (OPP {$sucursal}-{$numero})";
                }

                continue;
            }

            $regimenId = $this->resolverRegimenId($codigoRet);
            $claveOp = $empresaId.'|'.$tipo.'|'.$numero;
            $neto = $this->resolverNetoPago(
                $pagoActual,
                $auxpagPorOp[$claveOp] ?? [],
            );

            if ($dryRun) {
                $stats['creados']++;

                continue;
            }

            try {
                $resultado = DB::transaction(function () use (
                    $empresaId, $tipo, $letra, $sucursal, $numero, $proveedorId, $fecha,
                    $pagoActual, $importe, $neto, $nroCert, $regimenId, $codigoRet, $uid, $fila
                ) {
                    $pago = $this->asegurarPagoproveedor(
                        $empresaId, $tipo, $letra, $sucursal, $numero, $proveedorId, $fecha, $pagoActual, $uid
                    );
                    $pagoCreado = $pago['_creado'] ?? false;
                    /** @var Pagoproveedor $pagoModel */
                    $pagoModel = $pago['model'];

                    $existente = Pagoproveedor_Retencion::query()
                        ->where('pagoproveedor_id', $pagoModel->id)
                        ->where('tiporetencion', Pagoproveedor_Retencion::TIPO_GANANCIAS)
                        ->when($nroCert !== '', fn ($q) => $q->where('nro_certificado', $nroCert))
                        ->when($nroCert === '' && $regimenId, fn ($q) => $q->where('retencionganancia_id', $regimenId))
                        ->first();

                    $payload = [
                        'tiporetencion' => Pagoproveedor_Retencion::TIPO_GANANCIAS,
                        'retencionganancia_id' => $regimenId,
                        'codigo_regimen' => (string) (Retencionganancia::query()->whereKey($regimenId)->value('regimen') ?? ''),
                        'codigo_retencion' => $codigoRet,
                        'base_calculo' => $neto,
                        'alicuota' => 0,
                        'importe' => $importe,
                        'nro_certificado' => $nroCert !== '' ? $nroCert : null,
                        'moneda_id' => (int) ($pagoModel->moneda_id ?: 1),
                        'cotizacion' => (float) ($pagoModel->cotizacion ?: 1),
                        'motivo' => 'ok',
                        'detalle_calculo' => [
                            'origen' => 'anita_retmov',
                            'neto_pago' => $neto,
                            'pago_actual_anita' => $pagoActual,
                            'retv_codigo_ret' => $codigoRet,
                            'retv_nro_retencion' => $nroCert,
                            'retv_fecha' => (string) ($fila['retv_fecha'] ?? ''),
                            'forma_calculo' => 'S',
                            'regimen_id' => $regimenId,
                            'codigo' => $codigoRet,
                        ],
                    ];

                    if ($existente) {
                        $existente->fill($payload);
                        $existente->save();

                        return ['accion' => 'actualizado', 'pago_creado' => $pagoCreado];
                    }

                    Pagoproveedor_Retencion::query()->create(array_merge($payload, [
                        'pagoproveedor_id' => $pagoModel->id,
                    ]));

                    return ['accion' => 'creado', 'pago_creado' => $pagoCreado];
                });

                if (! empty($resultado['pago_creado'])) {
                    $stats['pagos_creados']++;
                }
                if (($resultado['accion'] ?? '') === 'actualizado') {
                    $stats['actualizados']++;
                } else {
                    $stats['creados']++;
                }
            } catch (\Throwable $e) {
                $stats['errores'][] = "{$tipo} {$sucursal}-{$numero} cert {$nroCert}: ".$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @param  list<int>  $empresas
     * @param  list<string>  $errores
     * @return list<array<string, mixed>>
     */
    private function listarRetmov(int $desde, int $hasta, array $empresas, array &$errores): array
    {
        $empresas = array_values(array_filter(array_map('intval', $empresas)));
        $in = $empresas !== [] ? implode(',', $empresas) : '1,2,3';
        $campos = 'retv_proveedor,retv_tipo,retv_letra,retv_sucursal,retv_nro,retv_fecha,'
            .'retv_codigo_ret,retv_pago_actual,retv_retencion,retv_nro_retencion,retv_empresa,retv_porc_excl';

        $raw = (string) $this->api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'retmov',
            'campos' => $campos,
            'whereArmado' => ' WHERE retv_empresa IN ('.$in.')'
                .' AND retv_fecha BETWEEN '.$desde.' AND '.$hasta
                .' AND retv_retencion <> 0'
                .' AND (retv_tipo="OPP" OR retv_tipo LIKE "OPP%")',
            'orderBy' => 'retv_fecha, retv_empresa, retv_nro',
        ]);
        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = 'retmov: '.$msg;

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        $out = [];
        foreach ($filas as $f) {
            $out[] = (array) $f;
        }

        return $out;
    }

    /**
     * @param  list<int>  $empresas
     * @param  list<string>  $errores
     * @return array<string, list<object>>
     */
    private function indexarAuxpagFacturas(array $empresas, int $desde, int $hasta, array &$errores): array
    {
        $filas = $this->reader->listarAuxpag($empresas, $desde, $hasta, $errores);
        $out = [];
        $tiposFactura = ['FNB', 'FCA', 'FCB', 'FCC', 'FCE', 'FCI', 'FGA', 'FIB', 'FIS', 'FNS', 'FDT', 'NCA', 'NCB', 'NCC'];
        foreach ($filas as $fila) {
            $tipoAp = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));
            if (! in_array($tipoAp, $tiposFactura, true)) {
                continue;
            }
            $clave = (int) ($fila->axp_empresa ?? 0).'|'
                .strtoupper(trim((string) ($fila->axp_tipo ?? 'OPP'))).'|'
                .(int) ($fila->axp_rec ?? 0);
            $out[$clave][] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<object>  $facturasAuxpag
     */
    private function resolverNetoPago(float $pagoActualAnita, array $facturasAuxpag): float
    {
        if ($pagoActualAnita <= 0) {
            return 0.0;
        }
        if ($facturasAuxpag === []) {
            return round($pagoActualAnita, 2);
        }

        $retenible = 0.0;
        $totalDoc = 0.0;
        foreach ($facturasAuxpag as $axp) {
            $nroInt = (int) ($axp->axp_nro_interno ?? 0);
            $monto = abs((float) ($axp->axp_monto_ap ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $totalDoc = round($totalDoc + $monto, 4);
            $ratio = $this->ratioRetenibleGanancias($nroInt);
            $retenible = round($retenible + ($monto * $ratio), 4);
        }

        if ($totalDoc <= 0 || $retenible <= 0) {
            return round($pagoActualAnita, 2);
        }

        // Escala el total ARS del certificado por la proporción retenible de las facturas.
        return round($pagoActualAnita * ($retenible / $totalDoc), 2);
    }

    private function ratioRetenibleGanancias(int $anitaNroInterno): float
    {
        if ($anitaNroInterno <= 0) {
            return 1.0;
        }
        if (array_key_exists($anitaNroInterno, $this->cacheRatioNeto)) {
            return $this->cacheRatioNeto[$anitaNroInterno];
        }

        $cp = Comprobante_Proveedor::query()
            ->with(['comprobante_proveedor_conceptos.concepto_ivacompras'])
            ->where('anita_nro_interno', $anitaNroInterno)
            ->first();

        if ($cp === null || $cp->comprobante_proveedor_conceptos->isEmpty()) {
            return $this->cacheRatioNeto[$anitaNroInterno] = 1.0;
        }

        $total = 0.0;
        $retenible = 0.0;
        foreach ($cp->comprobante_proveedor_conceptos as $linea) {
            $monto = abs((float) $linea->monto);
            $total += $monto;
            $flag = strtoupper(trim((string) ($linea->concepto_ivacompras->retieneganancia ?? 'N')));
            if ($flag === 'S') {
                $retenible += $monto;
            }
        }

        $ratio = $total > 0 ? ($retenible / $total) : 1.0;

        return $this->cacheRatioNeto[$anitaNroInterno] = $ratio;
    }

    /**
     * @return array{model: Pagoproveedor, _creado: bool}
     */
    private function asegurarPagoproveedor(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        int $proveedorId,
        string $fecha,
        float $monto,
        int $uid,
    ): array {
        $existente = Pagoproveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('tipocomprobante', $tipo)
            ->where('sucursal', $sucursal)
            ->where('numerotransaccion', (string) $numero)
            ->first();

        if ($existente) {
            return ['model' => $existente, '_creado' => false];
        }

        // Algunas OP usan letra distinta; buscar solo por empresa+tipo+número.
        $existente = Pagoproveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('tipocomprobante', $tipo)
            ->where('numerotransaccion', (string) $numero)
            ->first();
        if ($existente) {
            return ['model' => $existente, '_creado' => false];
        }

        $row = Pagoproveedor::query()->create([
            'empresa_id' => $empresaId,
            'tipotransaccion_caja_id' => $this->resolverTipoCajaId($tipo),
            'tipocomprobante' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal > 0 ? $sucursal : 1,
            'numerotransaccion' => (string) $numero,
            'fecha' => $fecha,
            'proveedor_id' => $proveedorId,
            'detalle' => 'Importado Anita — retención Ganancias (acumulado RG 830)',
            'estado' => 'CONFIRMADA',
            'monto' => round($monto, 4),
            'cotizacion' => 1,
            'moneda_id' => RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita(1),
            'modo_cotizacion' => 'dia',
            'usuario_id' => $uid,
        ]);

        Pagoproveedor_Estado::query()->create([
            'pagoproveedor_id' => $row->id,
            'fecha' => now(),
            'estado' => 'CONFIRMADA',
            'usuario_id' => $uid,
            'observacion' => 'Alta por importación acumulado Ganancias Anita',
        ]);

        return ['model' => $row, '_creado' => true];
    }

    private function resolverProveedorId(string $codigoAnita): ?int
    {
        if ($codigoAnita === '') {
            return null;
        }
        if (array_key_exists($codigoAnita, $this->cacheProveedor)) {
            return $this->cacheProveedor[$codigoAnita];
        }
        $norm = ltrim($codigoAnita, '0');
        $id = (int) (Proveedor::query()
            ->where('codigo', $codigoAnita)
            ->orWhere('codigo', $norm)
            ->orWhere('codigo', str_pad($norm !== '' ? $norm : '0', 6, '0', STR_PAD_LEFT))
            ->value('id') ?: 0);

        return $this->cacheProveedor[$codigoAnita] = ($id > 0 ? $id : null);
    }

    private function resolverRegimenId(string $codigoAnita): ?int
    {
        $codigoAnita = trim($codigoAnita);
        if ($codigoAnita === '') {
            return null;
        }
        if (array_key_exists($codigoAnita, $this->cacheRegimen)) {
            return $this->cacheRegimen[$codigoAnita];
        }
        $id = (int) (Retencionganancia::query()->where('codigo', $codigoAnita)->value('id') ?: 0);

        return $this->cacheRegimen[$codigoAnita] = ($id > 0 ? $id : null);
    }

    private function resolverTipoCajaId(string $abrev): ?int
    {
        $abrev = ComprobanteProveedorAnitaImportClaveSupport::tipo($abrev);
        if (! array_key_exists($abrev, $this->cacheTipoCaja)) {
            $id = (int) (Tipotransaccion_Caja::query()->where('abreviatura', $abrev)->value('id') ?: 0);
            if ($id <= 0) {
                $id = (int) (Tipotransaccion_Caja::query()->where('abreviatura', 'OPP')->value('id') ?: 0);
            }
            $this->cacheTipoCaja[$abrev] = $id > 0 ? $id : null;
        }

        return $this->cacheTipoCaja[$abrev];
    }
}
