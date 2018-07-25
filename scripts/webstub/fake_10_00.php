<?php
/**
 * Enrutador de peticiones provenientes de un formulario web; permite probar BotBasic sin conectarse con un chatapp
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



include "../../botbasic/bbautoloader.php";

use \botbasic\WebRouterWebStub;
use \botbasic\Log;

$die = function ($msg) { fwrite(STDERR, $msg); exit(1); };

if (php_sapi_name() === 'cli') { $die("this script can only be invoked from the web server"); }

$wr  = new WebRouterWebStub();
$res = $wr->run();
if ($res === null) {
    // TODO log this: can't start because couldn't find cmAuthInfo (cmBotName) based on scriptName
}

// go back and preserve form content (better than webform reload)
?>

<html><head>
<script language="Javascript" type="text/javascript">
    history.back();
</script>
</head></html>
