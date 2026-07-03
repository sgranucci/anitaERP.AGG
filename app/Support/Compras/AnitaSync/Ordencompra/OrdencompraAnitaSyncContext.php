<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Requisicion;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Formapago;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Services\Compras\OrdencompraGestionService;

/**
 * Resolución de FK y utilidades compartidas entre mappers de sincronización OC.
 */
class OrdencompraAnitaSyncContext
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(
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
        private readonly OrdencompraGestionService $ordencompraGestionService,
        public readonly int $usuarioSyncId,
    ) {
    }

    public function fechaYmd(mixed $valor): ?string
    {
        if ($valor === null || $valor === '' || (int) $valor <= 0) {
            return null;
        }
        try {
            return \Carbon\Carbon::createFromFormat('Ymd', (string) (int) $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function fechaHoraAnita(mixed $fechaYmd, mixed $hora): ?string
    {
        $f = $this->fechaYmd($fechaYmd);
        if ($f === null) {
            return null;
        }
        $h = trim((string) ($hora ?? '00:00:00'));
        if ($h === '') {
            $h = '00:00:00';
        }
        if (strlen($h) === 5) {
            $h .= ':00';
        }

        return $f.' '.$h;
    }

    public function mapEstadoOc(mixed $codigo): string
    {
        return OrdencompraAnitaEstadosSupport::haciaEstadoErp($codigo);
    }

    public function mapTratamientoAnticipo(mixed $esAnticipo): string
    {
        return strtoupper(trim((string) $esAnticipo)) === 'S' ? 'ANTICIPADA' : 'NO ANTICIPADA';
    }

    public function sectorComprasId(): ?int
    {
        return $this->ordencompraGestionService->idSectorCompras();
    }

    public function existeOrdencompraPorNumero(int $numeroAnita): bool
    {
        return Ordencompra::query()->where('numeroordencompra', $numeroAnita)->exists();
    }

    public function fkProveedor(mixed $codigoProveedor): ?int
    {
        $key = 'prov_'.(string) $codigoProveedor;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $p = $this->proveedorQuery->traeProveedorporCodigo(ltrim((string) $codigoProveedor, '0'));

        return $this->cache[$key] = $p?->id;
    }

    public function fkCentrocosto(mixed $codigo): ?int
    {
        $key = 'cc_'.(string) $codigo;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $cc = $this->centrocostoRepository->findPorCodigo($codigo);

        return $this->cache[$key] = $cc?->id;
    }

    public function fkMoneda(mixed $codigo): ?int
    {
        $key = 'mon_'.(string) $codigo;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $m = $this->monedaRepository->findPorCodigo($codigo);

        return $this->cache[$key] = $m?->id;
    }

    public function fkCondicioncompra(mixed $codigo): ?int
    {
        $key = 'ccom_'.(string) $codigo;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $c = $this->condicioncompraRepository->findPorCodigo($codigo);

        return $this->cache[$key] = $c?->id;
    }

    public function fkCondicionentrega(mixed $codigo): ?int
    {
        $key = 'cent_'.(string) $codigo;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $c = $this->condicionentregaRepository->findPorCodigo($codigo);

        return $this->cache[$key] = $c?->id;
    }

    public function fkCondicionpago(mixed $codigo): ?int
    {
        $key = 'cpag_'.(string) $codigo;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $c = $this->condicionpagoRepository->findPorCodigo($codigo);

        return $this->cache[$key] = $c?->id;
    }

    public function fkTransporte(mixed $codigoExpreso): ?int
    {
        $key = 'tr_'.(string) $codigoExpreso;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        if ($codigoExpreso === null || (int) $codigoExpreso <= 0) {
            return $this->cache[$key] = null;
        }
        $t = $this->transporteRepository->findPorCodigo($codigoExpreso);

        return $this->cache[$key] = $t?->id;
    }

    public function fkRequisicionPorNumero(mixed $nroRequisicion): ?int
    {
        if ($nroRequisicion === null || (int) $nroRequisicion <= 0) {
            return null;
        }
        $key = 'req_'.(int) $nroRequisicion;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = Requisicion::query()
            ->where('numerorequisicion', (int) $nroRequisicion)
            ->value('id');
    }

    public function fkArticuloSku(mixed $sku): ?int
    {
        $key = 'art_'.ltrim((string) $sku, '0');
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $a = $this->articuloQuery->traeArticuloPorSku(ltrim((string) $sku, '0'));

        return $this->cache[$key] = $a?->id;
    }

    public function fkPartidagasto(mixed $codigo): ?int
    {
        $key = 'pg_'.(string) $codigo;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        if ($codigo === null || (int) $codigo <= 0) {
            return $this->cache[$key] = null;
        }
        $p = $this->partidagastoRepository->findPorCodigo($codigo);

        return $this->cache[$key] = $p?->id;
    }

    public function fkCapex(mixed $codigoProyecto): ?int
    {
        $key = 'cpx_'.(string) $codigoProyecto;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        if ($codigoProyecto === null || (int) $codigoProyecto <= 0) {
            return $this->cache[$key] = null;
        }
        $c = $this->capexRepository->findPorCodigo($codigoProyecto);

        return $this->cache[$key] = $c?->id;
    }

    public function fkFormapagoMedio(mixed $medioPago): int
    {
        $medio = trim((string) $medioPago);
        if ($medio === '') {
            return 1;
        }

        $abrev = OrdencompraAnitaMedioPagoSupport::haciaFormapagoAbreviatura($medio);
        if ($abrev !== null) {
            $medio = $abrev;
        }

        $key = 'fp_'.$medio;
        if (array_key_exists($key, $this->cache)) {
            return (int) $this->cache[$key];
        }

        $id = Formapago::query()->where('abreviatura', $medio)->value('id');

        return (int) ($this->cache[$key] = $id ? (int) $id : 1);
    }

    public function fkUsuarioAnita(mixed $usuarioAnita): int
    {
        $u = trim((string) $usuarioAnita);
        if ($u === '') {
            return $this->usuarioSyncId;
        }
        $key = 'usu_'.$u;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $id = Usuario::query()->where('usuario', $u)->orWhere('nombre', $u)->value('id');

        return $this->cache[$key] = $id ? (int) $id : $this->usuarioSyncId;
    }
}
