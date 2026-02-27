# Sistema de Marca Blanca - PrestaShop PlacetoPay

Este sistema permite generar automáticamente versiones personalizadas del plugin PrestaShop PlacetoPay para diferentes clientes y países.

## 📋 Características

- **Configuración centralizada**: Todas las configuraciones en `config/clients.php`
- **Templates por cliente**: Archivos `PaymentUrl.php` completos por cada cliente en `config/templates/`
- **Automatización completa**: Genera ZIPs listos para distribuir
- **Fácil mantenimiento**: Agregar nuevos clientes solo requiere editar archivos de configuración
- **Compatible con bash 3.2**: Funciona en macOS sin necesidad de actualizar bash

## 🗂️ Estructura del Proyecto

```
prestashop-placetopay/
├── config/
│   ├── clients.php              # Configuraciones de todos los clientes
│   └── templates/               # Templates de PaymentUrl.php por cliente
│       ├── EcuadorPaymentUrl.php
│       ├── BelicePaymentUrl.php
│       ├── GetnetPaymentUrl.php
│       ├── HondurasPaymentUrl.php
│       ├── UruguayPaymentUrl.php
│       ├── GouPaymentUrl.php
│       └── BanchilePaymentUrl.php
├── generate_white_label.sh     # Script de generación
└── builds/                      # ZIPs generados (creado automáticamente)
```

## 🚀 Uso

### Generar todas las versiones

```bash
./generate_white_label.sh
```

### Generar versión específica

```bash
./generate_white_label.sh ecuador
```

### Ver clientes disponibles

```bash
./generate_white_label.sh --list
```

### Ver ayuda

```bash
./generate_white_label.sh --help
```

## ⚙️ Configuración de Clientes

### Archivo Principal: `config/clients.php`

```php
'nuevo_cliente' => [
    'client' => 'NombreCliente',
    'country_code' => 'XX',
    'country_name' => 'País',
    'image' => 'https://example.com/logo.svg',
    'template' => 'NuevoClientePaymentUrl',  // Nombre del template
    'endpoints' => [
        'prod' => 'https://api.cliente.com',
        'test' => 'https://test.cliente.com',
        'dev' => 'https://dev.placetopay.com',
    ],
]
```

### Reglas de Naming

- **Si client = "Placetopay"**: `prestashop-placetopay-{country_name_lowercase}`
- **Si client ≠ "Placetopay"**: `prestashop-placetopay-{client_lowercase}`

**Ejemplos:**
- Ecuador (Placetopay) → `prestashop-placetopay-ecuador`
- Chile (Getnet) → `prestashop-placetopay-getnet`
- Uruguay (Placetopay) → `prestashop-placetopay-uruguay`

## 🎨 Templates Personalizados

### Crear Template de PaymentUrl.php

Crear: `config/templates/{NombreCliente}PaymentUrl.php`

```php
<?php

namespace PlacetoPay\Constants;

abstract class PaymentUrl
{
    public static function getEndpointsTo(string $countryCode): array
    {
        switch ($countryCode) {
            case CountryCode::CHILE:
                $endpoints = [
                    Environment::PRODUCTION => 'https://checkout.getnet.cl',
                    Environment::TEST => 'https://checkout.test.getnet.cl',
                    Environment::DEVELOPMENT => 'https://checkout-cl.placetopay.dev',
                ];

                break;
        }

        return array_merge([
            Environment::PRODUCTION => 'https://checkout.placetopay.com',
            Environment::TEST => 'https://checkout-test.placetopay.com',
            Environment::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
        ], $endpoints ?? []);
    }
}
```

## 📦 Archivos Generados

Cada ZIP contiene:
- Código completo del plugin
- `PaymentUrl.php` personalizado (copiado desde el template)
- Imagen por defecto actualizada en `PlacetoPayPayment.php`
- Todas las dependencias y assets

## 🔧 Agregar Nuevo Cliente

### 1. Editar `config/clients.php`

```php
'nuevo_pais' => [
    'client' => 'NuevoCliente',
    'country_code' => 'XX',
    'country_name' => 'NuevoPais',
    'image' => 'https://logo.url',
    'template' => 'NuevoPaisPaymentUrl',
    'endpoints' => [
        'prod' => 'https://prod.url',
        'test' => 'https://test.url',
        'dev' => 'https://dev.placetopay.dev',
    ],
]
```

### 2. Crear template `config/templates/NuevoPaisPaymentUrl.php`

Copiar y modificar un template existente con los endpoints correctos.

### 3. Probar

```bash
./generate_white_label.sh nuevo_pais
```

## 📋 Clientes Configurados

| Cliente | País | Código | Template |
|---------|------|--------|----------|
| Placetopay | Ecuador | EC | EcuadorPaymentUrl |
| Placetopay | Belice | BZ | BelicePaymentUrl |
| Getnet | Chile | CL | GetnetPaymentUrl |
| Placetopay | Honduras | HN | HondurasPaymentUrl |
| Placetopay | Uruguay | UY | UruguayPaymentUrl |
| GOU | Colombia | CO | GouPaymentUrl |
| Banchile | Chile | CL | BanchilePaymentUrl |

## 🛠️ Troubleshooting

### Error: "Archivo de configuración no encontrado"
- Verifica que existe `config/clients.php`
- Revisa la sintaxis PHP del archivo

### Error: "Cliente desconocido"
- Verifica el nombre del cliente en `config/clients.php`
- Usa `--list` para ver clientes disponibles

### Template no se aplica
- Verifica que el archivo esté en `config/templates/{template}.php`
- Asegúrate de que el nombre del template en `clients.php` coincida con el nombre del archivo

### Error de permisos en macOS
- El script requiere bash 3.2 o superior
- Compatible con la versión de bash que viene por defecto en macOS

## 📌 Compatibilidad

- Este sistema genera módulos compatibles con PrestaShop >= 8 (incluye 9.x).
- Versiones anteriores a 8 ya no están soportadas.

## 📝 Notas Importantes

- Los templates son archivos PHP completos, no se modifican líneas de código
- Cada cliente tiene su propio template de `PaymentUrl.php`
- La imagen por defecto se actualiza en `PlacetoPayPayment.php` usando el método `getImageByCountry`
- Los ZIPs se generan en la carpeta `builds/`
- La carpeta `config/` se excluye automáticamente de los ZIPs generados

## 🎯 Ventajas del Sistema

1. **Claridad**: Templates completos por cliente, fácil de entender
2. **Mantenimiento**: Cambios en un cliente no afectan a otros
3. **Escalabilidad**: Agregar nuevos clientes es simple y rápido
4. **Consistencia**: Misma estructura que el plugin de WooCommerce
5. **Seguridad**: Separación clara entre configuración y código

---

**Versión**: 1.0.0  
**Última actualización**: Diciembre 2025
