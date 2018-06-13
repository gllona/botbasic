<?php
/**
 * Configuración de los bots y futuro proxy para configuración en BD
 *
 * @author      Gorka G LLona                               <gorka@gmail.com> <gorka@venicua.com>
 * @license     http://www.venicua.com/botbasic/license     Licencia de BotBasic
 * @see         http://www.venicua.com/botbasic             Referencia de BotBasic
 * @version     1.0 - 01.jan.2017
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase BotConfig
 *
 * Parámetros de configuración de todos los bots de esta VM de BotBasic, implementados de forma directa y/o
 * en la BD y accedidos siempre a través de métodos.
 *
 * @package botbasic
 */
abstract class BotConfig
{



    //////////////////////////////////////////////////////
    // DEFINICION DE BOTS Y CREDENCIALES PARA CADA CHATAPP
    //////////////////////////////////////////////////////



    /** @var array Definición de los bots del programa BotBasic; cada uno está asociado a un conjunto de bots de cada ChatMedium,
     *             y estas definiciones se reflejan (con las mismas claves del mapa) en ChatMediumTelegram:$cmBots; cada entrada es:
     *             [ bbCode => [ bbcodeCodename, authorizedMajorversionnumber, bbcodeBotname, optionalThisArrayIndexForLogging ], ... ]
     *             No se acualizará el código BB en ejecución por un runtime a una versión mayor que la especificada aquí, para cada
     *             bot del programa BotBasic; esto permite hacer actualizaciones progresivas de funcionalidad "mayor" bot por bot,
     *             a pesar que los programas de todos los bots estén incluidos en el mismo archivo de código fuente */
    static private $bbBots = [

        // NEUROPOWER (TEST DRIVE #1) - FOTOBOT

        10 => [ 'neuropower', '1', 'bot1',  12  ],
        12 => [ 'neuropower', '1', 'bot3',      ],

        // VENI - BOT PARA PRUEBAS DE BB

        20 => [ 'bbtests', '1', 'main',     21  ],
        21 => [ 'bbtests', '1', 'monitor',      ],

        // T3 - TURN TRASH INTO TREASURES

        30 => [ 't3', '0', 'sponsor',       39  ],
        31 => [ 't3', '0', 'exchanger',     39  ],
        32 => [ 't3', '0', 'main',          39  ],
        33 => [ 't3', '0', 'handler',       39  ],
        34 => [ 't3', '0', 'truck',         39  ],
        35 => [ 't3', '0', 'converter',     39  ],
        36 => [ 't3', '0', 'partner',       39  ],
        37 => [ 't3', '0', 'admin',         39  ],
      //38 => [ 't3', '0', 'edu',           39  ],
        39 => [ 't3', '0', 'monitor',           ],

        // BOTS DE JORGE

        210 => [ 'copa',  '0', 'main',      249 ],
        211 => [ 'jtest', '0', 'botJ1',     249 ],
        212 => [ 'jtest', '0', 'botJ2',     249 ],
        249 => [ 'jtest', '0', 'monitor',       ],

        // BOTS DE NICOLE

        260 => [ 'thebotbox', '0', 'main',  299 ],
        261 => [ 'ntest',     '0', 'botN1', 299 ],
        262 => [ 'ntest',     '0', 'botN2', 299 ],
        299 => [ 'ntest',     '0', 'monitor',   ],

        // BOTS DE LUIS

        310 => [ 'groca',  '0', 'main',      349 ],
        311 => [ 'ltest',  '0', 'botL1',     349 ],
        312 => [ 'ltest',  '0', 'botL2',     349 ],
        349 => [ 'ltest',  '0', 'monitor',       ],

        // BOTS DE CARLOS

        360 => [ 'coffeexpress', '0', 'main',  399 ],
        361 => [ 'ctest',        '0', 'botC1', 399 ],
        362 => [ 'ctest',        '0', 'botC2', 399 ],
        399 => [ 'ctest',        '0', 'monitor',   ],

        // BOTS DE GORKA

        899 => [ 'gorkathebot',     '0', 'main',        ],
        800 => [ 'oraloco',         '0', 'main',        ],
        801 => [ 'biblioboquete',   '0', 'main',        ],
        820 => [ 'nima',            '0', 'main',        ],
        821 => [ 'chavezvive',      '0', 'main',        ],
        822 => [ 'bot14bot',        '0', 'main',        ],
        823 => [ 'nimadonkey',      '0', 'main',        ],
        830 => [ 'constituyente',   '0', 'main',        ],

    ];



