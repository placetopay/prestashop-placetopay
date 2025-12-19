<?php
return [
  'banchile' => [
      'client' => 'BanchilePagos',
      'country_code' => 'CL',
      'country_name' => 'Chile',
      'module_name' => 'banchilepayment', // nuevo
      'image' => 'https://example.com/logo.svg',
      'logo_file' => 'Banchile.png', // nuevo no documentado
      'template_file' => 'BanchilePaymentUrl',  // Nombre del template
      'endpoints' => [
          'prod' => 'https://api.cliente.com',
          'test' => 'https://test.cliente.com',
          'dev' => 'https://dev.placetopay.com',
      ],
  ]
];