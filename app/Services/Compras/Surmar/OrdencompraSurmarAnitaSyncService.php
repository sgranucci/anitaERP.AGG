<?php

namespace App\Services\Compras\Surmar;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Compras\Ordencompra_ArticuloRepositoryInterface;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Services\Compras\OrdencompraGestionService;
use App\Support\Compras\AnitaSync\Ordencompra\AnitaOcClave;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaEstadosSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaSyncContext;
use App\Support\Compras\AnitaSync\Surmar\OrdencompraSurmarAnitaBridgeSupport;
use App\Support\Compras\AnitaSync\Surmar\SurmarArticuloLineaFieldMapper;
use App\Support\Compras\AnitaSync\Surmar\SurmarCabeceraFieldMapper;
use App\Support\Compras\OrdencompraCondicionesContratacionGenerator;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Stock\SurmarSupport;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Log;

/**
 * Importador OC Anita Surmar (pendmaep/pendmovp). No modifica el sync AGG.
 */
class OrdencompraSurmarAnitaSyncService
{
    public function __construct(
        private readonly OrdencompraRepositoryInterface $ordencompraRepository,
        private readonly Ordencompra_ArticuloRepositoryInterface $ordencompraArticuloRepository,
        private readonly Ordencompra_EstadoRepositoryInterface $ordencompraEstadoRepository,
        private readonly OrdencompraGestionService $ordencompraGestionService,
        private readonly ProveedorQueryInterface $proveedorQuery,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
        private readonly CondicioncompraRepositoryInterface $condicioncompraRepository,
        private readonly CondicionentregaRepositoryInterface $condicionentregaRepository,
        private readonly CondicionpagoRepositoryInterface $condicionpagoRepository,
        private readonly TransporteRepositoryInterface $transporteRepository,
        private readonly PartidagastoRepositoryInterface $partidagastoRepository,
        private readonly CapexRepositoryInterface $capexRepository,
        private readonly ArticuloQueryInterface $articuloQuery,
    ) {
    }

