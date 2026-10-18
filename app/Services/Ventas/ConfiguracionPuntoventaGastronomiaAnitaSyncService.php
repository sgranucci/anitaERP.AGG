<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Salida;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Ventas\ConfiguracionPuntoventaGastronomiaRepositoryInterface;
use App\Support\Ventas\AnitaSync\Confptovta\ConfptovtaFieldMapper;
use App\Support\Ventas\GastronomiaSalaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Importa confptovta (Anita / cncadmin) → configuracion_puntoventa_gastronomia.
 *
 * Paridad a-confptovta.c / a-comandera.c. CROWN: ifx cncadmin + sala CIP.
 */
class ConfiguracionPuntoventaGastronomiaAnitaSyncService
{
    public function __construct(
        private readonly ConfiguracionPuntoventaGastronomiaRepositoryInterface $repository,
    ) {}

    /**
     * @return array{
     *   dry_run: bool,
     *   en_anita: int,
     *   importados: int,
     *   actualizados: int,
     *   omitidos: int,
     *   errores: list<string>,
     *   advertencias: list<string>,
     *   muestra: list<string>
     * }
     */
    public function sincronizarConAnita(bool $dryRun = true): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = [
            'dry_run' => $dryRun,
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
            'advertencias' => [],
            'muestra' => [],
        ];

        $lista = $this->listarDesdeAnita();
        $ret['en_anita'] = count($lista);

        $this->permitirCrearSalidas = ! $dryRun
            && (bool) config('configuracion_puntoventa_gastronomia_anita.crear_salidas_faltantes', true);

        $empresaId = (int) config('configuracion_puntoventa_gastronomia_anita.empresa_default_id', 1);
        $salaId = GastronomiaSalaSupport::idPorCodigo(
            (string) config('configuracion_puntoventa_gastronomia_anita.sala_codigo', '')
        );

