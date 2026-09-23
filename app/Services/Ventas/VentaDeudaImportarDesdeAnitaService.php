<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Models\Ventas\Vendedor;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportBridgeReader;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportVentaMatchSupport;
use Illuminate\Support\Facades\DB;

/**
 * Importa cabeceras Anita `venta` (+ CAE de vencae + desglose IVA) → ERP `venta`.
 * Uso: deuda CC e IVA ventas. No escribe Anita.
 */
class VentaDeudaImportarDesdeAnitaService
{
    public const LEYENDA_IMPORT = 'Importado Anita (deuda CC)';

    public const LEYENDA_IVA = 'Importado Anita (IVA ventas)';

    /** Tipos típicos de comprobante fiscal (no PRE/COB/RIN). */
    public const TIPOS_IVA_VENTAS = [
        'FAC', 'FAE', 'FAI', 'FAJ', 'FAN', 'FAR', 'FAS', 'FAK',
        'NCA', 'NCB', 'NCC', 'NCD', 'NCE', 'NCI', 'NCP', 'NCR',
        'NDA', 'NDB', 'NDC', 'NDI', 'NDP', 'NDR', 'NDV',
    ];

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

    private ?int $clienteCfId = null;

    public function __construct(
        private readonly ClienteCuentacorrienteAnitaImportBridgeReader $reader = new ClienteCuentacorrienteAnitaImportBridgeReader,
    ) {}

    /**
     * @param  list<string>  $clavesDocumento  tipo|letra|suc|nro
     * @return array<string, mixed>
     */
    public function importarPorClaves(
        array $clavesDocumento,
        bool $dryRun = true,
        int $usuarioId = 1,
        string $leyenda = self::LEYENDA_IMPORT,
    ): array {
        $clavesDocumento = array_values(array_unique(array_filter($clavesDocumento)));
        $stats = $this->statsVacios($dryRun);
        $stats['claves'] = count($clavesDocumento);
        if ($clavesDocumento === []) {
            return $stats;
        }

        $anita = $this->reader->indexarVentaPorClaves($clavesDocumento);
        $stats['anita_venta'] = count($anita);
        $vencae = $this->reader->indexarVencaePorClaves(array_keys($anita));

        return $this->procesarFilasAnita($anita, $vencae, $dryRun, $usuarioId, $leyenda, $stats);
    }

    /**
     * Cabeceras ERP desde climov cuando Anita no tiene fila en `venta` (COA, etc.).
     *
     * @param  list<array<string, mixed>>  $climovs
     * @return array<string, mixed>
     */
    public function importarDesdeClimov(
        array $climovs,
        bool $dryRun = true,
        int $usuarioId = 1,
        string $leyenda = 'Importado Anita climov (crédito sin venta)',
    ): array {
        $stats = $this->statsVacios($dryRun);
        $stats['claves'] = count($climovs);
        if ($climovs === []) {
            return $stats;
        }

        $anita = [];
        foreach ($climovs as $climov) {
            $fila = $this->filaVentaSinteticaDesdeClimov($climov);
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                (string) ($fila['ven_tipo'] ?? ''),
                (string) ($fila['ven_letra'] ?? ''),
                (int) ($fila['ven_sucursal'] ?? 0),
                (int) ($fila['ven_nro'] ?? 0),
            );
            if ($clave === '' || str_starts_with($clave, '|')) {
                continue;
            }
            $anita[$clave] = $fila;
        }
        $stats['anita_venta'] = count($anita);

