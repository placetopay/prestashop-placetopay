#!/usr/bin/env bash

# Generar versiones de marca blanca del plugin PrestaShop PlacetoPay
# Este script crea versiones personalizadas para diferentes clientes
#
# Compatible con macOS (bash 3.2 + BSD sed) y Linux (bash 4+ + GNU sed).
# No usar arreglos asociativos (declare -A) ni `sed -i` sin la capa de
# compatibilidad definida más abajo.

set -euo pipefail

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

# Matriz de compilación: "etiqueta-prestashop|php-plataforma"
#
# Se genera un artefacto por versión de PrestaShop porque el árbol de
# dependencias NO puede ser el mismo para ambas: el autoloader de Composer se
# registra con prepend=true, así que las clases que empaqueta el módulo tapan a
# las del core de PrestaShop durante todo el request. Si la versión empaquetada
# es incompatible con la del core, PHP revienta en tiempo de compilación.
#
#   - PrestaShop 8.x → PHP 7.2–8.1. Resolviendo con 7.4 se obtiene
#     psr/log 1.1.x, que es el que trae el core de PS 8 (monolog 1/2).
#   - PrestaShop 9.x → PHP >= 8.1. Resolviendo con 8.1 se obtiene
#     psr/log 3.x, que es el que trae el core de PS 9 (monolog 3).
#
# psr/log no tiene versión compatible con ambos (1.x no declara tipos y monolog 3
# los estrecha; 3.x declara `: void` y monolog 1/2 no) => el split es obligatorio.
BUILD_TARGETS=(
    "prestashop-8.x|7.4.33"
    "prestashop-9.x|8.1"
)

# Rutas excluidas al copiar el código fuente al directorio de trabajo
RSYNC_EXCLUDES=(
    --exclude='builds/'
    --exclude='temp_builds/'
    --exclude='vendor/'
    --exclude='node_modules/'
    --exclude='composer.lock'
    --exclude='.git/'
    --exclude='.git*'
    --exclude='.github/'
    --exclude='.claude/'
    --exclude='.idea/'
    --exclude='.vscode/'
    --exclude='.DS_Store'
    --exclude='*.sh'
    --exclude='config/'
    --exclude='src/Countries/'
    --exclude='placetopaypayment.php'
    --exclude='woocommerce-gateway-placetopay/'
)

# ---------------------------------------------------------------------------
# Capa de compatibilidad de sed (BSD vs GNU)
# ---------------------------------------------------------------------------
# BSD sed (macOS) exige el sufijo de respaldo justo después de -i; GNU sed no lo
# admite. Se detecta por capacidad (no por $OSTYPE) para funcionar también cuando
# hay GNU sed instalado como `sed` en macOS.
if sed --version >/dev/null 2>&1; then
    SED_INPLACE=(sed -i)
else
    SED_INPLACE=(sed -i '')
fi

# Aplica expresiones sed in-place sobre un único archivo (no falla si no existe)
sed_file() {
    local file="$1"
    shift

    [[ -f "$file" ]] || return 0
    "${SED_INPLACE[@]}" "$@" "$file"
}

# Aplica expresiones sed in-place a todos los archivos de un árbol que coincidan
# con el patrón indicado. Una sola invocación de sed por lote (`-exec ... +`).
sed_tree() {
    local dir="$1"
    local name_pattern="$2"
    shift 2

    [[ -d "$dir" ]] || return 0
    find "$dir" -type f -name "$name_pattern" -exec "${SED_INPLACE[@]}" "$@" {} +
}

# Funciones para imprimir con colores
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCC]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERRO]${NC} $1"
}

# Verificar que las herramientas necesarias estén disponibles
check_requirements() {
    local missing=0
    local tool

    for tool in php composer rsync zip find awk; do
        if ! command -v "$tool" >/dev/null 2>&1; then
            print_error "Herramienta requerida no encontrada en PATH: $tool"
            missing=1
        fi
    done

    [[ $missing -eq 0 ]] || exit 1
}