        foreach ($lista as $row) {
            $host = ConfptovtaFieldMapper::host($row);
            if ($host === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $resultado = $this->importarFila($row, $host, $empresaId, $salaId, $dryRun, $ret['advertencias']);
                if ($resultado['estado'] === 'importado') {
                    $ret['importados']++;
                } elseif ($resultado['estado'] === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
                if (count($ret['muestra']) < 12 && $resultado['resumen'] !== '') {
                    $ret['muestra'][] = $resultado['resumen'];
                }
            } catch (\Throwable $e) {
                $msg = "confptovta host={$host}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('ConfigPvGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return list<object>
     */
    public function listarDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = $this->payloadBridge([
            'acc' => 'list',
            'tabla' => config('configuracion_puntoventa_gastronomia_anita.tabla', 'confptovta'),
            'campos' => config('configuracion_puntoventa_gastronomia_anita.campos_listado'),
            'orderBy' => 'cpvta_host',
        ]);

        $raw = $api->apiCallEscritura($payload);
        $rows = json_decode((string) $raw);
        if (! is_array($rows)) {
            Log::warning('ConfigPvGastronomiaAnitaSync listar: JSON inválido', [
                'json_error' => json_last_error_msg(),
                'raw_prefix' => substr((string) $raw, 0, 300),
            ]);

            return [];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $advertencias
     * @return array{estado: 'importado'|'actualizado'|'omitido', resumen: string}
     */
    private function importarFila(
        object $row,
        string $host,
        int $empresaId,
        ?int $salaId,
        bool $dryRun,
        array &$advertencias,
    ): array {
        $payload = $this->armarPayload($row, $host, $empresaId, $salaId, $advertencias);

        $existente = ConfiguracionPuntoventaGastronomia::query()
            ->where('identificador_pc', $host)
            ->where('empresa_id', $empresaId)
            ->first();

        $resumen = sprintf(
            '%s %s | modo=%s comanda=%s pv_cae=%s dep=%s lista=%s',
            $existente ? 'upd' : 'new',
            $host,
            $payload['modo_pos'],
            $payload['politica_comanda'],
            $payload['puntoventa_cae_id'],
            $payload['deposito_venta_id'] ?? '?',
            $payload['listaprecio_id']
        );

        if ($dryRun) {
            return [
                'estado' => $existente ? 'actualizado' : 'importado',
                'resumen' => $resumen,
            ];
        }

        if ($existente) {
            $existente->update($payload);

            return ['estado' => 'actualizado', 'resumen' => $resumen];
        }

        $this->repository->create($payload);

        return ['estado' => 'importado', 'resumen' => $resumen];
    }

    /**
     * @param  list<string>  $advertencias
     * @return array<string, mixed>
     */
    private function armarPayload(
        object $row,
        string $host,
        int $empresaId,
        ?int $salaId,
        array &$advertencias,
    ): array {
        $descripcion = ConfptovtaFieldMapper::ubicacionDescripcion($row);
        $sucAnita = ConfptovtaFieldMapper::enteroCodigo($row, 'cpvta_suc_kiosco');
        $listaCod = ConfptovtaFieldMapper::enteroCodigo($row, 'cpvta_lista');
        $listaHotelCod = ConfptovtaFieldMapper::enteroCodigo($row, 'cpvta_lista_hotel');
        $depCod = ConfptovtaFieldMapper::enteroCodigo($row, 'cpvta_deposito');
        $depInsumosCod = ConfptovtaFieldMapper::enteroCodigo($row, 'cpvta_deposito_a');

        $pvCae = $this->resolverPuntoventaCae($sucAnita, $empresaId, $host, $advertencias);
        $pvCaea = $this->resolverPuntoventaCaea($empresaId, $host, $advertencias);

        $listaId = $this->resolverListaprecioId($listaCod, $host, $advertencias);
        $listaHotelId = $listaHotelCod > 0
            ? $this->resolverListaprecioId($listaHotelCod, $host, $advertencias)
            : null;

        $depVentaId = $this->resolverDepmaeId($depCod, $empresaId, $salaId, $host, $advertencias);
        $depInsumosId = $depInsumosCod > 0
            ? $this->resolverDepmaeId($depInsumosCod, $empresaId, $salaId, $host, $advertencias)
            : $depVentaId;

        $salidaFacturaId = $this->resolverSalidaId(
            ConfptovtaFieldMapper::textoSalida($row, 'cpvta_salida_fac'),
            $host,
            $advertencias
        );
        $salidaComandaId = $this->resolverSalidaId(
            ConfptovtaFieldMapper::textoSalida($row, 'cpvta_salida_a'),
            $host,
            $advertencias
        );
        // Comanda A vacía → misma salida de factura (terminal sin impresora de cocina).
        if ($salidaComandaId === (int) config('configuracion_puntoventa_gastronomia_anita.salida_default_id', 1)
            && ConfptovtaFieldMapper::textoSalida($row, 'cpvta_salida_a') === '') {
            $salidaComandaId = $salidaFacturaId;
        }

        $salidaB = ConfptovtaFieldMapper::textoSalida($row, 'cpvta_salida_b');
        $salidaK = ConfptovtaFieldMapper::textoSalida($row, 'cpvta_salida_k');
        $salidaHotel = ConfptovtaFieldMapper::textoSalida($row, 'cpvta_salida_hotel');

        $tipoFac = $this->resolverTipotransaccionId(
            (string) config('configuracion_puntoventa_gastronomia_anita.tipotransaccion_factura_abreviatura', 'FAC')
        );
        $tipoNc = $this->resolverTipotransaccionId(
            (string) config('configuracion_puntoventa_gastronomia_anita.tipotransaccion_nc_abreviatura', 'NCD')
        );
        $tipoCajaId = (int) (config('gastronomia.tipotransaccion_caja_id') ?: 0);
        if ($tipoCajaId <= 0) {
            $tipoCajaId = 2;
        }

        return [
            'identificador_pc' => $host,
            'descripcion' => $descripcion !== '' ? $descripcion : $host,
            'modo_pos' => ConfptovtaFieldMapper::modoPos($row),
            'politica_comanda' => ConfptovtaFieldMapper::politicaComanda($row),
            'solo_marcha' => false,
            'habilita_hotel' => ConfptovtaFieldMapper::habilitaHotel($row),
            'habilita_canje_gst' => ConfptovtaFieldMapper::habilitaCanjeGst($row),
            'pide_cubiertos' => true,
            'pide_mozo_manual' => false,
            'empresa_id' => $empresaId,
            'puntoventa_cae_id' => $pvCae,
            'puntoventa_caea_id' => $pvCaea,
            'ubicacion_id' => null,
            'sala_id' => $salaId,
            'salida_comanda_id' => $salidaComandaId,
            'salida_comanda_b_id' => $salidaB !== '' ? $this->resolverSalidaId($salidaB, $host, $advertencias) : null,
            'salida_comanda_k_id' => $salidaK !== '' ? $this->resolverSalidaId($salidaK, $host, $advertencias) : null,
            'salida_factura_id' => $salidaFacturaId,
            'salida_hotel_id' => $salidaHotel !== '' ? $this->resolverSalidaId($salidaHotel, $host, $advertencias) : null,
            'listaprecio_id' => $listaId,
            'listaprecio_hotel_id' => $listaHotelId,
            'deposito_venta_id' => $depVentaId,
            'deposito_insumos_id' => $depInsumosId,
            'tipotransaccion_id' => $tipoFac,
            'tipotransaccion_nota_credito_id' => $tipoNc,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'waitry_habilitado' => false,
        ];
    }

    /**
     * @param  list<string>  $advertencias
     */
    private function resolverPuntoventaCae(int $sucAnita, int $empresaId, string $host, array &$advertencias): int
    {
        if ($sucAnita > 0) {
            $codigo = Puntoventa::normalizarCodigoArca((string) $sucAnita)
                ?? str_pad((string) $sucAnita, 5, '0', STR_PAD_LEFT);
            $pv = Puntoventa::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->where('estado', 'A')
                ->whereNull('deleted_at')
                ->first();
            if ($pv && in_array((string) $pv->modofacturacion, ['C', 'E'], true)) {
                return (int) $pv->id;
            }
            if ($pv) {
                $advertencias[] = "{$host}: suc {$codigo} modo={$pv->modofacturacion} (no C/E) → fallback CAE";
            } else {
                $advertencias[] = "{$host}: suc Anita {$sucAnita} no encontrada → fallback CAE";
            }
        }

        $fallback = trim((string) config('configuracion_puntoventa_gastronomia_anita.puntoventa_cae_fallback_codigo', '00090'));
        $pvFb = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $fallback)
            ->where('estado', 'A')
            ->whereNull('deleted_at')
            ->first();
        if (! $pvFb) {
            throw new \RuntimeException("PV CAE fallback {$fallback} no existe para empresa {$empresaId}");
        }

        return (int) $pvFb->id;
    }

    /**
     * @param  list<string>  $advertencias
     */
    private function resolverPuntoventaCaea(int $empresaId, string $host, array &$advertencias): int
    {
        $codigo = trim((string) config('configuracion_puntoventa_gastronomia_anita.puntoventa_caea_codigo', '00117'));
        $pv = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->where('estado', 'A')
            ->where('modofacturacion', 'A')
            ->whereNull('deleted_at')
            ->first();
        if (! $pv) {
            throw new \RuntimeException("PV CAEA {$codigo} no existe para empresa {$empresaId} (host {$host})");
        }

        return (int) $pv->id;
    }

    /**
     * @param  list<string>  $advertencias
     */
    private function resolverListaprecioId(int $codigoAnita, string $host, array &$advertencias): int
    {
        if ($codigoAnita <= 0) {
            $codigoAnita = 1;
        }
        $lp = Listaprecio::query()->where('codigo', (string) $codigoAnita)->first();
        if (! $lp) {
            $advertencias[] = "{$host}: lista Anita {$codigoAnita} no existe → usa lista 1";
            $lp = Listaprecio::query()->where('codigo', '1')->first()
                ?? Listaprecio::query()->orderBy('id')->first();
        }
        if (! $lp) {
            throw new \RuntimeException('No hay listaprecio en ERP');
        }

        return (int) $lp->id;
    }

    /**
     * @param  list<string>  $advertencias
     */
    private function resolverDepmaeId(
        int $codigoAnita,
        int $empresaId,
        ?int $salaId,
        string $host,
        array &$advertencias,
    ): ?int {
        if ($codigoAnita <= 0) {
            $advertencias[] = "{$host}: depósito Anita vacío";

            return null;
        }

        $q = Depmae::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoAnita);
        if ($salaId) {
            $conSala = (clone $q)->where('sala_id', $salaId)->first();
            if ($conSala) {
                return (int) $conSala->id;
            }
        }
        $dep = $q->orderByRaw('sala_id is null')->first();
        if (! $dep) {
            $advertencias[] = "{$host}: depósito código {$codigoAnita} no encontrado en ERP";

            return null;
        }

        return (int) $dep->id;
    }

    /**
     * @param  list<string>  $advertencias
     */
    private function resolverSalidaId(string $nombreAnita, string $host, array &$advertencias): int
    {
        $defaultId = (int) config('configuracion_puntoventa_gastronomia_anita.salida_default_id', 1);
        $nombreAnita = trim($nombreAnita);
        if ($nombreAnita === '') {
            return $defaultId > 0 ? $defaultId : 1;
        }

        $existente = Salida::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreAnita)])
            ->first();
        if ($existente) {
            return (int) $existente->id;
        }

