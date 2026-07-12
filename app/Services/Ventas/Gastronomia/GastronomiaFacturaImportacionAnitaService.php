<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Caja\Cobranza;
use App\Models\Stock\Articulo;
use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Impuesto;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Support\Caja\CobranzaNumeracionTransaccion;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportBridgeSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheReader;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportDescuentoSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportEstacionamientoSupport;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportMediosPagoSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

/**
 * Réplica ERP de facturas FAC gastronomía ya emitidas en Informix (venta, ítems, impuestos, resvta/cobranza).
 */
final class GastronomiaFacturaImportacionAnitaService
{
    private const TIPO = 'FAC';

    private const LETRA = 'B';

    private ?GastronomiaAnitaImportCacheReader $cacheReader = null;

    public function __construct(
        private readonly GastronomiaCobranzaService $cobranzaGastronomiaService,
        private readonly MozoGastronomiaRepositoryInterface $mozoRepository,
    ) {
    }

    public function setCacheReader(?GastronomiaAnitaImportCacheReader $reader): void
    {
        $this->cacheReader = $reader;
    }

    public function usaCacheLocal(): bool
    {
        return $this->cacheReader !== null;
    }

    /**
     * @return array{importados:int,omitidos:int,vinculados:int,errores:list<string>,advertencias:list<string>}
     */
    public function importarRango(
        int $sucursal,
        int $desde,
        int $hasta,
        int $empresaId,
        int $usuarioId,
        bool $dryRun = false,
        ?string $identificadorPc = null,
    ): array {
        if ($desde <= 0 || $hasta < $desde) {
            throw new InvalidArgumentException('Rango de comprobantes inválido.');
        }

        $ctx = $this->resolverContexto($sucursal, $empresaId, $identificadorPc);
        $ret = ['importados' => 0, 'omitidos' => 0, 'vinculados' => 0, 'errores' => [], 'advertencias' => []];

        $numeros = $this->listarNumerosAnitaEnRango($sucursal, $desde, $hasta, $ctx);

        foreach ($numeros as $nro) {
            if ($nro <= 0) {
                continue;
            }

            try {
                $r = $this->importarUno($sucursal, $nro, $ctx, $usuarioId, $dryRun);
                if ($r === 'importado') {
                    $ret['importados']++;
                } elseif ($r === 'omitido') {
                    $ret['omitidos']++;
                } elseif ($r === 'vinculado') {
                    $ret['vinculados']++;
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = $this->etiqueta($sucursal, $nro).': '.$e->getMessage();
            }
        }

        return $ret;
    }

    /**
     * Números con cabecera Anita para la empresa/PV indicados (filtra ven_empresa y tipo FAK/FAC).
     *
     * @return list<int>
     */
    public function listarNumerosDisponiblesEnAnita(
        int $sucursal,
        int $desde,
        int $hasta,
        int $empresaId,
        ?string $identificadorPc = null,
    ): array {
        if ($desde <= 0 || $hasta < $desde) {
            return [];
        }

        $ctx = $this->resolverContexto($sucursal, $empresaId, $identificadorPc);

        try {
            return $this->listarNumerosAnitaEnRango($sucursal, $desde, $hasta, $ctx);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return 'importado'|'omitido'|'vinculado'
     */
    public function importarNumero(
        int $sucursal,
        int $nro,
        int $empresaId,
        int $usuarioId,
        bool $dryRun = false,
        ?string $identificadorPc = null,
        ?string $tipoAnita = null,
    ): string {
        if ($sucursal <= 0 || $nro <= 0) {
            throw new InvalidArgumentException('Comprobante inválido.');
        }

        $ctx = $this->resolverContexto($sucursal, $empresaId, $identificadorPc);

        return $this->importarUno($sucursal, $nro, $ctx, $usuarioId, $dryRun, $tipoAnita);
    }

    /**
     * @return 'importado'|'omitido'|'vinculado'
     */
    private function importarUno(
        int $sucursal,
        int $nro,
        array $ctx,
        int $usuarioId,
        bool $dryRun,
        ?string $tipoAnita = null,
    ): string {
        $tipoCodigoErp = strtoupper(trim((string) ($ctx['tipo_codigo_erp'] ?? $ctx['tipo_anita'] ?? self::TIPO)));
        $codigo = $this->armarCodigo($sucursal, $nro, $tipoCodigoErp);

        $ventaExistente = $this->buscarVentaErpExistente($sucursal, $nro, $ctx, $tipoCodigoErp);
        if ($ventaExistente !== null) {
            if ($ventaExistente->gastronomiaEmision()->exists()) {
                return 'omitido';
            }

            if (GastronomiaAnitaImportEstacionamientoSupport::debeOmitirCircuitoGastronomia($ventaExistente)) {
                return 'omitido_estacionamiento';
            }

            if ($dryRun) {
                return 'vinculado';
            }

            $this->vincularEmisionGastronomia($ventaExistente, $sucursal, $nro, $ctx, $usuarioId, $tipoAnita);

            return 'vinculado';
        }

        $cab = $this->leerCabeceraAnita($sucursal, $nro, $ctx, $tipoAnita ?? $tipoCodigoErp);
        if ($cab === null) {
            throw new InvalidArgumentException('Sin cabecera venta en Anita.');
        }

        $tipoAnita = strtoupper(trim((string) ($cab->ven_tipo ?? $ctx['tipo_anita'] ?? self::TIPO)));
        $empresaCodigo = (string) ($ctx['empresa_codigo'] ?? '');
        $lineasStk = $this->leerStkmov($sucursal, $nro, $tipoAnita, $empresaCodigo, (int) $ctx['empresa_id']);
        $total = round(abs((float) ($cab->ven_monto ?? 0)), 2);
        $montoDesc = GastronomiaAnitaImportDescuentoSupport::montoDescDesdeCabecera($cab);
        if ($lineasStk === [] && $total <= 0 && $montoDesc <= 0) {
            throw new InvalidArgumentException('Sin ítems stkmov ni monto en Anita.');
        }

        $vengrav = $this->leerVengrav($sucursal, $nro, $tipoAnita, $empresaCodigo, (int) $ctx['empresa_id']);
        $vencae = $this->leerVencae($sucursal, $nro, $tipoAnita, $empresaCodigo, (int) $ctx['empresa_id']);
        $resvta = $this->leerResvta($sucursal, $nro, $tipoAnita, $empresaCodigo, (int) $ctx['empresa_id']);

        if (GastronomiaAnitaImportEstacionamientoSupport::esResvtaEstacionamiento($resvta)) {
            return 'omitido';
        }

        $descuentoImport = GastronomiaAnitaImportDescuentoSupport::resolverDesdeResvta($resvta, (int) $ctx['empresa_id']);
        $lineasEmision = GastronomiaAnitaImportDescuentoSupport::debeUsarLineaFicticiaVenMontoDesc($resvta, $cab)
            ? []
            : $lineasStk;

        $fecha = $this->parseFechaAnita((string) ($cab->ven_fecha ?? ''));
        $fechaJornada = $this->parseFechaJornadaAnita($cab, $fecha);

        $mozo = $this->resolverMozo($cab, (int) $ctx['empresa_id']);
        $medios = $this->resolverMediosPago($resvta, $total, (int) $ctx['empresa_id']);
        $sinCobranza = GastronomiaAnitaImportMediosPagoSupport::esCortesiaSinCobranza($total, $medios);

        if ($dryRun) {
            return 'importado';
        }

        $timestamp = $this->resolverTimestampImport(
            $nro,
            $fechaJornada,
            $resvta,
            (string) $ctx['identificador_pc'],
            (int) $ctx['empresa_id'],
        );

        DB::transaction(function () use (
            $cab,
            $lineasEmision,
            $vengrav,
            $vencae,
            $resvta,
            $ctx,
            $usuarioId,
            $codigo,
            $nro,
            $fecha,
            $fechaJornada,
            $total,
            $mozo,
            $medios,
            $sinCobranza,
            $timestamp,
            $descuentoImport,
        ) {
            $venta = Venta::query()->create([
                'fecha' => $fecha,
                'fechajornada' => $fechaJornada,
                'tipotransaccion_id' => (int) $ctx['tipotransaccion_id'],
                'puntoventa_id' => (int) $ctx['puntoventa_id'],
                'numerocomprobante' => $nro,
                'actividad_arca_id' => (int) config('gastronomia_anita_import.actividad_arca_id', 1),
                'cliente_id' => (int) config('gastronomia_anita_import.cliente_consumidor_final_id', 1),
                'condicionventa_id' => null,
                'vendedor_id' => null,
                'transporte_id' => null,
                'total' => $total,
                'moneda_id' => (int) ($cab->ven_cod_mon ?? 1),
                'cotizacion' => (float) ($cab->ven_cotizacion ?? 1) ?: 1.,
                'estado' => ' ',
                'usuario_id' => $usuarioId,
                'leyenda' => 'Generada en Anita POS — importación '.$codigo,
                'descuento' => (float) ($cab->ven_porc_desc ?? 0),
                'descuentointegrado' => ' ',
                'lugarentrega' => null,
                'cliente_entrega_id' => null,
                'codigo' => $codigo,
                'nombre' => trim((string) ($cab->ven_nombre_cliente ?? 'CONSUMIDOR FINAL')) ?: 'CONSUMIDOR FINAL',
                'domicilio' => (string) ($cab->ven_direccion_cli ?? ''),
                'localidad_id' => null,
                'provincia_id' => null,
                'pais_id' => 1,
                'codigopostal' => (string) ($cab->ven_cod_postal_cli ?? ''),
                'email' => null,
                'telefono' => null,
                'numerodocumento' => (string) ($cab->ven_cuit_cli ?? '0'),
                'condicioniva_id' => (int) config('gastronomia_anita_import.condicioniva_consumidor_final_id', 3),
                'cae' => $vencae?->venc_nro_cae ?? null,
                'fechavencimientocae' => $this->parseFechaCae($vencae?->venc_fecha_vto ?? null),
                'puntoventaremito_id' => null,
                'numeroremito' => 0,
                'cantidadbulto' => 1,
            ]);

            $venta->created_at = $timestamp;
            $venta->updated_at = $timestamp;
            $venta->save();

            $this->crearEmisiones($venta->id, $lineasEmision, $cab, $total, (int) ($cab->ven_cod_mon ?? 1), $timestamp, $resvta);
            $this->crearImpuestos($venta->id, $cab, $vengrav, $timestamp);

            $cuenta = CuentaGastronomia::query()->create([
                'tipo' => CuentaGastronomia::TIPO_CUENTA,
                'origen_pos' => CuentaGastronomia::ORIGEN_IMPORT_ANITA,
                'empresa_id' => (int) $ctx['empresa_id'],
                'mesa_gastronomia_id' => null,
                'mozo_gastronomia_id' => $mozo?->id,
                'cubiertos' => 1,
                'estado' => CuentaGastronomia::ESTADO_FACTURADA,
                'identificador_pc' => (string) $ctx['identificador_pc'],
                'cliente_id' => (int) config('gastronomia_anita_import.cliente_consumidor_final_id', 1),
                'descuento_gastronomia_id' => $descuentoImport['descuento_gastronomia_id'],
                'cliente_interno_descuento_id' => $descuentoImport['cliente_interno_descuento_id'],
                'configuracion_puntoventa_gastronomia_id' => (int) $ctx['configuracion_id'],
                'venta_id' => $venta->id,
            ]);
            $cuenta->created_at = $timestamp;
            $cuenta->updated_at = $timestamp;
            $cuenta->save();

            VentaGastronomiaEmision::query()->create([
                'venta_id' => $venta->id,
                'cuenta_gastronomia_id' => $cuenta->id,
                'origen_pos' => CuentaGastronomia::ORIGEN_IMPORT_ANITA,
                'identificador_pc' => (string) $ctx['identificador_pc'],
                'configuracion_puntoventa_gastronomia_id' => (int) $ctx['configuracion_id'],
            ]);

            if (! $sinCobranza && $medios !== [] && ! $this->existeCobranzaActivaParaNumerotransaccion((int) $ctx['empresa_id'], $codigo)) {
                session(['empresa_id' => (int) $ctx['empresa_id']]);
                $this->eliminarCobranzaHuerfanaAnulada((int) $ctx['empresa_id'], $codigo);
                $this->cobranzaGastronomiaService->registrarCobranzaPos(
                    $venta->fresh(),
                    $medios,
                    $ctx['configuracion'],
                    false,
                );
            }
        });

        return 'importado';
    }

    private function vincularEmisionGastronomia(
        Venta $venta,
        int $sucursal,
        int $nro,
        array $ctx,
        int $usuarioId,
        ?string $tipoAnita = null,
    ): void {
        if ($venta->gastronomiaEmision()->exists()) {
            return;
        }

        $tipo = strtoupper(trim($tipoAnita ?? (string) ($ctx['tipo_anita'] ?? self::TIPO)));
        $cab = $this->leerCabeceraAnita($sucursal, $nro, $ctx, $tipo);
        if ($cab === null) {
            throw new InvalidArgumentException('Sin cabecera venta en Anita para vincular emisión.');
        }

        $tipoAnita = strtoupper(trim((string) ($cab->ven_tipo ?? $ctx['tipo_anita'] ?? self::TIPO)));
        $empresaCodigo = (string) ($ctx['empresa_codigo'] ?? '');
        $resvta = $this->leerResvta($sucursal, $nro, $tipoAnita, $empresaCodigo, (int) $ctx['empresa_id']);

        if (GastronomiaAnitaImportEstacionamientoSupport::debeOmitirCircuitoGastronomia($venta, $resvta)) {
            throw new InvalidArgumentException('Comprobante de estacionamiento; no se vincula a gastronomía.');
        }

        $fecha = $this->parseFechaAnita((string) ($cab->ven_fecha ?? ''));
        $fechaJornada = $this->parseFechaJornadaAnita($cab, $fecha);
        $total = round(abs((float) ($venta->total ?? $cab->ven_monto ?? 0)), 2);
        $mozo = $this->resolverMozo($cab, (int) $ctx['empresa_id']);
        $descuentoImport = GastronomiaAnitaImportDescuentoSupport::resolverDesdeResvta($resvta, (int) $ctx['empresa_id']);
        $lineasEmision = GastronomiaAnitaImportDescuentoSupport::debeUsarLineaFicticiaVenMontoDesc($resvta, $cab)
            ? []
            : $this->leerStkmov($sucursal, $nro, $tipoAnita, $empresaCodigo, (int) $ctx['empresa_id']);
        $timestamp = $this->resolverTimestampImport(
            $nro,
            $fechaJornada,
            $resvta,
            (string) $ctx['identificador_pc'],
            (int) $ctx['empresa_id'],
        );

        DB::transaction(function () use ($venta, $ctx, $mozo, $timestamp, $total, $fechaJornada, $cab, $resvta, $descuentoImport, $lineasEmision): void {
            if ($venta->fechajornada === null || (string) $venta->fechajornada === '') {
                $venta->fechajornada = $fechaJornada;
                $venta->save();
            }

            if ($venta->venta_emisiones()->count() === 0) {
                $this->crearEmisiones(
                    (int) $venta->id,
                    $lineasEmision,
                    $cab,
                    $total,
                    (int) ($venta->moneda_id ?? $cab->ven_cod_mon ?? 1),
                    $timestamp,
                    $resvta,
                );
            }

            $cuenta = CuentaGastronomia::query()->create([
                'tipo' => CuentaGastronomia::TIPO_CUENTA,
                'origen_pos' => CuentaGastronomia::ORIGEN_SALON,
                'empresa_id' => (int) $ctx['empresa_id'],
                'mesa_gastronomia_id' => null,
                'mozo_gastronomia_id' => $mozo?->id,
                'cubiertos' => 1,
                'estado' => CuentaGastronomia::ESTADO_FACTURADA,
                'identificador_pc' => (string) $ctx['identificador_pc'],
                'cliente_id' => (int) ($venta->cliente_id ?? config('gastronomia_anita_import.cliente_consumidor_final_id', 1)),
                'descuento_gastronomia_id' => $descuentoImport['descuento_gastronomia_id'],
                'cliente_interno_descuento_id' => $descuentoImport['cliente_interno_descuento_id'],
                'configuracion_puntoventa_gastronomia_id' => (int) $ctx['configuracion_id'],
                'venta_id' => $venta->id,
            ]);
            $cuenta->created_at = $timestamp;
            $cuenta->updated_at = $timestamp;
            $cuenta->save();

            VentaGastronomiaEmision::query()->create([
                'venta_id' => $venta->id,
                'cuenta_gastronomia_id' => $cuenta->id,
                'origen_pos' => CuentaGastronomia::ORIGEN_SALON,
                'identificador_pc' => (string) $ctx['identificador_pc'],
                'configuracion_puntoventa_gastronomia_id' => (int) $ctx['configuracion_id'],
            ]);
        });
    }

    /**
     * @return array{
     *   empresa_id:int,
     *   puntoventa_id:int,
     *   tipotransaccion_id:int,
     *   configuracion_id:int,
     *   configuracion:ConfiguracionPuntoventaGastronomia,
     *   identificador_pc:string
     * }
     */
    private function resolverContexto(int $sucursal, int $empresaId, ?string $identificadorPc): array
    {
        $puntoventa = Puntoventa::query()
            ->with('empresas')
            ->where('codigo', $sucursal)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($puntoventa === null) {
            throw new InvalidArgumentException(
                'Punto de venta ERP código '.$sucursal.' no encontrado para empresa '.$empresaId.'.',
            );
        }

        $empresaId = (int) $puntoventa->empresa_id;

        $pc = $identificadorPc
            ?? (string) (config('gastronomia_anita_import.identificador_pc_por_sucursal')[$sucursal] ?? '');
        if ($pc === '') {
            throw new InvalidArgumentException('Defina identificador_pc para sucursal '.$sucursal.'.');
        }

        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->where('identificador_pc', $pc)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($cfg === null) {
            throw new InvalidArgumentException('Sin configuración PV gastronomía para PC '.$pc.' empresa '.$empresaId.'.');
        }

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? config('gastronomia.tipotransaccion_factura_id', 1));
        $empresaCodigo = GastronomiaAnitaImportEmpresaSupport::codigoEmpresa($empresaId);
        $tipoAnita = GastronomiaAnitaImportEmpresaSupport::tipoVentaAnita($puntoventa, $empresaCodigo);

        return [
            'empresa_id' => $empresaId,
            'empresa_codigo' => $empresaCodigo,
            'puntoventa_id' => (int) $puntoventa->id,
            'puntoventa_codigo' => (string) $puntoventa->codigo,
            'puntoventa' => $puntoventa,
            'tipotransaccion_id' => $tipoFacturaId,
            'configuracion_id' => (int) $cfg->id,
            'configuracion' => $cfg,
            'identificador_pc' => $pc,
            'tipo_anita' => $tipoAnita,
            'tipo_codigo_erp' => GastronomiaAnitaImportEmpresaSupport::tipoCodigoErpImport($puntoventa, $empresaCodigo),
            'tipos_anita_lectura' => GastronomiaAnitaImportEmpresaSupport::tiposCabeceraLecturaImport($puntoventa, $empresaCodigo),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return list<int>
     */
    private function listarNumerosAnitaEnRango(int $sucursal, int $desde, int $hasta, array $ctx): array
    {
        if ($this->cacheReader !== null) {
            $numeros = [];
            foreach ($this->cacheReader->numerosEnRango($sucursal, $desde, $hasta, $ctx['tipos_anita_lectura']) as $nro) {
                $cab = $this->leerCabeceraAnita($sucursal, $nro, $ctx);
                if ($cab !== null) {
                    $numeros[$nro] = $nro;
                }
            }
            if ($numeros === []) {
                throw new \RuntimeException('No se encontraron facturas en cache local para sucursal '.$sucursal.'.');
            }
            ksort($numeros);

            return array_values($numeros);
        }

        $numeros = [];

        foreach ($ctx['tipos_anita_lectura'] as $tipo) {
            $raw = $this->apiAnita([
                'acc' => 'list',
                'tabla' => 'venta',
                'campos' => 'ven_nro',
                'whereArmado' => $this->whereComprobante(
                    $sucursal,
                    $desde,
                    $hasta,
                    'ven',
                    (string) $tipo,
                    (string) ($ctx['empresa_codigo'] ?? ''),
                ),
            ], (int) $ctx['empresa_id']);
            $lista = json_decode($raw);
            if (! is_array($lista)) {
                continue;
            }
            foreach ($lista as $item) {
                $nro = (int) ($item->ven_nro ?? 0);
                if ($nro > 0) {
                    $numeros[$nro] = $nro;
                }
            }
        }

        if ($numeros === []) {
            throw new \RuntimeException('No se pudo listar facturas en Anita.');
        }

        ksort($numeros);

        return array_values($numeros);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function leerCabeceraAnita(int $sucursal, int $nro, array $ctx, ?string $tipoPreferido = null): ?stdClass
    {
        /** @var Puntoventa $puntoventa */
        $puntoventa = $ctx['puntoventa'];

        $tipos = $ctx['tipos_anita_lectura'];
        if ($tipoPreferido !== null && trim($tipoPreferido) !== '') {
            $preferido = strtoupper(trim($tipoPreferido));
            $tipos = array_values(array_unique([$preferido, ...$tipos]));
        }

        if ($this->cacheReader !== null) {
            $cab = $this->cacheReader->cabecera($sucursal, $nro, $tipos);
            if ($cab !== null && GastronomiaAnitaImportEmpresaSupport::cabeceraCorrespondeAlPv(
                $cab,
                $puntoventa,
                $ctx['empresa_codigo'] ?? null,
            )) {
                return $cab;
            }

            return null;
        }

        $campos = implode(',', [
            'ven_tipo', 'ven_empresa', 'ven_fecha', 'ven_fecha_vto', 'ven_monto', 'ven_gravado', 'ven_exento', 'ven_impuesto1',
            'ven_porc_desc', 'ven_monto_desc', 'ven_cod_mon', 'ven_cotizacion',
            'ven_nombre_cliente', 'ven_direccion_cli', 'ven_cod_postal_cli', 'ven_cuit_cli',
            'ven_vendedor',
        ]);
        $empresaId = (int) $ctx['empresa_id'];

        foreach ($tipos as $tipo) {
            $raw = $this->apiAnita([
                'acc' => 'list',
                'tabla' => 'venta',
                'campos' => $campos,
                'whereArmado' => $this->whereComprobante(
                    $sucursal,
                    $nro,
                    $nro,
                    'ven',
                    (string) $tipo,
                    (string) ($ctx['empresa_codigo'] ?? ''),
                ),
            ], $empresaId);
            $cab = ApiAnita::primeraFilaLista((string) $raw);
            if ($cab !== null && GastronomiaAnitaImportEmpresaSupport::cabeceraCorrespondeAlPv(
                $cab,
                $puntoventa,
                $ctx['empresa_codigo'] ?? null,
            )) {
                return $cab;
            }
        }

        return null;
    }

    /**
     * @return list<stdClass>
     */
    private function leerStkmov(int $sucursal, int $nro, string $tipoAnita, string $empresaCodigo, int $empresaId): array
    {
        $tipos = GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita);
        if ($this->cacheReader !== null) {
            $lista = $this->cacheReader->stkmov($sucursal, $nro, $tipos);

            return $lista !== [] ? $lista : [];
        }

        foreach ($tipos as $tipo) {
            $lista = $this->leerTablaDetalle(
                'stkmov',
                'stkv_articulo,stkv_cantidad,stkv_precio,stkv_cod_impuesto,stkv_descuento',
                $sucursal,
                $nro,
                'stkv',
                $tipo,
                $empresaCodigo,
                $empresaId,
            );
            if ($lista !== []) {
                return $lista;
            }
        }

        return [];
    }

    /**
     * @return list<stdClass>
     */
    private function leerVengrav(int $sucursal, int $nro, string $tipoAnita, string $empresaCodigo, int $empresaId): array
    {
        $tipos = GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita);
        if ($this->cacheReader !== null) {
            $lista = $this->cacheReader->vengrav($sucursal, $nro, $tipos);

            return $lista !== [] ? $lista : [];
        }

        foreach ($tipos as $tipo) {
            $lista = $this->leerTablaDetalle(
                'vengrav',
                'veng_codigo_tasa,veng_gravado,veng_impuesto,veng_tasa',
                $sucursal,
                $nro,
                'veng',
                $tipo,
                $empresaCodigo,
                $empresaId,
            );
            if ($lista !== []) {
                return $lista;
            }
        }

        return [];
    }

    private function leerVencae(int $sucursal, int $nro, string $tipoAnita, string $empresaCodigo, int $empresaId): ?stdClass
    {
        $tipos = GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita);
        if ($this->cacheReader !== null) {
            return $this->cacheReader->vencae($sucursal, $nro, $tipos);
        }

        foreach ($tipos as $tipo) {
            $raw = $this->apiAnita([
                'acc' => 'list',
                'tabla' => 'vencae',
                'campos' => 'venc_nro_cae,venc_fecha_vto',
                'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro, 'venc', $tipo, $empresaCodigo),
            ], $empresaId);
            $cab = ApiAnita::primeraFilaLista((string) $raw);
            if ($cab !== null) {
                return $cab;
            }
        }

        return null;
    }

    private function leerResvta(int $sucursal, int $nro, string $tipoAnita, string $empresaCodigo, int $empresaId): ?stdClass
    {
        $tipos = GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoAnita);
        if ($this->cacheReader !== null) {
            return $this->cacheReader->resvta($sucursal, $nro, $tipos);
        }

        foreach ($tipos as $tipo) {
            $raw = $this->apiAnita([
                'acc' => 'list',
                'tabla' => 'resvta',
                'campos' => implode(',', [
                    'resv_fecha', 'resv_hora', 'resv_host', 'resv_cubierto', 'resv_mozo', 'resv_total',
                    'resv_tot_efectivo', 'resv_tot_fiserv', 'resv_tot_qr', 'resv_tot_ctacte', 'resv_tot_tarjeta',
                    'resv_tipo_dto', 'resv_cliente',
                ]),
                'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro, 'resv', $tipo, $empresaCodigo),
            ], $empresaId);
            $cab = ApiAnita::primeraFilaLista((string) $raw);
            if ($cab !== null) {
                return $cab;
            }
        }

        return null;
    }

    /**
     * @return list<stdClass>
     */
    private function leerTablaDetalle(
        string $tabla,
        string $campos,
        int $sucursal,
        int $nro,
        string $prefijo,
        string $tipo,
        string $empresaCodigo,
        int $empresaId,
    ): array {
        $raw = $this->apiAnita([
            'acc' => 'list',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $this->whereComprobante($sucursal, $nro, $nro, $prefijo, $tipo, $empresaCodigo),
        ], $empresaId);
        $lista = json_decode($raw);

        return is_array($lista) ? $lista : [];
    }

    /**
     * Reemplaza renglones ficticios de import Anita por líneas stkmov reales (desde cache local).
     *
     * @param  list<stdClass|object>  $lineasStk
     * @return int Cantidad de renglones creados
     */
    public function regenerarEmisionesDesdeStkmov(
        int $ventaId,
        array $lineasStk,
        int $monedaId,
        Carbon $timestamp,
    ): int {
        if ($lineasStk === []) {
            throw new InvalidArgumentException('Sin líneas stkmov para regenerar emisión.');
        }

        return (int) DB::transaction(function () use ($ventaId, $lineasStk, $monedaId, $timestamp): int {
            Venta_Emision::query()->where('venta_id', $ventaId)->delete();
            $cab = (object) ['ven_monto_desc' => 0, 'ven_cod_mon' => $monedaId];
            $this->crearEmisiones($ventaId, $lineasStk, $cab, 0., $monedaId, $timestamp, null);

            return (int) Venta_Emision::query()->where('venta_id', $ventaId)->count();
        });
    }

    /**
     * @param  list<stdClass>  $lineasStk
     */
    private function crearEmisiones(
        int $ventaId,
        array $lineasStk,
        stdClass $cab,
        float $total,
        int $monedaId,
        Carbon $timestamp,
        ?stdClass $resvta = null,
    ): void {
        if ($lineasStk === []) {
            $montoDesc = GastronomiaAnitaImportDescuentoSupport::montoDescDesdeCabecera($cab);
            $precio = $montoDesc > 0 ? $montoDesc : max($total, 0.01);
            $esCortesia = $total <= 0.02;
            $impuestoId = (int) config('gastronomia.impuesto_exento_id', 1);
            $detalle = GastronomiaAnitaImportDescuentoSupport::tieneCodigoDescuentoResvta($resvta)
                ? 'Invitación / descuento import Anita'
                : 'Cortesía import Anita';

            $em = Venta_Emision::query()->create([
                'venta_id' => $ventaId,
                'numeroitem' => 1,
                'articulo_id' => $this->articuloCortesiaId(),
                'detalle' => $detalle,
                'cantidad' => 1,
                'precio' => $precio,
                'impuesto_id' => $impuestoId,
                'incluyeimpuesto' => $esCortesia ? 'N' : '1',
                'moneda_id' => $monedaId,
                'lotestock' => 0,
                'descuento' => 0,
                'descuentointegrado' => ' ',
            ]);
            $em->created_at = $timestamp;
            $em->updated_at = $timestamp;
            $em->save();

            return;
        }

        $item = 0;
        foreach ($lineasStk as $stk) {
            $skuRaw = (string) ($stk->stkv_articulo ?? '');
            if ($skuRaw === '' || $skuRaw === 'texto') {
                continue;
            }

            $sku = ltrim($skuRaw, '0');
            $articulo = Articulo::query()->where('sku', $sku)->orWhere('sku', $skuRaw)->first();
            if ($articulo === null) {
                throw new InvalidArgumentException('Artículo SKU '.$sku.' no existe en ERP.');
            }

            $impuestoId = (int) ($stk->stkv_cod_impuesto ?? 0) ?: null;
            $tasa = 0.;
            if ($impuestoId) {
                $imp = Impuesto::query()->find($impuestoId);
                $tasa = $imp ? (float) $imp->valor : 0.;
            }

            $precioNeto = (float) ($stk->stkv_precio ?? 0);
            $precio = $tasa > 0 ? round($precioNeto * (1 + $tasa / 100), 6) : $precioNeto;

            $em = Venta_Emision::query()->create([
                'venta_id' => $ventaId,
                'numeroitem' => ++$item,
                'pedido_combinacion_id' => null,
                'ordentrabajo_id' => null,
                'lotestock' => 0,
                'articulo_id' => (int) $articulo->id,
                'combinacion_id' => null,
                'detalle' => (string) ($articulo->descripcion ?? $articulo->sku),
                'modulo_id' => null,
                'talle_id' => null,
                'cantidad' => abs((float) ($stk->stkv_cantidad ?? 0)),
                'precio' => $precio,
                'impuesto_id' => $impuestoId,
                'incluyeimpuesto' => '1',
                'moneda_id' => $monedaId,
                'descuento' => (float) ($stk->stkv_descuento ?? 0),
                'descuentointegrado' => ' ',
                'deposito_id' => null,
                'loteimportacion_id' => null,
            ]);
            $em->created_at = $timestamp;
            $em->updated_at = $timestamp;
            $em->save();
        }

        if ($item === 0) {
            throw new InvalidArgumentException('No se generaron renglones de emisión.');
        }
    }

    /**
     * @param  list<stdClass>  $vengrav
     */
    private function crearImpuestos(int $ventaId, stdClass $cab, array $vengrav, Carbon $timestamp): void
    {
        $total = round(abs((float) ($cab->ven_monto ?? 0)), 2);

        if (GastronomiaAnitaVenGravadoSupport::esCortesiaMinima($total)) {
            $this->grabarFilasImpuesto($ventaId, GastronomiaAnitaVenGravadoSupport::filasVentaImpuestoImportCortesiaMinima(), $timestamp);

            return;
        }

        $gravado = round((float) ($cab->ven_gravado ?? 0) + (float) ($cab->ven_exento ?? 0), 2);
        $iva = round((float) ($cab->ven_impuesto1 ?? 0), 2);

        $filas = [
            ['concepto' => 'Subtotal', 'base' => 0., 'tasa' => 0., 'importe' => $gravado, 'impuesto_id' => null],
        ];

        foreach ($vengrav as $vg) {
            $tasa = (float) ($vg->veng_tasa ?? 0);
            $filas[] = [
                'concepto' => 'Gravado al '.number_format($tasa, 3, '.', '').'%',
                'base' => 0.,
                'tasa' => $tasa,
                'importe' => round((float) ($vg->veng_gravado ?? 0), 2),
                'impuesto_id' => (int) ($vg->veng_codigo_tasa ?? 0) ?: null,
            ];
            $filas[] = [
                'concepto' => 'Iva '.number_format($tasa, 3, '.', '').'%',
                'base' => round((float) ($vg->veng_gravado ?? 0), 2),
                'tasa' => $tasa,
                'importe' => round((float) ($vg->veng_impuesto ?? 0), 2),
                'impuesto_id' => (int) ($vg->veng_codigo_tasa ?? 0) ?: null,
            ];
        }

        if ($vengrav === [] && $iva > 0) {
            $filas[] = [
                'concepto' => 'Iva 21.000%',
                'base' => $gravado,
                'tasa' => 21.,
                'importe' => $iva,
                'impuesto_id' => 3,
            ];
        }

        $filas[] = ['concepto' => 'Total', 'base' => 0., 'tasa' => 0., 'importe' => $total, 'impuesto_id' => null];

        $this->grabarFilasImpuesto($ventaId, $filas, $timestamp);
    }

    /**
     * @param  list<array{concepto: string, base?: float, baseimponible?: float, tasa: float, importe: float, impuesto_id: int|null}>  $filas
     */
    private function grabarFilasImpuesto(int $ventaId, array $filas, Carbon $timestamp): void
    {
        foreach ($filas as $f) {
            if (abs($f['importe']) < 0.0001 && $f['concepto'] !== 'Total') {
                continue;
            }
            $vi = Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => $f['concepto'],
                'baseimponible' => $f['baseimponible'] ?? $f['base'] ?? 0.,
                'tasa' => $f['tasa'],
                'importe' => $f['importe'],
                'provincia_id' => null,
                'impuesto_id' => $f['impuesto_id'],
            ]);
            $vi->created_at = $timestamp;
            $vi->updated_at = $timestamp;
            $vi->save();
        }
    }

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>
     */
    private function resolverMediosPago(?stdClass $resvta, float $total, int $empresaId): array
    {
        if ($resvta !== null) {
            return GastronomiaAnitaImportMediosPagoSupport::lineasDesdeResvta($resvta, $empresaId);
        }

        if ($total <= 0.02) {
            return [];
        }

        $efectivo = \App\Support\Ventas\GastronomiaCuentacajaEfectivo::cuentaParaEmpresa($empresaId);
        if ($efectivo === null) {
            throw new InvalidArgumentException('Sin resvta y sin cuenta efectivo para fallback.');
        }

        return [[
            'cuentacaja_id' => (int) $efectivo['id'],
            'moneda_id' => (int) $efectivo['moneda_id'],
            'monto' => round($total, 2),
            'cotizacion' => 1.,
            'observacion' => 'Import Anita (sin resvta, efectivo)',
        ]];
    }

    private function resolverMozo(stdClass $cab, int $empresaId): ?MozoGastronomia
    {
        $codigo = isset($cab->ven_vendedor) ? trim((string) $cab->ven_vendedor) : '';
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        return $this->mozoRepository->findPorCodigo($codigo, $empresaId);
    }

    private function armarCodigo(int $sucursal, int $nro, string $tipo = self::TIPO): string
    {
        $digSuc = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digNro = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
        $tipo = strtoupper(trim($tipo));

        return $tipo.' '.self::LETRA.'-'
            .str_pad((string) $sucursal, $digSuc, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $nro, $digNro, '0', STR_PAD_LEFT);
    }

    /**
     * Evita duplicar si ya existe FAK o alias FAC (Kandiko CAEA) o la misma numeración gastronomía.
     *
     * @param  array<string, mixed>  $ctx
     */
    private function buscarVentaErpExistente(int $sucursal, int $nro, array $ctx, string $tipoCodigoErp): ?Venta
    {
        $codigo = $this->armarCodigo($sucursal, $nro, $tipoCodigoErp);
        $venta = Venta::query()->where('codigo', $codigo)->first();
        if ($venta !== null) {
            return $venta;
        }

        /** @var Puntoventa $puntoventa */
        $puntoventa = $ctx['puntoventa'];
        $empresaCodigo = $ctx['empresa_codigo'] ?? null;

        if (KandikoAnitaVentaTipoSupport::esPvCaeaKandiko(
            (string) $puntoventa->codigo,
            $empresaCodigo,
            $puntoventa->modofacturacion ?? null,
        )) {
            foreach (KandikoAnitaVentaTipoSupport::tiposAnitaEquivalentesFacErp() as $tipoAlias) {
                if ($tipoAlias === $tipoCodigoErp) {
                    continue;
                }
                $alias = $this->armarCodigo($sucursal, $nro, $tipoAlias);
                $venta = Venta::query()->where('codigo', $alias)->first();
                if ($venta !== null) {
                    return $venta;
                }
            }
        }

        return Venta::query()
            ->where('puntoventa_id', (int) $ctx['puntoventa_id'])
            ->where('numerocomprobante', $nro)
            ->first();
    }

    private function whereComprobante(
        int $sucursal,
        int $desde,
        ?int $hasta = null,
        string $prefijo = 'ven',
        string $tipo = self::TIPO,
        string $empresaCodigo = '',
    ): string {
        $hasta ??= $desde;
        $s = (string) $sucursal;
        $tipo = addslashes(strtoupper(trim($tipo)));

        return ' WHERE '.$prefijo."_tipo='".$tipo."' AND ".$prefijo."_letra='".self::LETRA
            ."' AND ".$prefijo."_sucursal='".$s."' AND ".$prefijo.'_nro >= '.$desde
            .' AND '.$prefijo.'_nro <= '.$hasta
            .GastronomiaAnitaImportEmpresaSupport::whereEmpresa($prefijo, $empresaCodigo);
    }

    private function parseFechaAnita(string $yyyymmdd): string
    {
        if (strlen($yyyymmdd) !== 8) {
            return Carbon::today()->format('Y-m-d');
        }

        return Carbon::createFromFormat('Ymd', $yyyymmdd)->format('Y-m-d');
    }

    private function parseFechaJornadaAnita(stdClass $cab, string $fechaFallback): string
    {
        $jornada = trim((string) ($cab->ven_fecha_vto ?? ''));
        if (strlen($jornada) === 8 && ctype_digit($jornada)) {
            return $this->parseFechaAnita($jornada);
        }

        return $fechaFallback;
    }

    private function parseFechaCae(mixed $yyyymmdd): ?string
    {
        if ($yyyymmdd === null || $yyyymmdd === '' || $yyyymmdd === 0) {
            return null;
        }

        return $this->parseFechaAnita((string) $yyyymmdd);
    }

    /**
     * Marca temporal ERP para imports históricos: cae en la jornada (fechajornada), no en now()
     * ni en ven_fecha calendario. Se reparte entre apertura de jornada y el turno habilitado
     * abierto en la misma PC (si existe), para no inflar el cierre de turno activo.
     */
    private function resolverTimestampImport(
        int $numeroComprobante,
        string $fechaJornada,
        ?stdClass $resvta,
        string $identificadorPc,
        int $empresaId,
    ): Carbon {
        if ($resvta !== null && ! empty($resvta->resv_hora)) {
            $fechaBase = ! empty($resvta->resv_fecha)
                ? $this->parseFechaAnita((string) $resvta->resv_fecha)
                : $fechaJornada;

            try {
                return Carbon::parse($fechaBase.' '.$this->parseHoraAnita($resvta->resv_hora));
            } catch (\Throwable) {
                // continúa con ventana de jornada
            }
        }

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->first();

        $desde = $jornada?->apertura_en !== null
            ? Carbon::parse((string) $jornada->apertura_en)
            : Carbon::parse($fechaJornada.' 18:00:00');

        if ($desde->format('Y-m-d') !== $fechaJornada) {
            $desde = Carbon::parse($fechaJornada.' 05:00:00');
        }

        $turnoAbierto = TurnoOperativoGastronomia::query()
            ->where('identificador_pc', $identificadorPc)
            ->where('empresa_id', $empresaId)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO)
            ->when($jornada !== null, fn ($q) => $q->where('jornada_gastronomia_id', (int) $jornada->id))
            ->when($jornada === null, fn ($q) => $q->whereHas('jornada', fn ($jq) => $jq->whereDate('fecha_jornada', $fechaJornada)))
            ->orderByDesc('habilitacion_en')
            ->first();

        $hasta = $turnoAbierto !== null
            ? Carbon::parse((string) $turnoAbierto->habilitacion_en)->subSecond()
            : Carbon::parse($fechaJornada.' 23:59:59');

        // Último turno cerrado de la misma jornada/PC: tope para no caer en hueco ni en turno noche abierto.
        $ultimoCerrado = TurnoOperativoGastronomia::query()
            ->where('identificador_pc', $identificadorPc)
            ->where('empresa_id', $empresaId)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->when($jornada !== null, fn ($q) => $q->where('jornada_gastronomia_id', (int) $jornada->id))
            ->when($jornada === null, fn ($q) => $q->whereHas('jornada', fn ($jq) => $jq->whereDate('fecha_jornada', $fechaJornada)))
            ->whereNotNull('cierre_en')
            ->orderByDesc('cierre_en')
            ->first();

        if ($ultimoCerrado?->cierre_en !== null) {
            $cierreUltimo = Carbon::parse((string) $ultimoCerrado->cierre_en);
            if ($turnoAbierto !== null) {
                $aperturaAbierto = Carbon::parse((string) $turnoAbierto->habilitacion_en);
                if ($cierreUltimo->lt($aperturaAbierto) && $hasta->gt($cierreUltimo)) {
                    $hasta = $cierreUltimo;
                }
            } elseif ($hasta->gt($cierreUltimo)) {
                $hasta = $cierreUltimo;
            }
        }

        if ($jornada !== null
            && $jornada->estado === JornadaGastronomia::ESTADO_CERRADA
            && $jornada->cierre_en !== null) {
            $cierreJornada = Carbon::parse((string) $jornada->cierre_en);
            if ($hasta->gt($cierreJornada)) {
                $hasta = $cierreJornada;
            }
        }

        if ($hasta->lte($desde)) {
            $desde = Carbon::parse($fechaJornada.' 18:00:00');
            $hasta = Carbon::parse($fechaJornada.' 18:59:59');
        }

        $ventanaSeg = max(60, (int) $desde->diffInSeconds($hasta));
        $offset = $numeroComprobante > 0 ? ($numeroComprobante % $ventanaSeg) : 0;

        return $desde->copy()->addSeconds($offset);
    }

    private function parseHoraAnita(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '12:00:00';
        }

        $hora = trim((string) $raw);
        if ($hora === '') {
            return '12:00:00';
        }

        if (preg_match('/^\d{6}$/', $hora)) {
            return substr($hora, 0, 2).':'.substr($hora, 2, 2).':'.substr($hora, 4, 2);
        }

        if (preg_match('/^\d{4}$/', $hora)) {
            return substr($hora, 0, 2).':'.substr($hora, 2, 2).':00';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
            return $hora;
        }

        return '12:00:00';
    }

    private function etiqueta(int $sucursal, int $nro): string
    {
        return self::TIPO.' '.self::LETRA.' '.$sucursal.'-'.$nro;
    }

    /**
     * Libera numerotransaccion si quedó una cobranza ANULADA/huérfana (post-restore) que bloquea el unique.
     */
    private function existeCobranzaActivaParaNumerotransaccion(int $empresaId, string $codigoVenta): bool
    {
        $numeroTx = CobranzaNumeracionTransaccion::numerotransaccionDesdeCodigoVenta($codigoVenta);
        if ($numeroTx === '') {
            return false;
        }

        $cobranza = Cobranza::query()
            ->where('empresa_id', $empresaId)
            ->where('numerotransaccion', $numeroTx)
            ->first();

        if ($cobranza === null) {
            return false;
        }

        $estado = strtoupper(trim((string) ($cobranza->estado ?? '')));

        return $cobranza->venta_id !== null
            && $cobranza->deleted_at === null
            && $estado !== 'ANULADA';
    }

    private function eliminarCobranzaHuerfanaAnulada(int $empresaId, string $codigoVenta): void
    {
        $numeroTx = CobranzaNumeracionTransaccion::numerotransaccionDesdeCodigoVenta($codigoVenta);
        if ($numeroTx === '') {
            return;
        }

        $cobranza = Cobranza::query()
            ->where('empresa_id', $empresaId)
            ->where('numerotransaccion', $numeroTx)
            ->first();

        if ($cobranza === null) {
            return;
        }

        $estado = strtoupper(trim((string) ($cobranza->estado ?? '')));
        $esHuerfana = $cobranza->venta_id === null || $cobranza->deleted_at !== null || $estado === 'ANULADA';
        if (! $esHuerfana) {
            throw new InvalidArgumentException('Cobranza activa existente para '.$codigoVenta.' (id '.$cobranza->id.').');
        }

        $movIds = DB::table('caja_movimiento')->where('cobranza_id', $cobranza->id)->pluck('id');
        if ($movIds->isNotEmpty()) {
            DB::table('caja_movimiento_cuentacaja')->whereIn('caja_movimiento_id', $movIds)->delete();
            DB::table('caja_movimiento_estado')->whereIn('caja_movimiento_id', $movIds)->delete();
            DB::table('caja_movimiento')->whereIn('id', $movIds)->delete();
        }

        DB::table('cobranza_estado')->where('cobranza_id', $cobranza->id)->delete();
        DB::table('cobranza_retencion')->where('cobranza_id', $cobranza->id)->delete();
        DB::table('cobranza_archivo')->where('cobranza_id', $cobranza->id)->delete();
        DB::table('cobranza_comprobante')->where('cobranza_id', $cobranza->id)->delete();
        DB::table('cobranza')->where('id', $cobranza->id)->delete();
    }

    private function articuloCortesiaId(): int
    {
        $id = (int) Articulo::query()->orderBy('id')->value('id');

        return $id > 0 ? $id : 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apiAnita(array $payload, int $empresaId): string
    {
        return (new ApiAnita)->apiCall(
            GastronomiaAnitaImportBridgeSupport::mergePayload($payload, $empresaId),
        );
    }
}
