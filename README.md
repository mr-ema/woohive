# WooHive - WooCommerce Multisite Synchronization Plugin

WooHive es un plugin de WordPress para WooCommerce que permite la sincronización de productos y stock entre múltiples tiendas de WooCommerce utilizando la API de WooCommerce. Facilita la gestión de inventarios en entornos multisitio, asegurando que tus tiendas se mantengan actualizadas con la misma información de productos.

---

## Características

- **Sincronización de Stock**: Mantén actualizado el inventario entre varias tiendas WooCommerce de forma automática.
- **Gestión de Productos**: Crea, edita y gestiona productos en múltiples tiendas desde un solo panel de administración.
- **Soporte Multisitio**: Compatible con la configuración de WordPress Multisite para facilitar la gestión de varias tiendas.
- **Fácil Instalación**: Instalación simple mediante el panel de administración de WordPress.
- **API de WooCommerce**: Usa la API de WooCommerce para interactuar con las tiendas de forma eficiente.

---

## Requisitos

- **WordPress 6.7+**
- **WooCommerce 6.0+**
- **PHP 8.0 o superior**

---

## Instalación

### 1. Instalación Manual

1. **Descargar el Plugin:**
   - Descarga el archivo `.zip` de WooHive desde el repositorio de tu plugin o desde la fuente de tu elección.

2. **Subir el Plugin a WordPress:**
   - Ve al panel de administración de WordPress.
   - Navega a **Plugins > Añadir nuevo > Subir plugin**.
   - Selecciona el archivo `.zip` descargado y haz clic en **Instalar ahora**.

3. **Activar el Plugin:**
   - Una vez que el plugin esté instalado, haz clic en **Activar**.

### 2. Instalación vía FTP

1. **Subir los Archivos del Plugin:**
   - Descomprime el archivo del plugin WooHive.
   - Sube la carpeta del plugin a `wp-content/plugins/` de tu instalación de WordPress.

2. **Activar el Plugin:**
   - Inicia sesión en el panel de administración de WordPress.
   - Ve a **Plugins > Plugins instalados** y haz clic en **Activar** junto al plugin **WooHive**.

---

## Configuración

### 1. Conectar Tiendas

Para empezar a usar WooHive, primero debes conectar todas las tiendas WooCommerce que deseas sincronizar.

1. **Accede a WooHive desde el Panel de Administración**:
   - Después de activar el plugin, ve a **WooHive > Configuración** en el menú de administración de WordPress.

2. **Conectar Sitios Remotos**:
   - En la configuración de WooHive, ingresa las credenciales y la URL de la tienda remota de WooCommerce que deseas sincronizar.
   - Usa las claves de API generadas desde las configuraciones de la API en WooCommerce para cada tienda.

3. **Verificar la Conexión**:
   - Haz clic en **Verificar Conexión** para asegurarte de que la tienda remota esté correctamente conectada.

### 2. Sincronización de Productos y Stock

1. **Acceder a la Página de Reportes**:
   - Ve a **WooHive > Reportes** para visualizar los productos y su información de stock.

2. **Filtrar Productos**:
   - Utiliza el formulario de búsqueda para filtrar productos por nombre, SKU o estado de stock (Disponible o No disponible).

3. **Sincronizar Productos**:
   - Desde el panel de administración de WooHive, podrás editar productos de manera centralizada y sincronizar el stock entre las tiendas de WooCommerce.

---

## Uso

### 1. Sincronizar Inventario

- **Sincronización Automática**: El inventario de tus productos se sincroniza automáticamente entre las tiendas conectadas.
- **Importación de Productos**: Puedes importar productos desde una tienda principal a otras tiendas conectadas usando el botón de importación en la página de reporte.

### 2. Ver Reportes de Productos

Accede a los reportes de productos para ver detalles sobre el stock, nombre del producto, SKU y el estado de la tienda de cada producto sincronizado.

---

## Personalización

Puedes personalizar el comportamiento de WooHive modificando los siguientes parámetros en los archivos de configuración de tu instalación:

- **Configuración de la API de WooCommerce**: Modifica los detalles de la API para cada tienda desde la sección **WooHive > Configuración**.
- **Filtros de Productos**: Personaliza los filtros de búsqueda según tus necesidades en la página de reportes.

---

## Licencia

WooHive está bajo la **Licencia GPL-2.0**. Puedes usar y distribuir el plugin según los términos de esta licencia.

---
