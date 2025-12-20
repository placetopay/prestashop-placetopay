<?php
// Configuración centralizada de clientes para generación de white-label
// Cada cliente tiene un CLIENT_ID único que se usa para identificación en el código
// 
// CLIENT_ID: Identificador único para el cliente (formato: cliente-país con guión)
//   - Se usa como base para generar namespaces, nombres de clases y archivos
//   - Ejemplo: "getnet-chile", "avalpay-colombia", "placetopay-ecuador"
//
// Formatos generados a partir del CLIENT_ID:
//   - Namespace PHP: GetnetChile (PascalCase)
//   - Clase PHP: PlacetoPayPaymentGetnetChile
//   - snake_case: getnet_chile (para nombres de funciones/hooks)

return [
    'placetopay-colombia' => [
        'client' => 'Placetopay',
        'country_code' => 'CO',
        'country_name' => 'Colombia',
        'client_id' => 'placetopay-colombia',
        'template_file' => 'ColombiaColombiaConfig',
        'logo_file' => 'Placetopay.png',
    ],
    'placetopay-ecuador' => [
        'client' => 'Placetopay',
        'country_code' => 'EC',
        'country_name' => 'Ecuador',
        'client_id' => 'placetopay-ecuador',
        'template_file' => 'PlacetopayEcuadorConfig',
        'logo_file' => 'Placetopay.png',
    ],
    'getnet-chile' => [
        'client' => 'Getnet',
        'country_code' => 'CL',
        'country_name' => 'Chile',
        'client_id' => 'getnet-chile',
        'template_file' => 'GetnetChileConfig',
        'logo_file' => 'Getnet.png',
    ],
    'banchile-chile' => [
        'client' => 'Banchile',
        'country_code' => 'CL',
        'country_name' => 'Chile',
        'client_id' => 'banchile-chile',
        'template_file' => 'BanchileChileConfig',
        'logo_file' => 'Banchile.png',
    ],
    'placetopay-honduras' => [
        'client' => 'Placetopay',
        'country_code' => 'HN',
        'country_name' => 'Honduras',
        'client_id' => 'placetopay-honduras',
        'template_file' => 'PlacetopayHondurasConfig',
        'logo_file' => 'Placetopay.png',
    ],
    'gou-belice' => [
        'client' => 'Gou',
        'country_code' => 'BZ',
        'country_name' => 'Belice',
        'client_id' => 'gou-belice',
        'template_file' => 'GouBeliceConfig',
        'logo_file' => 'Placetopay.png',
    ],
    'placetopay-uruguay' => [
        'client' => 'Placetopay',
        'country_code' => 'UY',
        'country_name' => 'Uruguay',
        'client_id' => 'placetopay-uruguay',
        'template_file' => 'PlacetopayUruguayConfig',
        'logo_file' => 'Placetopay.png',
    ],
    'avalpay-colombia' => [
        'client' => 'Avalpay',
        'country_code' => 'CO',
        'country_name' => 'Colombia',
        'client_id' => 'avalpay-colombia',
        'template_file' => 'AvalpayColombiaConfig',
        'logo_file' => 'AvalPay.png',
    ],
];