    /** @var array Mapa de bots definidos para el simulador CLI, expresado como:
     *             [ bbCode-key-as-in-ChatMedium::$bbBots-for-default-bot    => [ botName, webHookScriptName, credential-for-chatapp-servers-API ],
     *               bbCode-key-as-in-ChatMedium::$bbBots-for-nondefault-bot => [...], ... ] */
    static private $cmBotsCliStub = [

        // NP - Fotobot - main
        10 => [
            [ 'NeuroPowerBot',  '1000',                                                                                                              '171752376:AAGgO5P3_W8Q8KPLCvoQAKHafiQ54w-K6rw' ],
        ],
        // NP - Fotobot - staff (monitor) - used for monitoring
        12 => [
            [ 'neuropower_bot', '1200',                                                                                                              '227989979:AAG0lpleT4SlriqdeLUv35jhJsRXn2chMoc' ],
        ],

        // VENI - BBtests - main
        20 => [
            [ 'venibot',            '2000',                                                                                                              '204321172:AAFr62nK9j5phVwF6nPpe-JEZycC9VfDQLA' ],
        ],
        // VENI - BBtests - main
        21 => [
            [ 'venicuabot',         '2100',                                                                                                              '328154998:AAHXisYfjjr9Z1n58Y37vO4RuUGrLXJ0f3c' ],
        ],

        // T3 - sponsor (sponsor)
        30 => [
            [ 'T3sponsor_bot',      '3000',                                                                                                      '271367661:AAGUoheqgx75qFl1biQ-SmEwugYMD7WVZOI' ],
        ],
        // T3 - exchanger (retail)
        31 => [
            [ 'T3exchanger_bot',    '3100',                                                                                                      '332067633:AAEdiszdqTaNA5m8A6oGl5akpNyY4chCX2g' ],
        ],
        // T3 - main (reciclador + invitado)
        32 => [
            [ 'T3_bot',             '3200',                                                                                                      '242945055:AAGNJXNE-FOsL2JdkZrk053C264Ag3i-JqE' ],
        ],
        // T3 - handler (colector)
        33 => [
            [ 'T3handler_bot',      '3300',                                                                                                      '293419680:AAHA0XjEyPcACXdfs8kjiBnDsD8VhHF6OhY' ],
        ],
        // T3 - truck (camion)
        34 => [
            [ 'T3truck_bot',        '3400',                                                                                                      '220338994:AAGl4D1EMhafdjTuo0_x-UCnFpBrspdsZuE' ],
        ],
        // T3 - converter (acopio)
        35 => [
            [ 'T3converter_bot',    '3500',                                                                                                      '349436605:AAGKBZL05drUAQCH8w-C71kBLgAKdUM2cTs' ],
        ],
        // T3 - partner (fundacion)
        36 => [
            [ 'T3partner_bot',      '3600',                                                                                                      '305539707:AAFaU1pp1iJySqQwyJB0ZCxqGoRg2bJ5hJQ' ],
        ],
        // T3 - admin (operador)
        37 => [
            [ 'T3admin_bot',        '3700',                                                                                                      '313828017:AAG3v-Aom8NYIAgPezNQIdyv3xQBwqk3ZEU' ],
        ],
        // T3 - edu (education) (UNINPLEMENTED)
      //38 => [
      //    [ 'T3edu_bot',          '3800',                                                                                                      '295486770:AAF5y4buCZfpIi4EXzenbLROiXMPbVyaGXg' ],
      //],
        // T3 - monitor (logs + errors + tracing)
        39 => [
            [ 'T3monitor_bot',      '3900',                                                                                                      '327127354:AAEpDgwMeGY2-r5inVWL-TcG_Y9EceVOzwE' ],
        ],

        210 => [
            [ 'Copa_bot',           '21000',                                                                                                             '356650537:AAFQ2V4dkWd_u90d3Mpj6goaG4crA1vGyf0' ],
        ],
        211 => [
            [ 'TheHandybot',        '21100',                                                                                                             '317790698:AAFORJf9RbgKzRaIm4po6IYYuHv9FokksXc' ],
        ],
        212 => [
            [ 'SchedyBot',          '21200',                                                                                                             '344270765:AAEVzMA9q_B4fEoO65iohFXnp0gFj0mIYGE' ],
        ],

        260 => [
            [ 'TheBotBox_Bot',      '26000',                                                                                                             '349758138:AAEog_6bey4po9VJjpQvMaGH4EPhE-iz5HI' ],
        ],
        261 => [
            [ 'BotlightyearBot',    '26100',                                                                                                             '293002363:AAGu_XbCX9en29BLCEPG6L4AKZS5b5jw9Lg' ],
        ],
        262 => [
            [ 'BotstartupsBot',     '26200',                                                                                                             '345446126:AAFP0ZlGPyXfM2cU9rwfLsEWivHRDajvqoI' ],
        ],

        800 => [
            [ 'OralocoBot',                 '80000',                                                                                                             '292199253:AAGxLSWCZtq5fZ9e2my6Z_ZW3FSujNHman0' ],
        ],

    ];



    /** @var array Mapa de bots definidos para el simulador web, expresado como:
     *             [ bbCode-key-as-in-ChatMedium::$bbBots-for-default-bot    => [ botName, webHookScriptName, credential-for-chatapp-servers-API ],
     *               bbCode-key-as-in-ChatMedium::$bbBots-for-nondefault-bot => [...], ... ] */
    static private $cmBotsWebStub = [

        // NP - Fotobot - main
        10 => [
            [ 'NeuroPowerBot',  'tgrp_10_00_278347235423590890123454.php',                                                                                                              '171752376:AAGgO5P3_W8Q8KPLCvoQAKHafiQ54w-K6rw' ],
        ],
        // NP - Fotobot - staff (monitor) - used for monitoring
        12 => [
            [ 'neuropower_bot', 'tgrp_12_00_934967523854879438679845.php',                                                                                                              '227989979:AAG0lpleT4SlriqdeLUv35jhJsRXn2chMoc' ],
        ],

    ];