    /**
     * @return array{en_anita:int, importados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(?int $usuarioId = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $this->assertEntornoSurmar();

        $uid = $usuarioId ?? (int) (Auth::id() ?? 0);
        if ($uid <= 0) {
            throw new \RuntimeException('Usuario de sincronización no definido.');
        }

        $ctx = $this->nuevoContexto($uid);
        $fechaDesde = (int) config('ordencompra_anita_surmar.fecha_desde', 20260100);

        $api = new ApiAnita;
        $lista = json_decode($api->apiCall(OrdencompraSurmarAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'campos' => 'penmp_nro',
            'tabla' => config('ordencompra_anita_surmar.tablas.cabecera', 'pendmaep'),
            'whereArmado' => " WHERE penmp_fecha >= {$fechaDesde}",
        ])));

        $ret = [
            'en_anita' => is_array($lista) ? count($lista) : 0,
            'importados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        if (! is_array($lista)) {
            return $ret;
        }

        foreach ($lista as $item) {
            $nro = (int) ($item->penmp_nro ?? 0);
            if ($nro <= 0) {
                continue;
            }
            try {
                $r = $this->traerRegistroDeAnita($nro, $ctx);
                if ($r === 'importado') {
                    $ret['importados']++;
                } elseif ($r === 'omitido' || $r === 'lineas_completadas') {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "OC {$nro}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('OrdencompraSurmarAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'omitido'|'lineas_completadas'|'sin_datos'
     */
    public function traerRegistroDeAnita(int $numeroOc, ?OrdencompraAnitaSyncContext $ctx = null): string
    {
        $this->assertEntornoSurmar();
        $ctx ??= $this->nuevoContexto((int) (Auth::id() ?? 1));

        if ($ctx->existeOrdencompraPorNumero($numeroOc)) {
            $completadas = $this->completarLineasFaltantesDesdeAnita($numeroOc, $ctx);

            return $completadas > 0 ? 'lineas_completadas' : 'omitido';
        }

        $cabecera = $this->leerPendmaep($numeroOc);
        if ($cabecera === null) {
            return 'sin_datos';
        }

        $payload = SurmarCabeceraFieldMapper::mapAll($cabecera, $ctx);
        $this->validarCabeceraMinima($payload, $numeroOc);

        $clave = AnitaOcClave::desdePendmaep($cabecera);
        $lineas = $this->leerPendmovp($clave);
        $payload['estadoordencompra'] = OrdencompraAnitaEstadosSupport::haciaEstadoErpImportacion(
            $cabecera->penmp_estado ?? '0',
            $lineas
        );
        if ($lineas === []) {
            Log::warning('OrdencompraSurmarAnitaSync: OC sin líneas pendmovp', [
                'numero_oc' => $numeroOc,
            ]);
        }

        DB::beginTransaction();
        try {
            $oc = $this->ordencompraRepository->createDesdeAnita($payload);

            $this->ordencompraEstadoRepository->creaEstado(
                $oc->id,
                Carbon::now()->toDateTimeString(),
                (string) ($payload['estadoordencompra'] ?? OrdencompraEstados::PENDIENTE),
                $ctx->usuarioSyncId,
                'Alta de orden de compra desde Anita Surmar'
            );

            $monedaDefault = $ctx->fkMoneda($cabecera->penmp_cod_mon ?? '1') ?? 1;
            $this->importarLineasPendmovp(
                (int) $oc->id,
                $lineas,
                $cabecera,
                $payload,
                $ctx,
                $monedaDefault,
            );

            $this->regenerarCondicionesContratacion((int) $oc->id);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return 'importado';
    }

    public function completarLineasFaltantesDesdeAnita(int $numeroOc, ?OrdencompraAnitaSyncContext $ctx = null): int
    {
        $ctx ??= $this->nuevoContexto((int) (Auth::id() ?? 1));

        $oc = Ordencompra::query()->where('numeroordencompra', $numeroOc)->first();
        if (! $oc || $oc->ordencompra_articulos()->exists()) {
            return 0;
        }

        $cabecera = $this->leerPendmaep($numeroOc);
        if ($cabecera === null) {
            return 0;
        }

        $clave = AnitaOcClave::desdePendmaep($cabecera);
        $lineas = $this->leerPendmovp($clave);
        if ($lineas === []) {
            return 0;
        }

        $payload = SurmarCabeceraFieldMapper::mapAll($cabecera, $ctx);
        $monedaDefault = $ctx->fkMoneda($cabecera->penmp_cod_mon ?? '1') ?? 1;

        DB::beginTransaction();
        try {
            $importadas = $this->importarLineasPendmovp(
                (int) $oc->id,
                $lineas,
                $cabecera,
                $payload,
                $ctx,
                $monedaDefault,
            );
            $this->regenerarCondicionesContratacion((int) $oc->id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $importadas;
    }

    private function assertEntornoSurmar(): void
    {
        $empresaId = (int) config('ordencompra_anita_surmar.empresa_id', SurmarSupport::EMPRESA_ID);
        if (! SurmarSupport::esEmpresaSurmar($empresaId)) {
            throw new \RuntimeException(
                "Import Surmar: empresa_id={$empresaId} no es Surmar en este ERP (evitar uso en AGG)."
            );
        }
        if (! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \RuntimeException("Import Surmar: empresa_id {$empresaId} inexistente.");
        }
        $ccId = (int) config('ordencompra_anita_surmar.centrocosto_id', 1);
        if ($ccId > 0 && ! Centrocosto::query()->whereKey($ccId)->exists()) {
            throw new \RuntimeException("Import Surmar: centrocosto_id {$ccId} inexistente.");
        }
    }

    private function nuevoContexto(int $usuarioSyncId): OrdencompraAnitaSyncContext
    {
        return new OrdencompraAnitaSyncContext(
            $this->proveedorQuery,
            $this->centrocostoRepository,
            $this->monedaRepository,
            $this->condicioncompraRepository,
            $this->condicionentregaRepository,
            $this->condicionpagoRepository,
            $this->transporteRepository,
            $this->partidagastoRepository,
            $this->capexRepository,
            $this->articuloQuery,
            $this->ordencompraGestionService,
            $usuarioSyncId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarCabeceraMinima(array $payload, int $numeroOc): void
    {
        if (empty($payload['fecha'])) {
            throw new \InvalidArgumentException("Fechas inválidas en pendmaep (OC {$numeroOc}).");
        }
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (OC {$numeroOc}).");
        }
        if (empty($payload['centrocosto_id'])) {
            throw new \InvalidArgumentException("centrocosto_id obligatorio (OC {$numeroOc}).");
        }
        if (empty($payload['proveedor_id'])) {
            throw new \InvalidArgumentException("proveedor_id sin match (OC {$numeroOc}).");
        }
        // detalle / leyenda: no exigido (mapper completa fallback "OC N")
    }

    private function leerPendmaep(int $numeroOc): ?object
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall(OrdencompraSurmarAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita_surmar.tablas.cabecera', 'pendmaep'),
            'campos' => OrdencompraSurmarAnitaBridgeSupport::camposCabecera(),
            'whereArmado' => " WHERE penmp_nro={$numeroOc}",
        ])));

        return (is_array($rows) && count($rows) > 0) ? $rows[0] : null;
    }

    /**
     * @return list<object>
     */
    private function leerPendmovp(AnitaOcClave $clave): array
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall(OrdencompraSurmarAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita_surmar.tablas.linea', 'pendmovp'),
            'campos' => OrdencompraSurmarAnitaBridgeSupport::camposLinea(),
            'whereArmado' => $clave->wherePendmovp(),
        ])));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, mixed>  $payloadCabecera
     */
    private function importarLineasPendmovp(
        int $ordencompraId,
        array $lineas,
        object $cabecera,
        array $payloadCabecera,
        OrdencompraAnitaSyncContext $ctx,
        int $monedaDefault,
    ): int {
        $importadas = 0;

        foreach ($lineas as $linea) {
            $lineaPayload = SurmarArticuloLineaFieldMapper::mapAll(
                $linea,
                $cabecera,
                $ctx,
                $ordencompraId,
            );

            if (empty($lineaPayload['moneda_id'])) {
                $lineaPayload['moneda_id'] = $monedaDefault;
            }
            if (empty($lineaPayload['centrocostodestino_id'])) {
                $lineaPayload['centrocostodestino_id'] = $payloadCabecera['centrocosto_id'];
            }
            if (empty($lineaPayload['fechaentrega'])) {
                $lineaPayload['fechaentrega'] = $payloadCabecera['fechaentrega'] ?? $payloadCabecera['fecha'];
            }

            $this->ordencompraArticuloRepository->createDesdeAnita($lineaPayload);
            $importadas++;
        }

        return $importadas;
    }

    private function regenerarCondicionesContratacion(int $ordencompraId): void
    {
        $oc = $this->ordencompraRepository->find($ordencompraId);
        $texto = OrdencompraCondicionesContratacionGenerator::desdeModelo($oc);
        $this->ordencompraRepository->update(['condiciones_contratacion' => $texto], $ordencompraId);
    }
}
