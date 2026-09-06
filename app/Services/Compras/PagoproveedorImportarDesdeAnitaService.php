<?php

namespace App\Services\Compras;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Models\Seguridad\Usuario;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\PagosSabanaAnitaBridgeReader;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;

/**
 * Importa cabeceras OPP/OPA desde Anita che_ban.pago sin cuenta corriente.
 */
class PagoproveedorImportarDesdeAnitaService
{
    /** @var array<string, int|null> */
    private array $cacheTipoCaja = [];

    /** @var array<string, int|null> */
    private array $cacheProveedor = [];

    public function __construct(
        private readonly PagosSabanaAnitaBridgeReader $reader = new PagosSabanaAnitaBridgeReader,
    ) {}

    /**
     * @return array{
     *   en_anita: int,
     *   a_crear: int,
     *   creados: int,
     *   omitidos: int,
     *   sin_proveedor: int,
     *   sin_empresa: int,
     *   errores: list<string>,
     *   errores_bridge: list<string>
     * }
     */
    public function importar(
        string $desdeIso,
        string $hastaIso,
        bool $dryRun = true,
        ?int $usuarioId = null,
    ): array {
        $stats = [
            'en_anita' => 0,
            'a_crear' => 0,
            'creados' => 0,
            'omitidos' => 0,
            'sin_proveedor' => 0,
            'sin_empresa' => 0,
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

        $pagosAnita = $this->reader->listarPagos([1, 2, 3], $desde, $hasta, $stats['errores_bridge']);
        $stats['en_anita'] = count($pagosAnita);

        $existentes = $this->indexarPagosExistentes($desdeIso, $hastaIso);

        foreach ($pagosAnita as $pago) {
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($pago->pag_tipo ?? ''));
            if (! in_array($tipo, ['OPP', 'OPA'], true)) {
                continue;
            }
            $empresaId = (int) ($pago->pag_empresa ?? 0);
            if ($empresaId <= 0) {
                $stats['sin_empresa']++;

                continue;
            }
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($pago->pag_letra ?? 'A'));
            $sucursal = (int) ($pago->pag_sucursal ?? 0);
            $numero = (int) ($pago->pag_rec ?? 0);
            if ($numero <= 0) {
                continue;
            }

            $clave = $empresaId.'|'.$tipo.'|'.$letra.'|'.$sucursal.'|'.$numero;
            if (isset($existentes[$clave])) {
                $stats['omitidos']++;

                continue;
            }

            $provCodigo = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($pago->pag_pro ?? ''));
            $proveedorId = $this->resolverProveedorId($provCodigo);
            if (! $proveedorId) {
                $stats['sin_proveedor']++;
                if (count($stats['errores']) < 20) {
                    $stats['errores'][] = "Proveedor {$provCodigo} no está en ERP ({$tipo} {$letra} {$sucursal}-{$numero})";
                }

                continue;
            }

            $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($pago->pag_fecha ?? '');
            if ($fecha === '') {
                $stats['errores'][] = "Fecha inválida {$tipo} {$numero}";

                continue;
            }

            $stats['a_crear']++;
            if ($dryRun) {
                continue;
            }

            try {
                DB::transaction(function () use ($pago, $tipo, $letra, $sucursal, $numero, $empresaId, $proveedorId, $fecha, $uid) {
                    $monedaAnita = (int) ($pago->pag_cod_mon_me ?? 0);
                    $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($monedaAnita > 0 ? $monedaAnita : 1);
                    $cotizacion = (float) ($pago->pag_cotizacion ?? 1);
                    if ($cotizacion <= 0) {
                        $cotizacion = 1.0;
                    }
                    $monto = abs((float) ($pago->pag_trec ?? 0));
                    $detalle = trim((string) ($pago->pag_leyenda ?? ''));
                    if ($detalle === '') {
                        $detalle = 'Importado desde Anita — '.$tipo.' documento (sin cuenta corriente)';
                    }

                    $row = Pagoproveedor::query()->create([
                        'empresa_id' => $empresaId,
                        'tipotransaccion_caja_id' => $this->resolverTipoCajaId($tipo),
                        'tipocomprobante' => $tipo,
                        'letra' => $letra,
                        'sucursal' => $sucursal,
                        'numerotransaccion' => (string) $numero,
                        'fecha' => $fecha,
                        'proveedor_id' => $proveedorId,
                        'detalle' => $detalle,
                        'estado' => 'CONFIRMADA',
                        'monto' => round($monto, 4),
                        'cotizacion' => $cotizacion,
                        'moneda_id' => $monedaId,
                        'modo_cotizacion' => 'dia',
                        'usuario_id' => $uid,
                    ]);

                    Pagoproveedor_Estado::query()->create([
                        'pagoproveedor_id' => $row->id,
                        'fecha' => now(),
                        'estado' => 'CONFIRMADA',
                        'usuario_id' => $uid,
                        'observacion' => 'Importado desde Anita ('.$tipo.' sin cuenta corriente)',
                    ]);
                });
                $stats['creados']++;
                $existentes[$clave] = true;
            } catch (\Throwable $e) {
                $stats['errores'][] = "{$tipo} {$sucursal}-{$numero}: ".$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @return array<string, true>
     */
    private function indexarPagosExistentes(string $desdeIso, string $hastaIso): array
    {
        $out = [];
        foreach (Pagoproveedor::query()
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->whereIn('tipocomprobante', ['OPP', 'OPA'])
            ->get(['empresa_id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion']) as $p) {
            $out[(int) $p->empresa_id.'|'
                .strtoupper(trim((string) $p->tipocomprobante)).'|'
                .trim((string) $p->letra).'|'
                .(int) $p->sucursal.'|'
                .(int) $p->numerotransaccion] = true;
        }

        return $out;
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

    private function resolverTipoCajaId(string $abrev): ?int
    {
        $abrev = ComprobanteProveedorAnitaImportClaveSupport::tipo($abrev);
        if ($abrev === '') {
            return null;
        }
        if (! array_key_exists($abrev, $this->cacheTipoCaja)) {
            $id = (int) (Tipotransaccion_Caja::query()->where('abreviatura', $abrev)->value('id') ?: 0);
            if ($id <= 0 && $abrev !== 'OPP') {
                $id = (int) (Tipotransaccion_Caja::query()->where('abreviatura', 'OPP')->value('id') ?: 0);
            }
            $this->cacheTipoCaja[$abrev] = $id > 0 ? $id : null;
        }

        return $this->cacheTipoCaja[$abrev];
    }
}