    /** @var array Mapa de bots definidos para Telegram, expresado como:
     *             [ bbCode-key-as-in-ChatMedium::$bbBots-for-default-bot    => [ botName, webHookScriptName, credential-for-chatapp-servers-API ],
     *               bbCode-key-as-in-ChatMedium::$bbBots-for-nondefault-bot => [...], ... ] */
    static private $cmBotsTelegram = [

        ///////////////////////////////////////
        // NEUROPOWER (TEST DRIVE #1) - FOTOBOT
        ///////////////////////////////////////

        // NP - Fotobot - main
        10 => [
            [ 'NeuroPowerBot',  'tgrp_10_00_278347235423590890123454.php',                                                                                                              '171752376:AAGgO5P3_W8Q8KPLCvoQAKHafiQ54w-K6rw' ],
        ],
        // NP - unused - especialista channel 01 TO nn
        11 => [
            [ 'np00bot',        'tgrp_11_00_142857142857.php',                                                                                                                          '' ],
            [ 'np01bot',        'tgrp_11_01_142857142857.php',                                                                                                                          '' ],
            [ 'np02bot',        'tgrp_11_02_142857142857.php',                                                                                                                          '' ],
            [ 'np03bot',        'tgrp_11_03_142857142857.php',                                                                                                                          '' ],
            [ 'np04bot',        'tgrp_11_04_142857142857.php',                                                                                                                          '' ],
            [ 'np05bot',        'tgrp_11_05_142857142857.php',                                                                                                                          '' ],
        ],
        // NP - Fotobot - staff (monitor) - used for monitoring
        12 => [
            [ 'neuropower_bot', 'tgrp_12_00_934967523854879438679845.php',                                                                                                              '227989979:AAG0lpleT4SlriqdeLUv35jhJsRXn2chMoc' ],
        ],

        ////////////////////////////////
        // VENI - BOT PARA PRUEBAS DE BB
        ////////////////////////////////

        // VENI - BBtests - main
        20 => [
            [ 'venibot',            'tgrp_20_00_789349080985234237899046.php',                                                                                                              '204321172:AAFr62nK9j5phVwF6nPpe-JEZycC9VfDQLA' ],
        ],
        // VENI - BBtests - monitor
        21 => [
            [ 'venicuabot',         'tgrp_21_00_412676450978972345784385.php',                                                                                                              '328154998:AAHXisYfjjr9Z1n58Y37vO4RuUGrLXJ0f3c' ],
        ],

        /////////////////////////////////
        // T3 - TURN TRASH INTO TREASURES
        /////////////////////////////////

        // T3 - sponsor (sponsor)
        30 => [
            [ 'T3sponsor_bot',      'tgrp_30_00_41267890408723648979068236903477.php',                                                                                                      '271367661:AAGUoheqgx75qFl1biQ-SmEwugYMD7WVZOI' ],
        ],
        // T3 - exchanger (retail)
        31 => [
            [ 'T3exchanger_bot',    'tgrp_31_00_52358754518112182475568554552353.php',                                                                                                      '332067633:AAEdiszdqTaNA5m8A6oGl5akpNyY4chCX2g' ],
        ],
        // T3 - main (reciclador + invitado)
        32 => [
            [ 'T3_bot',             'tgrp_32_00_23545654887545454445198948945755.php',                                                                                                      '242945055:AAGNJXNE-FOsL2JdkZrk053C264Ag3i-JqE' ],
        ],
        // T3 - handler (colector)
        33 => [
            [ 'T3handler_bot',      'tgrp_33_00_23542344556446873414588854256223.php',                                                                                                      '293419680:AAHA0XjEyPcACXdfs8kjiBnDsD8VhHF6OhY' ],
        ],
        // T3 - truck (camion)
        34 => [
            [ 'T3truck_bot',        'tgrp_34_00_86232758263546826577748967444388.php',                                                                                                      '220338994:AAGl4D1EMhafdjTuo0_x-UCnFpBrspdsZuE' ],
        ],
        // T3 - converter (acopio)
        35 => [
            [ 'T3converter_bot',    'tgrp_35_00_34847741157856854873468634446755.php',                                                                                                      '349436605:AAGKBZL05drUAQCH8w-C71kBLgAKdUM2cTs' ],
        ],
        // T3 - partner (fundacion)
        36 => [
            [ 'T3partner_bot',      'tgrp_36_00_67983241008404134656454105743468.php',                                                                                                      '305539707:AAFaU1pp1iJySqQwyJB0ZCxqGoRg2bJ5hJQ' ],
        ],
        // T3 - admin (operador)
        37 => [
            [ 'T3admin_bot',        'tgrp_37_00_22453454575734135348788656685333.php',                                                                                                      '313828017:AAG3v-Aom8NYIAgPezNQIdyv3xQBwqk3ZEU' ],
        ],
        // T3 - edu (education) (UNINPLEMENTED)
      //38 => [
      //    [ 'T3edu_bot',          'tgrp_38_00_88876945664453239857756842336344.php',                                                                                                      '295486770:AAF5y4buCZfpIi4EXzenbLROiXMPbVyaGXg' ],
      //],
        // T3 - monitor (logs + errors + tracing)
        39 => [
            [ 'T3monitor_bot',      'tgrp_39_00_79090954642134137578357745546464.php',                                                                                                      '327127354:AAEpDgwMeGY2-r5inVWL-TcG_Y9EceVOzwE' ],
        ],

        ////////////////
        // BOTS DE JORGE
        ////////////////

        210 => [
            [ 'Copa_bot',           'tgrp_210_00_237245486123689752355454.php',                                                                                                             '356650537:AAFQ2V4dkWd_u90d3Mpj6goaG4crA1vGyf0' ],
        ],
        211 => [
            [ 'TheHandybot',        'tgrp_211_00_345457872345454875754468.php',                                                                                                             '317790698:AAFORJf9RbgKzRaIm4po6IYYuHv9FokksXc' ],
        ],
        212 => [
            [ 'SchedyBot',          'tgrp_212_00_234235634675689867983454.php',                                                                                                             '344270765:AAEVzMA9q_B4fEoO65iohFXnp0gFj0mIYGE' ],
        ],
        249 => [
            [ 'HeadEndBot',         'tgrp_249_00_235556854754345235356856.php',                                                                                                             '333814656:AAHnl0CaAjaYyDvw9jkswMqDUpKFtzFBvlI' ],
        ],

        /////////////////
        // BOTS DE NICOLE
        /////////////////

        260 => [
            [ 'TheBotBox_Bot',      'tgrp_260_00_234346568534655567342367.php',                                                                                                             '349758138:AAEog_6bey4po9VJjpQvMaGH4EPhE-iz5HI' ],
        ],
        261 => [
            [ 'BotlightyearBot',    'tgrp_261_00_233485854734665898553444.php',                                                                                                             '293002363:AAGu_XbCX9en29BLCEPG6L4AKZS5b5jw9Lg' ],
        ],
        262 => [
            [ 'BotstartupsBot',     'tgrp_262_00_236543475679123461478740.php',                                                                                                             '345446126:AAFP0ZlGPyXfM2cU9rwfLsEWivHRDajvqoI' ],
        ],
        299 => [
            [ 'MybotsmonitorBot',   'tgrp_299_00_345868549563486212678545.php',                                                                                                             '353002157:AAFPe_kNxjZ-DGbE4kV_7SQ44KXFc1T_aE8' ],
        ],

        ///////////////
        // BOTS DE LUIS
        ///////////////

        310 => [
            [ 'grocabot',           'tgrp_310_00_345547823345565687545438.php',                                                                                                             '432198335:AAE39s7b08gHV1grEErZpz7M8DP2IJNkbpI' ],
        ],
        311 => [
            [ 'cheverebot',         'tgrp_311_00_123615312846513613684316.php',                                                                                                             '424233639:AAGDBziaURXzZJvE3bN6bQqbdUriydN7kiM' ],
        ],
        312 => [
            [ 'LuxurySkinBot',      'tgrp_312_00_225845554721721874898990.php',                                                                                                             '357186665:AAElZkcfDA9fmJVAhkbywX5Kk6rMZAxmyWo' ],
        ],
        349 => [
            [ 'ServiWebBot',        'tgrp_349_00_124897612675636059626267.php',                                                                                                             '447345564:AAERyPqRcgwAXAWdsoF_yhDC3BVAO1xk6ek' ],
        ],

        /////////////////
        // BOTS DE CARLOS
        /////////////////

        360 => [
            [ 'CoffeExpressbot',    'tgrp_360_00_908698055113687895334533.php',                                                                                                             '412241406:AAHiUYGNJVAUJq0_ebcRML3thZi8JLwxiKo' ],
        ],
        361 => [
            [ 'GymMuscleBot',       'tgrp_361_00_011561095605020755654561.php',                                                                                                             '363854619:AAH238L4CAuPypmQm4TQOHN0qlXosiC2EfM' ],
        ],
        362 => [
            [ 'MusicTreebot',       'tgrp_362_00_346845865156789651348900.php',                                                                                                             '438907663:AAHpkci4tlkeoK11N4CwdQQOY4em3N-I7F4' ],
        ],
        399 => [
            [ 'Monitorexbot',       'tgrp_399_00_670565665480123655668108.php',                                                                                                             '392858945:AAHTbOsNjo1SvTfiYgz0yvBmcsh2P4VGGcM' ],
        ],

        ////////////////
        // BOTS DE GORKA
        ////////////////

        899 => [   // webhook aun no registrado
            [ 'gorkathebot',                'tgrp_899_00_235485656856441568687544.php',                                                                                                             '324536558:AAG1AFVBLhJ11aO-fz54yx6igPcX8A7-y7A' ],
        ],
        800 => [
            [ 'OralocoBot',                 'tgrp_800_00_423457834574335448638710.php',                                                                                                             '292199253:AAGxLSWCZtq5fZ9e2my6Z_ZW3FSujNHman0' ],
        ],
        801 => [
            [ 'BiblioBoqueteBot',           'tgrp_801_00_348676165746768411345688.php',                                                                                                             '382216956:AAGXlJw7hGc_qzl7vnWeyULami1oeTHcTyA' ],
        ],
        820 => [
            [ 'nicolasmadurobot',           'tgrp_820_00_235645654656865434181376.php',                                                                                                             '328230165:AAFUhL8wjwnDDmHhBDvncmCNYeQIiAQR_tY' ],
        ],
        821 => [   // webhook aun no registrado
            [ 'chavezvivebot',              'tgrp_821_00_484068789562371984141339.php',                                                                                                             '297590487:AAGVijc51pUJUv-rp5qTMm8H2uKSOnVvi6A' ],
        ],
        822 => [
            [ 'bot14bot',                   'tgrp_822_00_683455683461791812323128.php',                                                                                                             '383816360:AAF9AYbU65p3lvir52eIEmBazV-pBSpMKeM' ],
        ],
        823 => [   // webhook aun no registrado
            [ 'masburrobot',                'tgrp_823_00_065394504654406578055400.php',                                                                                                             '351948685:AAH9Vg_Fi9RLyVvD8aqtjOA8xtER_BJ2YmE' ],
        ],
        830 => [   // webhook aun no registrado
            [ 'ConstituyenteVenezuelaBot',  'tgrp_830_00_542354553412303858468016.php',                                                                                                             '334418130:AAEBFWacQ6ns5a9yvdtRlmaATlqyXZtMI_Q' ],
        ],

    ];