# Comprobar que la sintaxis de `sed -i` detectada realmente edita archivos.
# Sin esto, una detección errónea produce artefactos donde los reemplazos se
# omitieron en silencio (el peor fallo posible: el ZIP se genera igual).
verify_sed_inplace() {
    local probe="${TMPDIR:-/tmp}/p2p-sed-probe.$$"

    printf 'placetopay\n' > "$probe"
    "${SED_INPLACE[@]}" -e 's/placetopay/ok/' "$probe" >/dev/null 2>&1 || true

    if [[ "$(cat "$probe" 2>/dev/null)" != "ok" ]]; then
        rm -f "$probe" "$probe"*
        print_error "La sintaxis de 'sed -i' detectada no funciona en este sistema."
        print_error "Detectado: ${SED_INPLACE[*]}"
        exit 1
    fi

    rm -f "$probe" "$probe"*
}

# Función para obtener configuración de cliente desde archivo PHP
get_client_config() {
    local client_key="$1"

    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Archivo de configuración no encontrado: $CONFIG_FILE"
        return 1
    fi

    # Usar PHP para extraer la configuración del cliente
    CLIENT_KEY="$client_key" CONFIG_PATH="$CONFIG_FILE" php -r '
        $config = include getenv("CONFIG_PATH");
        $key = getenv("CLIENT_KEY");

        if (!isset($config[$key])) {
            exit(1);
        }

        $client = $config[$key];

        echo "CLIENT=" . $client["client"] . "|";
        echo "COUNTRY_CODE=" . $client["country_code"] . "|";
        echo "COUNTRY_NAME=" . $client["country_name"] . "|";
        echo "CLIENT_ID=" . (isset($client["client_id"]) ? $client["client_id"] : "") . "|";
        echo "TEMPLATE_FILE=" . (isset($client["template_file"]) ? $client["template_file"] : "") . "|";
        echo "LOGO_FILE=" . (isset($client["logo_file"]) ? $client["logo_file"] : "Placetopay.png");
    ' 2>/dev/null || echo ""
}

