<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Concepto_Venta;

/**
 * Fachada de tags de concepto: delega en ConceptoVentaPlantillaMotor.
 */
final class ConceptoVentaTagSupport
{
    public const TIPO_TEXTO = ConceptoVentaPlantillaMotor::TIPO_TEXTO;

    public const TIPO_FECHA = ConceptoVentaPlantillaMotor::TIPO_FECHA;

    public const TIPO_PERIODO = ConceptoVentaPlantillaMotor::TIPO_PERIODO;

    public const TIPO_LISTA = ConceptoVentaPlantillaMotor::TIPO_LISTA;

    /** @var list<string> */
    public const TIPOS = ConceptoVentaPlantillaMotor::TIPOS;

    public const REGEX = ConceptoVentaPlantillaMotor::REGEX_TAG;

    public const LARGO_DETALLE_ARCA = ConceptoVentaPlantillaMotor::LARGO_DETALLE_ARCA;

    /** @return list<string> */
    public static function extraerClaves(string $plantilla): array
    {
        return ConceptoVentaPlantillaMotor::extraerClaves($plantilla);
    }

    public static function normalizarClave(string $clave): string
    {
        return ConceptoVentaPlantillaMotor::normalizarClave($clave);
    }

    public static function esClaveValida(string $clave): bool
    {
        return ConceptoVentaPlantillaMotor::esClaveValida($clave);
    }

    /** @param  array<string, string>  $valores */
    public static function sustituir(string $plantilla, array $valores): string
    {
        return ConceptoVentaPlantillaMotor::render($plantilla, $valores);
    }

    public static function tieneTagsSinResolver(string $texto): bool
    {
        return ConceptoVentaPlantillaMotor::tieneTagsSinResolver($texto);
    }

    public static function mensajeTagsPendientes(string $texto): string
    {
        return ConceptoVentaPlantillaMotor::mensajeTagsPendientes($texto);
    }

    public static function serializarParaApi($tags): array
    {
        if ($tags === null) {
            return [];
        }

        $out = [];
        foreach ($tags as $tag) {
            $clave = self::normalizarClave((string) ($tag->clave ?? ''));
            if ($clave === '' || ! self::esClaveValida($clave)) {
                continue;
            }
            $largo = $tag->largo_max ?? null;
            $origen = ConceptoVentaPlantillaMotor::normalizarOrigen((string) ($tag->origen ?? ConceptoVentaPlantillaMotor::ORIGEN_PEDIBLE));
            if (ConceptoVentaPlantillaMotor::esTagSistema($clave)) {
                $origen = ConceptoVentaPlantillaMotor::ORIGEN_SISTEMA;
            }
            $out[] = [
                'clave' => $clave,
                'etiqueta' => trim((string) ($tag->etiqueta ?? $clave)) ?: $clave,
                'tipo' => self::normalizarTipo((string) ($tag->tipo ?? self::TIPO_TEXTO)),
                'origen' => $origen,
                'obligatorio' => (bool) ($tag->obligatorio ?? true),
                'orden' => max(1, (int) ($tag->orden ?? 1)),
                'largo_max' => $largo !== null && (int) $largo > 0 ? (int) $largo : null,
                'opciones' => trim((string) ($tag->opciones ?? '')),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

        return $out;
    }

    public static function tagsDesdeConcepto(?Concepto_Venta $concepto): array
    {
        if ($concepto === null) {
            return [];
        }

        if (! $concepto->relationLoaded('tags')) {
            $concepto->load(['tags' => fn ($q) => $q->orderBy('orden')->orderBy('id')]);
        }

        return self::serializarParaApi($concepto->tags);
    }

    /** @return list<array{clave: string, etiqueta: string, tipo: string, origen: string, obligatorio: bool, orden: int, largo_max: int|null}> */
    public static function tagsPediblesDesdeConcepto(?Concepto_Venta $concepto): array
    {
        return array_values(array_filter(
            self::tagsDesdeConcepto($concepto),
            static fn (array $t): bool => ($t['origen'] ?? '') !== ConceptoVentaPlantillaMotor::ORIGEN_SISTEMA
        ));
    }

    public static function normalizarTipo(string $tipo): string
    {
        return ConceptoVentaPlantillaMotor::normalizarTipo($tipo);
    }

    /** @param  list<string>  $clavesDefinidas */
    public static function mensajePlantillaSinDefinicion(string $plantilla, array $clavesDefinidas): ?string
    {
        $enPlantilla = self::extraerClaves($plantilla);
        if ($enPlantilla === []) {
            return null;
        }

        $definidas = [];
        foreach ($clavesDefinidas as $c) {
            $n = self::normalizarClave((string) $c);
            if ($n !== '') {
                $definidas[$n] = true;
            }
        }
        foreach (ConceptoVentaPlantillaMotor::TAGS_SISTEMA as $sis) {
            $definidas[$sis] = true;
        }

        $faltan = [];
        foreach ($enPlantilla as $clave) {
            if (! isset($definidas[$clave])) {
                $faltan[] = '@'.$clave.'@';
            }
        }

        if ($faltan === []) {
            return null;
        }

        return 'Defina en la grilla de tags: '.implode(', ', $faltan);
    }

    /**
     * @param  list<array<string, mixed>>  $filasForm
     * @return list<array{clave: string, etiqueta: string, tipo: string, origen: string, obligatorio: bool, orden: int, largo_max: int|null, opciones: string|null}>
     */
    public static function normalizarFilasFormulario(array $filasForm): array
    {
        $out = [];
        $vistos = [];
        $orden = 0;
        foreach ($filasForm as $fila) {
            $clave = self::normalizarClave((string) ($fila['clave'] ?? ''));
            if ($clave === '' || ! self::esClaveValida($clave) || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $orden++;
            $largo = $fila['largo_max'] ?? null;
            $largoInt = $largo !== null && $largo !== '' ? (int) $largo : null;
            $origen = ConceptoVentaPlantillaMotor::normalizarOrigen((string) ($fila['origen'] ?? ''));
            if (ConceptoVentaPlantillaMotor::esTagSistema($clave)) {
                $origen = ConceptoVentaPlantillaMotor::ORIGEN_SISTEMA;
            }
            $opciones = trim((string) ($fila['opciones'] ?? ''));

            $out[] = [
                'clave' => $clave,
                'etiqueta' => trim((string) ($fila['etiqueta'] ?? '')) ?: $clave,
                'tipo' => self::normalizarTipo((string) ($fila['tipo'] ?? self::TIPO_TEXTO)),
                'origen' => $origen,
                'obligatorio' => (bool) ($fila['obligatorio'] ?? true),
                'orden' => max(1, (int) ($fila['orden'] ?? $orden)),
                'largo_max' => $largoInt !== null && $largoInt > 0 ? min(255, $largoInt) : null,
                'opciones' => $opciones !== '' ? mb_substr($opciones, 0, 255) : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{tipo?: string, largo_max?: int|null}>  $metas
     * @return array<string, array{tipo?: string, largo_max?: int|null}>
     */
    public static function metasDesdeTagsApi(array $tags): array
    {
        $metas = [];
        foreach ($tags as $tag) {
            $clave = self::normalizarClave((string) ($tag['clave'] ?? ''));
            if ($clave === '') {
                continue;
            }
            $metas[$clave] = [
                'tipo' => self::normalizarTipo((string) ($tag['tipo'] ?? self::TIPO_TEXTO)),
                'largo_max' => isset($tag['largo_max']) ? (int) $tag['largo_max'] : null,
            ];
        }

        return $metas;
    }
}