    ///////////////////////////
    // OTROS PARAMETROS DE BOTS
    ///////////////////////////



    /** @var array Mapa que contiene la lista de nombres de usuario (concatenación de nombre, espacio y apellido) que si son así reportados por
     *             Telegram, son usados para difundir mensajes manejados por T3log::register (según máscara definida estáticamente, inicialmente
     *             sólo T3log::TYPE_BBCODE) de forma adicional al logging en el file system; ver 4to componente de cada entrada en
     *             ChatMedium::$bbBots. El nombre debe ser extraido de Telegram o Telegram web PERO garantizando que el número de teléfono asociado
     *             NO esté en la agenda de Android, pues en este caso el nombre mostrado en esas interfaces será el de Android */
    static private $cmLogBotsTelegram = [
        12  => [ 'Gorka G LLona'  ],
        21  => [ 'Gorka G LLona'  ],
        39  => [ 'Gorka G LLona'  ],
        249 => [ 'Jorge Guerrero' ],
        299 => [ 'Nicole'         ],
        349 => [ 'Luis Alberto'   ],
        399 => [ 'Carlos'         ],
    ];



    /** @var array Mapa cuyos valores <str> identifican arreglos $cmMessages<str> que serán usados por BotBasic para recuperar mensajes;
     *             en caso de no haber una entrada para un determinado bot, se usará el <str> "Base" */
    static private $botsMessagesMap = [
        820 => 'VePol',
        821 => 'VePol',
        822 => 'VePol',
        823 => 'VePol',
        830 => 'VePol',
    ];



