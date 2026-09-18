<?php

namespace Tests\Unit\Services\Compras;

use App\Http\Controllers\Compras\Comprobante_ProveedorController;
use App\Services\Compras\ComprobanteProveedorComLegajoResolucionService;
use App\Services\Compras\ComprobanteProveedorPersistenciaService;
use App\Services\Compras\ComprobanteProveedorPrefillService;
use App\Services\Compras\ComprobanteProveedorRecepcionesSupport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ComprobanteProveedorAltaSinBloqueoAnitaTest extends TestCase
{
    public function test_listar_disponibles_puede_omitir_sync_anita(): void
    {
        $method = new ReflectionMethod(ComprobanteProveedorRecepcionesSupport::class, 'listarDisponibles');
        $params = $method->getParameters();
        $this->assertSame('sincronizarAnita', $params[2]->getName());
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertFalse($params[2]->getDefaultValue());
    }

    public function test_controles_al_guardar_no_resincronizan_com_anita(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorPersistenciaService::class))->getFileName()
        );
        $this->assertStringContainsString(
            'listarDisponibles($ordencompraId, $excluirComprobanteId, false)',
            $src
        );
    }

    public function test_alta_separa_avisos_de_control_del_mensaje_ok(): void
    {
        $controller = new ReflectionClass(Comprobante_ProveedorController::class);
        $this->assertTrue($controller->hasMethod('conAvisosControles'));
    }

    public function test_prefill_puede_traer_oc_anita_fuera_del_get(): void
    {
        $this->assertTrue(method_exists(ComprobanteProveedorPrefillService::class, 'traerOrdencompraDesdeAnitaSiFalta'));
        $resolver = new ReflectionMethod(ComprobanteProveedorPrefillService::class, 'resolverOrdencompraDesdePrecarga');
        $this->assertTrue($resolver->isPrivate());
    }

    public function test_alta_expone_apis_anita_en_segundo_plano(): void
    {
        $controller = new ReflectionClass(Comprobante_ProveedorController::class);
        $this->assertTrue($controller->hasMethod('apiAvisoFacturaYaEnAnita'));
        $this->assertTrue($controller->hasMethod('apiSincronizarOcComAlta'));
        $this->assertTrue($controller->hasMethod('avisoFacturaYaMarcadaEnErpDesdePrefill'));
    }

    public function test_carga_con_oc_no_toma_com_de_otra_oc(): void
    {
        $controller = (string) file_get_contents(
            (new ReflectionClass(Comprobante_ProveedorController::class))->getFileName()
        );
        $this->assertStringContainsString('Solo COM de esta OC', $controller);

        $auto = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorComLegajoResolucionService::class))->getFileName()
        );
        $this->assertDoesNotMatchRegularExpression(
            '/listarDisponibles\(\(int\) \$ordencompra->id, \$excluirComprobanteId\);\s+if \(\$recepciones->isEmpty\(\)\)/s',
            $auto
        );

        $permitidas = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorRecepcionesSupport::class))->getFileName()
        );
        $this->assertStringContainsString('$ordencompraId <= 0', $permitidas);
    }
}
