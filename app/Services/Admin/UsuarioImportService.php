<?php

namespace App\Services\Admin;

use App\Imports\Admin\UsuarioImportLecturaCruda;
use App\Models\Admin\Rol;
use App\Models\Compras\SectorLegajocompra;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Oficinacompra;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Vendedor;
use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use App\Support\Admin\UsuarioImportColumnasSupport;
use App\Support\Admin\UsuarioImportIdentidadSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class UsuarioImportService
{
    public function __construct(
        private ContabilidadCuentaAutomaticaSeedService $cuentaAutomaticaSeedService
    ) {
    }

    /**
     * @param  list<int>  $rolIds
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function importar(
        UploadedFile $archivo,
        array $rolIds,
        array $empresaIds,
        int $centrocostoId,
        string $password,
        ?int $vendedorId,
        ?int $sectorLegajocompraId,
        ?int $oficinacompraId,
        ?string $colUsuario,
        ?string $colNombre,
        ?string $colEmail,
        ?int $filaEncabezadoManual,
        ?int $hojaIndice1Based,
        ?string $dominioEmail = null,
        ?bool $generarLoginSiFalta = null,
        ?bool $generarEmailSiFalta = null
    ): array {
        $rolIds = $this->idsEnterosValidos($rolIds);
        $empresaIds = $this->idsEnterosValidos($empresaIds);
        $dominioEmail = UsuarioImportIdentidadSupport::normalizarDominio(
            $dominioEmail ?? UsuarioImportIdentidadSupport::dominioEmailDefault()
        );
        $generarLoginSiFalta = $generarLoginSiFalta ?? (bool) config('usuario_import.generar_login_si_falta', true);
        $generarEmailSiFalta = $generarEmailSiFalta ?? (bool) config('usuario_import.generar_email_si_falta', true);

        if ($rolIds === []) {
            throw new \InvalidArgumentException('Debe asignar al menos un rol.');
        }
        if ($empresaIds === []) {
            throw new \InvalidArgumentException('Debe asignar al menos una empresa.');
        }
        if (strlen($password) < 5) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos 5 caracteres.');
        }
        if (! Centrocosto::query()->whereKey($centrocostoId)->exists()) {
            throw new \InvalidArgumentException('El centro de costo indicado no existe.');
        }
        if (Rol::query()->whereIn('id', $rolIds)->count() !== count($rolIds)) {
            throw new \InvalidArgumentException('Uno o más roles indicados no existen.');
        }
        if (Empresa::query()->whereIn('id', $empresaIds)->count() !== count($empresaIds)) {
            throw new \InvalidArgumentException('Una o más empresas indicadas no existen.');
        }
        if ($vendedorId !== null && $vendedorId > 0 && ! Vendedor::query()->whereKey($vendedorId)->exists()) {
            throw new \InvalidArgumentException('El vendedor indicado no existe.');
        }
        if ($sectorLegajocompraId !== null && $sectorLegajocompraId > 0
            && ! SectorLegajocompra::query()->whereKey($sectorLegajocompraId)->exists()) {
            throw new \InvalidArgumentException('El sector legajo de compras indicado no existe.');
        }
        if ($oficinacompraId !== null && $oficinacompraId > 0
            && ! Oficinacompra::query()->whereKey($oficinacompraId)->exists()) {
            throw new \InvalidArgumentException('La oficina de compras indicada no existe.');
        }
        if ($generarEmailSiFalta && ($dominioEmail === '' || $dominioEmail === '@')) {
            throw new \InvalidArgumentException('Indique un dominio de email válido para la generación automática.');
        }

        $colUsuario = $this->nombreColumna($colUsuario, UsuarioImportColumnasSupport::COL_USUARIO_DEFAULT);
        $colNombre = $this->nombreColumna($colNombre, UsuarioImportColumnasSupport::COL_NOMBRE_DEFAULT);
        $colEmail = $this->nombreColumna($colEmail, UsuarioImportColumnasSupport::COL_EMAIL_DEFAULT);

        $hojas = UsuarioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = UsuarioImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? ['indice' => 1, 'nombre' => 'Hoja1'];

        $hoja = Excel::toArray(new UsuarioImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            throw new \InvalidArgumentException('La hoja seleccionada no tiene filas legibles.');
        }

        $filaEncabezado = UsuarioImportColumnasSupport::detectarFilaEncabezado(
            $archivo,
            $filaEncabezadoManual,
            $hojaIndice0
        );
        $indiceEncabezado = $filaEncabezado - 1;
        $encabezados = $hoja[$indiceEncabezado] ?? [];

        if (! is_array($encabezados) || ! UsuarioImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
            throw new \InvalidArgumentException(
                'No se detectó fila de encabezados en la fila '.$filaEncabezado.'. Indique la fila manualmente.'
            );
        }

        $colUsuarioInfo = UsuarioImportColumnasSupport::resolverColumna(
            $encabezados,
            $colUsuario,
            UsuarioImportColumnasSupport::COL_USUARIO_DEFAULT,
            UsuarioImportColumnasSupport::ALIAS_ENCABEZADO_USUARIO
        );
        $colNombreInfo = UsuarioImportColumnasSupport::resolverColumna(
            $encabezados,
            $colNombre,
            UsuarioImportColumnasSupport::COL_NOMBRE_DEFAULT,
            UsuarioImportColumnasSupport::ALIAS_ENCABEZADO_NOMBRE
        );
        $colEmailInfo = UsuarioImportColumnasSupport::resolverColumna(
            $encabezados,
            $colEmail,
            UsuarioImportColumnasSupport::COL_EMAIL_DEFAULT,
            UsuarioImportColumnasSupport::ALIAS_ENCABEZADO_EMAIL
        );

        if ($colNombreInfo === null) {
            throw new \InvalidArgumentException('Falta la columna de nombre en el Excel (obligatoria).');
        }
        if ($colUsuarioInfo === null && ! $generarLoginSiFalta) {
            throw new \InvalidArgumentException('Falta columna de usuario y la generación automática de login está desactivada.');
        }
        if ($colEmailInfo === null && ! $generarEmailSiFalta) {
            throw new \InvalidArgumentException('Falta columna de email y la generación automática de email está desactivada.');
        }

        $this->cuentaAutomaticaSeedService->asegurarCatalogoEmpresas($empresaIds);

        $resumen = [
            'filas_leidas' => 0,
            'usuarios_creados' => 0,
            'filas_omitidas' => 0,
            'logins_generados' => 0,
            'emails_generados' => 0,
            'fila_encabezado' => $filaEncabezado,
            'hoja_indice' => (int) $hojaSeleccionada['indice'],
            'hoja_nombre' => (string) $hojaSeleccionada['nombre'],
            'dominio_email' => $dominioEmail,
            'logins_creados' => [],
            'errores' => [],
        ];

        $loginsVistos = [];
        $emailsVistos = [];
        $vendedorId = ($vendedorId !== null && $vendedorId > 0) ? $vendedorId : null;
        $sectorLegajocompraId = ($sectorLegajocompraId !== null && $sectorLegajocompraId > 0)
            ? $sectorLegajocompraId
            : null;
        $oficinacompraId = ($oficinacompraId !== null && $oficinacompraId > 0) ? $oficinacompraId : null;

        for ($i = $indiceEncabezado + 1; $i < count($hoja); $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila)) {
                continue;
            }

            $loginExcel = $colUsuarioInfo
                ? UsuarioImportColumnasSupport::normalizarTextoCelda(
                    UsuarioImportColumnasSupport::valorCeldaFila($fila, $colUsuarioInfo)
                )
                : '';
            $nombre = UsuarioImportColumnasSupport::normalizarTextoCelda(
                UsuarioImportColumnasSupport::valorCeldaFila($fila, $colNombreInfo)
            );
            $emailExcel = $colEmailInfo
                ? UsuarioImportColumnasSupport::normalizarEmail(
                    UsuarioImportColumnasSupport::valorCeldaFila($fila, $colEmailInfo)
                )
                : '';

            if ($loginExcel === '' && $nombre === '' && $emailExcel === '') {
                continue;
            }

            $resumen['filas_leidas']++;
            $filaExcel = $i + 1;

            $identidad = UsuarioImportIdentidadSupport::resolverIdentidadFila(
                $nombre,
                $loginExcel,
                $emailExcel,
                $dominioEmail,
                $generarLoginSiFalta,
                $generarEmailSiFalta,
                $loginsVistos,
                $emailsVistos
            );

            if ($identidad['error'] !== null) {
                $resumen['filas_omitidas']++;
                $resumen['errores'][] = 'Fila '.$filaExcel.': '.$identidad['error'].'.';

                continue;
            }

            $login = $identidad['login'];
            $email = $identidad['email'];

            if (mb_strlen($login) > 50 || mb_strlen($nombre) > 50 || mb_strlen($email) > 100) {
                $resumen['filas_omitidas']++;
                $resumen['errores'][] = 'Fila '.$filaExcel.': longitud de campo excedida.';

                continue;
            }

            $emailValidator = Validator::make(['email' => $email], ['email' => 'email']);
            if ($emailValidator->fails()) {
                $resumen['filas_omitidas']++;
                $resumen['errores'][] = 'Fila '.$filaExcel.': email inválido ('.$email.').';

                continue;
            }

            try {
                DB::transaction(function () use (
                    $login,
                    $nombre,
                    $email,
                    $password,
                    $centrocostoId,
                    $vendedorId,
                    $sectorLegajocompraId,
                    $oficinacompraId,
                    $rolIds,
                    $empresaIds
                ) {
                    $usuario = Usuario::create([
                        'usuario' => $login,
                        'nombre' => $nombre,
                        'email' => $email,
                        'password' => $password,
                        'centrocosto_id' => $centrocostoId,
                        'vendedor_id' => $vendedorId,
                        'sector_legajocompra_id' => $sectorLegajocompraId,
                        'oficinacompra_id' => $oficinacompraId,
                        'suspendido' => false,
                    ]);
                    $usuario->auditSync('roles', $rolIds);
                    $usuario->auditSync('usuario_empresas', $empresaIds);
                });

                if ($identidad['login_generado']) {
                    $resumen['logins_generados']++;
                }
                if ($identidad['email_generado']) {
                    $resumen['emails_generados']++;
                }
                $resumen['usuarios_creados']++;
                $resumen['logins_creados'][] = $login;
            } catch (Throwable $e) {
                $resumen['filas_omitidas']++;
                $resumen['errores'][] = 'Fila '.$filaExcel.': error al crear «'.$login.'» — '.$e->getMessage();
            }
        }

        return $resumen;
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function idsEnterosValidos(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $ids
        ), static fn (int $id) => $id > 0)));
    }

    private function nombreColumna(?string $valor, string $default): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : $default;
    }
}
