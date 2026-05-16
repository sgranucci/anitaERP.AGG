<?php

namespace App\Services\Stock\Gastronomia;

use App\Models\Stock\ConfiguracionPuntoventaGastronomia;
use App\Models\Stock\CuentaGastronomia;
use App\Models\Stock\CuentaGastronomiaLinea;
use App\Models\Stock\MesaGastronomia;
use App\Support\Stock\GastronomiaIdentificadorPc;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GastronomiaCuentaService
{
    public function resolverConfiguracionPv(int $empresaId): ?ConfiguracionPuntoventaGastronomia
    {
        $pc = GastronomiaIdentificadorPc::resolver();

        return ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('identificador_pc', $pc)
            ->with(['ubicacion', 'puntoventaCae', 'puntoventaCaea', 'salidaFactura', 'listaprecio'])
            ->first();
    }

    /**
     * @return Collection<int, MesaGastronomia>
     */
    public function listarMesasUbicacion(?int $ubicacionId): Collection
    {
        if ($ubicacionId === null || $ubicacionId === 0) {
            return new Collection;
        }

        return MesaGastronomia::query()
            ->where('ubicacion_id', $ubicacionId)
            ->orderBy('numeromesa')
            ->get();
    }

    public function mesasConOcupacion(Collection $mesas): array
    {
        $ids = $mesas->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $ocupadas = CuentaGastronomia::query()
            ->whereIn('mesa_gastronomia_id', $ids)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->pluck('mesa_gastronomia_id')
            ->unique()
            ->flip();

        return $mesas->map(function (MesaGastronomia $m) use ($ocupadas) {
            return [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'numeromesa' => $m->numeromesa,
                'codigo' => $m->codigo,
                'ocupada' => $ocupadas->has($m->id),
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, CuentaGastronomia>
     */
    public function listarCuentasLibresActivasPc(int $empresaId): Collection
    {
        $pc = GastronomiaIdentificadorPc::resolver();

        return CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', CuentaGastronomia::TIPO_CUENTA)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->where('identificador_pc', $pc)
            ->orderByDesc('id')
            ->with(['lineas.articulo', 'cliente', 'mozo'])
            ->get();
    }

    public function abrirMesa(int $mesaId, int $empresaId, ConfiguracionPuntoventaGastronomia $cfg): CuentaGastronomia
    {
        $mesa = MesaGastronomia::query()->where('id', $mesaId)->where('empresa_id', $empresaId)->firstOrFail();

        $exist = CuentaGastronomia::query()
            ->where('mesa_gastronomia_id', $mesaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->exists();

        if ($exist) {
            throw new InvalidArgumentException('La mesa ya tiene una cuenta abierta.');
        }

        return CuentaGastronomia::create([
            'tipo' => CuentaGastronomia::TIPO_MESA,
            'empresa_id' => $empresaId,
            'mesa_gastronomia_id' => $mesa->id,
            'mozo_gastronomia_id' => null,
            'cubiertos' => 0,
            'estado' => CuentaGastronomia::ESTADO_ABIERTA,
            'identificador_pc' => null,
            'configuracion_puntoventa_gastronomia_id' => $cfg->id,
        ]);
    }

    public function abrirCuentaLibre(int $empresaId, ConfiguracionPuntoventaGastronomia $cfg): CuentaGastronomia
    {
        $pc = GastronomiaIdentificadorPc::resolver();

        return CuentaGastronomia::create([
            'tipo' => CuentaGastronomia::TIPO_CUENTA,
            'empresa_id' => $empresaId,
            'mesa_gastronomia_id' => null,
            'mozo_gastronomia_id' => null,
            'cubiertos' => 0,
            'estado' => CuentaGastronomia::ESTADO_ABIERTA,
            'identificador_pc' => $pc,
            'configuracion_puntoventa_gastronomia_id' => $cfg->id,
        ]);
    }

    public function cuentaConLineas(int $id): CuentaGastronomia
    {
        return CuentaGastronomia::query()
            ->with([
                'lineas.articulo',
                'mesa',
                'mozo',
                'cliente',
                'descuentoGastronomia',
                'configuracionPuntoventa',
            ])
            ->findOrFail($id);
    }

    public function actualizarCabecera(CuentaGastronomia $cuenta, array $datos): CuentaGastronomia
    {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $cuenta->fill(array_filter([
            'mozo_gastronomia_id' => $datos['mozo_gastronomia_id'] ?? null,
            'cubiertos' => isset($datos['cubiertos']) ? (int) $datos['cubiertos'] : null,
            'cliente_id' => $datos['cliente_id'] ?? null,
            'descuento_gastronomia_id' => $datos['descuento_gastronomia_id'] ?? null,
        ], fn ($v) => $v !== null));

        $cuenta->save();

        return $cuenta->fresh(['lineas.articulo', 'cliente', 'mozo', 'mesa']);
    }

    /**
     * @param  array<int|string, int|null>  $opcionalesPorOrden  ej. ["1" => 123, "2" => 456]
     */
    public function agregarLinea(
        CuentaGastronomia $cuenta,
        int $articuloId,
        float $cantidad,
        float $precioUnitario,
        array $opcionalesPorOrden = [],
        float $descuentoLineaPct = 0.
    ): CuentaGastronomiaLinea {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if (! $this->puedeEditarLineas($cuenta)) {
            throw new InvalidArgumentException('No puede cargar consumos en esta cuenta desde esta PC.');
        }

        $maxNum = (int) DB::table('cuenta_gastronomia_linea')->where('cuenta_gastronomia_id', $cuenta->id)->max('numero_linea');

        return CuentaGastronomiaLinea::create([
            'cuenta_gastronomia_id' => $cuenta->id,
            'articulo_id' => $articuloId,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'descuento_linea_pct' => $descuentoLineaPct,
            'opcionales_json' => $opcionalesPorOrden === [] ? null : $opcionalesPorOrden,
            'numero_linea' => $maxNum + 1,
        ]);
    }

    public function cerrarSinFacturar(CuentaGastronomia $cuenta): void
    {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $cuenta->update(['estado' => CuentaGastronomia::ESTADO_CERRADA]);
    }

    public function marcarFacturada(CuentaGastronomia $cuenta, int $ventaId): void
    {
        $cuenta->update([
            'estado' => CuentaGastronomia::ESTADO_FACTURADA,
            'venta_id' => $ventaId,
        ]);
    }

    /**
     * Solo la PC creadora puede borrar líneas de cuenta libre; mesas compartidas permiten desde misma ubicación.
     */
    public function puedeEditarLineas(CuentaGastronomia $cuenta, ?string $identificadorPcActual = null): bool
    {
        $identificadorPcActual ??= GastronomiaIdentificadorPc::resolver();
        if ($cuenta->tipo === CuentaGastronomia::TIPO_MESA) {
            return true;
        }

        return (string) $cuenta->identificador_pc === $identificadorPcActual;
    }

    public function eliminarLinea(CuentaGastronomiaLinea $linea): void
    {
        $cuenta = $linea->cuenta;
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if (! $this->puedeEditarLineas($cuenta)) {
            throw new InvalidArgumentException('No puede modificar consumos de esta cuenta en esta PC.');
        }

        $linea->delete();
    }

    public function actualizarCantidadLinea(CuentaGastronomiaLinea $linea, float $cantidad): CuentaGastronomia
    {
        $cuenta = $linea->cuenta;
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if (! $this->puedeEditarLineas($cuenta)) {
            throw new InvalidArgumentException('No puede modificar consumos de esta cuenta en esta PC.');
        }

        if ($cantidad < 0.0001) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        $linea->update(['cantidad' => $cantidad]);

        return $this->cuentaConLineas($cuenta->id);
    }
}
