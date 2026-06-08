<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use App\Support\Stock\StkParteUnicaAnitaBridgeSupport;
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
        DB::transaction(function () use ($parte) {
            StkParteUnicaAnitaBridgeSupport::eliminar($parte);
            $parte->delete();
        });
    }

    public function listarPorArticulo(int $articuloId, int $porPagina = 20)
    {
        return Articulo_ParteUnica::query()
            ->where('articulo_id', $articuloId)
            ->orderByDesc('numeroparte')
            ->paginate($porPagina);
    }

    public function contarPorArticulo(int $articuloId): int
    {
        return (int) Articulo_ParteUnica::query()->where('articulo_id', $articuloId)->count();
    }
}
