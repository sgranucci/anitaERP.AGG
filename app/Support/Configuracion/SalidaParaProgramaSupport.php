<?php

namespace App\Support\Configuracion;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Filtra impresoras (salida) según usos y destinos de programa configurados en uso_salida_impresora.
 */
class SalidaParaProgramaSupport
{
    public static function aplicarFiltroQuery(Builder $query, ?string $programa): Builder
    {
        $programa = SeteoSalidaProgramaSupport::resolver($programa);

        return $query->where(function (Builder $q) use ($programa) {
            $q->whereDoesntHave('usoSalidaImpresoras')
                ->orWhereHas('usoSalidaImpresoras', function (Builder $uq) use ($programa) {
                    self::aplicarFiltroUsoParaPrograma($uq, $programa);
                });
        });
    }

    public static function aplicarFiltroUsoParaPrograma(Builder $query, string $programa): Builder
    {
        return $query->where(function (Builder $pq) use ($programa) {
            $pq->whereNull('programas_destino')
                ->orWhereJsonLength('programas_destino', 0);

            foreach (self::codigosCoincidentesPrograma($programa) as $codigo) {
                $pq->orWhereJsonContains('programas_destino', $codigo);
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function codigosCoincidentesPrograma(string $programa): array
    {
        $codigos = [$programa];

        foreach (SeteoSalidaProgramaSupport::codigosPrograma() as $codigoBase) {
            if ($codigoBase !== $programa && Str::startsWith($programa, $codigoBase.'_')) {
                $codigos[] = $codigoBase;
            }
        }

        return array_values(array_unique($codigos));
    }

    public static function salidaPermitidaParaPrograma(?string $programa, int $salidaId): bool
    {
        $programa = SeteoSalidaProgramaSupport::resolver($programa);

        return \App\Models\Configuracion\Salida::query()
            ->whereKey($salidaId)
            ->where(function (Builder $q) use ($programa) {
                self::aplicarFiltroQuery($q, $programa);
            })
            ->exists();
    }
}
