#!/usr/bin/env bash

# Generar versiones de marca blanca del plugin PrestaShop PlacetoPay
# Este script crea versiones personalizadas para diferentes clientes

set -e

# Colores para la salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Sin Color

# Directorio base
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMP_DIR="${BASE_DIR}/temp_builds"
OUTPUT_DIR="${BASE_DIR}/builds"
CONFIG_FILE="${BASE_DIR}/config/clients.php"

# Versiones de PHP y PrestaShop para generar
declare -a PHP_VERSIONS=("7.2" "7.4" "8.1")
declare -a PRESTASHOP_VERSIONS=("prestashop-1.7.x" "prestashop-8.x" "prestashop-9.x")

# Funciones para imprimir con colores
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Función para obtener configuración de cliente desde archivo PHP
get_client_config() {
    local client_key="$1"

    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Archivo de configuración no encontrado: $CONFIG_FILE"
        return 1
    fi

    # Usar PHP para extraer la configuración del cliente
    php -r "
        \$config = include '$CONFIG_FILE';
        if (!isset(\$config['$client_key'])) {
            exit(1);
        }
        \$client = \$config['$client_key'];
        echo 'CLIENT=' . \$client['client'] . '|';
        echo 'COUNTRY_CODE=' . \$client['country_code'] . '|';
        echo 'COUNTRY_NAME=' . \$client['country_name'] . '|';
        echo 'CLIENT_ID=' . (isset(\$client['client_id']) ? \$client['client_id'] : '') . '|';
        echo 'TEMPLATE_FILE=' . (isset(\$client['template_file']) ? \$client['template_file'] : '') . '|';
        echo 'LOGO_FILE=' . (isset(\$client['logo_file']) ? \$client['logo_file'] : 'Placetopay.png') . '|';
    " 2>/dev/null || echo ""
}

# Función para obtener todos los clientes disponibles desde archivo PHP
get_all_clients() {
    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Archivo de configuración no encontrado: $CONFIG_FILE"
        return 1
    fi

    php -r "
        \$config = include '$CONFIG_FILE';
        echo implode(' ', array_keys(\$config));
    " 2>/dev/null || echo ""
}

# Función para parsear configuración
parse_config() {
    local config="$1"

    # Reset variables
    CLIENT=""
    COUNTRY_CODE=""
    COUNTRY_NAME=""
    CLIENT_ID=""
    TEMPLATE_FILE=""
    LOGO_FILE=""

    IFS='|' read -ra PARTS <<< "$config"
    for part in "${PARTS[@]}"; do
        IFS='=' read -ra KV <<< "$part"
        local key="${KV[0]}"
        local value="${KV[1]}"

        case "$key" in
            "CLIENT") CLIENT="$value" ;;
            "COUNTRY_CODE") COUNTRY_CODE="$value" ;;
            "COUNTRY_NAME") COUNTRY_NAME="$value" ;;
            "CLIENT_ID") CLIENT_ID="$value" ;;
            "TEMPLATE_FILE") TEMPLATE_FILE="$value" ;;
            "LOGO_FILE") LOGO_FILE="$value" ;;
        esac
    done
}

