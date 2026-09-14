<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Vendedor;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportBridgeReader;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa cabeceras Anita `venta` (+ CAE de vencae) → ERP `venta` mínimas
 * para poder sincronizar cuenta corriente. No escribe Anita.
 */
class VentaDeudaImportarDesdeAnitaService
{
    /** @var array<string, int|null> */
    private array $cacheCliente = [];

    /** @var array<string, int|null> */
    private array $cacheTipo = [];

    /** @var array<int, int|null> */
    private array $cachePv = [];

    /** @var array<string, int|null> */
    private array $cacheVendedor = [];

    /** @var array<int, int|null> */
    private array $cacheCondicionventa = [];

    public function __construct(
        private readonly ClienteCuentacorrienteAnitaImportBridgeReader $reader = new ClienteCuentacorrienteAnitaImportBridgeReader,
    ) {}

    /**
     * @param  list<string>  $clavesDocumento  tipo|letra|suc|nro
     * @return array<string, mixed>
     */
    public function importarPorClaves(array $clavesDocumento, bool $dryRun = true, int $usuarioId = 1): array
    {
        $clavesDocumento = array_values(array_unique(array_filter($clavesDocumento)));
        $stats = [
            'claves' => count($clavesDocumento),
            'anita_venta' => 0,
            'ya_en_erp' => 0,
            'a_crear' => 0,
            'creadas' => 0,
            'sin_cliente' => 0,
            'sin_tipo' => 0,
            'sin_puntoventa' => 0,
            'muestra' => [],
            'errores' => [],
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
        ];
        if ($clavesDocumento === []) {
            return $stats;
        }

        $anita = $this->reader->indexarVentaPorClaves($clavesDocumento);
        $stats['anita_venta'] = count($anita);
        $vencae = $this->reader->indexarVencaePorClaves(array_keys($anita));

        $clavesMatch = [];
        foreach (array_keys($anita) as $clave) {
            [$tipo, $letra, $suc, $nro] = explode('|', $clave);
            $clavesMatch[] = [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => (int) $suc,
                'numero' => (int) $nro,
            ];
        }
        $indiceErp = \App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportVentaMatchSupport::indexarVentasPorClaves($clavesMatch);

        $plan = [];
        foreach ($anita as $clave => $fila) {
            if (isset($indiceErp[$clave]) && $indiceErp[$clave] !== []) {
                $stats['ya_en_erp']++;

                continue;
            }
            $prep = $this->preparar($fila, $vencae[$clave] ?? null);
            if ($prep['estado'] !== 'ok') {
                $stats[$prep['estado']] = ($stats[$prep['estado']] ?? 0) + 1;
                if (! empty($prep['error'])) {
                    $stats['errores'][] = $prep['error'];
                }

                continue;
            }
            $plan[] = $prep;
        }

        $stats['a_crear'] = count($plan);
        $stats['muestra'] = array_map(static fn (array $p) => $p['resumen'], array_slice($plan, 0, 25));

        if ($dryRun) {
            return $stats;
        }

        return DB::transaction(function () use ($plan, $stats, $usuarioId) {
            foreach ($plan as $item) {
                $this->persistir($item, $usuarioId);
                $stats['creadas']++;
            }
            $stats['modo'] = 'ejecutar';

            return $stats;
        });
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>|null  $cae
     * @return array<string, mixed>
     */
    private function preparar(array $fila, ?array $cae): array
    {
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['ven_tipo'] ?? ''));
        $letra = ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($fila['ven_letra'] ?? ''));
        $suc = (int) ($fila['ven_sucursal'] ?? 0);
        $nro = (int) ($fila['ven_nro'] ?? 0);
        $etiqueta = ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp($tipo, $letra, $suc, $nro);

        $tipoId = $this->resolverTipoId($tipo);
        if (! $tipoId) {
            return ['estado' => 'sin_tipo', 'error' => 'Sin tipotransaccion '.$tipo.' para '.$etiqueta];
        }
        $pvId = $this->resolverPuntoventaId($suc);
        if (! $pvId) {
            return ['estado' => 'sin_puntoventa', 'error' => 'Sin puntoventa sucursal '.$suc.' para '.$etiqueta];
        }
        $clienteId = $this->resolverClienteId((string) ($fila['ven_cliente'] ?? ''));
        if (! $clienteId) {
            return [
                'estado' => 'sin_cliente',
                'error' => 'Sin cliente Anita '.($fila['ven_cliente'] ?? '').' para '.$etiqueta,
            ];
        }

        $cliente = Cliente::query()->find($clienteId);
        $tipoModel = Tipotransaccion::query()->find($tipoId);
        $signo = ClienteCuentacorrienteAnitaImportClaveSupport::signoEntero(
            $tipoModel?->getAttributes()['signo'] ?? 1
        );
        $monto = round(abs((float) ($fila['ven_monto'] ?? 0)), 4);
        $fecha = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($fila['ven_fecha'] ?? '');
        if ($fecha === '') {
            return ['estado' => 'sin_fecha', 'error' => 'Fecha inválida en '.$etiqueta];
        }

        $nombre = trim((string) ($fila['ven_nombre_cliente'] ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($cliente->nombre ?? '')) ?: 'Cliente '.$clienteId;
        }
        $domicilio = trim((string) ($fila['ven_direccion_cli'] ?? ''));
        if ($domicilio === '') {
            $domicilio = trim((string) ($cliente->domicilio ?? '')) ?: '-';
        }
        $cuit = trim((string) ($fila['ven_cuit_cli'] ?? ''));
        if ($cuit === '') {
            $cuit = trim((string) ($cliente->numerodocumento ?? ''));
        }

        $caeNro = trim((string) ($cae['venc_nro_cae'] ?? ''));
        $caeVto = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($cae['venc_fecha_vto'] ?? '');

        return [
            'estado' => 'ok',
            'data' => [
                'fecha' => $fecha,
                'fechajornada' => $fecha,
                'tipotransaccion_id' => $tipoId,
                'puntoventa_id' => $pvId,
                'numerocomprobante' => $nro,
                'codigo_afip' => $tipoModel?->codigo,
                'cliente_id' => $clienteId,
                'condicionventa_id' => $this->resolverCondicionventaId((int) ($fila['ven_cond_venta'] ?? 0)),
                'vendedor_id' => $this->resolverVendedorId((string) ($fila['ven_vendedor'] ?? '')),
                'total' => round($monto * $signo, 4),
                'moneda_id' => RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($fila['ven_cod_mon'] ?? 1),
                'cotizacion' => (float) ($fila['ven_cotizacion'] ?? 1) ?: 1.0,
                'estado' => ' ',
                'usuario_id' => null,
                'leyenda' => 'Importado Anita (deuda CC)',
                'descuento' => (float) ($fila['ven_porc_desc'] ?? 0),
                'codigo' => $etiqueta,
                'nombre' => $nombre,
                'domicilio' => $domicilio,
                'localidad_id' => $cliente->localidad_id ?? null,
                'provincia_id' => $cliente->provincia_id ?? null,
                'pais_id' => (int) ($cliente->pais_id ?? 1) ?: 1,
                'codigopostal' => trim((string) ($fila['ven_cod_postal_cli'] ?? $cliente->codigopostal ?? '')),
                'nroinscripcion' => $cuit !== '' ? $cuit : null,
                'condicioniva_id' => $cliente->condicioniva_id ?? null,
                'cae' => $caeNro !== '' ? $caeNro : null,
                'fechavencimientocae' => $caeVto !== '' ? $caeVto : null,
                'numeroremito' => 0,
                'cantidadbulto' => 0,
            ],
            'resumen' => [
                'etiqueta' => $etiqueta,
                'fecha' => $fecha,
                'total' => round($monto * $signo, 4),
                'cliente_id' => $clienteId,
                'cae' => $caeNro !== '' ? $caeNro : '—',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function persistir(array $item, int $usuarioId): Venta
    {
        $data = $item['data'];
        $data['usuario_id'] = $usuarioId > 0 ? $usuarioId : null;

        return Venta::query()->create($data);
    }

    private function resolverTipoId(string $abrev): ?int
    {
        if (array_key_exists($abrev, $this->cacheTipo)) {
            return $this->cacheTipo[$abrev];
        }
        $id = Tipotransaccion::query()->where('abreviatura', $abrev)->value('id');

        return $this->cacheTipo[$abrev] = $id ? (int) $id : null;
    }

    private function resolverPuntoventaId(int $sucursal): ?int
    {
        if (array_key_exists($sucursal, $this->cachePv)) {
            return $this->cachePv[$sucursal];
        }
        $id = Puntoventa::query()
            ->whereRaw('CAST(codigo AS UNSIGNED) = ?', [$sucursal])
            ->value('id');

        return $this->cachePv[$sucursal] = $id ? (int) $id : null;
    }

    private function resolverClienteId(string $codigoAnita): ?int
    {
        $codigoAnita = trim($codigoAnita);
        if ($codigoAnita === '') {
            return null;
        }
        if (array_key_exists($codigoAnita, $this->cacheCliente)) {
            return $this->cacheCliente[$codigoAnita];
        }
        $erp = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp($codigoAnita);
        $padded = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita($codigoAnita);
        $id = Cliente::query()
            ->where(function ($q) use ($erp, $padded, $codigoAnita) {
                $q->where('codigo', $erp)
                    ->orWhere('codigo', $padded)
                    ->orWhere('codigo', $codigoAnita);
            })
            ->value('id');

        return $this->cacheCliente[$codigoAnita] = $id ? (int) $id : null;
    }

    private function resolverVendedorId(string $codigo): ?int
    {
        $codigo = ltrim(trim($codigo), '0');
        if ($codigo === '' || $codigo === '0') {
            return null;
        }
        if (array_key_exists($codigo, $this->cacheVendedor)) {
            return $this->cacheVendedor[$codigo];
        }
        $id = Vendedor::query()->where('codigo', $codigo)->orWhere('codigo', str_pad($codigo, 2, '0', STR_PAD_LEFT))->value('id');

        return $this->cacheVendedor[$codigo] = $id ? (int) $id : null;
    }

    private function resolverCondicionventaId(int $codigoAnita): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }
        if (array_key_exists($codigoAnita, $this->cacheCondicionventa)) {
            return $this->cacheCondicionventa[$codigoAnita];
        }
        // En Ferli ven_cond_venta suele coincidir con id ERP.
        $id = Condicionventa::query()->whereKey($codigoAnita)->value('id');

        return $this->cacheCondicionventa[$codigoAnita] = $id ? (int) $id : null;
    }
}
