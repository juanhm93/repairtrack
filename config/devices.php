<?php

/**
 * Popular brands and models by device type. Curated suggestions only;
 * the form still accepts free text and merges the technician's own history.
 *
 * @return array<string, array<string, list<string>>>
 */
return [
    'celular' => [
        'Apple' => [
            'iPhone 11',
            'iPhone 12',
            'iPhone 13',
            'iPhone 13 Pro',
            'iPhone 14',
            'iPhone 14 Pro',
            'iPhone 15',
            'iPhone 15 Pro',
            'iPhone 16',
            'iPhone 16 Pro',
            'iPhone SE',
        ],
        'Samsung' => [
            'Galaxy A15',
            'Galaxy A25',
            'Galaxy A35',
            'Galaxy A54',
            'Galaxy A55',
            'Galaxy S23',
            'Galaxy S24',
            'Galaxy S24 Ultra',
            'Galaxy S25',
            'Galaxy Z Flip',
            'Galaxy Z Fold',
        ],
        'Xiaomi' => [
            'Redmi 13',
            'Redmi Note 12',
            'Redmi Note 13',
            'Redmi Note 14',
            'Poco X6',
            'Poco F6',
            'Xiaomi 14',
        ],
        'Motorola' => [
            'Moto G24',
            'Moto G54',
            'Moto G84',
            'Moto G Stylus',
            'Razr',
            'Edge 50',
        ],
        'Huawei' => [
            'P30 Lite',
            'P40 Lite',
            'Nova 11',
            'Nova 12',
            'Y9a',
        ],
        'Honor' => [
            'X6',
            'X7',
            'X8',
            'Magic 6',
        ],
        'Google' => [
            'Pixel 7',
            'Pixel 8',
            'Pixel 8a',
            'Pixel 9',
        ],
        'OnePlus' => [
            'Nord N30',
            '12',
            '12R',
            '13',
        ],
        'Oppo' => [
            'A18',
            'A38',
            'Reno 11',
            'Reno 12',
        ],
        'Infinix' => [
            'Hot 40',
            'Hot 50',
            'Note 40',
            'Zero 40',
        ],
        'Tecno' => [
            'Spark 20',
            'Spark 30',
            'Camon 20',
            'Camon 30',
        ],
    ],
    'tablet' => [
        'Apple' => [
            'iPad',
            'iPad 9',
            'iPad 10',
            'iPad 11',
            'iPad Air',
            'iPad mini',
            'iPad Pro 11',
            'iPad Pro 12.9',
        ],
        'Samsung' => [
            'Galaxy Tab A9',
            'Galaxy Tab A9+',
            'Galaxy Tab S9',
            'Galaxy Tab S9 FE',
            'Galaxy Tab S10',
        ],
        'Lenovo' => [
            'Tab M10',
            'Tab M11',
            'Tab P11',
            'Tab P12',
        ],
        'Amazon' => [
            'Fire HD 8',
            'Fire HD 10',
            'Fire Max 11',
        ],
        'Xiaomi' => [
            'Redmi Pad',
            'Redmi Pad SE',
            'Pad 6',
        ],
        'Huawei' => [
            'MatePad',
            'MatePad SE',
            'MatePad 11',
        ],
    ],
    'laptop' => [
        'Apple' => [
            'MacBook Air M1',
            'MacBook Air M2',
            'MacBook Air M3',
            'MacBook Pro 13',
            'MacBook Pro 14',
            'MacBook Pro 16',
        ],
        'Dell' => [
            'XPS 13',
            'XPS 15',
            'Inspiron 15',
            'Latitude 3440',
            'Latitude 5540',
            'G15',
        ],
        'HP' => [
            'Pavilion 15',
            'Envy 13',
            'Envy x360',
            'EliteBook 840',
            'ProBook 450',
            'Victus 15',
        ],
        'Lenovo' => [
            'ThinkPad E14',
            'ThinkPad T14',
            'IdeaPad 3',
            'IdeaPad Slim 3',
            'Yoga 7',
            'LOQ 15',
        ],
        'Asus' => [
            'VivoBook 15',
            'ZenBook 14',
            'TUF Gaming A15',
            'ROG Strix',
        ],
        'Acer' => [
            'Aspire 5',
            'Aspire 3',
            'Swift 3',
            'Nitro 5',
        ],
        'MSI' => [
            'Thin GF63',
            'Katana 15',
            'Cyborg 15',
        ],
        'Microsoft' => [
            'Surface Laptop',
            'Surface Laptop Go',
            'Surface Pro',
        ],
    ],
    'pc_desktop' => [
        'Dell' => [
            'OptiPlex',
            'Inspiron Desktop',
            'XPS Desktop',
        ],
        'HP' => [
            'Pavilion Desktop',
            'EliteDesk',
            'ProDesk',
        ],
        'Lenovo' => [
            'ThinkCentre',
            'IdeaCentre',
        ],
        'Apple' => [
            'iMac',
            'iMac 24',
            'Mac mini',
            'Mac Studio',
        ],
        'Asus' => [
            'ROG Strix Desktop',
            'ExpertCenter',
        ],
        'Armada / custom' => [
            'PC armada',
        ],
    ],
    'consola' => [
        'Sony' => [
            'PlayStation 4',
            'PlayStation 4 Slim',
            'PlayStation 4 Pro',
            'PlayStation 5',
            'PlayStation 5 Slim',
            'PlayStation 5 Pro',
            'PSP',
            'PS Vita',
        ],
        'Microsoft' => [
            'Xbox One',
            'Xbox One S',
            'Xbox One X',
            'Xbox Series S',
            'Xbox Series X',
        ],
        'Nintendo' => [
            'Switch',
            'Switch Lite',
            'Switch OLED',
            'Switch 2',
            'Wii U',
            '3DS',
        ],
        'Valve' => [
            'Steam Deck',
            'Steam Deck OLED',
        ],
        'Asus' => [
            'ROG Ally',
            'ROG Ally X',
        ],
    ],
    'otro' => [],
];
