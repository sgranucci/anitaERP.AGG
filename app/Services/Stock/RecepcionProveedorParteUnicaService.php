<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Models\Stock\Recepcion_Proveedor_ParteUnica;
use App\Support\Stock\RecepcionProveedorParteUnicaSupport;
use App\Support\Stock\RecpunicaAnitaBridgeSupport;
use App\Support\Stock\StkParteUnicaAnitaBridgeSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecepcionProveedorParteUnicaService
{
    public function __construct(
        private readonly ArticuloParteUnicaService $articuloParteUnicaService,
    ) {
    }

    /**
     * Genera NPUs: maestro global (articulo_parte_unica) + vínculo recepción (recepcion_proveedor_parte_unica).
     * Numerador: MAX(numeroparte) global (ERP + Anita stk_parte_unica), secuencial por unidad.
     */
    public function generarYSincronizar(Recepcion_Proveedor $recepcion): void
    {
        if ($recepcion->tipo !== Recepcion_Proveedor::TIPO_RECEPCION) {
            return;
        }

        $recepcion->loadMissing('recepcion_proveedor_articulos.articulos');

        if ((int) $recepcion->numerorecepcion <= 0) {
            throw new \RuntimeException('La recepción debe tener numerorecepcion asignado.');
        }

        $partesNuevas = collect();

        if (! Recepcion_Proveedor_ParteUnica::query()->where('recepcion_proveedor_id', $recepcion->id)->exists()) {
            $partesNuevas = DB::transaction(function () use ($recepcion) {
                $siguienteNumero = $this->articuloParteUnicaService->siguienteNumeroparteGlobalConBloqueo();
                $creadas = collect();

                foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
                    if (! RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($linea->articulos)) {
                        continue;
                    }

                    $unidades = RecepcionProveedorParteUnicaSupport::unidadesDesdeCantidad((float) $linea->cantidad);

                    for ($u = 0; $u < $unidades; $u++) {
                        $numeroparte = $siguienteNumero++;
                        $creadas->push($this->persistirParteUnicaEnRecepcion($linea, $numeroparte));
                    }
                }

                return $creadas;
            });

            $this->sincronizarStkParteUnica($partesNuevas);
        }

        $this->sincronizarRecpunica($recepcion);
    }

    /**
     * Graba el mismo numeroparte en articulo_parte_unica (maestro) y recepcion_proveedor_parte_unica (vínculo).
     */
    public function persistirParteUnicaEnRecepcion(
        Recepcion_Proveedor_Articulo $linea,
        int $numeroparte
    ): Recepcion_Proveedor_ParteUnica {
        if ($numeroparte <= 0) {
            throw new \InvalidArgumentException('numeroparte inválido.');
        }

        if (Articulo_ParteUnica::query()->where('numeroparte', $numeroparte)->exists()) {
            throw new \RuntimeException("El número de parte {$numeroparte} ya existe en articulo_parte_unica.");
        }

        if (Recepcion_Proveedor_ParteUnica::query()->where('numeroparte', $numeroparte)->exists()) {
            throw new \RuntimeException("El número de parte {$numeroparte} ya está vinculado a una recepción.");
        }

        $this->articuloParteUnicaService->crearLocal((int) $linea->articulo_id, $numeroparte);

        return Recepcion_Proveedor_ParteUnica::create([
            'recepcion_proveedor_id' => $linea->recepcion_proveedor_id,
            'recepcion_proveedor_articulo_id' => $linea->id,
            'numeroparte' => $numeroparte,
        ]);
    }

    /** @param Collection<int, Recepcion_Proveedor_ParteUnica> $partes */
    private function sincronizarStkParteUnica(Collection $partes): void
    {
        foreach ($partes as $parte) {
            $parte->loadMissing('recepcion_proveedor_articulos.articulos');
            $apu = Articulo_ParteUnica::query()
                ->where('numeroparte', $parte->numeroparte)
                ->first();

            if ($apu) {
                StkParteUnicaAnitaBridgeSupport::insertar($apu);
            }
        }
    }

    public function sincronizarRecpunica(Recepcion_Proveedor $recepcion): void
    {
        $partes = Recepcion_Proveedor_ParteUnica::query()
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->orderBy('numeroparte')
            ->get();

        foreach ($partes as $parte) {
            RecpunicaAnitaBridgeSupport::insertarDesdeParte($parte);
        }
    }
}