# Función para obtener todos los clientes disponibles desde archivo PHP
get_all_clients() {
    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Archivo de configuración no encontrado: $CONFIG_FILE"
        return 1
    fi

    CONFIG_PATH="$CONFIG_FILE" php -r '
        $config = include getenv("CONFIG_PATH");
        echo implode(" ", array_keys($config));
    ' 2>/dev/null || echo ""
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

    local part key value
    local IFS_BACKUP="$IFS"

    IFS='|' read -ra PARTS <<< "$config"
    IFS="$IFS_BACKUP"

    for part in "${PARTS[@]}"; do
        [[ -n "$part" ]] || continue

        key="${part%%=*}"
        value="${part#*=}"

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
    local client_lower
    local country_lower
    client_lower=$(echo "$client" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
    country_lower=$(echo "$country_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')

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

# Función para reemplazar namespaces en archivos PHP
replace_namespaces() {
    local work_dir="$1"
    local namespace_name="$2"

    print_status "Reemplazando namespaces: PlacetoPay -> $namespace_name"

    sed_tree "$work_dir/src" '*.php' \
        -e "s|namespace PlacetoPay|namespace ${namespace_name}|g" \
        -e "s|use PlacetoPay\\\\|use ${namespace_name}\\\\|g" \
        -e "s|\\\\PlacetoPay\\\\|\\\\${namespace_name}\\\\|g" \
        -e "s|@package PlacetoPay|@package ${namespace_name}|g"
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
    # La última expresión elimina el use de Constants\Client que no existe
    sed_tree "$work_dir/src" '*.php' \
        -e "s/class PlacetoPayPayment /class ${namespace_name}Payment /g" \
        -e "s/PlacetoPayPayment::/${namespace_name}Payment::/g" \
        -e "s/new PlacetoPayPayment(/new ${namespace_name}Payment(/g" \
        -e "s/extends PlacetoPayPayment/extends ${namespace_name}Payment/g" \
        -e "/use.*Constants\\\\Client;/d"
}

# Función para actualizar getModuleName() en helpers.php
# NOTA: Ya no es necesaria porque getModuleName() usa _MODULE_NAME_ (siempre definida)
# y la detección por ruta (cada módulo tiene su propia copia de helpers.php)
# El fallback nunca debería ejecutarse en condiciones normales

# Función para actualizar referencias a la clase en archivos de proceso
update_class_references() {
    local work_dir="$1"
    local main_class_name="$2"

    print_status "Actualizando referencias a clase principal: PlacetoPayPayment -> $main_class_name"

    # Archivos que instancian la clase directamente
    local file
    for file in process.php redirect.php sonda.php; do
        sed_file "$work_dir/$file" \
            -e "s/new PlacetoPayPayment()/new ${main_class_name}()/g" \
            -e "s/PlacetoPayPayment()/${main_class_name}()/g" \
            -e "s/resolvePendingPaymentsPlacetoPay/resolvePendingPayments${main_class_name}/g"
    done

    # Pares "archivo:SufijoDeClase" (bash 3.2 no soporta arreglos asociativos)
    local front_controllers=("sonda:Sonda" "redirect:Redirect" "process:Process")
    local controller_entry controller_key class_suffix

    for controller_entry in "${front_controllers[@]}"; do
        controller_key="${controller_entry%%:*}"
        class_suffix="${controller_entry##*:}"

        sed_file "$work_dir/controllers/front/${controller_key}.php" \
            -e "s/class PlacetoPayPayment${class_suffix}ModuleFrontController/class ${main_class_name}${class_suffix}ModuleFrontController/g" \
            -e "s/resolvePendingPaymentsPlacetoPay/resolvePendingPayments${main_class_name}/g"
    done
}

# Función para reemplazar las constantes de configuración de la base de datos
# Esto asegura que cada cliente tenga sus propias claves únicas en ps_configuration
replace_configuration_constants() {
    local work_dir="$1"
    local client_id="$2"
    local namespace_name="$3"

    # Convertir CLIENT_ID a formato de constante (mayúsculas con guión bajo)
    # Ejemplo: getnet-chile -> GETNET_CHILE
    local const_prefix
    const_prefix=$(echo "$client_id" | tr '[:lower:]' '[:upper:]' | tr '-' '_')

    print_status "Reemplazando constantes de configuración: PLACETOPAY_ -> ${const_prefix}_"

    # La regla es genérica a propósito: cualquier constante nueva 'PLACETOPAY_*'
    # queda cubierta sin tener que enumerarla aquí.
    # PS_OS_PLACETOPAY va aparte porque no empieza por 'PLACETOPAY_.
    sed_file "$work_dir/src/Models/${namespace_name}Payment.php" \
        -e "s/'PS_OS_PLACETOPAY'/'PS_OS_${const_prefix}'/g" \
        -e "s/'PLACETOPAY_/'${const_prefix}_/g"
}

# Función para crear el archivo principal del módulo con nombre único
create_main_module_file() {
    local work_dir="$1"
    local module_name="$2"
    local namespace_name="$3"
    local main_class_name="$4"

    print_status "Creando archivo principal del módulo: ${module_name}.php"

    # Crear el archivo principal del módulo
    cat > "$work_dir/${module_name}.php" << EOF
<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

// Cada módulo tiene sus propias funciones únicas (getModuleName${namespace_name}, getPathCMS${namespace_name}, etc.)
// Ya no es necesario definir _MODULE_NAME_ porque cada función retorna directamente el nombre del módulo

require_once __DIR__ . '/vendor/autoload.php';

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

    sed_tree "$work_dir/translations" '*.php' \
        -e "s/placetopaypayment/$module_name/g"

    # Actualizar templates (.tpl): mod='placetopaypayment'
    sed_tree "$work_dir/views" '*.tpl' \
        -e "s/mod='placetopaypayment'/mod='$module_name'/g"

    # Renombrar el template principal si existe
    local admin_tpl="$work_dir/views/templates/admin/placetopaypayment.tpl"
    if [[ -f "$admin_tpl" ]]; then
        mv "$admin_tpl" "$work_dir/views/templates/admin/${module_name}.tpl"
    fi
}

# Función para actualizar archivos raíz (process.php, redirect.php, sonda.php, helpers.php)
update_root_files() {
    local work_dir="$1"
    local module_name="$2"
    local namespace_name="$3"
    local main_class_name="$4"

    print_status "Actualizando archivos raíz: use statements y referencias hardcodeadas"

    local file
    for file in process.php redirect.php sonda.php; do
        sed_file "$work_dir/$file" \
            -e "s/use PlacetoPay\\\\Loggers\\\\PaymentLogger;/use ${namespace_name}\\\\Loggers\\\\PaymentLogger;/g" \
            -e "s/new PlacetoPayPayment()/new ${main_class_name}()/g" \
            -e "s/getPathCMS(/getPathCMS${namespace_name}(/g" \
            -e "s/getModuleName()/getModuleName${namespace_name}()/g"

        sed_file "$work_dir/controllers/front/$file" \
            -e "s/use PlacetoPay\\\\Loggers\\\\PaymentLogger;/use ${namespace_name}\\\\Loggers\\\\PaymentLogger;/g"
    done

    # Actualizar templates admin (admin_order.tpl) - IDs y referencias
    sed_file "$work_dir/views/templates/admin/admin_order.tpl" \
        -e "s/id=\"placetopaypayment_/id=\"${module_name}_/g" \
        -e "s/#placetopaypayment_/#${module_name}_/g"
}

# Función para actualizar referencias internas hardcodeadas
update_internal_references() {
    local work_dir="$1"
    local module_name="$2"
    local namespace_name="$3"

    print_status "Actualizando referencias internas: placetopay -> $module_name"

    # Convertir el nombre del módulo a snake_case para usar en nombres de tabla/función
    # Ejemplo: banchilechile -> banchile_chile (aunque sin mayúsculas no se aplica, lo dejamos por si acaso)
    local snake_case_name
    snake_case_name=$(echo "$module_name" | sed 's/\([a-z]\)\([A-Z]\)/\1_\2/g' | tr '[:upper:]' '[:lower:]')

    sed_tree "$work_dir/src" '*.php' \
        -e "s/'payment_placetopay'/'payment_${snake_case_name}'/g" \
        -e "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" \
        -e "s/insertPaymentPlaceToPay/insertPayment${namespace_name}/g" \
        -e "s/getModuleName()/getModuleName${namespace_name}()/g"

    # También actualizar en archivos raíz (process.php, redirect.php, sonda.php)
    local file
    for file in process.php redirect.php sonda.php controllers/front/sonda.php; do
        sed_file "$work_dir/$file" \
            -e "s/getModuleName()/getModuleName${namespace_name}()/g"
    done

    # Actualizar helpers.php - renombrar funciones para que sean únicas por módulo
    # El orden importa: primero las declaraciones, luego las llamadas.
    sed_file "$work_dir/helpers.php" \
        -e "s/versionComparePlaceToPay/versionCompare${namespace_name}/g" \
        -e "s/function getModuleName()/function getModuleName${namespace_name}()/g" \
        -e "s/if (!function_exists('getModuleName'))/if (!function_exists('getModuleName${namespace_name}'))/g" \
        -e "s/function getPathCMS(/function getPathCMS${namespace_name}(/g" \
        -e "s/if (!function_exists('getPathCMS'))/if (!function_exists('getPathCMS${namespace_name}'))/g" \
        -e "s/getModuleName()/getModuleName${namespace_name}()/g" \
        -e "s/return 'placetopaypayment';/return '${module_name}';/g"
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

# Versión "mayor.menor" que reporta un binario de PHP
php_binary_version() {
    "$1" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || true
}

# Resolver un binario de PHP para Linux, macOS/Homebrew o el PHP activo del sistema.
# Siempre se valida la versión reportada por el binario, nunca sólo su nombre.
resolve_php_binary() {
    local wanted="$1"
    local compact="${wanted//./}"
    local candidate resolved brew_prefix

    for candidate in \
        "php${wanted}" \
        "php${compact}" \
        "/usr/bin/php${wanted}" \
        "/usr/local/bin/php${wanted}" \
        "/opt/homebrew/opt/php@${wanted}/bin/php" \
        "/usr/local/opt/php@${wanted}/bin/php" \
        "/opt/plesk/php/${wanted}/bin/php" \
        "/opt/cpanel/ea-php${compact}/root/usr/bin/php" \
        "/usr/local/php${compact}/bin/php"
    do
        resolved=""

        if [[ "$candidate" == /* ]]; then
            if [[ -x "$candidate" ]]; then
                resolved="$candidate"
            fi
        else
            resolved="$(command -v "$candidate" 2>/dev/null || true)"
        fi

        if [[ -n "$resolved" && "$(php_binary_version "$resolved")" == "$wanted" ]]; then
            printf '%s\n' "$resolved"
            return 0
        fi
    done

    # Homebrew puede tener el prefijo en una ruta no estándar
    if command -v brew >/dev/null 2>&1; then
        brew_prefix="$(brew --prefix "php@${wanted}" 2>/dev/null || true)"

        if [[ -n "$brew_prefix" && -x "${brew_prefix}/bin/php" ]] \
            && [[ "$(php_binary_version "${brew_prefix}/bin/php")" == "$wanted" ]]; then
            printf '%s\n' "${brew_prefix}/bin/php"
            return 0
        fi
    fi

    # Último recurso: el PHP activo, sólo si coincide la versión
    if command -v php >/dev/null 2>&1 && [[ "$(php_binary_version php)" == "$wanted" ]]; then
        command -v php
        return 0
    fi

    return 1
}

# Reescribir composer.json con PHP (json_decode/encode) en lugar de sed.
# Evita el infierno de escapes y permite fijar autoloader-suffix, que es lo que
# garantiza un autoloader único por módulo sin parchear vendor/ a posteriori.
patch_composer_json() {
    local work_dir="$1"
    local namespace_name="$2"
    local client_id="$3"
    local php_version="$4"
    local autoload_suffix="$5"
    local php_bin="$6"

    print_status "Ajustando composer.json (namespace ${namespace_name}, PHP ${php_version}, autoloader único)"

    COMPOSER_FILE="$work_dir/composer.json" \
    NEW_NAME="placetopay/prestashop-gateway-${client_id}" \
    NEW_NAMESPACE="$namespace_name" \
    PHP_PLATFORM="$php_version" \
    AUTOLOAD_SUFFIX="$autoload_suffix" \
    "$php_bin" -r '
        $file = getenv("COMPOSER_FILE");
        $json = json_decode(file_get_contents($file), true);

        if (!is_array($json)) {
            fwrite(STDERR, "composer.json inválido: " . $file . PHP_EOL);
            exit(1);
        }

        // Nombre único por cliente: evita colisiones cuando hay varios módulos
        // de marca blanca instalados en la misma tienda.
        $json["name"] = getenv("NEW_NAME");
        $json["autoload"]["psr-4"] = [getenv("NEW_NAMESPACE") . "\\" => "src/"];
        $json["require"]["php"] = ">=" . getenv("PHP_PLATFORM");
        $json["config"]["platform"]["php"] = getenv("PHP_PLATFORM");

        // Hace únicas las clases ComposerAutoloaderInit*/ComposerStaticInit*
        $json["config"]["autoloader-suffix"] = getenv("AUTOLOAD_SUFFIX");

        file_put_contents(
            $file,
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
        );
    '
}

# Función para instalar dependencias con una versión específica de PHP.
# Expone el binario de PHP utilizado en RESOLVED_PHP_BIN para los pasos de
# verificación posteriores.
RESOLVED_PHP_BIN=""

install_composer_dependencies() {
    local work_dir="$1"
    local php_version="$2"
    local namespace_name="$3"
    local client_id="$4"

    # Versión corta para localizar el binario (ej: 7.4.33 -> 7.4)
    local php_short
    php_short=$(echo "$php_version" | cut -d. -f1-2)

    local php_bin
    if ! php_bin="$(resolve_php_binary "$php_short")"; then
        print_error "PHP ${php_short} no encontrado."
        print_error "Linux: instala php${php_short} (php${php_short}-cli). macOS: brew install php@${php_short}"
        exit 1
    fi

    local composer_bin
    if ! composer_bin="$(command -v composer)"; then
        print_error "Composer no encontrado en PATH"
        exit 1
    fi

    local autoload_suffix
    autoload_suffix="$("$php_bin" -r 'echo bin2hex(random_bytes(16));')"

    patch_composer_json \
        "$work_dir" "$namespace_name" "$client_id" "$php_version" "$autoload_suffix" "$php_bin"

    # Cada plataforma debe resolver su propio árbol de dependencias. El lock del
    # repositorio no puede representar simultáneamente PHP 7.4 y PHP 8.1.
    rm -f "$work_dir/composer.lock"

    print_status "Resolviendo dependencias con $php_bin (PHP ${php_short})..."
    (
        cd "$work_dir"
        "$php_bin" "$composer_bin" update \
            --no-dev \
            --no-interaction \
            --no-progress \
            --prefer-dist \
            --optimize-autoloader
    )

    # El suffix se fija vía composer.json, así que el autoloader generado ya es
    # único; sólo se verifica que Composer lo haya respetado.
    if ! grep -q "ComposerAutoloaderInit${autoload_suffix}" "$work_dir/vendor/autoload.php"; then
        print_error "Composer no aplicó el autoloader-suffix esperado (${autoload_suffix})"
        exit 1
    fi

    print_status "✓ Autoloader aislado: ComposerAutoloaderInit${autoload_suffix}"

    RESOLVED_PHP_BIN="$php_bin"
}

# Verificar que las dependencias empaquetadas no rompan las clases del core.
#
# El autoloader del módulo se registra con prepend=true, así que sus interfaces
# PSR ganan sobre las del core de PrestaShop durante todo el request. Si aquí
# entra psr/http-message 2.x (que declara `__toString(): string`) el core revienta
# con:
#   Declaration of Nyholm\Psr7\Stream::__toString() must be compatible with
#   Psr\Http\Message\StreamInterface::__toString(): string
# psr/http-message 1.1 es la única versión segura para PS 8 y PS 9 a la vez:
# añade tipos en parámetros (que guzzlehttp/psr7 2.x necesita) pero no tipos de
# retorno (que romperían a los implementadores del core).
assert_bundled_dependencies() {
    local work_dir="$1"
    local stream_interface="$work_dir/vendor/psr/http-message/src/StreamInterface.php"

    print_status "Verificando compatibilidad de las dependencias empaquetadas..."

    if [[ -f "$stream_interface" ]]; then
        if grep -qE 'function __toString\(\)[[:space:]]*:' "$stream_interface"; then
            print_error "psr/http-message empaquetado declara tipos de retorno (2.x)."
            print_error "Rompe Nyholm\\Psr7 y GuzzleHttp\\Psr7 del core de PrestaShop."
            print_error "Mantén \"psr/http-message\": \"^1.1\" en composer.json."
            exit 1
        fi
    fi

    if [[ -d "$work_dir/vendor/psr/log" ]]; then
        print_error "psr/log quedó empaquetado y sobrescribirá el del core de PrestaShop."
        print_error "Mantén \"replace\": {\"psr/log\": \"*\"} en composer.json."
        exit 1
    fi

    print_status "✓ Dependencias compatibles con el core de PrestaShop"
}

# Validar la sintaxis de todo el código generado con el PHP de destino.
# Detecta reemplazos de sed mal escapados antes de empaquetar el ZIP.
lint_generated_sources() {
    local work_dir="$1"
    local php_bin="$2"
    local failed=0
    local file

    print_status "Validando sintaxis del código generado..."

    while IFS= read -r file; do
        if ! "$php_bin" -l "$file" >/dev/null 2>&1; then
            print_error "Sintaxis inválida en ${file#"$work_dir"/}"
            "$php_bin" -l "$file" 2>&1 | head -3
            failed=1
        fi
    done < <(find "$work_dir" -type f -name '*.php' -not -path "$work_dir/vendor/*")

    if [[ $failed -ne 0 ]]; then
        exit 1
    fi

    print_status "✓ Sintaxis válida"
}

# Verificar que no quedaron marcas de la plantilla original en el artefacto
assert_no_placetopay_leftovers() {
    local work_dir="$1"
    local leftovers

    leftovers=$(grep -rlE 'PlacetoPayPayment|namespace PlacetoPay|placetopaypayment' \
        "$work_dir" --include='*.php' --include='*.tpl' 2>/dev/null \
        | grep -v "^${work_dir}/vendor/" || true)

    if [[ -n "$leftovers" ]]; then
        print_warning "Quedan referencias a la plantilla original en:"
        echo "$leftovers" | while IFS= read -r file; do
            echo "    ${file#"$work_dir"/}"
        done
    fi
}

# Función para limpiar archivos innecesarios del vendor (siguiendo el Makefile)
cleanup_vendor_files() {
    local work_dir="$1"

    print_status "Limpiando archivos innecesarios del vendor..."

    # Eliminar directorios .git* y squizlabs (usando find como en el Makefile)
    find "$work_dir" -type d -name ".git*" -exec rm -rf {} + 2>/dev/null || true
    find "$work_dir" -type d -name "squizlabs" -exec rm -rf {} + 2>/dev/null || true

    # Limpiar vendor exactamente como en el Makefile
    rm -rf "$work_dir/vendor/bin"
    rm -rf "$work_dir/vendor/alejociro/redirection/tests"
    rm -rf "$work_dir/vendor/alejociro/redirection/examples"
    rm -rf "$work_dir/vendor/guzzlehttp/ringphp/docs"
    rm -rf "$work_dir/vendor/guzzlehttp/ringphp/tests"
    rm -rf "$work_dir/vendor/guzzlehttp/guzzle/docs"
    rm -rf "$work_dir/vendor/guzzlehttp/guzzle/tests"
    rm -rf "$work_dir/vendor/guzzlehttp/streams/tests"
}

# Función para limpiar archivos de desarrollo del build (siguiendo el Makefile)
cleanup_build_files() {
    local work_dir="$1"

    print_status "Eliminando archivos de desarrollo innecesarios..."

    rm -rf "$work_dir"/.git*
    rm -rf "$work_dir/.idea"
    rm -rf "$work_dir/.vscode"
    rm -rf "$work_dir/.claude"
    rm -rf "$work_dir/config"
    rm -rf "$work_dir/logos"
    rm -rf "$work_dir/Dockerfile"
    rm -rf "$work_dir/Makefile"
    rm -rf "$work_dir"/.env*
    rm -rf "$work_dir"/composer.*
    rm -rf "$work_dir/.phpactor.json"
    rm -rf "$work_dir/.php_cs.cache"
    rm -rf "$work_dir/.php-cs-fixer.cache"
    rm -rf "$work_dir/.vimrc.setup"
    rm -rf "$work_dir"/*.md
    rm -rf "$work_dir"/*.sh
    rm -rf "$work_dir"/*.sql
    rm -rf "$work_dir"/*.log
    rm -rf "$work_dir"/*.diff
    rm -rf "$work_dir"/*.hasts
    rm -rf "$work_dir"/*.hasaia

    # Basura de macOS: no debe viajar en el ZIP
    find "$work_dir" -name '.DS_Store' -type f -delete 2>/dev/null || true
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
    local module_name
    module_name=$(echo "${CLIENT_ID}" | tr -d '-')

    local work_dir="$TEMP_DIR/$module_name"
    rm -rf "$work_dir"
    mkdir -p "$work_dir"

    print_status "Nombre del módulo: $module_name"

    # Nombre de la clase principal (PascalCase, primera letra en mayúscula)
    # Ejemplo: banchilechile -> Banchilechile
    local main_class_name
    main_class_name="$(echo "${module_name:0:1}" | tr '[:lower:]' '[:upper:]')${module_name:1}"

    # Copiar los archivos fuente al directorio de trabajo
    print_status "Copiando archivos fuente..."
    rsync -a "${RSYNC_EXCLUDES[@]}" "$BASE_DIR/" "$work_dir/"

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

    # Crear archivo principal del módulo con nombre único
    create_main_module_file "$work_dir" "$module_name" "$namespace_name" "$main_class_name"

    # Actualizar referencias a la clase en process.php, redirect.php, sonda.php
    update_class_references "$work_dir" "$main_class_name"

    # Actualizar archivos de traducción
    update_translation_files "$work_dir" "$module_name"

    # Actualizar archivos raíz (process.php, redirect.php, sonda.php, helpers.php, templates)
    update_root_files "$work_dir" "$module_name" "$namespace_name" "$main_class_name"

    # Actualizar referencias internas hardcodeadas (tablas, funciones)
    update_internal_references "$work_dir" "$module_name" "$namespace_name"

    # Instalar dependencias de composer con la versión específica de PHP
    install_composer_dependencies "$work_dir" "$php_version" "$namespace_name" "$CLIENT_ID"

    # Verificaciones antes de empaquetar
    assert_bundled_dependencies "$work_dir"
    lint_generated_sources "$work_dir" "$RESOLVED_PHP_BIN"
    assert_no_placetopay_leftovers "$work_dir"

    # Limpiar archivos innecesarios del vendor
    cleanup_vendor_files "$work_dir"

    # Limpiar archivos de desarrollo
    cleanup_build_files "$work_dir"

    # Crear archivo ZIP
    print_status "Creando archivo ZIP..."
    mkdir -p "$OUTPUT_DIR"
    rm -f "$OUTPUT_DIR/$project_name.zip"
    (
        cd "$TEMP_DIR"
        zip -rq -X "$OUTPUT_DIR/$project_name.zip" "$module_name"
    )

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

    # Un artefacto por versión de PrestaShop (ver BUILD_TARGETS)
    local target prestashop_version php_version
    for target in "${BUILD_TARGETS[@]}"; do
        prestashop_version="${target%%|*}"
        php_version="${target##*|}"

        create_white_label_version_with_php \
            "$client_key" "$php_version" "$prestashop_version" "$plugin_version"
        echo
    done
}

# Función principal
main() {
    local plugin_version="${1:-untagged}"

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
    local client_key
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

# Listar los clientes configurados
list_clients() {
    local client_key config

    for client_key in $(get_all_clients); do
        config=$(get_client_config "$client_key")

        if [[ -n "$config" ]]; then
            parse_config "$config"
            echo "  $client_key: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
        fi
    done
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
    echo "Cada cliente produce un artefacto por versión de PrestaShop:"
    local target
    for target in "${BUILD_TARGETS[@]}"; do
        echo "  - ${target%%|*} (dependencias resueltas con PHP ${target##*|})"
    done
    echo ""
    echo "Clientes disponibles:"
    list_clients
}

check_requirements
verify_sed_inplace

# Manejar argumentos de línea de comandos
case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
    -l|--list)
        echo "Configuraciones de clientes disponibles:"
        list_clients
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
