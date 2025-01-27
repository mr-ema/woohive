# Documentación de Acciones y Filtros del Plugin

Este documento describe las acciones y filtros personalizados que utilizan `Constants::PLUGIN_SLUG` en su nombre dinámico.

### Nota:
El valor predeterminado de `PLUGIN_SLUG` está definido como `woo_multisite_stock_sync` en la clase `WooHive\Config\Constants` (ubicada en `config/constants`).

```php
public const PLUGIN_SLUG = 'woo_multisite_stock_sync';
```

Si no se cambia manualmente, las acciones y filtros dinámicos generados incluirán este prefijo (`woo_multisite_stock_sync`).

---

## Acciones

### **`woo_multisite_stock_sync_sync_product_stock`**
Acción que sincroniza el stock de un producto específico.

#### Argumentos:
- **`WC_Product $product`**: Instancia del producto de WooCommerce cuyo stock se sincroniza.

#### Ejemplo de Uso:
```php
add_action( 'woo_multisite_stock_sync_sync_product_stock', function( $product ) {
    // Lógica personalizada para sincronizar stock
    error_log( 'Sincronizando stock del producto: ' . $product->get_id() );
});
```

---

### **`woo_multisite_stock_sync_sync_variation_stock`**
Acción que sincroniza el stock de una variación de producto.

#### Argumentos:
- **`WC_Product_Variation $variation`**: Instancia de la variación de producto de WooCommerce cuyo stock se sincroniza.

#### Ejemplo de Uso:
```php
add_action( 'woo_multisite_stock_sync_sync_variation_stock', function( $variation ) {
    // Lógica personalizada para sincronizar stock de la variación
    error_log( 'Sincronizando stock de la variación: ' . $variation->get_id() );
});
```

---

### **`woo_multisite_stock_sync_before_product_sync_stock`**
Acción ejecutada antes de sincronizar el stock de un producto.

#### Argumentos:
- **`WC_Product $product`**: Instancia del producto.

#### Ejemplo de Uso:
```php
add_action( 'woo_multisite_stock_sync_before_product_sync_stock', function( $product ) {
    // Lógica personalizada antes de la sincronización
    error_log( 'Antes de sincronizar el stock del producto: ' . $product->get_id() );
});
```

---

### **`woo_multisite_stock_sync_before_variation_sync_stock`**
Acción ejecutada antes de sincronizar el stock de una variación.

#### Argumentos:
- **`WC_Product_Variation $variation`**: Instancia de la variación del producto.

#### Ejemplo de Uso:
```php
add_action( 'woo_multisite_stock_sync_before_variation_sync_stock', function( $variation ) {
    // Lógica personalizada antes de la sincronización
    error_log( 'Antes de sincronizar el stock de la variación: ' . $variation->get_id() );
});
```

---

## Filtros

### **`woo_multisite_stock_sync_product_invalid_meta_keys`**
Filtro utilizado para modificar las claves meta consideradas inválidas en la sincronización del producto.

#### Argumentos:
- **`array $invalid_meta_keys`**: Lista de claves meta iniciales consideradas inválidas.

#### Ejemplo de Uso:
```php
add_filter( 'woo_multisite_stock_sync_product_invalid_meta_keys', function( $invalid_meta_keys ) {
    // Agregar claves meta personalizadas como inválidas
    $invalid_meta_keys[] = '_custom_meta_key';
    return $invalid_meta_keys;
});
```

---

### **`woo_multisite_stock_sync_product_invalid_props`**
Filtro utilizado para personalizar las propiedades inválidas de un producto.

#### Argumentos:
- **`array $invalid_props`**: Lista inicial de propiedades consideradas inválidas.

#### Ejemplo de Uso:
```php
add_filter( 'woo_multisite_stock_sync_product_invalid_props', function( $invalid_props ) {
    // Agregar propiedades personalizadas como inválidas
    $invalid_props[] = 'custom_invalid_property';
    return $invalid_props;
});
```

---

### **`woo_multisite_stock_sync_should_sync`**
Filtro que permite determinar si se debe sincronizar un producto o variación.

#### Argumentos:
- **`bool $should_sync`**: Valor inicial que indica si debe sincronizarse.
- **`WC_Product|WC_Product_Variation $product`**: Producto o variación.
- **`string $context`**: Contexto de sincronización (por ejemplo, `'stock_qty'`).

#### Ejemplo de Uso:
```php
add_filter( 'woo_multisite_stock_sync_should_sync', function( $should_sync, $product, $context ) {
    // Lógica para desactivar la sincronización según el contexto
    if ( $context === 'stock_qty' && $product->get_id() === 123 ) {
        return false;
    }
    return $should_sync;
}, 10, 3 );
```

---

### **`woo_multisite_stock_sync_exclude_skus_from_sync`**
Filtro utilizado para excluir productos (basados en sus SKUs) de la sincronización.

#### Argumentos:
- **`array $exclude_skus`**: Lista inicial de SKUs a excluir.

#### Ejemplo de Uso:
```php
add_filter( 'woo_multisite_stock_sync_exclude_skus_from_sync', function( $exclude_skus ) {
    // Agregar SKUs personalizados para excluir
    $exclude_skus[] = 'custom-sku-001';
    return $exclude_skus;
});
```
