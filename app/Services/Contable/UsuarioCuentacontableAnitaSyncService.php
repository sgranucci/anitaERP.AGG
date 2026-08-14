<?php

namespace App\Services\Contable;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;

class UsuarioCuentacontableAnitaSyncService
{
    /**
     * Sincroniza ctamusu como fuente de verdad. Un usuario Anita sin filas queda
     * sin restricciones de cuentas en ERP. Las filas Anita con empresa/cuenta
     * inexistente en ERP se omiten con advertencia; el resto igual se aplica.
     *
     * @return array<string, mixed>
     */
    public function sincronizar(bool $aplicar = false): array
    {
        $filasUsuarios = $this->listarAnita(
            'compras',
            'usuario',
            'usu_usuario,usu_logname',
        );
        $filasCuentas = $this->listarAnita(
            'contab',
            'ctamusu',
            'ctamu_usuario,ctamu_empresa,ctamu_cuenta',
        );

        if ($filasCuentas === []) {
            throw new \RuntimeException(
                'Anita devolvió ctamusu vacío. Se aborta para no quitar todas las restricciones locales.',
            );
        }

        $advertencias = [];
        $usuariosErpPorLogname = $this->usuariosErpPorLogname($advertencias);
        $usuariosAnita = $this->usuariosAnitaPorId($filasUsuarios, $advertencias);
        $mapaEmpresas = $this->mapaEmpresasPorCodigo($advertencias);
        $mapaCuentas = $this->mapaCuentasPorEmpresaYCodigo($advertencias);

        $filasPorUsuarioAnita = [];
        foreach ($filasCuentas as $fila) {
            $usuarioAnitaId = (int) ($fila->ctamu_usuario ?? 0);
            if ($usuarioAnitaId <= 0) {
                $advertencias[] = 'Fila ctamusu con ctamu_usuario inválido; se omite.';
                continue;
            }
            $filasPorUsuarioAnita[$usuarioAnitaId][] = $fila;
        }

        $resumen = [
            'modo' => $aplicar ? 'APLICADO' : 'VISTA PREVIA',
            'filas_ctamusu' => count($filasCuentas),
            'usuarios_anita' => count($usuariosAnita),
            'usuarios_con_match' => 0,
            'usuarios_sin_match' => 0,
            'filas_omitidas' => 0,
            'usuarios_con_cambios' => 0,
            'usuarios_iguales' => 0,
            'relaciones_agregadas' => 0,
            'relaciones_quitadas' => 0,
            'advertencias' => &$advertencias,
        ];

        foreach ($usuariosAnita as $usuarioAnitaId => $logname) {
            $usuariosErp = $usuariosErpPorLogname[$logname] ?? [];
            if (count($usuariosErp) !== 1) {
                $resumen['usuarios_sin_match']++;
                $advertencias[] = "Usuario Anita {$usuarioAnitaId} ({$logname}) sin match único en ERP.";
                continue;
            }

            $resumen['usuarios_con_match']++;
            $usuarioErpId = $usuariosErp[0];
            $cuentasDeseadas = [];

            foreach ($filasPorUsuarioAnita[$usuarioAnitaId] ?? [] as $fila) {
                $empresaAnita = $this->codigoNumerico($fila->ctamu_empresa ?? null);
                $cuentaAnita = $this->codigoNumerico($fila->ctamu_cuenta ?? null);
                $empresaIds = $mapaEmpresas[$empresaAnita] ?? [];

                if (count($empresaIds) !== 1) {
                    $resumen['filas_omitidas']++;
                    $advertencias[] = "{$logname}: empresa Anita {$empresaAnita} sin match único en ERP; fila omitida.";
                    continue;
                }

                $claveCuenta = $empresaIds[0].'|'.$cuentaAnita;
                $cuentaIds = $mapaCuentas[$claveCuenta] ?? [];
                if (count($cuentaIds) !== 1) {
                    $resumen['filas_omitidas']++;
                    $advertencias[] = "{$logname}: cuenta {$cuentaAnita} empresa Anita {$empresaAnita} inexistente en ERP; fila omitida.";
                    continue;
                }

                $cuentasDeseadas[$cuentaIds[0]] = true;
            }

            $deseadas = array_map('intval', array_keys($cuentasDeseadas));
            sort($deseadas);
            $actuales = DB::table('usuario_cuentacontable')
                ->where('usuario_id', $usuarioErpId)
                ->pluck('cuentacontable_id')
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $agregar = array_values(array_diff($deseadas, $actuales));
            $quitar = array_values(array_diff($actuales, $deseadas));
            if ($agregar === [] && $quitar === []) {
                $resumen['usuarios_iguales']++;
                continue;
            }

            $resumen['usuarios_con_cambios']++;
            $resumen['relaciones_agregadas'] += count($agregar);
            $resumen['relaciones_quitadas'] += count($quitar);

            if ($aplicar) {
                $this->reemplazarCuentasUsuario($usuarioErpId, $deseadas);
            }
        }

        unset($resumen['advertencias']);
        $resumen['advertencias'] = $advertencias;

        return $resumen;
    }