    /** @const Indice que especifica que el hint en INPUT's debe mostrarse la primera y sucesivas veces */
    const INPUT_HINT_ALWAYS         = 101;

    /** @const Indice que especifica que el hint en INPUT's no debe mostrarse nunca */
    const INPUT_HINT_NEVER          = 102;

    /** @const Indice que especifica que el hint en INPUT's no debe mostrarse nunca */
    const INPUT_HINT_NOT_FIRST_TIME = 103;

    /** @const Una de las constantes INPUT_HINT..., para cuando no está definida la entrada para un bot en $cmBotsInputBehaviour */
    const INPUT_HINT_DEFAULT        = self::INPUT_HINT_ALWAYS;



    /** @var array Comportamiento de los hints de los INPUT's para cada bot; por defecto será INPUT_HINT_ALWAYS */
    static private $botsInputHintsBehaviour = [
        820 => self::INPUT_HINT_NEVER,
        821 => self::INPUT_HINT_NEVER,
        822 => self::INPUT_HINT_NEVER,
        823 => self::INPUT_HINT_NEVER,
        830 => self::INPUT_HINT_NEVER,
    ];



    /** @var array Arreglo que indica los $bbBots/$cmBots que se consideran anónimos, es decir, donde la identificación de usuario (nombre, ...)
     *             que llega con los Updates no es almacenada en BD */
    static private $anonBots = [
        820,
        821,
        822,
        823,
        830,
    ];



    /** @var string[] locales válidos aceptados por BotBasic; hay textos definidos para estos locales en esta clase y en ChatMedium;
     *                el primer locale es el locale por defecto */
    static private $validLocales = [ 'es', 'en' ];



    /** @const Indice de mensaje autónomo: no se puede crear el ChatMediumChannel */
    const MSG_EXCEPTION_CANT_CREATE_CMC   = 101;

    /** @const Indice de mensaje autónomo: se ha borrado un BotBasicChannel con CHANNEL DELETE */
    const MSG_BBCHANNEL_WAS_DELETED       = 102;

    /** @const Indice de mensaje autónomo: se ha reusado un canal y destinado a un nuevo propósito */
    const MSG_BBCHANNEL_WAS_REUSED_PREFIX = 103;

    /** @const Indice de mensaje autónomo: no se puede actualizar (edit) updates enviados previamente por el usuario */
    const MSG_CANT_EDIT_PREVIUOS_UPDATES  = 104;

    /** @const Indice de mensaje autónomo: no se puede actualizar (edit) updates enviados previamente por el usuario */
    const MSG_CANT_DO_THAT                = 105;

    /** @const Indice de mensaje autónomo: el usuario debe elegir una opción del menú y no ingresar texto */
    const MSG_MUST_CHOOSE_FROM_MENU       = 106;

    /** @const Indice de mensaje autónomo: un define() de debug ha sido fijado para que se de una respuesta generica de bajo nivel a toda interaccion del usuario */
    const MSG_BOT_IS_POSSESSED            = 107;

    /** @const Indice de mensaje autónomo: se debe reintentar la entrada de datos */
    const MSG_PLEASE_REPEAT_ENTRY         = 108;

    /** @const Indice de mensaje autónomo: mensaje por defecto para un MENU al cual no se le han definido títulos o que no tiene PRINTs previos */
    const MSG_DEFAULT_TITLE_FOR_MENU      = 109;

    /** @const Indice de mensaje autónomo (paramétrico): mensaje que se muestra para hacer explícito el funcionamiento de WORD en INPUT's */
    const MSG_TYPE_WORD_FOR_DEFAULT       = 110;

    /** @const Indice de mensaje autónomo (paramétrico): mensaje que se muestra como hint al solicitar un valor con un INPUT (incluye ejemplo) */
    const MSG_HINT_TEMPLATE1_FOR_DATATYPE = 111;

    /** @const Indice de mensaje autónomo (paramétrico): mensaje que se muestra como hint al solicitar un valor con un INPUT (no incluye ejemplo) */
    const MSG_HINT_TEMPLATE2_FOR_DATATYPE = 112;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_DATE          = 113;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_POSINTEGER    = 114;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_POSDECIMAL    = 115;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_STRING        = 116;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_PHONE         = 117;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_EMAIL         = 118;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_INTEGER       = 119;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_DECIMAL       = 120;

    /** @const Indice de mensaje autónomo (dual): descripción de un tipo de dato para INPUT's y ejemplo a mostrarse */
    const MSG_DATATYPE_DATA_ARROBAUSERNAME= 121;



