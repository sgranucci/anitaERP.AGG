<?php

namespace App\Services\Contable;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Support\Contable\AsientoImportColumnasSupport;
use App\Support\Contable\AsientoImpresionLecturaSupport;
use App\Support\Contable\AsientoPegadoTextoSupport;

class AsientoPegadoTextoService
{
    /**
     * @return array<string, mixed>
     */
    public function decodificar(string $texto, int $empresaId): array
    {
        return $this->completar(AsientoPegadoTextoSupport::decodificar($texto), $empresaId);
    }

    public function decodificarArchivo(string $ruta, ?string $mime, int $empresaId): array
    {
        return $this->completar(AsientoImpresionLecturaSupport::leer($ruta, $mime), $empresaId);
    }

    /**
     * @param  array{filas: list<array<string, mixed>>, con_encabezado: bool}  $parseado
     * @return array<string, mixed>
     */
    private function completar(array $parseado, int $empresaId): array
    {
        $filas = [];
        $advertencias = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $cacheCuentas = [];
        $cacheCc = [];

        if ($parseado['filas'] === []) {
            return [
                'ok' => false,
                'mensaje' => 'No se reconocieron líneas. Suba el PDF o la foto de la impresión con los títulos Nro.Cta., Debe y Haber.',
                'filas' => [],
                'advertencias' => [],
                'con_encabezado' => $parseado['con_encabezado'],
            ];
        }

        if ($empresaId <= 0) {
            $advertencias[] = 'Indique la empresa para resolver las cuentas contables.';
        }

        foreach ($parseado['filas'] as $fila) {
            $debe = (float) $fila['debe'];
            $haber = (float) $fila['haber'];
            $totalDebe += $debe;
            $totalHaber += $haber;

            $resuelta = [
                'codigo_cuenta' => $fila['codigo_cuenta'],
                'cuenta_nombre' => '',
                'cuentacontable_id' => null,
                'manejaccosto' => 'N',
                'codigo_centrocosto' => $fila['codigo_centrocosto'],
                'centrocosto_id' => null,
                'centrocosto_nombre' => '',
                'debe' => $debe,
                'haber' => $haber,
                'debe_texto' => $debe > 0 ? AsientoImportColumnasSupport::formatearImporte($debe) : '',
                'haber_texto' => $haber > 0 ? AsientoImportColumnasSupport::formatearImporte($haber) : '',
                'detalle' => $fila['detalle'],
                'estado' => 'ok',
                'mensaje' => '',
            ];

            if ($empresaId <= 0) {
                $resuelta['estado'] = 'pendiente';
                $resuelta['mensaje'] = 'Sin empresa';
                $filas[] = $resuelta;
                continue;
            }

            $cuenta = $this->resolverCuenta($empresaId, $fila['codigo_cuenta'], $cacheCuentas);
            if ($cuenta === null) {
                $resuelta['estado'] = 'advertencia';
                $resuelta['mensaje'] = 'Cuenta '.$fila['codigo_cuenta'].' no encontrada en la empresa';
                $advertencias[] = $resuelta['mensaje'];
                $filas[] = $resuelta;
                continue;
            }

            $resuelta['cuentacontable_id'] = (int) $cuenta->id;
            $resuelta['cuenta_nombre'] = (string) $cuenta->nombre;
            $resuelta['codigo_cuenta'] = (string) $cuenta->codigo;
            $maneja = $cuenta->manejaccosto === 'S' || $cuenta->manejaccosto === '1' || $cuenta->manejaccosto === 1;
            $resuelta['manejaccosto'] = $maneja ? 'S' : 'N';

            if ($fila['codigo_centrocosto'] !== '') {
                $cc = $this->resolverCentrocosto($fila['codigo_centrocosto'], $cacheCc);
                if ($cc === null) {
                    $resuelta['estado'] = 'advertencia';
                    $resuelta['mensaje'] = 'Centro de costo '.$fila['codigo_centrocosto'].' no existe';
                    $advertencias[] = $resuelta['mensaje'].' (cuenta '.$resuelta['codigo_cuenta'].')';
                } else {
                    $resuelta['centrocosto_id'] = (int) $cc->id;
                    $resuelta['centrocosto_nombre'] = (string) $cc->nombre;
                    $resuelta['codigo_centrocosto'] = (string) $cc->codigo;
                }
            } elseif ($maneja) {
                $resuelta['estado'] = 'advertencia';
                $resuelta['mensaje'] = 'La cuenta '.$resuelta['codigo_cuenta'].' requiere centro de costo';
                $advertencias[] = $resuelta['mensaje'];
            }

            $filas[] = $resuelta;
        }

        $diferencia = round($totalDebe - $totalHaber, 2);
        if (abs($diferencia) > 0.009) {
            $advertencias[] = 'El pegado no balancea: Debe '
                .AsientoImportColumnasSupport::formatearImporte($totalDebe)
                .' vs Haber '
                .AsientoImportColumnasSupport::formatearImporte($totalHaber).'.';
        }

        $advertencias = array_values(array_unique($advertencias));

        return [
            'ok' => true,
            'mensaje' => null,
            'filas' => $filas,
            'advertencias' => $advertencias,
            'con_encabezado' => $parseado['con_encabezado'],
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'total_debe_texto' => AsientoImportColumnasSupport::formatearImporte($totalDebe),
            'total_haber_texto' => AsientoImportColumnasSupport::formatearImporte($totalHaber),
        ];
    }

    /**
     * @param  array<string, ?Cuentacontable>  $cache
     */
    private function resolverCuenta(int $empresaId, string $codigo, array &$cache): ?Cuentacontable
    {
        $clave = $empresaId.'|'.$codigo;
        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $candidatos = [$codigo];
        if (str_contains($codigo, '-')) {
            $candidatos[] = str_replace('-', '', $codigo);
        } elseif (preg_match('/^\d{6,}$/', $codigo)) {
            $candidatos[] = substr($codigo, 0, -3).'-'.substr($codigo, -3);
        }

        $cuenta = null;
        foreach ($candidatos as $candidato) {
            $cuenta = Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $candidato)
                ->first();
            if ($cuenta !== null) {
                break;
            }
        }

        $cache[$clave] = $cuenta;

        return $cuenta;
    }

    /**
     * @param  array<string, ?Centrocosto>  $cache
     */
    private function resolverCentrocosto(string $codigo, array &$cache): ?Centrocosto
    {
        if (array_key_exists($codigo, $cache)) {
            return $cache[$codigo];
        }

        $cc = Centrocosto::query()->where('codigo', $codigo)->first();
        if ($cc === null && ctype_digit($codigo)) {
            $cc = Centrocosto::query()->where('codigo', ltrim($codigo, '0') ?: '0')->first();
        }

        $cache[$codigo] = $cc;

        return $cc;
    }
}