        static $cacheIds = [];
        $key = mb_strtolower($nombreAnita);
        if (isset($cacheIds[$key])) {
            return $cacheIds[$key];
        }

        if (! $this->permitirCrearSalidas) {
            $advertencias[] = "{$host}: salida «{$nombreAnita}» se crearía al ejecutar (o usar default {$defaultId})";

            return $defaultId > 0 ? $defaultId : 1;
        }

        $nueva = Salida::create([
            'nombre' => $nombreAnita,
            'ubicacion_impresora_id' => null,
            'comando' => '# Importada desde Anita confptovta (host '.$host.') — configurar impresora',
        ]);
        $cacheIds[$key] = (int) $nueva->id;
        $advertencias[] = "{$host}: creada salida «{$nombreAnita}» id={$nueva->id}";

        return (int) $nueva->id;
    }

    private bool $permitirCrearSalidas = false;

    private function resolverTipotransaccionId(string $abreviatura): ?int
    {
        $abreviatura = trim($abreviatura);
        if ($abreviatura === '') {
            return null;
        }
        $id = Tipotransaccion::query()
            ->where('abreviatura', $abreviatura)
            ->whereNull('deleted_at')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadBridge(array $payload): array
    {
        $sistema = config('configuracion_puntoventa_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }
        $ifx = trim((string) config('configuracion_puntoventa_gastronomia_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('configuracion_puntoventa_gastronomia_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }

        return $payload;
    }
}
