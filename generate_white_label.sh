#!/bin/bash

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
declare -a PHP_VERSIONS=("7.2" "7.4")
declare -a PRESTASHOP_VERSIONS=("prestashop-1.7.x" "prestashop-8.x")

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
        echo 'TEMPLATE_FILE=' . (isset(\$client['template_file']) ? \$client['template_file'] : '') . '|';
        echo 'LOGO_FILE=' . (isset(\$client['logo_file']) ? \$client['logo_file'] : 'Placetopay.png') . '|';
        echo 'MODULE_NAME=' . \$client['module_name'] . '|';
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
            "TEMPLATE_FILE") TEMPLATE_FILE="$value" ;;
            "LOGO_FILE") LOGO_FILE="$value" ;;
            "MODULE_NAME") MODULE_NAME="$value" ;;
        esac
    done
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
            sed -i '' 's/"php": "[0-9]\.[0-9]\.[0-9]"/"php": "'"${php_version}"'"/' "$composer_file"
            sed -i '' 's/"php": "[>=^~].*"/"php": ">='"${php_version}"'"/' "$composer_file"
        else
            # Linux usa -i sin argumento
            sed -i 's/"php": "[0-9]\.[0-9]\.[0-9]"/"php": "'"${php_version}"'"/' "$composer_file"
            sed -i 's/"php": "[>=^~].*"/"php": ">='"${php_version}"'"/' "$composer_file"
        fi
    fi
    
    # Eliminar composer.lock si existe
    rm -rf "$work_dir/composer.lock"
    
    # Instalar dependencias con la versión específica de PHP
    cd "$work_dir"
    
    # Verificar si existe el comando php con la versión específica
    if command -v "php${php_version}" >/dev/null 2>&1; then
        print_status "Usando php${php_version} para instalar dependencias..."
        php${php_version} "$(which composer)" install 2>&1 | grep -v "^$" || true
    else
        print_warning "php${php_version} no encontrado, usando php por defecto..."
        php "$(which composer)" install 2>&1 | grep -v "^$" || true
    fi
    
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
    local config
    config=$(get_client_config "$client_key")
    
    if [[ -z "$config" ]]; then
        print_error "Cliente desconocido: $client_key"
        return 1
    fi
    
    # Parsear configuración
    parse_config "$config"
    
    # Determinar nombre del proyecto base
    local project_name_base
    project_name_base=$(get_project_name "$CLIENT" "$COUNTRY_NAME")
    
    # Agregar versión de PrestaShop al nombre del proyecto
    local project_name="${project_name_base}-${prestashop_version}"
    
    print_status "Creando versión de marca blanca: $project_name"
    print_status "Cliente: $CLIENT, País: $COUNTRY_NAME ($COUNTRY_CODE), PHP: $php_version"
    
    # Crear directorio de trabajo temporal con nombre fijo del módulo
    local module_name="$MODULE_NAME"
    local work_dir="$TEMP_DIR/$module_name"
    echo "------- Creating work_dir at $work_dir"
    mkdir -p "$work_dir"
    
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

    # Instalar dependencias de composer con la versión específica de PHP
    install_composer_dependencies "$work_dir" "$php_version"
    
    # Limpiar archivos innecesarios del vendor
    cleanup_vendor_files "$work_dir"
    
    # Limpiar archivos de desarrollo
    cleanup_build_files "$work_dir"
    
    # Crear archivo ZIP
    print_status "Creando archivo ZIP..."
    mkdir -p "$OUTPUT_DIR"
    echo "work_dir is $work_dir , module_name is $module_name"
    cd "$TEMP_DIR"
    zip -rq "$OUTPUT_DIR/$project_name.zip" "$module_name"
    cd "$BASE_DIR"
    
    # Limpiar directorio temporal de este build
#    rm -rf "$work_dir"
    
    print_success "Creado: $OUTPUT_DIR/$project_name.zip (carpeta interna: $module_name)"
}

# Función para crear todas las versiones de marca blanca para un cliente
create_white_label_version() {
    local client_key="$1"
    
    print_status "========================================="
    print_status "Procesando cliente: $client_key"
    print_status "========================================="
    echo
    
    # Generar una versión para cada versión de PHP/PrestaShop
    local i=0
    for php_version in "${PHP_VERSIONS[@]}"; do
        local prestashop_version="${PRESTASHOP_VERSIONS[$i]}"
        create_white_label_version_with_php "$client_key" "$php_version" "$prestashop_version"
        echo
        i=$((i + 1))
    done
}

# Función principal
main() {
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
        create_white_label_version "$client_key"
        echo
    done
    
    # Limpiar directorio temporal
    print_status "Limpiando archivos temporales..."
#    rm -rf "$TEMP_DIR"
    
    print_success "¡Generación de marca blanca completada!"
    print_status "Los archivos generados están en: $OUTPUT_DIR"
    
    # Listar archivos generados
    echo
    print_status "Versiones de marca blanca generadas:"
    ls -la "$OUTPUT_DIR"/*.zip 2>/dev/null | while read -r line; do
        echo "  $line"
    done || print_warning "No se encontraron archivos ZIP en el directorio de salida"
}

# Mostrar información de uso
usage() {
    echo "Uso: $0 [OPCIONES] [CLIENTE]"
    echo ""
    echo "Generar versiones de marca blanca del plugin PrestaShop PlacetoPay"
    echo ""
    echo "Opciones:"
    echo "  -h, --help    Mostrar este mensaje de ayuda"
    echo "  -l, --list    Listar configuraciones de clientes disponibles"
    echo "  CLIENTE       Generar solo para un cliente específico (opcional)"
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
        # Verificar si es un cliente válido
        config=$(get_client_config "$1")
        if [[ -n "$config" ]]; then
            print_status "Generando versión de marca blanca para: $1"
            rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
            mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"
            
            create_white_label_version "$1"
#            rm -rf "$TEMP_DIR"
            print_success "¡Generación de marca blanca completada para $1!"
        else
            print_error "Opción desconocida: $1"
            echo ""
            usage
            exit 1
        fi
        ;;
esac