    /** @return list<object> */
    private function listarAnita(string $sistema, string $tabla, string $campos): array
    {
        $api = new ApiAnita();
        $respuesta = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
        ]));

        if ($respuesta['error_lectura'] !== null) {
            throw new \RuntimeException("Error leyendo {$tabla} desde Anita: ".$respuesta['error_lectura']);
        }

        return $respuesta['filas'];
    }

    /**
     * @param  list<string>  $advertencias
     * @return array<string, list<int>>
     */
    private function usuariosErpPorLogname(array &$advertencias): array
    {
        $mapa = [];
        foreach (Usuario::query()->get(['id', 'usuario']) as $usuario) {
            $logname = $this->normalizarLogname($usuario->usuario);
            if ($logname === '') {
                continue;
            }
            $mapa[$logname][] = (int) $usuario->id;
        }

        foreach ($mapa as $logname => $ids) {
            if (count($ids) > 1) {
                $advertencias[] = "Logname ERP duplicado {$logname}: ids ".implode(', ', $ids).'.';
            }
        }

        return $mapa;
    }

    /**
     * @param  list<object>  $filas
     * @param  list<string>  $advertencias
     * @return array<int, string>
     */
    private function usuariosAnitaPorId(array $filas, array &$advertencias): array
    {
        $mapa = [];
        $idsPorLogname = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila->usu_usuario ?? 0);
            $logname = $this->normalizarLogname($fila->usu_logname ?? '');
            if ($id <= 0 || $logname === '') {
                continue;
            }
            $mapa[$id] = $logname;
            $idsPorLogname[$logname][] = $id;
        }

        foreach ($idsPorLogname as $logname => $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) > 1) {
                $advertencias[] = "Logname Anita duplicado {$logname}: ids ".implode(', ', $ids).'.';
                foreach ($ids as $id) {
                    unset($mapa[$id]);
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $advertencias
     * @return array<string, list<int>>
     */
    private function mapaEmpresasPorCodigo(array &$advertencias): array
    {
        $mapa = [];
        foreach (Empresa::query()->get(['id', 'codigo']) as $empresa) {
            $codigo = $this->codigoNumerico($empresa->codigo);
            if ($codigo !== '') {
                $mapa[$codigo][] = (int) $empresa->id;
            }
        }

        foreach ($mapa as $codigo => $ids) {
            if (count($ids) > 1) {
                $advertencias[] = "Código de empresa ERP duplicado {$codigo}: ids ".implode(', ', $ids).'.';
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $advertencias
     * @return array<string, list<int>>
     */
    private function mapaCuentasPorEmpresaYCodigo(array &$advertencias): array
    {
        $mapa = [];
        foreach (Cuentacontable::query()->get(['id', 'empresa_id', 'codigo']) as $cuenta) {
            $codigo = $this->codigoNumerico($cuenta->codigo);
            if ($codigo === '') {
                continue;
            }
            $mapa[(int) $cuenta->empresa_id.'|'.$codigo][] = (int) $cuenta->id;
        }

        foreach ($mapa as $clave => $ids) {
            if (count($ids) > 1) {
                $advertencias[] = "Cuenta ERP duplicada {$clave}: ids ".implode(', ', $ids).'.';
            }
        }

        return $mapa;
    }

    /** @param  list<int>  $cuentacontableIds */
    private function reemplazarCuentasUsuario(int $usuarioId, array $cuentacontableIds): void
    {
        DB::transaction(function () use ($usuarioId, $cuentacontableIds) {
            DB::table('usuario_cuentacontable')->where('usuario_id', $usuarioId)->delete();
            if ($cuentacontableIds === []) {
                return;
            }

            $ahora = now();
            DB::table('usuario_cuentacontable')->insert(array_map(
                static fn (int $cuentaId) => [
                    'usuario_id' => $usuarioId,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ],
                $cuentacontableIds,
            ));
        });
    }

    private function normalizarLogname($valor): string
    {
        return mb_strtolower(trim((string) $valor));
    }

    private function codigoNumerico($valor): string
    {
        $codigo = trim((string) $valor);

        return $codigo !== '' && ctype_digit($codigo) ? (string) ((int) $codigo) : '';
    }
}