    /** @var array Mapa Base que contiene los textos de los mensajes MSG_..., según locale */
    static private $cmMessagesBase = [
        "es" => [
            self::MSG_EXCEPTION_CANT_CREATE_CMC   => "Lo sentimos, no es posible acceder al sistema en este momento.",
            self::MSG_BBCHANNEL_WAS_DELETED       => "Este canal de comunicación ha sido desactivado.",
            self::MSG_BBCHANNEL_WAS_REUSED_PREFIX => "Este canal de comunicación está dedicado ahora a: ",
            self::MSG_CANT_EDIT_PREVIUOS_UPDATES  => "No está permitido modificar mensajes ya enviados; escríbelos nuevamente.",
            self::MSG_CANT_DO_THAT                => "No se puede efectuar esa operación.",
            self::MSG_MUST_CHOOSE_FROM_MENU       => "Por favor, elige una opción del menú.",
            self::MSG_BOT_IS_POSSESSED            => "Estamos en modo de verificación...",
            self::MSG_PLEASE_REPEAT_ENTRY         => "Por favor, intenta ingresar el dato de nuevo:",
            self::MSG_DEFAULT_TITLE_FOR_MENU      => "Por favor, selecciona:",
            self::MSG_TYPE_WORD_FOR_DEFAULT       => "Escribe '{word}' para aceptar el valor '{fromVarValue}'",
            self::MSG_HINT_TEMPLATE1_FOR_DATATYPE => "Escribe {datatype} (ej. {example}).",
            self::MSG_HINT_TEMPLATE2_FOR_DATATYPE => "Escribe {datatype}.",
            self::MSG_DATATYPE_DATA_DATE          => "una fecha en formato dd-mm-aaaa|31-12-2001",
            self::MSG_DATATYPE_DATA_POSINTEGER    => "un número entero positivo|123",
            self::MSG_DATATYPE_DATA_POSDECIMAL    => "un número decimal positivo|123,45",
            self::MSG_DATATYPE_DATA_STRING        => "cualquier texto|",
            self::MSG_DATATYPE_DATA_PHONE         => "un número de teléfono|6917-4440",
            self::MSG_DATATYPE_DATA_EMAIL         => "una dirección de e-mail|yo@venicua.com",
            self::MSG_DATATYPE_DATA_INTEGER       => "un número entero|123",
            self::MSG_DATATYPE_DATA_DECIMAL       => "un número decimal|123,45",
            self::MSG_DATATYPE_DATA_ARROBAUSERNAME=> "un nombre de usuario|@usuario",
        ],
        "en" => [
            self::MSG_EXCEPTION_CANT_CREATE_CMC   => "Sorry, can't access system at this time.",
            self::MSG_BBCHANNEL_WAS_DELETED       => "This communication channel has been deactivated.",
            self::MSG_BBCHANNEL_WAS_REUSED_PREFIX => "This communication channel is now dedicated to: ",
            self::MSG_CANT_EDIT_PREVIUOS_UPDATES  => "It's not allowed to edit previuos messages. Please write again.",
            self::MSG_CANT_DO_THAT                => "Operation is not allowed.",
            self::MSG_MUST_CHOOSE_FROM_MENU       => "Please, use the menu now.",
            self::MSG_BOT_IS_POSSESSED            => "We are in verification mode...",
            self::MSG_PLEASE_REPEAT_ENTRY         => "Please try to re-enter the value:",
            self::MSG_DEFAULT_TITLE_FOR_MENU      => "Please select:",
            self::MSG_TYPE_WORD_FOR_DEFAULT       => "Type '{word}' in order to accept the value '{fromVarValue}'",
            self::MSG_HINT_TEMPLATE1_FOR_DATATYPE => "Write {datatype} (ex. {example}).",
            self::MSG_HINT_TEMPLATE2_FOR_DATATYPE => "Write {datatype}.",
            self::MSG_DATATYPE_DATA_DATE          => "a date formatted as dd-mm-yyyy|31-12-2001",
            self::MSG_DATATYPE_DATA_POSINTEGER    => "a positive integer number|123",
            self::MSG_DATATYPE_DATA_POSDECIMAL    => "a positive decimal number|123.45",
            self::MSG_DATATYPE_DATA_STRING        => "any text|",
            self::MSG_DATATYPE_DATA_PHONE         => "a phone number|6917-4440",
            self::MSG_DATATYPE_DATA_EMAIL         => "an e-mail address|me@venicua.com",
            self::MSG_DATATYPE_DATA_INTEGER       => "an integer number|123",
            self::MSG_DATATYPE_DATA_DECIMAL       => "an decimal number|123.45",
            self::MSG_DATATYPE_DATA_ARROBAUSERNAME=> "a username|@username",
        ],
    ];



