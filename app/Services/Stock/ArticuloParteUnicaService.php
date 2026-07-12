<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use App\Support\Stock\ArticuloParteUnicaDisponibilidadSupport;
use App\Support\Stock\ArticuloParteUnicaEstados;
use App\Support\Stock\BajaNpuMovimientoStockSupport;
use App\Support\Stock\StkParteUnicaAnitaBridgeSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ArticuloParteUnicaService
{
    public function maxNumeroparteGlobal(): int
    {
        return StkParteUnicaAnitaBridgeSupport::maxNumeroparteGlobal();
    }

    public function maxNumeroparteLocal(): int
    {
        return (int) Articulo_ParteUnica::query()->max('numeroparte');
    }

    public function siguienteNumeroparteGlobal(): int
    {
        return $this->maxNumeroparteGlobal() + 1;
    }

    /**
     * Reserva el siguiente numeroparte con bloqueo sobre articulo_parte_unica (evita colisiones concurrentes).
     */
    public function siguienteNumeroparteGlobalConBloqueo(): int
    {
        $maxLocal = (int) Articulo_ParteUnica::query()->lockForUpdate()->max('numeroparte');
        $maxAnita = StkParteUnicaAnitaBridgeSupport::maxNumeroparteAnita();

        return max($maxLocal, $maxAnita) + 1;
    }

    /**
     * Inserta solo en articulo_parte_unica (sin Anita). Usar desde recepción dentro de una transacción mayor.
     */
    public function crearLocal(int $articuloId, int $numeroparte): Articulo_ParteUnica
    {
        if ($numeroparte <= 0) {
            throw new \InvalidArgumentException('numeroparte inválido.');
        }

        if (Articulo_ParteUnica::query()->where('numeroparte', $numeroparte)->exists()) {
            throw new \RuntimeException("El número de parte {$numeroparte} ya existe.");
        }

        return Articulo_ParteUnica::create([
            'articulo_id' => $articuloId,
            'numeroparte' => $numeroparte,
            'estado' => ArticuloParteUnicaEstados::ACTIVO,
        ]);
    }

    public function crear(int $articuloId, ?int $numeroparte = null, bool $sincronizarAnita = true): Articulo_ParteUnica
    {
        return DB::transaction(function () use ($articuloId, $numeroparte, $sincronizarAnita) {
            $num = $numeroparte ?? $this->siguienteNumeroparteGlobalConBloqueo();
            $parte = $this->crearLocal($articuloId, $num);

            if ($sincronizarAnita) {
                StkParteUnicaAnitaBridgeSupport::insertar($parte);
            }

            return $parte;
        });
    }

    public function eliminar(Articulo_ParteUnica $parte): void
    {
        if (ArticuloParteUnicaEstados::esBaja($parte->estado)) {
            throw new \RuntimeException('El NPU ya fue dado de baja; no puede eliminarse desde el ABM de artículo.');
        }

        DB::transaction(function () use ($parte) {
            StkParteUnicaAnitaBridgeSupport::eliminar($parte);
            $parte->delete();
        });
    }

    public function darDeBaja(Articulo_ParteUnica $parte, int $movimientostockId, ?string $motivo = null): Articulo_ParteUnica
    {
        return DB::transaction(function () use ($parte, $movimientostockId, $motivo) {
            $parte->refresh();
            ArticuloParteUnicaDisponibilidadSupport::assertActivaParaUso((int) $parte->numeroparte);

            StkParteUnicaAnitaBridgeSupport::eliminar($parte);

            $parte->update([
                'estado' => ArticuloParteUnicaEstados::BAJA,
                'fecha_baja' => now(),
                'motivo_baja' => trim((string) $motivo) !== ''
                    ? trim((string) $motivo)
                    : BajaNpuMovimientoStockSupport::MOTIVO_DEFAULT,
                'movimientostock_id' => $movimientostockId > 0 ? $movimientostockId : null,
            ]);

            return $parte->fresh();
        });
    }

    /**
     * Reactiva todos los NPU dados de baja por un movimiento de stock (reversión).
     */
    public function reactivarPorMovimientoOrigen(int $movimientoOrigenId): int
    {
        $partes = Articulo_ParteUnica::query()
            ->where('movimientostock_id', $movimientoOrigenId)
            ->where('estado', ArticuloParteUnicaEstados::BAJA)
            ->get();

        $reactivados = 0;
        foreach ($partes as $parte) {
            $this->reactivar($parte);
            $reactivados++;
        }

        return $reactivados;
    }

    public function reactivar(Articulo_ParteUnica $parte): Articulo_ParteUnica
    {
        return DB::transaction(function () use ($parte) {
            $parte->refresh();

            if (! ArticuloParteUnicaEstados::esBaja($parte->estado)) {
                throw new \RuntimeException('El NPU '.$parte->numeroparte.' no está dado de baja.');
            }

            $parte->update([
                'estado' => ArticuloParteUnicaEstados::ACTIVO,
                'fecha_baja' => null,
                'motivo_baja' => null,
                'movimientostock_id' => null,
            ]);

            $parte->loadMissing('articulos');
            StkParteUnicaAnitaBridgeSupport::insertar($parte);

            return $parte->fresh();
        });
    }

    /**
     * @return LengthAwarePaginator<int, Articulo_ParteUnica>
     */
    public function listarPorArticulo(int $articuloId, int $porPagina = 20, ?string $estado = null): LengthAwarePaginator
    {
        $query = Articulo_ParteUnica::query()
            ->where('articulo_id', $articuloId);

        if ($estado !== null && $estado !== '' && $estado !== 'T') {
            $query->where('estado', $estado);
        }

        return $query
            ->orderByDesc('numeroparte')
            ->paginate($porPagina);
    }

    public function contarPorArticulo(int $articuloId, ?string $estado = null): int
    {
        $query = Articulo_ParteUnica::query()->where('articulo_id', $articuloId);

        if ($estado !== null && $estado !== '' && $estado !== 'T') {
            $query->where('estado', $estado);
        }

        return (int) $query->count();
    }
}
