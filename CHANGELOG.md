# Change Log  
Todos los cambios importantes en este proyecto serán documentados en este archivo.  

<br>  

## [v1.1.0] - UNRELEASED

### **Agregado**  

- **Nuevos hooks y reemplazo de los antiguos:** Se agregaron nuevos hooks para una mejor personalización y se eliminaron los obsoletos.  
- **Mejora en la sincronización de productos:** Ahora la caché se actualiza automáticamente cuando un producto es modificado.  

### **Cambiado**  

- **Actualización de la versión:** Se ajustó la numeración de la versión para reflejar los últimos cambios.  

### **Corregido**  

- **Error en la opción de sincronizar solo stock:** Se solucionó un problema donde la opción de "sincronizar solo stock" no funcionaba correctamente.  

<br>  

## [v1.0.0] - 2025-01-27  

### **Agregado**  

- **Opción para sincronizar solo stock:** Ahora los usuarios pueden elegir sincronizar solo las actualizaciones de stock.  
- **Nueva acción para la sincronización de productos:** Se agregó un nuevo hook de acción para mejorar el manejo de la sincronización.  
- **Funcionalidad para crear productos en sitios secundarios:** Permite la creación de productos en múltiples tiendas dentro de una red multisitio.  
- **Soporte para eliminación de productos, variaciones y atributos:** Ahora es posible eliminar estos elementos en tiendas secundarias de forma remota.  
- **Sincronización de imágenes de productos:** Se agregó la capacidad de sincronizar imágenes de productos entre tiendas.  
- **Importación masiva de productos:** Nueva opción para importar productos en bloque con soporte para variaciones.  
- **Filtros para excluir propiedades en la sincronización:** Permite definir qué propiedades de un producto deben ignorarse durante la sincronización.  

### **Cambiado**  

- **Refactorización de la API interna para mejorar el rendimiento:** Se optimizaron las llamadas a la API interna para reducir redundancias y mejorar la eficiencia.  
- **Código modularizado para mejor mantenimiento:** Se mejoró la estructura del código dividiéndolo en módulos más pequeños y reutilizables.  

### **Corregido**  

- **Error en el endpoint de sincronización de stock:** Se solucionó un problema donde los niveles de stock no se actualizaban correctamente.  
- **Corrección en la lógica de asignación de categorías:** Se corrigió la asignación de categorías padre durante la sincronización.  
- **Corrección en los atributos de las variaciones:** Se solucionó un error que impedía la sincronización de atributos globales correctamente.  
- **Error en la actualización de imágenes eliminadas:** Se corrigió un problema donde imágenes eliminadas seguían apareciendo en la sincronización.  
- **Problema con la importación masiva:** Se solucionaron errores relacionados con la importación de productos con SKUs duplicados.  