    /** @var array Otro mapa que contiene los textos de los mensajes MSG_..., según locale */
    static private $cmMessagesVePol = [
        "es" => [
            self::MSG_EXCEPTION_CANT_CREATE_CMC   => "El sistema está en mantenimiento actualmente.",
            self::MSG_BBCHANNEL_WAS_DELETED       => "Este chat está inactivo.",
            self::MSG_BBCHANNEL_WAS_REUSED_PREFIX => "Este chat está dedicado ahora a ",
            self::MSG_CANT_EDIT_PREVIUOS_UPDATES  => "No se pueden modificar mensajes anteriores.",
            self::MSG_CANT_DO_THAT                => "Operación inválida.",
            self::MSG_MUST_CHOOSE_FROM_MENU       => "Pulsa sobre un botón...",
            self::MSG_BOT_IS_POSSESSED            => "Modalidad ROOT activada.",
            self::MSG_PLEASE_REPEAT_ENTRY         => "Reintroduce la entrada:",
            self::MSG_DEFAULT_TITLE_FOR_MENU      => "Elige una opción:",
            self::MSG_TYPE_WORD_FOR_DEFAULT       => "Para aceptar \"{fromVarValue}\" ingresa \"{word}\".",
            self::MSG_HINT_TEMPLATE1_FOR_DATATYPE => "Ingresa {datatype}.",
            self::MSG_HINT_TEMPLATE2_FOR_DATATYPE => "Ingresa {datatype}.",
            self::MSG_DATATYPE_DATA_DATE          => "una fecha (día-mes-año)|21-03-1999",
            self::MSG_DATATYPE_DATA_POSINTEGER    => "un número sin decimales|99",
            self::MSG_DATATYPE_DATA_POSDECIMAL    => "un número con o sin decimales|99,12",
            self::MSG_DATATYPE_DATA_STRING        => "texto|",
            self::MSG_DATATYPE_DATA_PHONE         => "un teléfono|1234567",
            self::MSG_DATATYPE_DATA_EMAIL         => "un e-mail|joe@acme.com",
            self::MSG_DATATYPE_DATA_POSINTEGER    => "un número sin decimales|99",
            self::MSG_DATATYPE_DATA_POSDECIMAL    => "un número con o sin decimales|99,12",
            self::MSG_DATATYPE_DATA_ARROBAUSERNAME=> "un usuario|@joe",
        ],
        "en" => [
            self::MSG_EXCEPTION_CANT_CREATE_CMC   => "The system is currently in manteinance mode.",
            self::MSG_BBCHANNEL_WAS_DELETED       => "This chat was deactivated.",
            self::MSG_BBCHANNEL_WAS_REUSED_PREFIX => "This chat is now dedicated to ",
            self::MSG_CANT_EDIT_PREVIUOS_UPDATES  => "Can't modify previous messages.",
            self::MSG_CANT_DO_THAT                => "Invalid operation.",
            self::MSG_MUST_CHOOSE_FROM_MENU       => "Press a button...",
            self::MSG_BOT_IS_POSSESSED            => "ROOT mode active.",
            self::MSG_PLEASE_REPEAT_ENTRY         => "Retry...",
            self::MSG_DEFAULT_TITLE_FOR_MENU      => "Choose an option:",
            self::MSG_TYPE_WORD_FOR_DEFAULT       => "For \"{fromVarValue}\" type \"{word}\".",
            self::MSG_HINT_TEMPLATE1_FOR_DATATYPE => "Write {datatype}.",
            self::MSG_HINT_TEMPLATE2_FOR_DATATYPE => "Write {datatype}.",
            self::MSG_DATATYPE_DATA_DATE          => "a date (day-month-year)|21-03-1999",
            self::MSG_DATATYPE_DATA_POSINTEGER    => "a number without decimals|99",
            self::MSG_DATATYPE_DATA_POSDECIMAL    => "a number with or without decimals|99.12",
            self::MSG_DATATYPE_DATA_STRING        => "text|",
            self::MSG_DATATYPE_DATA_PHONE         => "a phone |1234567",
            self::MSG_DATATYPE_DATA_EMAIL         => "an e-mail|joe@acme.com",
            self::MSG_DATATYPE_DATA_POSINTEGER    => "a number without decimals|99",
            self::MSG_DATATYPE_DATA_POSDECIMAL    => "a number with or without decimals|99.12",
            self::MSG_DATATYPE_DATA_ARROBAUSERNAME=> "a username|@joe",
        ],
    ];



    /** @var array Meses del año, localizados, tanto abreviados como no */
    static private $months = [
        'es' => [
            'ene' => 'enero',
            'feb' => 'febrero',
            'mar' => 'marzo',
            'abr' => 'abril',
            'may' => 'mayo',
            'jun' => 'junio',
            'jul' => 'julio',
            'ago' => 'agosto',
            'sep' => 'septiembre',
            'oct' => 'octubre',
            'nov' => 'noviembre',
            'dic' => 'diciembre',
        ],
        'en' => [
            'jan' => 'january',
            'feb' => 'february',
            'mar' => 'march',
            'apr' => 'april',
            'may' => 'may',
            'jun' => 'june',
            'jul' => 'july',
            'aug' => 'august',
            'sep' => 'september',
            'oct' => 'october',
            'nov' => 'november',
            'dec' => 'december',
        ]
    ];



    ///////////////////////////////////////////////////////////////////////////////
    // BEHAVIOUR - NO HAY DATA DE CONFIGURACION EN ESTA SECCION
    // (si se agrega un datatype nuevo para INPUT ver buildInputHelperForDatatype()
    ///////////////////////////////////////////////////////////////////////////////



    /**
     * Retorna el mapa [ <abreviatura> => <nombre>, ... ] correspondiente a los meses del año para un determinado locale
     *
     * @param  string   $locale     Locale
     * @return array                Uno de los componentes de self::$months
     */
    static public function monthsOfYear ($locale)
    {
        if (! in_array($locale, self::$validLocales)) { $locale = self::$validLocales[0]; }
        return self::$months[$locale];
    }



    /**
     * Retorna una copia del atributo de clase $botsMessagesMap... apropiado para el bot de índice pasado como parámetro
     *
     * @param  int      $bbCode     Una de las claves de $bbBots y $cmBots...
     * @return array
     */
    static private function botMessages ($bbCode)
    {
        $suffix  = isset(self::$botsMessagesMap[$bbCode]) ? self::$botsMessagesMap[$bbCode] : "Base";
        $varName = "cmMessages$suffix";
        if (isset(self::$$varName)) {
            $map = self::$$varName;
        }
        else {
            Log::register(Log::TYPE_RUNTIME, "BC422 MessagesMap no encontrado para botIdx $bbCode");
            $map = self::$cmMessagesBase;
        }
        return $map;
    }



    /**
     * Retorna un mensaje de la VM de BotBasic especificado, pero adaptado al bot y al locale indicados
     *
     * @param  int          $bbCode         Una de las claves de $bbBots y $cmBots...
     * @param  string       $locale         Locale
     * @param  int          $messageIdx     Una de las constantes MSG_...
     * @return string|null                  Mensaje; o null si no se encuentra el $messageIdx en los mapas
     */
    static public function botMessage ($bbCode, $locale, $messageIdx)
    {
        $cmMessages = self::botMessages($bbCode);
        if (! isset($cmMessages[$locale]) || ! isset($cmMessages[$locale][$messageIdx])) { return null; }
        $res = $cmMessages[$locale][$messageIdx];
        return $res;
    }



    /**
     * Construye un texto localizado que refleja el uso de WORD con valores por defecto en INPUTs
     *
     * @param  string   $locale         Locale
     * @param  string   $word           Palabra definida con WORD
     * @param  string   $fromVarValue   Valor que sustituirá a la palabra definida con WORD
     * @param  int      $bbCode         Uno de los índices de $bbBots y $cmBots...
     * @return string                   Texto para mostrar en la chatapp
     */
    static public function buildInputPromptForDefaultValue ($locale, $word, $fromVarValue, $bbCode)
    {
        if (! in_array($locale, self::$validLocales)) { $locale = self::$validLocales[0]; }
        $res = self::botMessage($bbCode, $locale, self::MSG_TYPE_WORD_FOR_DEFAULT);
        $res = str_replace('{word}',         $word,         $res);
        $res = str_replace('{fromVarValue}', $fromVarValue, $res);
        return $res;
    }



