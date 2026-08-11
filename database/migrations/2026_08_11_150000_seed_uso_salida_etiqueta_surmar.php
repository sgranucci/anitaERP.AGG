<?php

use App\Models\Configuracion\UsoSalidaImpresora;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $programa = SeteoSalidaProgramaSupport::STOCK_ETIQUETA_SURMAR;

        $uso = UsoSalidaImpresora::query()
            ->whereRaw('LOWER(nombre) IN (?, ?, ?)', ['etiqueta', 'etiquetas', 'etiqueta surmar'])
            ->orderBy('id')
            ->first();

        if (! $uso) {
            $uso = UsoSalidaImpresora::query()->create([
                'nombre' => 'Etiqueta Surmar',
                'descripcion' => 'Impresión de etiquetas recepción Surmar (Zebra ZPL en red o cola CUPS).',
                'programas_destino' => [$programa],
            ]);

            return;
        }

        $destinos = array_values(array_filter((array) ($uso->programas_destino ?? [])));
        if (! in_array($programa, $destinos, true)) {
            $destinos[] = $programa;
            $uso->programas_destino = $destinos;
            $uso->save();
        }

        // Asegurar que usos genéricos "etiqueta(s)" también incluyan el programa Surmar.
        UsoSalidaImpresora::query()->each(function (UsoSalidaImpresora $row) use ($programa) {
            $clave = Str::lower(Str::ascii(trim((string) $row->nombre)));
            if (! in_array($clave, ['etiqueta', 'etiquetas', 'etiqueta surmar'], true)) {
                return;
            }
            $destinos = array_values(array_filter((array) ($row->programas_destino ?? [])));
            if (in_array($programa, $destinos, true)) {
                return;
            }
            $destinos[] = $programa;
            $row->programas_destino = $destinos;
            $row->save();
        });
    }

    public function down(): void
    {
        $programa = SeteoSalidaProgramaSupport::STOCK_ETIQUETA_SURMAR;

        UsoSalidaImpresora::query()->each(function (UsoSalidaImpresora $uso) use ($programa) {
            $destinos = array_values(array_filter((array) ($uso->programas_destino ?? [])));
            $filtrados = array_values(array_filter($destinos, fn ($c) => $c !== $programa));
            if ($filtrados === $destinos) {
                return;
            }
            $uso->programas_destino = $filtrados === [] ? null : $filtrados;
            $uso->save();
        });
    }
};
