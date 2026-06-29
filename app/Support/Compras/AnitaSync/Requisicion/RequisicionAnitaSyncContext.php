<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Models\Seguridad\Usuario;
use Carbon\Carbon;

/**
 * Valores comunes ERP → Anita (reqmae, reqmov, reqmref).
 */
final class RequisicionAnitaSyncContext
{
    private readonly Carbon $ahora;

    public function __construct(
        public readonly Requisicion $requisicion,
        public readonly int $usuarioSyncId,
    ) {
        $this->ahora = Carbon::now();
    }

    public function numeroRequisicion(): int
    {
        return (int) $this->requisicion->numerorequisicion;
    }

    public function fechaYmd(?string $fecha = null): string
    {
        $f = $fecha ?? $this->requisicion->fecha;

        return Carbon::parse($f)->format('Ymd');
    }

    public function fechaAlfa(?string $fecha = null): string
    {
        return $this->fechaYmd($fecha);
    }

    public function fechaIngYmd(): string
    {
        $created = $this->requisicion->created_at ?? $this->ahora;

        return Carbon::parse($created)->format('Ymd');
    }

    public function horaIngCabecera(): string
    {
        $created = $this->requisicion->created_at ?? $this->ahora;

        return Carbon::parse($created)->format('H:i:s');
    }

    public function horaIngRef(): string
    {
        $created = $this->requisicion->created_at ?? $this->ahora;

        return Carbon::parse($created)->format('H:i');
    }

    public function empresaCodigo(): int
    {
        return (int) ($this->requisicion->empresas?->codigo ?? $this->requisicion->empresa_id ?? 1);
    }

    public function centrocostoCodigo(?int $centrocostoId = null): int
    {
        if ($centrocostoId !== null) {
            $cc = $this->requisicion->centrocostos;
            if ($centrocostoId === (int) $this->requisicion->centrocosto_id) {
                return (int) ($cc?->codigo ?? $centrocostoId);
            }
        }

        return (int) ($this->requisicion->centrocostos?->codigo ?? $this->requisicion->centrocosto_id ?? 0);
    }

    public function centrocostoCodigoLinea(Requisicion_Articulo $linea): int
    {
        $destino = $linea->centrocostodestino_id ?? $this->requisicion->centrocosto_id;

        if ($linea->relationLoaded('centrocostos_destino') && $linea->centrocostos_destino) {
            return (int) $linea->centrocostos_destino->codigo;
        }

        return $this->centrocostoCodigo($destino);
    }

    /** reqm_ccosto_dest: centro de costo destino de la primera línea de artículos. */
    public function centrocostoDestinoCodigo(): int
    {
        $primeraLinea = $this->requisicion->requisicion_articulos->sortBy('id')->first();
        if ($primeraLinea instanceof Requisicion_Articulo) {
            return $this->centrocostoCodigoLinea($primeraLinea);
        }

        return $this->centrocostoCodigo();
    }

    public function proveedorCodigo(): string
    {
        $codigo = (string) ($this->requisicion->proveedores?->codigo ?? '0');

        return str_pad(ltrim($codigo, '0') === '' ? '0' : ltrim($codigo, '0'), 6, '0', STR_PAD_LEFT);
    }

    public function monedaCodigoAnitaChar(?int $monedaId = null): string
    {
        $moneda = null;
        if ($monedaId !== null) {
            $linea = $this->requisicion->requisicion_articulos->firstWhere('moneda_id', $monedaId);
            $moneda = $linea?->monedas;
        }
        if ($moneda === null) {
            $primeraLinea = $this->requisicion->requisicion_articulos->sortBy('id')->first();
            $moneda = $primeraLinea?->monedas;
        }

        if ($moneda && filled($moneda->codigo)) {
            return (string) (int) $moneda->codigo;
        }

        return '1';
    }

    public function condicionPagoCodigo(): int
    {
        return (int) ($this->requisicion->formapago_id ?? 0);
    }

    public function estadoAnitaChar(): string
    {
        return RequisicionAnitaEstadoMapper::erpNombreToAnitaChar($this->requisicion->estado);
    }

    public function esUrgenteChar(): string
    {
        return strcasecmp(trim((string) $this->requisicion->tratamiento), 'Urgente') === 0 ? 'S' : 'N';
    }

    public function contratacionDirectaChar(): string
    {
        return strcasecmp(trim((string) $this->requisicion->contrataciondirecta), 'Si') === 0 ? 'S' : 'N';
    }

    public function usuarioAnitaCodigo(?int $usuarioId = null): int
    {
        $id = $usuarioId ?? (int) ($this->requisicion->creousuario_id ?? $this->usuarioSyncId);
        if ($id <= 0) {
            return 0;
        }

        $usuario = Usuario::query()->find($id);
        if ($usuario === null) {
            return $id;
        }

        return (int) $usuario->id;
    }

    public function leyendaCabecera(): string
    {
        $detalle = trim((string) $this->requisicion->detalle);
        if ($detalle !== '') {
            return $detalle;
        }

        return trim((string) $this->requisicion->comentario);
    }

    public function whereReqmae(): string
    {
        return ' WHERE reqm_nro = '.$this->numeroRequisicion();
    }

    public function whereReqmov(): string
    {
        return ' WHERE reqv_nro = '.$this->numeroRequisicion();
    }

    public function whereReqmref(): string
    {
        return ' WHERE reqr_nro_requi = '.$this->numeroRequisicion();
    }

    public function articuloSkuPadded(?string $sku): string
    {
        $sku = trim((string) $sku);
        if ($sku === '' || strcasecmp($sku, 'texto') === 0) {
            return 'texto';
        }

        return str_pad(substr($sku, 0, 13), 13, '0', STR_PAD_LEFT);
    }
}