    /**
     * Construye un texto localizado que refleja la manera en la que el usuario debe escribir un tipo de dato específico en INPUTs
     *
     * @param  string   $locale         Locale
     * @param  string   $dataType       Tipo de dato, uno de 'date', 'positiveInteger', 'positiveDecimal', 'string'
     * @param  int      $bbCode         Uno de los índices de $bbBots y $cmBots...
     * @param  bool     $aNewInput      Indica si el INPUT es nuevo o se trata de un re-display de fase 2 (por tipo de dato incorrecto)
     * @return string                   Texto para mostrar en la chatapp
     */
    static public function buildInputHelperForDatatype ($locale, $dataType, $bbCode, $aNewInput = true)
    {
        $map = [
            'date'            => self::MSG_DATATYPE_DATA_DATE,
            'positiveInteger' => self::MSG_DATATYPE_DATA_POSINTEGER,
            'positiveDecimal' => self::MSG_DATATYPE_DATA_POSDECIMAL,
            'string'          => self::MSG_DATATYPE_DATA_STRING,
            'phone'           => self::MSG_DATATYPE_DATA_PHONE,
            'email'           => self::MSG_DATATYPE_DATA_EMAIL,
            'integer'         => self::MSG_DATATYPE_DATA_INTEGER,
            'decimal'         => self::MSG_DATATYPE_DATA_DECIMAL,
            'arrobaUsername'  => self::MSG_DATATYPE_DATA_ARROBAUSERNAME,
        ];
        if (! isset($map[$dataType])) {
            Log::register(Log::TYPE_RUNTIME, "BC560 Datatype $dataType no soportado");
            return null;
        }
        $behaviour = isset(self::$botsInputHintsBehaviour[$bbCode]) ? self::$botsInputHintsBehaviour[$bbCode] : self::INPUT_HINT_DEFAULT;
        if ($behaviour == self::INPUT_HINT_NEVER || $behaviour == self::INPUT_HINT_NOT_FIRST_TIME && $aNewInput) {
            return null;
        }
        if (! in_array($locale, self::$validLocales)) { $locale = self::$validLocales[0]; }
        $templateMsgIdx = BOTBASIC_INPUT_HINTS_WITH_EXAMPLES ? self::MSG_HINT_TEMPLATE1_FOR_DATATYPE : self::MSG_HINT_TEMPLATE2_FOR_DATATYPE;
        $res            = self::botMessage($bbCode, $locale, $templateMsgIdx);
        $pair           = self::botMessage($bbCode, $locale, $map[$dataType]);
        list ($datatype, $example) = explode('|', $pair);
        $res = str_replace('{datatype}', $datatype, $res);
        $res = str_replace('{example}',  $example,  $res);
        return $res;
    }



    /**
     * Indica si el bot de índice pasado como parámetro es anónimo, es decir, si la información que identifica a los usuarios en los Updates
     * (nombre, ...) es descartada antes de que el Update sea almacenado en BD
     *
     * @param  int      $bbCode     Una de las claves de $bbBots y $cmBots...
     * @return bool
     */
    static public function cmBotIsAnonymous ($bbCode)
    {
        return in_array($bbCode, self::$anonBots);
    }



    /**
     * Retorna el arreglo $bbBots
     *
     * @return array
     */
    static public function bbBots ()
    {
        return self::$bbBots;
    }



    /**
     * Retorna un atributo de clase de esta clase, cuyo nombre es la concatenación del prefijo indicado y del nombre de la chatapp
     * correspondiente al ChatMedium indicado
     *
     * @param  string   $prefix     Prefijo del atributo de clase
     * @param  int      $cmType     Una de las constantes ChatMedium::TYPE_...
     * @return mixed                El valor del atributo
     */
    static private function selfAttribByCMtype ($prefix, $cmType)
    {
        $suffix = ChatMedium::typeString($cmType);
        if ($suffix === null) { return null; }
        $varName = "$prefix$suffix";
        if (! isset(self::$$varName)) { return null; }
        $res = self::$$varName;
        return $res;
    }



    /**
     * Retorna el arreglo $cmBotsTelegram si el ChatMedium indicado es Telegram, y así para otros ChatMedia
     *
     * @param  int      $cmType     Una de las constantes ChatMedium::TYPE_...
     * @return array
     */
    static public function cmBots ($cmType)
    {
        return self::selfAttribByCMtype("cmBots", $cmType);
    }



    /**
     * Retorna el arreglo $cmLogBotsTelegram si el ChatMedium indicado es Telegram, y así para otros ChatMedia
     *
     * (No tiene sentido aplicarlo a otros ChatMedia porque el diseño actual de BotBasic soporta botlogs sólo en Telegram).
     *
     * @param  int      $cmType     Una de las constantes ChatMedium::TYPE_...
     * @return array
     */
    static public function cmLogBots ($cmType)
    {
        return self::selfAttribByCMtype("cmLogBots", $cmType);
    }



    /**
     * Retorna un arreglo con los locales válidos de BotBasic
     *
     * @return string[]
     */
    static public function validLocales ()
    {
        return self::$validLocales;
    }



    /**
     * IDE spoofer
     */
    private function IDEspoofer ()
    {
        $this->IDEspoofer ();
        self::$cmMessagesVePol = self::$cmLogBotsTelegram = self::$cmBotsTelegram = self::$cmBotsWebStub = self::$cmBotsCliStub = null;
    }



}