        return $this->procesarFilasAnita($anita, [], $dryRun, $usuarioId, $leyenda, $stats);
    }

    /**
     * Cabecera ERP de un crédito que en Anita no tiene fila `venta` (el COA que aplica una factura).
     * El cliente es el de la factura del ERP: el código Anita puede repetirse en otro maestro.
     *
     * @param  array<string, mixed>  $climov
     * @return array{venta_id:?int, etiqueta:string, total:float, creada:bool, error:?string}
     */
    public function asegurarCabeceraDesdeClimov(
        array $climov,
        int $clienteIdErp,
        bool $dryRun = true,
        int $usuarioId = 1,
    ): array {
        $fila = $this->filaVentaSinteticaDesdeClimov($climov);
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['ven_tipo'] ?? ''));
        $letra = ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($fila['ven_letra'] ?? ''));
        $suc = (int) ($fila['ven_sucursal'] ?? 0);
        $nro = (int) ($fila['ven_nro'] ?? 0);
        $etiqueta = ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp($tipo, $letra, $suc, $nro);
        $vacio = [
            'venta_id' => null,
            'etiqueta' => $etiqueta,
            'total' => 0.0,
            'creada' => false,
            'error' => null,
        ];
        if ($clienteIdErp <= 0 || $tipo === '' || $nro <= 0) {
            $vacio['error'] = 'Climov incompleto para '.$etiqueta;

            return $vacio;
        }

        $indice = ClienteCuentacorrienteAnitaImportVentaMatchSupport::indexarVentasPorClaves([[
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $suc,
            'numero' => $nro,
        ]]);
        $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento($tipo, $letra, $suc, $nro);
        foreach ($indice[$clave] ?? [] as $venta) {
            if ((int) ($venta->cliente_id ?? 0) === $clienteIdErp) {
                $vacio['venta_id'] = (int) $venta->id;
                $vacio['total'] = round((float) (Venta::query()->whereKey($venta->id)->value('total') ?? 0), 4);

                return $vacio;
            }
        }
        if (($indice[$clave] ?? []) !== []) {
            $vacio['error'] = $etiqueta.' ya existe en otro cliente.';

            return $vacio;
        }

        $prep = $this->preparar($fila, null, 'Importado Anita climov (COA contrapartida)');
        if (($prep['estado'] ?? '') !== 'ok') {
            $vacio['error'] = (string) ($prep['error'] ?? 'No se pudo armar '.$etiqueta);

            return $vacio;
        }

        $cliente = Cliente::query()->find($clienteIdErp);
        $prep['data']['cliente_id'] = $clienteIdErp;
        if ($cliente) {
            $prep['data']['nombre'] = trim((string) ($cliente->nombre ?? '')) ?: $prep['data']['nombre'];
        }
        $vacio['total'] = round((float) ($prep['data']['total'] ?? 0), 4);
        if ($dryRun) {
            return $vacio;
        }

        $venta = $this->persistir($prep, $usuarioId);
        $vacio['venta_id'] = (int) $venta->id;
        $vacio['total'] = round((float) $venta->total, 4);
        $vacio['creada'] = true;

        return $vacio;
    }

    /**
     * @param  array<string, mixed>  $climov
     * @return array<string, mixed>
     */
    private function filaVentaSinteticaDesdeClimov(array $climov): array
    {
        return [
            'ven_cliente' => $climov['cliv_cliente'] ?? '',
            'ven_tipo' => $climov['cliv_tipo'] ?? '',
            'ven_letra' => $climov['cliv_letra'] ?? '',
            'ven_sucursal' => $climov['cliv_sucursal'] ?? 0,
            'ven_nro' => $climov['cliv_nro'] ?? 0,
            'ven_fecha' => $climov['cliv_fecha'] ?? '',
            'ven_fecha_vto' => $climov['cliv_fecha_vto'] ?? ($climov['cliv_fecha'] ?? ''),
            'ven_monto' => $climov['cliv_monto'] ?? 0,
            'ven_gravado' => 0,
            'ven_impuesto1' => 0,
            'ven_exento' => 0,
            'ven_cod_mon' => $climov['cliv_cod_mon'] ?? 1,
            'ven_cotizacion' => $climov['cliv_cotizacion'] ?? 1,
            'ven_nombre_cliente' => '',
            'ven_direccion_cli' => '',
            'ven_localidad_cli' => '',
            'ven_provincia_cli' => '',
            'ven_cod_postal_cli' => '',
            'ven_cuit_cli' => '',
            'ven_cond_iva_cli' => 0,
            'ven_cond_venta' => 0,
            'ven_vendedor' => '',
            'ven_cta_cte' => 'S',
            'ven_porc_desc' => 0,
            'ven_monto_desc' => 0,
        ];
    }

    /**
     * Importa comprobantes Anita del rango (ven_fecha) que falten en ERP.
     *
     * @param  list<string>|null  $tipos  null = TIPOS_IVA_VENTAS
     * @return array<string, mixed>
     */
    public function importarPorRangoFecha(
        string $desdeIso,
        string $hastaIso,
        bool $dryRun = true,
        int $usuarioId = 1,
        ?array $tipos = null,
        string $leyenda = self::LEYENDA_IVA,
    ): array {
        $desdeYmd = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso);
        $hastaYmd = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso);
        if ($desdeYmd <= 0 || $hastaYmd <= 0) {
            throw new \InvalidArgumentException('Fechas inválidas para importar venta Anita.');
        }

        $tiposOk = array_map('strtoupper', $tipos ?? self::TIPOS_IVA_VENTAS);
        $tiposFlip = array_fill_keys($tiposOk, true);

        $filas = $this->reader->listarVentaPorRangoFecha($desdeYmd, $hastaYmd);
        $stats = $this->statsVacios($dryRun);
        $stats['anita_rango'] = count($filas);

        $anita = [];
        foreach ($filas as $fila) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['ven_tipo'] ?? ''));
            if ($tipo === '' || ! isset($tiposFlip[$tipo])) {
                $stats['omitidas_tipo']++;

                continue;
            }
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                $tipo,
                (string) ($fila['ven_letra'] ?? ''),
                (int) ($fila['ven_sucursal'] ?? 0),
                (int) ($fila['ven_nro'] ?? 0),
            );
            $anita[$clave] = $fila;
        }
        $stats['anita_venta'] = count($anita);
        $vencae = $this->reader->indexarVencaePorClaves(array_keys($anita));

        return $this->procesarFilasAnita($anita, $vencae, $dryRun, $usuarioId, $leyenda, $stats);
    }

    /**
     * Completa venta_impuesto faltante en ventas ERP del rango, leyendo Anita por clave.
     *
     * @return array<string, mixed>
     */
    public function backfillImpuestosRango(string $desdeIso, string $hastaIso, bool $dryRun = true): array
    {
        $ventas = Venta::query()
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->whereDoesntHave('venta_impuestos')
            ->orderBy('id')
            ->get(['id', 'codigo', 'total', 'fecha']);

        $stats = [
            'ventas_sin_impuesto' => $ventas->count(),
            'a_actualizar' => 0,
            'actualizadas' => 0,
            'sin_anita' => 0,
            'errores' => [],
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
        ];
        if ($ventas->isEmpty()) {
            return $stats;
        }

        $claves = [];
        $ventaPorClave = [];
        foreach ($ventas as $venta) {
            $partes = $this->parseCodigoVenta((string) $venta->codigo);
            if ($partes === null) {
                $stats['errores'][] = 'Código inválido venta '.$venta->id.' '.$venta->codigo;

                continue;
            }
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                $partes['tipo'],
                $partes['letra'],
                $partes['sucursal'],
                $partes['numero'],
            );
            $claves[] = $clave;
            $ventaPorClave[$clave] = $venta;
        }

        $anita = $this->reader->indexarVentaPorClaves($claves);
        $plan = [];
        foreach ($ventaPorClave as $clave => $venta) {
            if (! isset($anita[$clave])) {
                $stats['sin_anita']++;

                continue;
            }
            $plan[] = ['venta' => $venta, 'fila' => $anita[$clave]];
        }
        $stats['a_actualizar'] = count($plan);
        if ($dryRun) {
            return $stats;
        }

        foreach ($plan as $item) {
            $this->persistirImpuestos((int) $item['venta']->id, $item['fila'], abs((float) $item['venta']->total));
            $stats['actualizadas']++;
        }
        $stats['modo'] = 'ejecutar';

        return $stats;
    }

    /**
     * @param  array<string, array<string, mixed>>  $anita
     * @param  array<string, array<string, mixed>>  $vencae
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function procesarFilasAnita(
        array $anita,
        array $vencae,
        bool $dryRun,
        int $usuarioId,
        string $leyenda,
        array $stats,
    ): array {
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
        $indiceErp = ClienteCuentacorrienteAnitaImportVentaMatchSupport::indexarVentasPorClaves($clavesMatch);

        $plan = [];
        foreach ($anita as $clave => $fila) {
            if (isset($indiceErp[$clave]) && $indiceErp[$clave] !== []) {
                $stats['ya_en_erp']++;

                continue;
            }
            $prep = $this->preparar($fila, $vencae[$clave] ?? null, $leyenda);
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
     * @return array<string, mixed>
     */
    private function statsVacios(bool $dryRun): array
    {
        return [
            'claves' => 0,
            'anita_rango' => 0,
            'anita_venta' => 0,
            'omitidas_tipo' => 0,
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
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>|null  $cae
     * @return array<string, mixed>
     */
    private function preparar(array $fila, ?array $cae, string $leyenda): array
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
        if ($nombre === '' || strtolower($nombre) === 'm') {
            $nombre = trim((string) ($cliente->nombre ?? '')) ?: 'Consumidor final';
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
        $condIvaAnita = (int) ($fila['ven_cond_iva_cli'] ?? 0);
        $condicionivaId = $this->mapCondicionIvaAnita($condIvaAnita) ?? ($cliente->condicioniva_id ?? null);

        return [
            'estado' => 'ok',
            'fila_anita' => $fila,
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
                'leyenda' => $leyenda,
                'descuento' => (float) ($fila['ven_porc_desc'] ?? 0),
                'codigo' => $etiqueta,
                'nombre' => $nombre,
                'domicilio' => $domicilio,
                'localidad_id' => $cliente->localidad_id ?? null,
                'provincia_id' => $cliente->provincia_id ?? null,
                'pais_id' => (int) ($cliente->pais_id ?? 1) ?: 1,
                'codigopostal' => trim((string) ($fila['ven_cod_postal_cli'] ?? $cliente->codigopostal ?? '')),
                'nroinscripcion' => $cuit !== '' ? $cuit : null,
                'condicioniva_id' => $condicionivaId,
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
        $venta = Venta::query()->create($data);
        $this->persistirImpuestos((int) $venta->id, $item['fila_anita'] ?? [], abs((float) $venta->total));

        return $venta;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function persistirImpuestos(int $ventaId, array $fila, float $totalAbs): void
    {
        if (Venta_Impuesto::query()->where('venta_id', $ventaId)->exists()) {
            return;
        }

        $gravado = round(abs((float) ($fila['ven_gravado'] ?? 0)), 2);
        $exento = round(abs((float) ($fila['ven_exento'] ?? 0)), 2);
        $iva = round(abs((float) ($fila['ven_impuesto1'] ?? 0)), 2);
        $total = round($totalAbs > 0 ? $totalAbs : abs((float) ($fila['ven_monto'] ?? 0)), 2);
        $baseSubtotal = round($gravado + $exento, 2);

        $filas = [
            ['concepto' => 'Subtotal', 'baseimponible' => 0.0, 'tasa' => 0.0, 'importe' => $baseSubtotal, 'impuesto_id' => null],
        ];
        if ($gravado > 0.0001) {
            $filas[] = [
                'concepto' => 'Gravado al 21.000%',
                'baseimponible' => 0.0,
                'tasa' => 21.0,
                'importe' => $gravado,
                'impuesto_id' => 3,
            ];
        }
        if ($exento > 0.0001) {
            $filas[] = [
                'concepto' => 'Exento',
                'baseimponible' => 0.0,
                'tasa' => 0.0,
                'importe' => $exento,
                'impuesto_id' => 1,
            ];
        }
        if ($iva > 0.0001) {
            $filas[] = [
                'concepto' => 'Iva 21.000%',
                'baseimponible' => $gravado,
                'tasa' => 21.0,
                'importe' => $iva,
                'impuesto_id' => 3,
            ];
        }
        $filas[] = [
            'concepto' => 'Total',
            'baseimponible' => 0.0,
            'tasa' => 0.0,
            'importe' => $total,
            'impuesto_id' => null,
        ];

        foreach ($filas as $f) {
            if (abs($f['importe']) < 0.0001 && $f['concepto'] !== 'Total') {
                continue;
            }
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => $f['concepto'],
                'baseimponible' => $f['baseimponible'],
                'tasa' => $f['tasa'],
                'importe' => $f['importe'],
                'provincia_id' => null,
                'impuesto_id' => $f['impuesto_id'],
            ]);
        }
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
        $erpProbe = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp($codigoAnita);
        if ($codigoAnita === '' || $erpProbe === '' || $erpProbe === '0') {
            return $this->resolverClienteConsumidorFinal();
        }
        if (array_key_exists($codigoAnita, $this->cacheCliente)) {
            return $this->cacheCliente[$codigoAnita];
        }
        $erp = $erpProbe;
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

    private function resolverClienteConsumidorFinal(): int
    {
        if ($this->clienteCfId !== null) {
            return $this->clienteCfId;
        }
        $id = Cliente::query()->where('codigo', '0')->value('id');
        if ($id) {
            return $this->clienteCfId = (int) $id;
        }
        $cliente = Cliente::query()->create([
            'codigo' => '0',
            'nombre' => 'CONSUMIDOR FINAL (import Anita)',
            'condicioniva_id' => 3,
            'numerodocumento' => '0',
            'domicilio' => '-',
            'pais_id' => 1,
        ]);

        return $this->clienteCfId = (int) $cliente->id;
    }

    private function mapCondicionIvaAnita(int $codigoAnita): ?int
    {
        // Anita clim_cond_iva / ven_cond_iva_cli → condicioniva.id ERP (Ferli).
        return match ($codigoAnita) {
            1 => 1, // RI
            2 => 2, // Exento
            3, 5 => 3, // CF
            4, 6 => 4, // Monotributo
            9 => 5, // Export
            default => null,
        };
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
        $id = Condicionventa::query()->whereKey($codigoAnita)->value('id');

        return $this->cacheCondicionventa[$codigoAnita] = $id ? (int) $id : null;
    }

    /**
     * @return array{tipo:string,letra:string,sucursal:int,numero:int}|null
     */
    private function parseCodigoVenta(string $codigo): ?array
    {
        if (! preg_match('/^([A-Z]+)\s+([A-Z])-(\d+)-(\d+)$/', trim($codigo), $m)) {
            return null;
        }

        return [
            'tipo' => $m[1],
            'letra' => $m[2],
            'sucursal' => (int) $m[3],
            'numero' => (int) $m[4],
        ];
    }
}
