<?php
/**
 * Utilidad CLI para simular peticiones provenientes de un chatapp y mostrar su respuesta en un archivo
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



include __DIR__ . "/../../botbasic/bbautoloader.php";

use \botbasic\WebRouterCliStub;

$die = function ($msg) { fwrite(STDERR, $msg); exit(1); };

if (php_sapi_name() !== 'cli') { die("this script can only be invoked from the CLI\n"); }

// validate input options
$options = getopt("b:i:c:u:m:t:");
if ($options === false || ! isset($options['b']) || ! isset($options['i']) || ! isset($options['c']) || ! isset($options['u'])) {
    $die("Usage: stub_cli.php -b<botCode> -i<seqId> -c<chatId> -u<userId> [ -m<menuhook> ] [ -t<text-of-the-message> ]\n");
}
if (! isset($options['m']) && ! isset($options['t'])) {
    $die("Either -m or -t options should be specified.\n");
}

// simulate a $_POST as global variable (see WebRouterDummy class)
$_post = [
    'id'       => $options['i'],
    'chatId'   => $options['c'],
    'userId'   => $options['u'],
    'menuhook' => isset($options['m']) ? $options['m'] : null,
    'text'     => isset($options['t']) ? $options['t'] : null,
];

$wr = new WebRouterCliStub($options['b']);
$res = $wr->run();
if ($res === null) {
    $die("Can't run() because couldn't locate cmAuthInfo (bad params)\n");
}

exit(0);
