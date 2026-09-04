# Guías paso a paso (HTML interactivo)

Archivos servidos solo autenticados vía `route('guia_paso_a_paso', ['slug' => '…'])`.

| Slug | Archivo | Pantallas |
|------|---------|-----------|
| `ingreso-proveedores` | `ingreso-proveedores.html` | Seguridad → Ingreso de proveedores |
| `autorizacion-ingresos` | `autorizacion-ingresos.html` | Pendientes de autorizar / Control de ingreso |
| `bandeja-legajos` | `bandeja-legajos.html` | Compras → Bandeja de legajos |

Catálogo PHP: `App\Support\Ayuda\GuiaPasoAPasoCatalogo`.
Controller: `App\Http\Controllers\Ayuda\GuiaPasoAPasoController`.

No publicar estos HTML bajo `public/`.