# Función para generar CLIENT_ID si no está definido en la configuración
# Convierte "Getnet" + "Chile" -> "getnet-chile" (minúsculas con guión)
get_client_id() {
    local client="$1"
    local country_name="$2"

    # Convertir a minúsculas y unir con guión
    local client_lower=$(echo "$client" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
    local country_lower=$(echo "$country_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')

    echo "${client_lower}-${country_lower}"
}

# Función para obtener el nombre del namespace desde CLIENT_ID
# Convierte "getnet-chile" -> "GetnetChile" (capitaliza cada palabra después del guión)
get_namespace_name() {
    local client_id="$1"

    # Convertir formato "cliente-país" a "ClientePais" (PascalCase)
    # Dividir por guiones, capitalizar primera letra de cada palabra, unir sin espacios
    echo "$client_id" | awk -F'-' '{
        result = ""
        for (i=1; i<=NF; i++) {
            word = $i
            if (length(word) > 0) {
                first = toupper(substr(word,1,1))
                rest = tolower(substr(word,2))
                result = result first rest
            }
        }
        print result
    }'
}

# Función para convertir CLIENT_ID a snake_case para nombres de funciones PHP
# Convierte "getnet-chile" -> "getnet_chile" (reemplaza guiones con guiones bajos)
get_php_function_id() {
    local client_id="$1"
    echo "$client_id" | tr '-' '_'
}

# Función para reemplazar namespaces en archivos PHP
replace_namespaces() {
    local work_dir="$1"
    local namespace_name="$2"

    print_status "Reemplazando namespaces: PlacetoPay -> $namespace_name"

    # Buscar y reemplazar en todos los archivos PHP
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "s|namespace PlacetoPay|namespace $namespace_name|g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "s|use PlacetoPay\\\\|use $namespace_name\\\\|g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "s|\\\\PlacetoPay\\\\|\\\\$namespace_name\\\\|g" {} \;
    else
        # Linux
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "s|namespace PlacetoPay|namespace $namespace_name|g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "s|use PlacetoPay\\\\|use $namespace_name\\\\|g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "s|\\\\PlacetoPay\\\\|\\\\$namespace_name\\\\|g" {} \;
    fi
}

# Función para reemplazar nombres de clases en archivos PHP
replace_class_names() {
    local work_dir="$1"
    local namespace_name="$2"

    print_status "Renombrando clases: PlacetoPayPayment -> ${namespace_name}Payment"

    # Primero renombrar los archivos
    if [[ -f "$work_dir/src/Models/PlacetoPayPayment.php" ]]; then
        mv "$work_dir/src/Models/PlacetoPayPayment.php" "$work_dir/src/Models/${namespace_name}Payment.php"
    fi

    # Reemplazar declaración y referencias de clase en archivos
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "s/class PlacetoPayPayment /class ${namespace_name}Payment /g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "s/PlacetoPayPayment::/\${namespace_name}Payment::/g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "s/new PlacetoPayPayment(/new ${namespace_name}Payment(/g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "s/extends PlacetoPayPayment/extends ${namespace_name}Payment/g" {} \;

        # Eliminar el use de Constants\Client que no existe
        find "$work_dir/src" -type f -name "*.php" -exec sed -i '' "/use.*Constants\\\\Client;/d" {} \;
    else
        # Linux
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "s/class PlacetoPayPayment /class ${namespace_name}Payment /g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "s/PlacetoPayPayment::/\${namespace_name}Payment::/g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "s/new PlacetoPayPayment(/new ${namespace_name}Payment(/g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "s/extends PlacetoPayPayment/extends ${namespace_name}Payment/g" {} \;
        find "$work_dir/src" -type f -name "*.php" -exec sed -i "/use.*Constants\\\\Client;/d" {} \;
    fi
}

# Función para actualizar getModuleName() en helpers.php
# NOTA: Ya no es necesaria porque getModuleName() usa _MODULE_NAME_ (siempre definida)
# y la detección por ruta (cada módulo tiene su propia copia de helpers.php)
# El fallback nunca debería ejecutarse en condiciones normales

# Función para actualizar el namespace y nombre del paquete en composer.json
# Esto genera un hash único del autoloader para cada cliente, evitando conflictos
update_composer_namespace() {
    local work_dir="$1"
    local namespace_name="$2"
    local client_id="$3"

    print_status "Actualizando namespace y nombre del paquete en composer.json: PlacetoPay -> $namespace_name"

    local composer_file="$work_dir/composer.json"
    if [[ -f "$composer_file" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            # Actualizar el namespace en PSR-4 autoloader
            sed -i '' 's|"PlacetoPay\\\\": "src/"|"'"${namespace_name}"'\\\\": "src/"|g' "$composer_file"
            # Cambiar el nombre del paquete para generar hash único del autoloader
            # Esto evita conflictos cuando múltiples módulos están instalados
            # Usar patrón más flexible que maneje espacios y comas opcionales
            sed -i '' 's|"name": "placetopay/prestashop-gateway"|"name": "placetopay/prestashop-gateway-'"${client_id}"'"|g' "$composer_file"
        else
            # Linux
            # Actualizar el namespace en PSR-4 autoloader
            sed -i 's|"PlacetoPay\\\\": "src/"|"'"${namespace_name}"'\\\\": "src/"|g' "$composer_file"
            # Cambiar el nombre del paquete para generar hash único del autoloader
            sed -i 's|"name": "placetopay/prestashop-gateway"|"name": "placetopay/prestashop-gateway-'"${client_id}"'"|g' "$composer_file"
        fi

        # Verificar que el cambio se aplicó correctamente
        if grep -q "\"name\": \"placetopay/prestashop-gateway-${client_id}\"" "$composer_file"; then
            print_status "✓ composer.json actualizado con nombre único: placetopay/prestashop-gateway-${client_id}"
        else
            print_warning "⚠ No se pudo verificar el cambio en composer.json"
        fi
    fi
}

# Función para actualizar el namespace en spl_autoload.php
update_spl_autoload_namespace() {
    local work_dir="$1"
    local namespace_name="$2"

    print_status "Actualizando namespace en spl_autoload.php: PlacetoPay -> $namespace_name"

    local autoload_file="$work_dir/spl_autoload.php"
    if [[ -f "$autoload_file" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            # Actualizar la llamada a versionComparePlaceToPay en la línea 5
            sed -i '' "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" "$autoload_file"

            # Actualizar comentarios
            sed -i '' "s/PlacetoPay and Dnetix/${namespace_name} and Dnetix/g" "$autoload_file"
            sed -i '' "s/load PlacetoPay/load ${namespace_name}/g" "$autoload_file"

            # Actualizar la verificación del namespace en el switch
            sed -i '' "s/substr(\$className, 0, 10) === 'PlacetoPay'/substr(\$className, 0, ${#namespace_name}) === '${namespace_name}'/g" "$autoload_file"
            # Actualizar el str_replace
            sed -i '' "s|str_replace('PlacetoPay\\\\\\\\', '', \$className)|str_replace('${namespace_name}\\\\\\\\', '', \$className)|g" "$autoload_file"
        else
            # Linux
            sed -i "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" "$autoload_file"
            sed -i "s/PlacetoPay and Dnetix/${namespace_name} and Dnetix/g" "$autoload_file"
            sed -i "s/load PlacetoPay/load ${namespace_name}/g" "$autoload_file"
            sed -i "s/substr(\$className, 0, 10) === 'PlacetoPay'/substr(\$className, 0, ${#namespace_name}) === '${namespace_name}'/g" "$autoload_file"
            sed -i "s|str_replace('PlacetoPay\\\\\\\\', '', \$className)|str_replace('${namespace_name}\\\\\\\\', '', \$className)|g" "$autoload_file"
        fi
    fi
}

# Función para actualizar referencias a la clase en archivos de proceso
update_class_references() {
    local work_dir="$1"
    local main_class_name="$2"

    print_status "Actualizando referencias a clase principal: PlacetoPayPayment -> $main_class_name"

    # Archivos que instancian la clase directamente
    local files_to_update=(
        "$work_dir/process.php"
        "$work_dir/redirect.php"
        "$work_dir/sonda.php"
    )

    for file in "${files_to_update[@]}"; do
        if [[ -f "$file" ]]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                # macOS
                sed -i '' "s/new PlacetoPayPayment()/new ${main_class_name}()/g" "$file"
                sed -i '' "s/PlacetoPayPayment()/\${main_class_name}()/g" "$file"
            else
                # Linux
                sed -i "s/new PlacetoPayPayment()/new ${main_class_name}()/g" "$file"
                sed -i "s/PlacetoPayPayment()/\${main_class_name}()/g" "$file"
            fi
        fi
    done

    # Actualizar el controlador Front (controllers/front/sonda.php)
    local controller_file="$work_dir/controllers/front/sonda.php"
    if [[ -f "$controller_file" ]]; then
        # Convertir nombre a snake_case para función
        local function_suffix=$(echo "$main_class_name" | tr '[:upper:]' '[:lower:]')

        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            # Actualizar nombre de clase del controlador
            sed -i '' "s/class PlacetoPayPaymentSondaModuleFrontController/class ${main_class_name}SondaModuleFrontController/g" "$controller_file"
            # Actualizar llamada a función
            sed -i '' "s/resolvePendingPaymentsPlacetoPay()/resolvePendingPayments${function_suffix}()/g" "$controller_file"
        else
            # Linux
            sed -i "s/class PlacetoPayPaymentSondaModuleFrontController/class ${main_class_name}SondaModuleFrontController/g" "$controller_file"
            sed -i "s/resolvePendingPaymentsPlacetoPay()/resolvePendingPayments${function_suffix}()/g" "$controller_file"
        fi
    fi

    # Actualizar nombre de función en sonda.php
    if [[ -f "$work_dir/sonda.php" ]]; then
        # Convertir el nombre de la clase a snake_case para la función
        # Ejemplo: Banchilechile -> banchilechile
        local function_suffix=$(echo "$main_class_name" | tr '[:upper:]' '[:lower:]')
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s/resolvePendingPaymentsPlacetoPay/resolvePendingPayments${function_suffix}/g" "$work_dir/sonda.php"
        else
            sed -i "s/resolvePendingPaymentsPlacetoPay/resolvePendingPayments${function_suffix}/g" "$work_dir/sonda.php"
        fi
    fi
}

# Función para reemplazar las constantes de configuración de la base de datos
# Esto asegura que cada cliente tenga sus propias claves únicas en ps_configuration
replace_configuration_constants() {
    local work_dir="$1"
    local client_id="$2"
    local namespace_name="$3"

    # Convertir CLIENT_ID a formato de constante (mayúsculas con guión bajo)
    # Ejemplo: getnet-chile -> GETNET_CHILE
    local const_prefix=$(echo "$client_id" | tr '[:lower:]' '[:upper:]' | tr '-' '_')

    print_status "Reemplazando constantes de configuración: PLACETOPAY_ -> ${const_prefix}_"

    local payment_file="$work_dir/src/Models/${namespace_name}Payment.php"

    if [[ -f "$payment_file" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            # Reemplazar las constantes de configuración de la base de datos
            sed -i '' "s/'PLACETOPAY_COMPANYDOCUMENT'/'${const_prefix}_COMPANYDOCUMENT'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_COMPANYNAME'/'${const_prefix}_COMPANYNAME'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_EMAILCONTACT'/'${const_prefix}_EMAILCONTACT'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_TELEPHONECONTACT'/'${const_prefix}_TELEPHONECONTACT'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_DESCRIPTION'/'${const_prefix}_DESCRIPTION'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_EXPIRATION_TIME_MINUTES'/'${const_prefix}_EXPIRATION_TIME_MINUTES'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_SHOWONRETURN'/'${const_prefix}_SHOWONRETURN'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_CIFINMESSAGE'/'${const_prefix}_CIFINMESSAGE'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_ALLOWBUYWITHPENDINGPAYMENTS'/'${const_prefix}_ALLOWBUYWITHPENDINGPAYMENTS'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_FILL_TAX_INFORMATION'/'${const_prefix}_FILL_TAX_INFORMATION'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_FILL_BUYER_INFORMATION'/'${const_prefix}_FILL_BUYER_INFORMATION'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_SKIP_RESULT'/'${const_prefix}_SKIP_RESULT'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_CLIENT'/'${const_prefix}_CLIENT'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_DISCOUNT'/'${const_prefix}_DISCOUNT'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_INVOICE'/'${const_prefix}_INVOICE'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_ENVIRONMENT'/'${const_prefix}_ENVIRONMENT'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_CUSTOM_CONNECTION_URL'/'${const_prefix}_CUSTOM_CONNECTION_URL'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_PAYMENT_BUTTON_IMAGE'/'${const_prefix}_PAYMENT_BUTTON_IMAGE'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_LOGIN'/'${const_prefix}_LOGIN'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_TRANKEY'/'${const_prefix}_TRANKEY'/g" "$payment_file"
            sed -i '' "s/'PLACETOPAY_LIGHTBOX'/'${const_prefix}_LIGHTBOX'/g" "$payment_file"
            sed -i '' "s/'PS_OS_PLACETOPAY'/'PS_OS_${const_prefix}'/g" "$payment_file"
        else
            # Linux
            sed -i "s/'PLACETOPAY_COMPANYDOCUMENT'/'${const_prefix}_COMPANYDOCUMENT'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_COMPANYNAME'/'${const_prefix}_COMPANYNAME'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_EMAILCONTACT'/'${const_prefix}_EMAILCONTACT'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_TELEPHONECONTACT'/'${const_prefix}_TELEPHONECONTACT'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_DESCRIPTION'/'${const_prefix}_DESCRIPTION'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_EXPIRATION_TIME_MINUTES'/'${const_prefix}_EXPIRATION_TIME_MINUTES'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_SHOWONRETURN'/'${const_prefix}_SHOWONRETURN'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_CIFINMESSAGE'/'${const_prefix}_CIFINMESSAGE'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_ALLOWBUYWITHPENDINGPAYMENTS'/'${const_prefix}_ALLOWBUYWITHPENDINGPAYMENTS'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_FILL_TAX_INFORMATION'/'${const_prefix}_FILL_TAX_INFORMATION'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_FILL_BUYER_INFORMATION'/'${const_prefix}_FILL_BUYER_INFORMATION'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_SKIP_RESULT'/'${const_prefix}_SKIP_RESULT'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_CLIENT'/'${const_prefix}_CLIENT'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_DISCOUNT'/'${const_prefix}_DISCOUNT'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_INVOICE'/'${const_prefix}_INVOICE'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_ENVIRONMENT'/'${const_prefix}_ENVIRONMENT'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_CUSTOM_CONNECTION_URL'/'${const_prefix}_CUSTOM_CONNECTION_URL'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_PAYMENT_BUTTON_IMAGE'/'${const_prefix}_PAYMENT_BUTTON_IMAGE'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_LOGIN'/'${const_prefix}_LOGIN'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_TRANKEY'/'${const_prefix}_TRANKEY'/g" "$payment_file"
            sed -i "s/'PLACETOPAY_LIGHTBOX'/'${const_prefix}_LIGHTBOX'/g" "$payment_file"
            sed -i "s/'PS_OS_PLACETOPAY'/'PS_OS_${const_prefix}'/g" "$payment_file"
        fi
    fi
}

# Función para crear el archivo principal del módulo con nombre único
create_main_module_file() {
    local work_dir="$1"
    local module_name="$2"
    local namespace_name="$3"

    print_status "Creando archivo principal del módulo: ${module_name}.php"

    # Nombre de la clase (PascalCase, primera letra en mayúscula)
    # El nombre del módulo ya no tiene guiones, así que solo capitalizamos la primera letra
    # Ejemplo: banchilechile -> Banchilechile
    local main_class_name="$(echo ${module_name:0:1} | tr '[:lower:]' '[:upper:]')${module_name:1}"

    # Crear el archivo principal del módulo
    cat > "$work_dir/${module_name}.php" << EOF
<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

// Cada módulo tiene sus propias funciones únicas (getModuleName${namespace_name}, getPathCMS${namespace_name}, etc.)
// Ya no es necesario definir _MODULE_NAME_ porque cada función retorna directamente el nombre del módulo

require_once 'spl_autoload.php';

use ${namespace_name}\\Models\\${namespace_name}Payment;

class ${main_class_name} extends ${namespace_name}Payment
{
}
EOF
}

# Función para actualizar archivos de traducción
update_translation_files() {
    local work_dir="$1"
    local module_name="$2"

    print_status "Actualizando archivos de traducción: placetopaypayment -> $module_name"

    local translations_dir="$work_dir/translations"
    if [[ -d "$translations_dir" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            find "$translations_dir" -type f -name "*.php" -exec sed -i '' "s/placetopaypayment/$module_name/g" {} \;
        else
            # Linux
            find "$translations_dir" -type f -name "*.php" -exec sed -i "s/placetopaypayment/$module_name/g" {} \;
        fi
    fi

    # Actualizar templates (.tpl)
    local views_dir="$work_dir/views"
    if [[ -d "$views_dir" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS - Actualizar mod='placetopaypayment' en templates
            find "$views_dir" -type f -name "*.tpl" -exec sed -i '' "s/mod='placetopaypayment'/mod='$module_name'/g" {} \;
        else
            # Linux
            find "$views_dir" -type f -name "*.tpl" -exec sed -i "s/mod='placetopaypayment'/mod='$module_name'/g" {} \;
        fi

        # Renombrar el template principal si existe
        if [[ -f "$views_dir/templates/admin/placetopaypayment.tpl" ]]; then
            mv "$views_dir/templates/admin/placetopaypayment.tpl" "$views_dir/templates/admin/$module_name.tpl"
        fi
    fi
}

# Función para actualizar archivos raíz (process.php, redirect.php, sonda.php, helpers.php)
update_root_files() {
    local work_dir="$1"
    local module_name="$2"
    local namespace_name="$3"
    local main_class_name="$4"

    print_status "Actualizando archivos raíz: use statements y referencias hardcodeadas"

    # Archivos a actualizar
    local files=("process.php" "redirect.php" "sonda.php")

    for file in "${files[@]}"; do
        if [[ -f "$work_dir/$file" ]]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                # macOS
                # Actualizar namespace del PaymentLogger
                sed -i '' "s/use PlacetoPay\\\\Loggers\\\\PaymentLogger;/use ${namespace_name}\\\\Loggers\\\\PaymentLogger;/g" "$work_dir/$file"

                # Actualizar instanciación de clase (ej: new PlacetoPayPayment() -> new Banchilechile())
                sed -i '' "s/new PlacetoPayPayment()/new ${main_class_name}()/g" "$work_dir/$file"

                # Actualizar llamadas a getPathCMS() y getModuleName() por las versiones renombradas
                sed -i '' "s/getPathCMS(/getPathCMS${namespace_name}(/g" "$work_dir/$file"
                sed -i '' "s/getModuleName()/getModuleName${namespace_name}()/g" "$work_dir/$file"
            else
                # Linux
                sed -i "s/use PlacetoPay\\\\Loggers\\\\PaymentLogger;/use ${namespace_name}\\\\Loggers\\\\PaymentLogger;/g" "$work_dir/$file"
                sed -i "s/new PlacetoPayPayment()/new ${main_class_name}()/g" "$work_dir/$file"
                sed -i "s/getPathCMS(/getPathCMS${namespace_name}(/g" "$work_dir/$file"
                sed -i "s/getModuleName()/getModuleName${namespace_name}()/g" "$work_dir/$file"
            fi
        fi
    done

    # Actualizar helpers.php - fallback en getModuleName()
    if [[ -f "$work_dir/helpers.php" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS - Actualizar el fallback de placetopaypayment a module_name
            sed -i '' "s/\$moduleName = 'placetopaypayment';/\$moduleName = '${module_name}';/g" "$work_dir/helpers.php"
        else
            # Linux
            sed -i "s/\$moduleName = 'placetopaypayment';/\$moduleName = '${module_name}';/g" "$work_dir/helpers.php"
        fi
    fi

    # Actualizar templates admin (admin_order.tpl) - IDs y referencias
    local admin_order_tpl="$work_dir/views/templates/admin/admin_order.tpl"
    if [[ -f "$admin_order_tpl" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "s/id=\"placetopaypayment_/id=\"${module_name}_/g" "$admin_order_tpl"
            sed -i '' "s/#placetopaypayment_/#${module_name}_/g" "$admin_order_tpl"
        else
            # Linux
            sed -i "s/id=\"placetopaypayment_/id=\"${module_name}_/g" "$admin_order_tpl"
            sed -i "s/#placetopaypayment_/#${module_name}_/g" "$admin_order_tpl"
        fi
    fi
}

# Función para actualizar referencias internas hardcodeadas
update_internal_references() {
    local work_dir="$1"
    local module_name="$2"
    local namespace_name="$3"

    print_status "Actualizando referencias internas: placetopay -> $module_name"

    # Convertir el nombre del módulo a snake_case para usar en nombres de tabla/función
    # Ejemplo: banchilechile -> banchile_chile (aunque sin mayúsculas no se aplica, lo dejamos por si acaso)
    local snake_case_name=$(echo "$module_name" | sed 's/\([a-z]\)\([A-Z]\)/\1_\2/g' | tr '[:upper:]' '[:lower:]')

    # Archivos fuente donde hacer los reemplazos
    local files_to_update=$(find "$work_dir/src" -type f -name "*.php")

    for file in $files_to_update; do
        if [[ -f "$file" ]]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                # macOS
                # Reemplazar nombre de tabla de pagos
                sed -i '' "s/'payment_placetopay'/'payment_${snake_case_name}'/g" "$file"

                # Reemplazar función versionComparePlaceToPay
                sed -i '' "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" "$file"

                # Reemplazar función insertPaymentPlaceToPay
                sed -i '' "s/insertPaymentPlaceToPay/insertPayment${namespace_name}/g" "$file"

                # Reemplazar llamadas a getModuleName() por getModuleName{Namespace}()
                sed -i '' "s/getModuleName()/getModuleName${namespace_name}()/g" "$file"
            else
                # Linux
                sed -i "s/'payment_placetopay'/'payment_${snake_case_name}'/g" "$file"
                sed -i "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" "$file"
                sed -i "s/insertPaymentPlaceToPay/insertPayment${namespace_name}/g" "$file"
                sed -i "s/getModuleName()/getModuleName${namespace_name}()/g" "$file"
            fi
        fi
    done

    # También actualizar en archivos raíz (process.php, redirect.php, sonda.php)
    local root_files=("process.php" "redirect.php" "sonda.php" "controllers/front/sonda.php")
    for file in "${root_files[@]}"; do
        if [[ -f "$work_dir/$file" ]]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s/getModuleName()/getModuleName${namespace_name}()/g" "$work_dir/$file"
            else
                sed -i "s/getModuleName()/getModuleName${namespace_name}()/g" "$work_dir/$file"
            fi
        fi
    done

    # Actualizar helpers.php - renombrar funciones para que sean únicas por módulo
    if [[ -f "$work_dir/helpers.php" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # Renombrar versionComparePlaceToPay
            sed -i '' "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" "$work_dir/helpers.php"

            # Renombrar getModuleName() a getModuleName{Namespace}()
            sed -i '' "s/function getModuleName()/function getModuleName${namespace_name}()/g" "$work_dir/helpers.php"
            sed -i '' "s/if (!function_exists('getModuleName'))/if (!function_exists('getModuleName${namespace_name}'))/g" "$work_dir/helpers.php"

            # Renombrar getPathCMS() a getPathCMS{Namespace}()
            sed -i '' "s/function getPathCMS(/function getPathCMS${namespace_name}(/g" "$work_dir/helpers.php"
            sed -i '' "s/if (!function_exists('getPathCMS'))/if (!function_exists('getPathCMS${namespace_name}'))/g" "$work_dir/helpers.php"

            # Actualizar la llamada a getModuleName() dentro de getPathCMS
            sed -i '' "s/getModuleName()/getModuleName${namespace_name}()/g" "$work_dir/helpers.php"

            # Actualizar el fallback para que retorne el nombre del módulo correcto
            sed -i '' "s/return 'placetopaypayment';/return '${module_name}';/g" "$work_dir/helpers.php"
        else
            # Linux
            sed -i "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" "$work_dir/helpers.php"
            sed -i "s/function getModuleName()/function getModuleName${namespace_name}()/g" "$work_dir/helpers.php"
            sed -i "s/if (!function_exists('getModuleName'))/if (!function_exists('getModuleName${namespace_name}'))/g" "$work_dir/helpers.php"
            sed -i "s/function getPathCMS(/function getPathCMS${namespace_name}(/g" "$work_dir/helpers.php"
            sed -i "s/if (!function_exists('getPathCMS'))/if (!function_exists('getPathCMS${namespace_name}'))/g" "$work_dir/helpers.php"
            sed -i "s/getModuleName()/getModuleName${namespace_name}()/g" "$work_dir/helpers.php"
            sed -i "s/return 'placetopaypayment';/return '${module_name}';/g" "$work_dir/helpers.php"
        fi
    fi
}

# Función para obtener nombre del proyecto
get_project_name() {
    local client="$1"
    local country_name="$2"

    if [[ "$client" == "Placetopay" ]]; then
        # Convertir nombre del país a minúsculas y sin espacios
        echo "prestashop-placetopay-$(echo "$country_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')"
    else
        # Usar nombre del cliente en minúsculas
        echo "prestashop-placetopay-$(echo "$client" | tr '[:upper:]' '[:lower:]')"
    fi
}

# Función para copiar template de CountryConfig.php
copy_country_config_template() {
    local target_file="$1"
    local template_name="$2"
    local template_file="${BASE_DIR}/config/templates/${template_name}.php"

    if [[ -f "$template_file" ]]; then
        print_status "Copiando template de CountryConfig: $template_name"
        cp "$template_file" "$target_file"
    else
        print_warning "Template no encontrado: $template_file, usando CountryConfig.php original"
    fi
}

# Función para instalar dependencias con una versión específica de PHP
install_composer_dependencies() {
    local work_dir="$1"
    local php_version="$2"

    print_status "Instalando dependencias de Composer con PHP $php_version..."

    # Actualizar la versión de PHP en composer.json (siguiendo el patrón del Makefile)
    local composer_file="$work_dir/composer.json"
    if [[ -f "$composer_file" ]]; then
        # Actualizar versión de PHP usando sed (compatible con macOS y Linux)
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS usa -i '' para sed
            # Reemplazar solo en la sección require (línea que contiene "php" después de "require")
            sed -i '' "/\"require\"/,/}/ s|\"php\": \".*\"|\"php\": \">=${php_version}\"|g" "$composer_file"
            # También actualizar platform si existe
            sed -i '' "/\"platform\"/,/}/ s|\"php\": \".*\"|\"php\": \"${php_version}\"|g" "$composer_file"
        else
            # Linux usa -i sin argumento
            sed -i "/\"require\"/,/}/ s|\"php\": \".*\"|\"php\": \">=${php_version}\"|g" "$composer_file"
            sed -i "/\"platform\"/,/}/ s|\"php\": \".*\"|\"php\": \"${php_version}\"|g" "$composer_file"
        fi
    fi

    # Eliminar composer.lock si existe
    rm -rf "$work_dir/composer.lock"

    # Instalar dependencias con la versión específica de PHP
    cd "$work_dir"

    hash=`head -c 32 /dev/urandom | md5sum | awk '{print $1}'`

    # Actualizar el nombre del paquete en composer.json para evitar conflictos de autoloader
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/prestashop-gateway/prestashop-gateway-$hash/g" "$work_dir/composer.json"
    else
        sed -i "s/prestashop-gateway/prestashop-gateway-$hash/g" "$work_dir/composer.json"
    fi

    # Verificar si existe el comando php con la versión específica
    if command -v "php${php_version}" >/dev/null 2>&1; then
        print_status "Usando php${php_version} para instalar dependencias..."
        php${php_version} "$(which composer)" install --no-dev 2>&1 | grep -v "^$" || true
    else
        print_warning "php${php_version} no encontrado, usando php por defecto..."
        php "$(which composer)" install --no-dev 2>&1 | grep -v "^$" || true
    fi

    # Evitar conflictos de spl_autoload
    sed -i -E "s/ComposerAutoloaderInit([a-zA-Z0-9])/ComposerAutoloaderInit$hash/g" $work_dir/vendor/composer/autoload_real.php
    sed -i -E "s/ComposerStaticInit([a-zA-Z0-9])/ComposerStaticInit$hash/g" $work_dir/vendor/composer/autoload_real.php
    sed -i -E "s/'ComposerStaticInit([a-zA-Z0-9])'/'ComposerStaticInit$hash'/g" $work_dir/vendor/composer/autoload_real.php

    sed -i -E "s/ComposerAutoloaderInit([a-zA-Z0-9])/ComposerAutoloaderInit$hash/g" $work_dir/vendor/composer/autoload_static.php
    sed -i -E "s/ComposerStaticInit([a-zA-Z0-9])/ComposerStaticInit$hash/g" $work_dir/vendor/composer/autoload_static.php

    sed -i -E "s/ComposerAutoloaderInit([a-zA-Z0-9])/ComposerAutoloaderInit$hash/g" $work_dir/vendor/autoload.php

    cd "$BASE_DIR"
}

# Función para limpiar archivos innecesarios del vendor (siguiendo el Makefile)
cleanup_vendor_files() {
    local work_dir="$1"

    print_status "Limpiando archivos innecesarios del vendor..."

    # Eliminar directorios .git* y squizlabs (usando find como en el Makefile)
    find "$work_dir" -type d -name ".git*" -exec rm -rf {} + 2>/dev/null || true
    find "$work_dir" -type d -name "squizlabs" -exec rm -rf {} + 2>/dev/null || true

    # Limpiar vendor exactamente como en el Makefile (líneas 34-41)
    rm -rf "$work_dir/vendor/bin"
    rm -rf "$work_dir/vendor/alejociro/redirection/tests"
    rm -rf "$work_dir/vendor/alejociro/redirection/examples"
    rm -rf "$work_dir/vendor/guzzlehttp/ringphp/docs"
    rm -rf "$work_dir/vendor/guzzlehttp/ringphp/tests"
    rm -rf "$work_dir/vendor/guzzlehttp/guzzle/docs"
    rm -rf "$work_dir/vendor/guzzlehttp/guzzle/tests"
    rm -rf "$work_dir/vendor/guzzlehttp/streams/tests"
    rm -Rf "$work_dir/.phpactor.json"
    rm -Rf "$work_dir/.php-cs-fixer.cache"
    rm -Rf "$work_dir/.vimrc.setup"
    rm -Rf "$work_dir/*.hasts"
    rm -Rf "$work_dir/*.hasaia"
    rm -Rf "$work_dir/*.sql"
    rm -Rf "$work_dir/*.log"
    rm -Rf "$work_dir/*.diff"
}

# Función para limpiar archivos de desarrollo del build (siguiendo el Makefile líneas 25-33)
cleanup_build_files() {
    local work_dir="$1"

    print_status "Eliminando archivos de desarrollo innecesarios..."

    # Eliminar .git* (ya se hizo con find, pero por si acaso)
    rm -rf "$work_dir/.git"*

    # Eliminar según el Makefile
    rm -rf "$work_dir/.idea"
    rm -rf "$work_dir/config"*
    rm -rf "$work_dir/Dockerfile"
    rm -rf "$work_dir/Makefile"
    rm -rf "$work_dir/.env"*
    rm -rf "$work_dir/composer."*
    rm -rf "$work_dir/.php_cs.cache"
    rm -rf "$work_dir"/*.md

    # Eliminar logos y scripts de generación (adicionales para white label)
    rm -rf "$work_dir/logos"
    rm -rf "$work_dir"/*.sh
}

# Función para crear versión de marca blanca con una versión específica de PHP
create_white_label_version_with_php() {
    local client_key="$1"
    local php_version="$2"
    local prestashop_version="$3"
    local plugin_version="$4"
    local config
    config=$(get_client_config "$client_key")

    if [[ -z "$config" ]]; then
        print_error "Cliente desconocido: $client_key"
        return 1
    fi

    # Parsear configuración
    parse_config "$config"

    # Generar CLIENT_ID si no está definido en la configuración
    if [[ -z "$CLIENT_ID" ]]; then
        CLIENT_ID=$(get_client_id "$CLIENT" "$COUNTRY_NAME")
        print_warning "CLIENT_ID no encontrado en config, generando: $CLIENT_ID"
    fi

    # Obtener nombre del namespace desde CLIENT_ID
    local namespace_name
    namespace_name=$(get_namespace_name "$CLIENT_ID")

    # Determinar nombre del proyecto base
    local project_name_base
    project_name_base=$(get_project_name "$CLIENT" "$COUNTRY_NAME")

    # Agregar versión de PrestaShop al nombre del proyecto
    local project_name="${project_name_base}-${plugin_version}-${prestashop_version}"

    print_status "Creando versión de marca blanca: $project_name"
    print_status "Cliente: $CLIENT, País: $COUNTRY_NAME ($COUNTRY_CODE), CLIENT_ID: $CLIENT_ID"
    print_status "Namespace: $namespace_name, PHP: $php_version"

    # El nombre del módulo debe ser único por cliente (sin guiones para PrestaShop)
    # PrestaShop es estricto: nombre_carpeta = nombre_archivo = nombre_clase (sin guiones)
    # Ejemplos:
    #   - banchile-chile -> banchilechile
    #   - placetopay-colombia -> placetopaycolombia
    #   - getnet-chile -> getnetchile
    local module_name=$(echo "${CLIENT_ID}" | tr -d '-')
    local work_dir="$TEMP_DIR/$module_name"
    mkdir -p "$work_dir"

    print_status "Nombre del módulo: $module_name"

    # Copiar todos los archivos (como cp -pr en el Makefile, pero usando rsync para excluir lo necesario)
    print_status "Copiando archivos fuente..."
    rsync -a \
        --exclude='builds/' \
        --exclude='temp_builds/' \
        --exclude='.git/' \
        --exclude='.git*' \
        --exclude='*.sh' \
        --exclude='config/' \
        --exclude='src/Countries/' \
        --exclude='placetopaypayment.php' \
        --exclude='woocommerce-gateway-placetopay/' \
        "$BASE_DIR/" "$work_dir/" 2>/dev/null || true

    # Copiar template de CountryConfig.php si existe
    if [[ -n "$TEMPLATE_FILE" ]]; then
        print_status "Usando template personalizado: $TEMPLATE_FILE"
        copy_country_config_template "$work_dir/src/CountryConfig.php" "$TEMPLATE_FILE"
    else
        print_warning "No se especificó template_file, manteniendo CountryConfig.php original"
    fi

    # Copiar el logo correcto según el cliente (antes de borrar la carpeta logos)
    if [[ -n "$LOGO_FILE" ]]; then
        print_status "Copiando logo: $LOGO_FILE"
        if [[ -f "$work_dir/logos/$LOGO_FILE" ]]; then
            cp "$work_dir/logos/$LOGO_FILE" "$work_dir/logo.png"
        else
            print_warning "Logo no encontrado: $work_dir/logos/$LOGO_FILE"
        fi
    fi

    # Reemplazar namespaces y nombres de clases para cliente específico
    replace_namespaces "$work_dir" "$namespace_name"
    replace_class_names "$work_dir" "$namespace_name"

    # Reemplazar constantes de configuración de la base de datos
    replace_configuration_constants "$work_dir" "$CLIENT_ID" "$namespace_name"

    # Actualizar namespace y nombre del paquete en composer.json antes de instalar dependencias
    # IMPORTANTE: Esto debe hacerse ANTES de composer install para generar hash único del autoloader
    update_composer_namespace "$work_dir" "$namespace_name" "$CLIENT_ID"
    update_spl_autoload_namespace "$work_dir" "$namespace_name"

    # Crear archivo principal del módulo con nombre único
    create_main_module_file "$work_dir" "$module_name" "$namespace_name"

    # Obtener el nombre de la clase principal para actualizar referencias
    local main_class_name="$(echo ${module_name:0:1} | tr '[:lower:]' '[:upper:]')${module_name:1}"

    # Actualizar referencias a la clase en process.php, redirect.php, sonda.php
    update_class_references "$work_dir" "$main_class_name"

    # Actualizar archivos de traducción
    update_translation_files "$work_dir" "$module_name"

    # Actualizar archivos raíz (process.php, redirect.php, sonda.php, helpers.php, templates)
    update_root_files "$work_dir" "$module_name" "$namespace_name" "$main_class_name"

    # Actualizar referencias internas hardcodeadas (tablas, funciones)
    update_internal_references "$work_dir" "$module_name" "$namespace_name"

    # Instalar dependencias de composer con la versión específica de PHP
    install_composer_dependencies "$work_dir" "$php_version"

    # Limpiar archivos innecesarios del vendor
    cleanup_vendor_files "$work_dir"

    # Limpiar archivos de desarrollo
    cleanup_build_files "$work_dir"

    # Crear archivo ZIP
    print_status "Creando archivo ZIP..."
    mkdir -p "$OUTPUT_DIR"
    cd "$TEMP_DIR"
    zip -rq "$OUTPUT_DIR/$project_name.zip" "$module_name"
    cd "$BASE_DIR"

    # Limpiar directorio temporal de este build
    rm -rf "$work_dir"

    print_success "Creado: $OUTPUT_DIR/$project_name.zip (carpeta interna: $module_name)"
}

# Función para crear todas las versiones de marca blanca para un cliente
create_white_label_version() {
    local client_key="$1"
    local plugin_version="$2"

    print_status "========================================="
    print_status "Procesando cliente: $client_key"
    print_status "========================================="
    echo

    # Generar una versión para cada versión de PHP/PrestaShop
    local i=0
    for php_version in "${PHP_VERSIONS[@]}"; do
        local prestashop_version="${PRESTASHOP_VERSIONS[$i]}"
        create_white_label_version_with_php "$client_key" "$php_version" "$prestashop_version" "$plugin_version"
        echo
        i=$((i + 1))
    done
}

# Función principal
main() {
    local plugin_version="$1"
    print_status "Iniciando proceso de generación de marca blanca..."

    # Verificar que existe el archivo de configuración
    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Archivo de configuración no encontrado: $CONFIG_FILE"
        print_error "Por favor asegúrate de que el archivo config/clients.php existe."
        exit 1
    fi

    # Limpiar builds anteriores
    print_status "Limpiando builds anteriores..."
    rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
    mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"

    # Procesar cada configuración de cliente
    for client_key in $(get_all_clients); do
        create_white_label_version "$client_key" "$plugin_version"

        echo
    done

    # Limpiar directorio temporal
    print_status "Limpiando archivos temporales..."
    rm -rf "$TEMP_DIR"

    print_success "¡Generación de marca blanca completada!"
    print_status "Los archivos generados están en: $OUTPUT_DIR"

    # Listar archivos generados
    echo
    print_status "Versiones de marca blanca generadas:"
    ls -la "$OUTPUT_DIR"/*.zip 2>/dev/null | while read -r line; do
        echo "  $line"
    done || print_warning "No se encontraron archivos ZIP en el directorio de salida: $OUTPUT_DIR"
}

# Mostrar información de uso
usage() {
    echo "Uso: $0 [OPCIONES] [CLIENTE] [VERSION]"
    echo ""
    echo "Generar versiones de marca blanca del plugin PrestaShop PlacetoPay"
    echo ""
    echo "Opciones:"
    echo "  -h, --help    Mostrar este mensaje de ayuda"
    echo "  -l, --list    Listar configuraciones de clientes disponibles"
    echo "  CLIENTE       Generar solo para un cliente específico (opcional)"
    echo "  VERSION       Generar .zip para cargar en GitHub tag (opcional)"
    echo ""
    echo "Clientes disponibles:"
    for client in $(get_all_clients); do
        config=$(get_client_config "$client")

        if [[ -n "$config" ]]; then
            parse_config "$config"
            echo "  - $client: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
        fi
    done
}

# Manejar argumentos de línea de comandos
case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
    -l|--list)
        echo "Configuraciones de clientes disponibles:"
        for client_key in $(get_all_clients); do
            config=$(get_client_config "$client_key")
            if [[ -n "$config" ]]; then
                parse_config "$config"
                echo "  $client_key: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
            fi
        done
        exit 0
        ;;
    "")
        main
        ;;
    *)
        if [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            main "$1"
        else
            # Verificar si es un cliente válido
            config=$(get_client_config "$1")

            if [[ -n "$config" ]]; then
                print_status "Generando versión de marca blanca para: $1"
                rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
                mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"

                create_white_label_version "$1" "${2-untagged}"

                rm -rf "$TEMP_DIR"
                print_success "¡Generación de marca blanca completada para $1!"
            else
                print_error "Opción desconocida: $1"
                echo ""
                usage

                exit 1
            fi
        fi
        ;;
esac
