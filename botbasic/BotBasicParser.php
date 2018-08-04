<?php
/**
 * Parser de BotBasic
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;

include_once "bbautoloader.php";



/**
 * Clase BotBasicParser
 *
 * Parser de BotBasic. Un resultado exitoso del parsing se guarda en base de datos en la tabla bbcode.
 *
 * @package botbasic
 */
class BotBasicParser extends BotBasic
{



    //////////////////////////////
    // DEFINITIONS AND CONSTRUCTOR
    //////////////////////////////



    /** @var null|string Nombre del programa BotBasic (ej. "neuropower") */
    protected $codename    = null;

    /** @var null|string Versión del programa BotBasic, tal como está especificada en el primer encabezado del archivo que lo contiene */
    protected $codeVersion = null;

    /** @var null|array Contenedor de todas las líneas del programa BotBasic asociadas a cada sección */
    private $sections     = null;

    /** @var null|array Contenedor de todos los errores de parsing */
    private $errors       = null;

    /** @var string Contexto al cual se asocia cada error agregado con addError(); corresponde a una clave de $errors */
    private $errorContext = "no_context";

    /** @var array Contenedor de los errores propios de la implementación del lenguaje BotBasic (no del código BotBasic de la app) */
    private $bbErrors     = [];



    /**
     * Retorna la versión de BotBasic implementada por este parser
     *
     * @return string
     */
    public function getBBversion ()
    {
        return BOTBASIC_LANG_VERSION;
    }



    /**
     * Retorna el codename del programa BotBasic que se está parseando
     *
     * @return null|string
     */
    public function getCodename ()
    {
        return $this->codename;
    }



    /**
     * Retorna la versión completa del programa BotBasic que se está parseando
     *
     * @return null|string
     */
    public function getCodeVersion ()
    {
        return $this->codeVersion;
    }



    /**
     * Retorna la versión mayor del programa BotBasic que se está parseando
     *
     * @return string
     */
    public function getMajorCodeVersion ()
    {
        return self::getMajorCodeVersionFor($this->codeVersion);
    }



    /**
     * Retorna al versión menor del programa BotBasic que se está parseando
     *
     * @return string
     */
    public function getMinorCodeVersion ()
    {
        return self::getMinorCodeVersionFor($this->codeVersion);
    }



    /**
     * Retorna la versión submenor del programa BotBasic que se está parseando
     *
     * @return string
     */
    public function getSubminorCodeVersion ()
    {
        return self::getSubminorCodeVersionFor($this->codeVersion);
    }



    protected function __construct ($codename)
    {
        parent::__construct();
        $this->codename = $codename;
    }



    /**
     * Factory method
     *
     * @param  string           $codename   Codename del programa BotBasic; es la única información del programa que no está contenida en el archivo del programa
     * @return BotBasicParser               Instancia creada
     */
    static public function create ($codename)
    {
        return new BotBasicParser($codename);
    }



    protected function evalLogicPredicate ($directive, &$args, $lineno, $bot)
    {
        // do nothing
        return false;
    }



    ////////////
    // UTILITIES
    ////////////



    /**
     * Divide una fila leida del archivo CSV que contiene al programa BotBasic en celdas, limpiando las comillas de las celdas entrecomilladas
     * y sólo evitando hacer trim() cuando la celda sólo contiene espacios; reemplaza también los pares de comillas dobles por una sola comillas dobles
     *
     * @param  string   $line           Una línea del archivo CSV, separada por tabuladores
     * @param  int      $fillUntilSize  Tamaño mínimo garantizado del resultado (llenado a la derecha con ''), siempre que sea pasada
     * @return string[]                 Componentes de la línea (celdas)
     */
    private function splitLine ($line, $fillUntilSize = null)
    {
        $cells = preg_split('/\t/', $line);
        for ($i = 0; $i < count($cells); $i++) {
            if (1 === preg_match('/^".*"$/', $cells[$i]))          { $cells[$i] = substr($cells[$i], 1, strlen($cells[$i]) - 2); }
            if ($cells[$i] != str_repeat(' ', strlen($cells[$i]))) { $cells[$i] = trim($cells[$i]);                              }
            $cells[$i] = str_replace('""', '"', $cells[$i]);
        }
        if ($fillUntilSize !== null) {
            for ($i = count($cells); $i < $fillUntilSize; $i++) { $cells[] = ''; }
        }
        return $cells;
        // old code (doesn't preserve only-spaces cells)
        //$cells = preg_split('/ *\t */', trim($line, self::SEP));
        //for ($i = 0; $i < count($cells); $i++) {
        //    $what = $cells[$i];
        //    if (1 === preg_match('/^".*"$/', $what)) { $cells[$i] = substr($what, 1, strlen($what)-2); }
        //}
        //return $cells;
    }



    ///////////////////
    // ERROR MANAGEMENT
    ///////////////////



    /**
     * Retorna los errores de parseo acumulados
     *
     * @return array
     */
    public function errors ()
    {
        return $this->errors;
    }



    /**
     * Fija un "contexto" para el registro de los sucesivos errores de parseo
     *
     * Los contextos se derivan de las secciones del programa BotBasic.
     *
     * @param string    $context    Uno de: 'header', 'messages', 'menus', 'magicvars', 'primitives', 'program'
     */
    private function setErrorContext ($context)
    {
        if (! in_array($context, array_keys($this->errors))) { $context = "no_context"; }
        $this->errorContext = $context;
    }



    protected function addError ($lineno, $message, $isBBerror = false)
    {
        if ($isBBerror) { $where =& $this->bbErrors; } else { $where =& $this->errors[$this->errorContext]; }
        $message = $this->errorContext . " [" . $lineno . "]: $message";
        if (! in_array($message, $where)) { $where[] = $message; }
    }



    /////////
    // PARSER
    /////////



    /**
     * Implementa el parsing de un programa BotBasic, cuyo contenido es pasado como argumento una vez que se extrae del archivo CSV que lo contiene
     *
     * Esta rutina extrae las líneas del programa correspondientes a cada sección del archivo y llama a los métodos que procesan cada sección.
     *
     * @param  string   $text   Contenido del programa
     * @return bool             Indicación de si el parsing tuvo éxito o no
     */
    public function parse ($text)
    {
        $ok = true;

        // preparar estructuras de datos generales
        $this->sections = [ 'header' => [], 'messages' => [], 'menus' => [], 'magicvars' => [], 'primitives' => [], 'program' => [] ];
        $this->errors = $this->sections;
        $this->errors['no_context'] = [];

        // segmentar por secciones de la definicion del bot
        // header, messages, menus, magicvars, primitives, program
        $lines = preg_split('/\t*(\n|\r\n)/', "\n$text");   // incluir una linea en blanco para que la numeracion empiece desde uno como en la hoja de calculo
        $context = null;
        for ($lineno = 1; $lineno < count($lines); $lineno++)
        {
            $line = $lines[$lineno];
            $cells = $this->splitLine($line);
            if (join('', $cells) == '') { continue; }
            $firstWord = $this->splitCell($cells[0])[0];
            $isContent = false;
            switch ($firstWord) {
                case 'BOTBASIC'     : $context = 'header';     break;
                case 'MESSAGES'     : $context = 'messages';   break;
                case 'MENUS'        : $context = 'menus';      break;
                case 'MAGICVARS'    : $context = 'magicvars';  break;
                case 'PRIMITIVES'   : $context = 'primitives'; break;
                case 'PROGRAM'      : $context = 'program';    break;
                default             : $isContent = true;
            }
            if ($isContent) {
                if ($context !== null) { $this->sections[$context][] = [ $lineno, $line ];                    }
                else                   { $this->addError($lineno, "out-of-context line: $line"); $ok = false; }
            }
        }
        if (BOTBASIC_DEBUG) {} // { $this->showIntermediateStructures(); }

        // verificar correctitud de BizModelAdapter (la existencia de metodos mv_, pr_ y mn_ es verificada en las llamadas de abajo)
        if (! is_subclass_of('\botbasic\BizModelAdapter', '\botbasic\BizModelAdapterTemplate')) {
            $this->addError(-1, "BizModelAdapter PHP class must extend BizModelAdapterTemplate");
            $ok &= false;
        }

        // procesar cada seccion
        $ok &= $this->parseHeader();
        $ok &= $this->parseMagicVars();
        $ok &= $this->parsePrimitives();
        $ok &= $this->parseMenus();
        $ok &= $this->parseMessages();
        $ok &= $this->parseProgram();

        // retornar true solo si no hay errores
        return ! $ok ? false : true;
    }



    /**
     * Parsea el header del programa BotBasic (primera sección)
     *
     * @return bool     Si el parsing fue o no exitoso
     */
    private function parseHeader ()
    {
        $this->setErrorContext('header');
        $lines =& $this->sections['header'];
        if (count($lines) != 3) { $this->addError(-1, "header section must have 3 lines (MAGIC, VERSION, BOTS)"); return false; }

        for ($k = 0; $k < count($lines); $k++) {
            list ($lineno, $text) = $lines[$k];
            $parts = $this->splitLine($text);
            if (count($parts) < 2) { continue; }
            list ($directive, $args) = $parts;
            switch ($directive) {
                case 'MAGIC' :
                    list ($langName, $langVersion) = $this->splitCell($args);
                    if ($langName !== BOTBASIC_LANG_NAME)        { $this->addError($lineno, "This parser only works with " . BOTBASIC_LANG_NAME); return false; }
                    if ($langVersion !== BOTBASIC_LANG_VERSION)  { $this->addError($lineno, BOTBASIC_LANG_NAME  . " parser version mismatch (" . BOTBASIC_LANG_VERSION . " expected; $langVersion found)"); return false; }
                    break;
                case 'VERSION' :
                    if (! $this->isCodeVersion($args)) { $this->addError($lineno, "Invalid code version specification: $args"); }
                    $this->codeVersion = $args;
                    break;
                case 'BOTS' :
                    $bots = $this->splitCell($args);
                    for ($i = 0; $i < count($bots); $i++) {
                        $bot = $bots[$i];
                        if (! $this->isLowercase($bot)) { $this->addError($lineno, "Bad bot name: $bot"); }
                        else                            { $this->bots[$bot] = []; }
                    }
                    break;
                default:
                    $this->addError($lineno, "Bad directive: $directive");
            }
        }
        return count($this->errors[$this->errorContext]) == 0;
    }



    /**
     * Parsea los mensajes (variables predefinidas localizadas) del programa BotBasic (segunda sección)
     *
     * @return bool     Si el parsing fue o no exitoso
     */
    private function parseMessages ()
    {
        $this->setErrorContext('messages');
        $lines =& $this->sections['messages'];
        if (count($lines) == 0) { $this->addError(-1, "messages declaration line (with locales) not found"); return false; }

        // subheader
        list ($lineno, $text) = $lines[0];
        $tokens = $this->splitLine($text, 3);
        if ($tokens[0] != 'name') { $this->addError($lineno, "bad locales specification line ('name' expected)");          return false; }
        if ($tokens[1] != '')     { $this->addError($lineno, "bad locales specification line (second cell is not empty)"); return false; }
        if ($tokens[2] == '')     { $this->addError($lineno, "bad locales specification line (third cell is empty)");      return false; }
        $columns = [];
        for ($i = 2; $i < count($tokens); $i++) {
            $locale = $tokens[$i];
            if (! $this->isLocale($locale)) { $this->addError($lineno, "bad locale: $locale"); }
            $columns[$i] = $locale;
            $this->messages[$locale] = [];
        }

        // messages
        for ($k = 1; $k < count($lines); $k++) {
            list ($lineno, $text) = $lines[$k];
            $parts = $this->splitLine($text, 2);
            $name = $parts[0];
            foreach (array_keys($this->messages) as $locale) {
                if (in_array($name, array_keys($this->messages[$locale]))) { $this->addError($lineno, "repeated name for locale $locale: $name");  }
            }
            if (! $this->isLowercase($name))         { $this->addError($lineno, "bad message name: $name");  }
            if (isset($parts[1]) && $parts[1] != '') { $this->addError($lineno, "second cell is not empty"); }
            for ($i = 2; $i < count($parts); $i++) {
                $locale = isset($columns[$i]) ? $columns[$i] : null;
                if (! $this->isLocale($locale)) { continue; }
                $spec = $parts[$i];
                if (($res = $this->isMessageSpec($spec)) !== true) { $this->addError($lineno, "bad message specification ($res) for locale '$locale': $spec"); }
                $spec = str_replace("\\n", "\n", $spec);
                $this->messages[$locale][$name] = $spec;
            }
        }

        return count($this->errors[$this->errorContext]) == 0;
    }



    /**
     * Parsea los menús del programa BotBasic (tercera sección)
     *
     * Incluye la verificación de la cardinalidad de los argumentos IN/OUT de los menús predefinidos y la existencia de los métodos correspondientes
     * en BizModelAdapter.
     *
     * @return bool     Si el parsing fue o no exitoso
     */
    private function parseMenus ()
    {
        $this->setErrorContext('menus');
        $lines =& $this->sections['menus'];
        if (count($lines) == 0) { $this->addError(-1, "menus declaration line not found"); return false; }

        // subheader
        list ($lineno, $text) = $lines[0];
        $tokens = $this->splitLine($text, 7);
        if ($tokens[0] != 'name')          { $this->addError($lineno, "bad menus declaration line ('name' expected)");                    return false; }
        if ($tokens[1] != '')              { $this->addError($lineno, "bad menus declaration line (second cell is not empty)");           return false; }
        if ($tokens[2] != 'description')   { $this->addError($lineno, "bad menus declaration line (third cell should be 'description')"); return false; }
        if ($tokens[3] != '')              { $this->addError($lineno, "bad menus declaration line (fourth cell is not empty)");           return false; }
        if ($tokens[4] != 'in')            { $this->addError($lineno, "bad menus declaration line (fifth cell should be 'in')");          return false; }
        if ($tokens[5] != 'out')           { $this->addError($lineno, "bad menus declaration line (sixth cell should be 'out')");         return false; }

        // menus
        for ($k = 1; $k < count($lines); $k++) {
            list ($lineno, $text) = $lines[$k];
            list ($name, , , , $inVars, $outVars) = $this->splitLine($text, 7);
            if (! $this->isCapitalized($name)) { $this->addError($lineno, "bad menu name $name"); }
            if ($inVars  == '--') { $inVars  = ''; }
            if ($outVars == '--') { $outVars = ''; }
            $inDefs = $outDefs = [];
            $ins = $this->splitCell($inVars, false);
            for ($i = 0; $i < count($ins); $i++) {
                $res = $this->isMenuOrPrimitiveInOutVar($ins[$i]);
                if ($res === false) { $this->addError($lineno, "bad input variable name {$ins[$i]} for menu $name"); $res = self::TYPE_VOID; }
                $inDefs[] = $res;   // type
            }
            $outs = $this->splitCell($outVars, false);
            for ($i = 0; $i < count($outs); $i++) {
                $res = $this->isMenuOrPrimitiveInOutVar($outs[$i]);
                if ($res === false) { $this->addError($lineno, "bad output variable name {$outs[$i]} for menu $name"); $res = self::TYPE_VOID; }
                $outDefs[] = $res;   // type
            }
            $method = self::MENUS_PHPACCESSOR_PREFIX . $name;
            if (! method_exists('\botbasic\BizModelAdapter', $method)) { $this->addError($lineno, "method name $method for predefined menu $name was not found in BizModelAdapter class"); }
            $this->predefmenus[$name] = [ $inDefs, $outDefs ];
        }

        return count($this->errors[$this->errorContext]) == 0;
    }



    /**
     * Parsea las variables mágicas del programa BotBasic (cuarta sección)
     *
     * Incluye la verificación de la existencia de los métodos correspondientes en BizModelAdapter.
     *
     * @return bool     Si el parsing fue o no exitoso
     */
    private function parseMagicVars ()
    {
        $this->setErrorContext('magicvars');
        $lines =& $this->sections['magicvars'];
        if (count($lines) == 0) { $this->addError(-1, "magic variables declaration line not found"); return false; }

        // subheader
        list ($lineno, $text) = $lines[0];
        $tokens = $this->splitLine($text, 3);
        if ($tokens[0] != 'name')         { $this->addError($lineno, "bad magic variables declaration line ('name' expected)");                    return false; }
        if ($tokens[1] != '')             { $this->addError($lineno, "bad magic variables declaration line (second cell should be empty)");        return false; }
        if ($tokens[2] != 'description')  { $this->addError($lineno, "bad magic variables declaration line (third cell should be 'description')"); return false; }

        // magic variables
        for ($k = 1; $k < count($lines); $k++) {
            list ($lineno, $text) = $lines[$k];
            list ($name) = $this->splitLine($text, 3);
            if (! $this->isLowercase($name)) { $this->addError($lineno, "bad magic variable name $name"); }
            $methods = [ self::MAGICVARS_PHPACCESSOR_PREFIX . $name . self::MAGICVARS_PHPACCESSOR_POSTFIX_GET,
                         self::MAGICVARS_PHPACCESSOR_PREFIX . $name . self::MAGICVARS_PHPACCESSOR_POSTFIX_SET ];
            foreach ($methods as $method) {
                if (! method_exists('\botbasic\BizModelAdapter', $method)) { $this->addError($lineno, "method name $method for magic var $name was not found in BizModelAdapter class"); }
            }
            $this->magicvars[] = $name;
        }

        return count($this->errors[$this->errorContext]) == 0;
    }



    /**
     * Parsea las primitivas del programa BotBasic (quinta sección)
     *
     * Incluye la verificación de la cardinalidad de los argumentos IN/OUT de las primitivas y la existencia de los métodos correspondientes
     * en BizModelAdapter
     *
     * @return bool     Si el parsing fue o no exitoso
     */
    private function parsePrimitives ()
    {
        $this->setErrorContext('primitives');
        $lines =& $this->sections['primitives'];
        if (count($lines) == 0) { $this->addError(-1, "primitives declaration line not found"); return false; }

        // subheader
        list ($lineno, $text) = $lines[0];
        $tokens = $this->splitLine($text, 7);
        if ($tokens[0] != 'name')          { $this->addError($lineno, "bad primitives declaration line ('name' expected)");                    return false; }
        if ($tokens[1] != '')              { $this->addError($lineno, "bad primitives declaration line (second cell is not empty)");           return false; }
        if ($tokens[2] != 'description')   { $this->addError($lineno, "bad primitives declaration line (third cell should be 'description')"); return false; }
        if ($tokens[3] != '')              { $this->addError($lineno, "bad primitives declaration line (fourth cell is not empty)");           return false; }
        if ($tokens[4] != 'in')            { $this->addError($lineno, "bad primitives declaration line (fifth cell should be 'in')");          return false; }
        if ($tokens[5] != 'out')           { $this->addError($lineno, "bad primitives declaration line (sixth cell should be 'out')");         return false; }

        // primitives
        for ($k = 1; $k < count($lines); $k++) {
            list ($lineno, $text) = $lines[$k];
            list ($name, , , , $inVars, $outVars) = $this->splitLine($text, 7);
            if (! $this->isCapitalized($name)) { $this->addError($lineno, "bad primitive name $name"); }
            if ($inVars == '' || $outVars == '') { $this->addError($lineno, "bad in/out parameter specification for primitive $name"); }
            if ($inVars  == '--') { $inVars  = ''; }
            if ($outVars == '--') { $outVars = ''; }
            $inDefs = $outDefs = [];
            $ins = $this->splitCell($inVars, false);
            for ($i = 0; $i < count($ins); $i++) {
                $res = $this->isMenuOrPrimitiveInOutVar($ins[$i]);
                if ($res === false) { $this->addError($lineno, "bad input variable {$ins[$i]} for primitive $name"); $res = self::TYPE_VOID; }
                $inDefs[] = $res;   // type
            }
            $outs = $this->splitCell($outVars, false);
            for ($i = 0; $i < count($outs); $i++) {
                $res = $this->isMenuOrPrimitiveInOutVar($outs[$i]);
                if ($res === false) { $this->addError($lineno, "bad output variable {$outs[$i]} for primitive $name"); $res = self::TYPE_VOID; }
                $outDefs[] = $res;   // type
            }
            $method = self::PRIMITIVES_PHPACCESSOR_PREFIX . $name;
            if (! method_exists('\botbasic\BizModelAdapter', $method)) { $this->addError($lineno, "method name $method for primitive $name was not found in BizModelAdapter class"); }
            $this->primitives[$name] = [ $inDefs, $outDefs ];
        }

        return count($this->errors[$this->errorContext]) == 0;
    }



    /**
     * Parsea el código BotBasic del programa BotBasic (sexta sección)
     *
     * Esta rutina invoca, por medio de processSymbol(), a los métodos parser4...() definidos para cada sentencia.
     *
     * Al final del procesamiento se efectúa la conversión de labels a números de línea en la estructura de datos que representa al código.
     *
     * @return bool     Si el parsing fue o no exitoso
     */
    private function parseProgram ()
    {
        $this->setErrorContext('program');
        $lines =& $this->sections['program'];
        if (count($lines) == 0) { $this->addError(-1, "program declaration line not found"); return false; }

        // subheader
        list ($lineno, $text) = $lines[0];
        $ttokens = $this->splitLine($text);
        $botsPerColum = [];
        for ($c = 0; $c < count($ttokens); $c += 4) {
            if (! isset($ttokens[$c+1])) { $ttokens[] = ''; }
            if (! isset($ttokens[$c+2])) { $ttokens[] = ''; }
            if (! isset($ttokens[$c+3])) { $ttokens[] = ''; }
            $bot = $ttokens[$c];
            if (! isset($this->bots[$bot]))                       {
                $this->addError($lineno, "found an undeclared bot $bot");                                    return false;
            }
            if ($bot != $ttokens[$c+1] || $bot != $ttokens[$c+2]) { $this->addError($lineno, "non-consistent bot name declaration $bot");                        return false; }
            if ($ttokens[$c+3] != '')                             { $this->addError($lineno, "bad program declaration line (cell " . ($c+4) . " is not empty)"); return false; }
            $botsPerColum[$c] = $bot;
            $this->bots[$bot]['startColumn']  = $c;
            $this->bots[$bot]['commonHooks']  = [];
            $this->bots[$bot]['regexpHooks']  = [];
            $this->bots[$bot]['eventHooks']   = [];
            $this->bots[$bot]['specialHooks'] = [ 'entry' => null, 'menu' => null, 'input' => null, 'event' => null ];
            $this->bots[$bot]['labels']       = [];
            $this->bots[$bot]['sentences']    = [];
        }
        $allBotsHaveCode = true;
        foreach ($this->bots as $bot => $botdefs) {
            if (! isset($botdefs['startColumn'])) { $this->addError($lineno, "no code found for bot $bot"); $allBotsHaveCode = false; }
        }
        if (! $allBotsHaveCode) { return false; }

        // segmentar las lineas de codigo en columnas y procesar on-the-fly para cada bot
        for ($k = 1; $k < count($lines); $k++) {
            list ($lineno, $text) = $lines[$k];
            $ttokens = $this->splitLine($text);
            for ($c = 0; $c < count($ttokens); $c += 4) {
                if (! isset($ttokens[$c+1])) { $ttokens[] = ''; }
                if (! isset($ttokens[$c+2])) { $ttokens[] = ''; }
                if (! isset($ttokens[$c+3])) { $ttokens[] = ''; }
                list ($hooks, $label, $sentence) = [ $ttokens[$c], $ttokens[$c+1], $ttokens[$c+2] ];
                $bot = $botsPerColum[$c];

                // hook
                $hooks = $this->splitCell($hooks, false);
                foreach ($hooks as $hook) {
                    // special hooks
                    if ($hook == self::BBCODEHOOK_ENTRY) {
                        if ($this->bots[$bot]['specialHooks']['entry'] !== null) { $this->addError($lineno, "$bot: duplicate " . self::BBCODEHOOK_ENTRY); }
                        $this->bots[$bot]['specialHooks']['entry'] = $lineno;
                    }
                    elseif ($hook == self::BBCODEHOOK_MENU) {
                        if ($this->bots[$bot]['specialHooks']['menu'] !== null) { $this->addError($lineno, "$bot: duplicate " . self::BBCODEHOOK_MENU); }
                        $this->bots[$bot]['specialHooks']['menu'] = $lineno;
                    }
                    elseif ($hook == self::BBCODEHOOK_INPUT) {
                        if ($this->bots[$bot]['specialHooks']['input'] !== null) { $this->addError($lineno, "$bot: duplicate " . self::BBCODEHOOK_INPUT); }
                        $this->bots[$bot]['specialHooks']['input'] = $lineno;
                    }
                    elseif ($hook == self::BBCODEHOOK_EVENT) {
                        if ($this->bots[$bot]['specialHooks']['event'] !== null) { $this->addError($lineno, "$bot: duplicate " . self::BBCODEHOOK_EVENT); }
                        $this->bots[$bot]['specialHooks']['event'] = $lineno;
                    }
                    // event hooks
                    elseif (1 == preg_match('/^\$/', $hook)) {
                        if (! $this->isLowercase($hook)) { $this->addError($lineno, "$bot: malformed event hook $hook"); continue; }
                        $this->bots[$bot]['eventHooks'][$hook] = $lineno;
                    }
                    // regexp hooks
                    elseif (1 == preg_match('/^\/.*\/$/', $hook)) {
                        $valid = (@preg_match($hook, '') !== false);
                        if (! $valid) { $this->addError($lineno, "$bot: malformed regexp hook $hook"); continue; }
                        $this->bots[$bot]['regexpHooks'][$hook] = $lineno;
                    }
                    // common hooks including "command" hooks (/abcd)
                    else {
                        if (! ($this->isLowercase($hook) || $this->isCommand($hook))) { $this->addError($lineno, "$bot: malformed common hook $hook"); continue; }
                        if (isset($this->bots[$bot]['commonHooks'][$hook]))           { $this->addError($lineno, "$bot: duplicate common hook $hook"); continue; }
                        $this->bots[$bot]['commonHooks'][$hook] = $lineno;
                    }
                }

                // label
                $labels = $this->splitCell($label, false);
                if (count($labels) > 1) { $this->addError($lineno, "$bot: more than one label declared"); continue; }
                if ($label != '') {
                    if (! $this->isLowercase($label)) { $this->addError($lineno, "$bot: malformed label $label"); continue; }
                    if (isset($this->bots[$bot]['labels'][$label])) {
                        $this->addError($lineno, "$bot: duplicate label: " . $label);
                    }
                    else {
                        $this->bots[$bot]['labels'][$label] = $lineno;
                    }
                }

                // sentence
                $res = $this->processSymbol($lineno, $bot, 'root', $sentence);
                if ($res === false) {
                    $this->addError($lineno, "$bot: malformed statement: " . $sentence);
                }
                $this->bots[$bot]['sentences'][$lineno] = $res;
            }
        }

        // convertir labels en directivas GOTO, GOSUB, OPTION GOTO y OPTION GOSUB a los respectivos numeros de linea
        // convertir TOKs en TIDs (directivas de cada sentence)
        foreach (array_keys($this->bots) as $bot) {
            if ($this->runWith($bot, false, "convertLabelsToLinenos") === false) {
                $this->addError($lineno, "$bot: at least one bad label was used in an GOTO, GOSUB, OPTION GOTO or OPTION GOSUB statement");
            }
        }

        // return true if no parsing errors happened
        return count($this->errors[$this->errorContext]) == 0;
    }



    /**
     * "$processor" de parent::runWith() que convierte labels a números de línea en la representación del código del programa BotBasic
     *
     * @param  string   $bot                Nombre del bot de BotBasic
     * @param  int      $lineno             Número de línea asociada a $parsedContent
     * @param  array    $parsedContent      Contenido (ya procesado por los parser4...()) de una línea del código BotBasic
     * @return bool                         false si un GOTO, GOSUB u OPTION incluyen un label no declarado en la columna correspondiente del código;
     *                                      true en caso de éxito
     */
    public function convertLabelsToLinenos ($bot, $lineno, &$parsedContent)
    {
        $directive = $parsedContent[0];
        switch ($directive) {
            case $this->TOK('GOTO'  ) : $pos = 1; break;
            case $this->TOK('GOSUB' ) : $pos = 1; break;
            case $this->TOK('OPTION') : $pos = $parsedContent[2] == $this->TOK('AS') ? 5 : 3; break;
            default                   : $pos = null;
        }
        if (isset($parsedContent[$pos]) && $pos !== null) {
            $label = $parsedContent[$pos];
            if (! $this->isLabel($label, $bot)) { $this->addError($lineno, "$bot: using undeclared label $label in $directive statement"); return false; }
            else                                { $parsedContent[$pos] = $this->bots[$bot]['labels'][$label];                              return true;  }
        }
        return true;
    }



    protected function initRunStructs () {}



    /**
     * Método invocado por parseProgram() que procesa una línea (raw) del código del programa BotBasic, valida la sintaxis y retorna una
     * estructura de datos que representa el código, apta para su interpretación en forma estándar por parte del runtime, y que incluye en
     * forma anidada a las subexpresiones o subsentencias que forman parte del texto pasado
     *
     * @param  int          $lineno     Número de línea del código del programa BotBasic
     * @param  string       $bot        Nombre del bot del programa BotBasic
     * @param  string       $symbol     Símbolo de la gramática por el que se tratará de hacer match al texto pasado a través de las reglas de
     *                                  producción asociadas a ese símbolo
     * @param  string       $text       Texto (raw) de le línea del código BotBasic, sin ningún procesamiento previo
     * @return bool|array               En caso de match con una de las reglas de producción, se retorna la estructura de datos procesada por
     *                                  el parser4...() apropiado; si no hay match, se retorna false
     */
    private function processSymbol ($lineno, $bot, $symbol, $text)
    {
        if ($text == '') { $text = "REM empty_line"; }
        $ttokens = $this->splitCell($text);

        // identificar si es una sentencia compuesta y tratarla con el caso especial del IF THEN compound [ELSE compound]
        // cuando despues de un IF hay ':', estos separadores se incluyen dentro de las subsentencias del THEN/ELSE
        $sepPos = [];
        $ifHappened = $seqHappened = false;
        for ($i = 0; $i < count($ttokens); $i++) {
            $tt = $ttokens[$i];
            if ($tt == $this->TOK('IF'))                 { $ifHappened = true; }
            if (! $ifHappened && $tt == $this->TOK(':')) { $sepPos[] = $i; $seqHappened = true; }
        }
        if ($seqHappened) {
            if (! in_array($symbol, [ 'root', 'sentence' ])) { $this->addError($lineno, "$bot: invalid use of sequencer ':' in: " . $text); return false; }
            array_unshift($ttokens, $this->TOK(':'));
            array_unshift($sepPos, -1);
            $res = [ $this->TOK(':') ];
            for ($i = 0; $i < count($sepPos); $i++) {
                $pos     = $sepPos[$i];
                $nextPos = isset($sepPos[$i+1]) ? $sepPos[$i+1] : count($ttokens)-1;
                $subpart = array_slice($ttokens, $pos+2, $nextPos - $pos - 1);
                if (($res[] = $this->processSymbol($lineno, $bot, $symbol, join(self::SEP, $subpart))) === false) {
                    $this->addError($lineno, "$bot: malformed statement: " . $text);
                }
            }
            return $res;
        }

        // identificar la regla de la sintaxis de BotBasic que aplica y reconocer sus componentes (tokens, palabras reservadas y simbolos) del texto
        $method  = false;
        foreach ($this->rules[$symbol] as $rule) {
            if (! is_string($rule)) { continue; }
            $rtokens = $this->splitCell($rule);
            // regla en cascada hacia otro simbolo
            if ($this->isSymbol($rtokens[0]) && count($rtokens) == 1) {
                $res = $this->processSymbol($lineno, $bot, $rtokens[0], $text);
                if ($res !== false) { return $res; }
            }
            // regla con una palabra reservada al comienzo; ttokens queda transformado: agrupado segun la sintaxis de la regla
            else {
                $method = $this->tryToMatch($rtokens, $ttokens, $lineno, $bot);
                if ($method === false) { continue; }
                break;
            }
        }

        // no hay una regla que aplica segun la sintaxis (cuando es '', se trata por ejemplo de una sentencia que comienza por un THEN o un ELSE)
        if ($method === '')        { return true;  }   // aplica en caso de terminales estilo splitSpec :: spaces | ... ; no hay que fallar ni llamar a "el metodo", o a logicPredicates Primitivas validas
        elseif ($method === false) { return false; }   // fallaron todas las reglas y no se reconocio nada

        // derivar cada una a su interpretador particular
        $theMethod = "parser4" . $method;
        if (! method_exists($this, $theMethod)) {
            $this->addError($lineno, "can't locate PHP method $theMethod for parsing: " . $text, true);
            return false;
        }
        $res = $this->$theMethod($symbol, $ttokens, $lineno, $bot);
        if ($res === false) { $this->addError($lineno, "$bot: malformed statement: " . $text); }
        return $res === false ? false : $ttokens;
    }



    /**
     * Dada una regla y un texto, trata de matchear ambas, retornando true, false, o el nombre del metodo parser4... a invocar para procesar los tokens
     *
     * Modifica ttokens a su estructura final de arbol apta para el interpretador.
     *
     * @param  string[]         $rtokens    arreglo de tokens de la regla de botbasic
     * @param  string[]|mixed[] $ttokens    IN: string[] ; OUT: [ string|mutable[], ... ] ; ttokens en texto, luego transformados a su estructura de arbol
     * @param  int              $lineno     linea de codigo de botbasic
     * @param  string           $bot        nombre del bot de botbasic
     * @return bool|string                  false si no se consiguio una regla, true si es una regla que no requiere procesar con un metodo parser4..., o el nombre del metodo
     */
    public function tryToMatch (&$rtokens, &$ttokens, $lineno, $bot)
    {
        // ttokens sufre una transformación asi:
        // IN  (12 tokens): IF NOT EQ hola chao THEN SET a 1 ELSE GOTO b
        // OUT ( 6 tokens): 'IF', 'NOT EQ hola chao', 'THEN', 'SET a 1', 'ELSE', 'GOTO b'
        // el procesamiento del operador sequenciacion se efectua desde processSymbol; no entra a esta funcion nunca
        if (count($ttokens) == 0) { return false; }   // false --> no se cumple la regla
        $newttokens       = [];
        $ellipsisHappened = false;
        $directive        = $rtokens[0];
        $rtResWords       = array_merge(array_filter($rtokens, function ($elem) { return $this->isReservedWord($elem) || $elem == '...'; }));
        for ($ttp = 0, $rtp = 0, $nttp = -1; $ttp < count($ttokens); ) {
            $rtoken = isset($rtResWords[$rtp]) ? $rtResWords[$rtp] : '';
            if ($rtoken == '...') { $ellipsisHappened = true; $rtp++; continue; }   // no debe suceder
            $rnextt = ! isset($rtResWords[$rtp+1]) ? '' : ($rtResWords[$rtp+1] != '...' ? $rtResWords[$rtp+1] : (! isset($rtResWords[$rtp+2]) ? '' : $rtResWords[$rtp+2]));
            $ttoken = $ttokens[$ttp];
            // matchear proxima palabra reservada de la regla + texto
            if ($this->isReservedWord($ttoken)) {
                if ($rtoken != $ttoken) { return false; }
                $newttokens[++$nttp] = $rtoken;
                $ellipsisHappened = false;
                $ttp++; $rtp++;
                $tnextt = isset($ttokens[$ttp]) ? $ttokens[$ttp] : '';
                if ($tnextt == $rnextt) { continue; }   // NO USAR continue
            }
            $newttokens[++$nttp] = '';
            // acumular y guardar hasta la proxima coincidencia de palabras reservadas conjuntas en regla + texto
            while ($ttp < count($ttokens)) {
                $ttoken = $ttokens[$ttp];
                $tnextt = isset($ttokens[$ttp+1]) ? $ttokens[$ttp+1] : '';
                // solamente secuencia de numeros, nombres capitalizados, nombres sin capitalizar, o palabras reservadas que no están en la sintaxis de la regla
                // esta validacion de todos los ttokens por los 4 criterios se esta repitiendo con cada iteracion; es mejor sacarla de este metodo y aplicarla antes de llamar a cualquier trytomatch()
                if ($this->TOK('REM') != $directive && ! (
                        $ttoken == '' || $this->isRvalue($ttoken) || $this->isLvalue($ttoken) || $this->isCapitalized($ttoken) || $this->isReservedWord($ttoken)
                    ) ) {
                    $this->addError($lineno, "$bot: malformed syntax with '$ttoken'");
                    return false;
                }
                $newttokens[$nttp] .= ($newttokens[$nttp] == '' ? '' : self::SEP) . $ttoken;
                $ttp++;
                if ($rnextt != '' && $tnextt == $rnextt) { break; }   // lookahead es un token de la regla
            }
            if (! $ellipsisHappened       && is_array($newttokens[$nttp]) && count($newttokens[$nttp]) == 1) { $newttokens[$nttp] = $newttokens[$nttp][0]; }
            if ($newttokens[$nttp] === '' || is_array($newttokens[$nttp]) && count($newttokens[$nttp]) == 0) { array_pop($newttokens); $nttp--;            }
        }
        if ($rtp < count($rtResWords) && ! ($rtp == count($rtResWords) - 1 && $rtResWords[$rtp] == '...')) { return false; }   // la regla tiene, al final, tokens/simbolos no satisfechos por el texto de entrada

        // caso especial de primitivas que actuan como predicados logicos
        if (! isset($this->tokensByName[$ttokens[0]]) && $rtokens[0] == 'LogicPrimitive' && $this->isPrimitive($ttokens[0]) && $this->checkPrimitiveArgcounts($ttokens[0], 0, 1)) {
            $ttokens = $newttokens;
            return '';   // caso para IF PrimitiveName THEN ...
        }
        elseif (! isset($this->tokensByName[$ttokens[0]])) {
            return false;
        }

        // retornar los ttokens separados segun sintaxis y el metodo php que sera usado para interpretar la regla (segun primera palabra del texto)
        $ttokens = $newttokens;
        return $this->tokensByName[$ttokens[0]][1];
    }



    /**
     * Aplica una secuencia de operaciones de procesamiento sobre los tokens de una línea de código BotBasic, los cuales quedan modificados
     * en la versión final que será usada por el runtime (a excepción de los postprocesamientos que se efectúen con runWith() en parseProgram())
     *
     * Las operaciones son ubn subconjunto de: split (de tokens que contienen espacios), chequeo del número de argumentos, chequeo de tipos,
     * y subprocesamiento de tokens según símbolos de la gramatica. Se representan como "órdenes".
     *
     * La operación final de "vaporización" no está implementada debido a que se consideró que reduce la expresividad del resultado.
     *
     * @param  string           $symbol         Símbolo de la gramática por el cual se están procesando los tokens
     * @param  string[]         $ttokens        Tokens de la línea de código BotBasic; queda modificado así:
     *                                          [ directive-token, arg1-truefalse-from-checks-or-array-for-symbol-proc-result, arg2-same, a-non-vaporized-token, argN-same, ... ]
     * @param  int              $lineno         Número de línea asociada del programa BotBasic
     * @param  string           $bot            Nombre del bot BotBasic
     * @param  array            $allOrders      Ordenes, de forma: [ <pos> => <parser4checkByPosition()-orders> , ... ], donde <pos> es una posición
     *                                          numérica en $ttokens o la palabra reservada (string) que precede a la posición numérica
     * @param  null|string[]    $toVaporize     Tokens del lenguaje a eliminar ("vaporizar") de $ttokens, o null para ninguno
     * @return bool                             Indica el éxito del resultado del procesamiento según las órdenes
     */
    private function parserCheckAllPoss ($symbol, &$ttokens, $lineno, $bot, $allOrders, $toVaporize = null)
    {
        $ruleCanProduceEmpty = function ($rule)
        {
            return substr($rule, 0, 8) == 'split::0';
        };

        $this->doDummy($symbol);
        $directive = $ttokens[0];
        $res       = true;
        // make all indexes in orders be integers
        $theOrders =  [];
        $stopwords = isset($this->rules[strtolower($directive)]) ? $this->rules[strtolower($directive)]['_stopwords'] : null;   // no more set to $this->rules[$symbol]
        for ($pos = 0, $lastTtoken = -1; $pos < count($ttokens); $pos++, $lastTtoken = $ttoken) {
            $ttoken          = $ttokens[$pos];
            $rule4lastTtoken = is_string($lastTtoken) && isset($allOrders[$lastTtoken]) ? $allOrders[$lastTtoken] : null;
            if ($stopwords !== null && in_array($ttoken, $stopwords) && $rule4lastTtoken !== null && ! $ruleCanProduceEmpty($rule4lastTtoken)) {
                return false;
            }
            if     ($stopwords !== null && in_array($ttoken, $stopwords)) { continue; }   // filtro que evita aplicar ordenes a palabras reservadas de la regla que vienen despues de otras palabras reservadas de $allOrders
            if     (isset($allOrders[$pos]))   { $theOrders[$pos] = ($rule4lastTtoken = $allOrders[$pos]); }   // priority is numeric specification over previous token value
            elseif ($rule4lastTtoken !== null) { $theOrders[$pos] = $rule4lastTtoken;                      }
        }
        // apply the orders
        foreach ($theOrders as $pos => $posOrders) {
            $res &= $this->parserCheckByPosition($ttokens[$pos], $posOrders, $lineno, $bot, $directive);   // $ttoken = $ttokens[$pos];
        }
        // delete tokens to vaporize by name
        if (false) {
            if ($toVaporize !== null) {
                $toVaporize = array_map(function ($elem) { return $this->TOK($elem); }, $toVaporize);
                $ttokens = array_filter($ttokens, function ($elem) use ($toVaporize) { return ! ($this->isReservedWord($elem) && in_array($elem, $toVaporize)); });
            }
        }
        // ready
        return $res;
    }



    /**
     * Chequea o procesa un token de acuerdo a órdenes especificadas
     *
     * Las órdenes se especifican como un string con la siguiente estructura (cada elemento es opcional):
     * * <splitspec>|<typespec>|<symbolspec>|<upspec>                                                                // all specs are optional
     * * splitspec   : split::<min>..<max> | split::<count1>,<count2>,...,<countN>                                   // <min>,<count1,2,...> : <integer>, <max>,<countN>:<integer>|inf
     * * typespec    : type::<pos>:<argName1>:<checkMethod1a>,<checkMethod1b>+<pos>:<argName2>:<checkMethod2>+...    // <pos> : <integer>|inf
     * * symbolspec  : symbol::<pos>:<symbol1>+<pos>:<symbol2>+...                                                   // <pos> : <integer>|inf
     * * upspec      : up::<pos>+<posFrom>..<posTo>+...                                                              // <pos> : <integer>
     *
     * @param  string   $ttoken     Token; puede ser compuesto por varios tokens separados por espacios
     * @param  string   $orders     Ordenes
     * @param  int      $lineno     Línea de código asociada del programa BotBasic
     * @param  string   $bot        Nombre del bot del programa BotBasic asociado
     * @param  string   $directive  Directiva; normalmente corrsponde a la primera palabra de la línea de código
     * @return bool                 Si las órdenes pudieron ser aplicadas exitosamente al token
     */
    private function parserCheckByPosition (&$ttoken, $orders, $lineno, $bot, $directive)
    {
        if ($ttoken == null || $orders == null || $orders == '') { return true; }
        $newTtokens           = [ $ttoken ];
        $wasSplitted          = false;
        $orders               = explode('|', $orders);
        $checksRes            = true;
        $symbolProcessingRess = null;
        foreach ($orders as $orderSpec) {
            list ($orderType, $orderParams) = explode('::', $orderSpec);
            switch ($orderType) {
                case 'split' :
                    $limits      = [];
                    $newTtokens = $this->splitCell($ttoken);
                    $wasSplitted = true;
                    if (1 == preg_match('/^[0-9]+\.\.$/', $orderParams)) { $orderParams = "{$orderParams}inf";          }
                    if (1 == preg_match('/^[0-9]+$/'    , $orderParams)) { $orderParams = "$orderParams..$orderParams"; }
                    if (1 == preg_match('/^([0-9]+)\.\.([0-9]+|inf)$/', $orderParams, $limits)) {
                        list (, $min, $max) = $limits;
                        if (count($newTtokens) < $min || ($max === 'inf' ? false : count($newTtokens) > $max)) {
                            $this->addError($lineno, "$bot: invalid number of arguments for $directive");
                            $checksRes = false;
                        }
                    }
                    elseif (1 == preg_match('/^([0-9]+,)+([0-9]+|inf)$/', $orderParams)) {
                        $limits = explode(',', $orderParams);
                        if ($limits[count($limits)-1] === 'inf') { $canBeInf = true; array_pop($limits); } else { $canBeInf = false; }
                        $max = $limits[count($limits)-1];
                        if (! (in_array(count($newTtokens), $limits) || $canBeInf && count($newTtokens) >= $max)) {
                            $this->addError($lineno, "$bot: invalid number of arguments for $directive");
                            $checksRes = false;
                        }
                    }
                    else {
                        // $orderParams = 'INVALID';
                        continue;
                    }
                    break;
                case 'type' :
                    $allCheckSpecs = explode('+', $orderParams);
                    $maxPos        = -1;
                    $allParams     = [];
                    foreach ($allCheckSpecs as $checkSpec) {
                        $params = [];
                        if ($checkSpec == '') { continue; }
                        if (1 != preg_match('/^([0-9]+|inf):([a-zA-Z0-9 ]+):([a-zA-Z0-9,]*)?$/', $checkSpec, $params)) {
                            // $checkSpec = 'INVALID';
                            continue;
                        }
                        $checkPos             = $params[1];
                        $argName              = $params[2];
                        $checkMethods         = $params[3] != '' ? $params[3] : 'isAnything';
                        $allParams[$checkPos] = [ $argName, explode(',', $checkMethods) ];   // pos => [ argName, array-of-checkMethods ]
                        if ($checkPos !== 'inf' && $maxPos < $checkPos) { $maxPos = $checkPos; }
                        foreach ($allParams[$checkPos][1] as $idx => $checkMethod) {
                            if (! method_exists($this, $checkMethod)) {
                                // $checkMethod = 'INVALID';
                                $allParams[$checkPos][1][$idx] = 'isAnything';
                            }
                        }
                    }
                    for ($pos = 0; $pos < count($newTtokens); $pos++) {
                        foreach ($allParams as $checkPos => $checkSpec) {
                            list ($argName, $checkMethods) = $checkSpec;
                            if ($pos === $checkPos || $checkPos === 'inf' && $pos > $maxPos) {
                                $valid = true;
                                $failedChecks = [];
                                foreach ($checkMethods as $checkMethod) {
                                    $res = $this->$checkMethod($newTtokens[$pos]);
                                    if ($res === false) { $failedChecks[] = $checkMethod; }
                                    $valid &= $res;
                                }
                                if (! $valid) {
                                    $this->addError($lineno, "$bot: invalid $argName in $directive (checking by: " . join(', ', $failedChecks) . ")");
                                    $checksRes = false;
                                }
                            }
                        }
                    }
                    break;
                case 'symbol' :
                    $allCheckSpecs = explode('+', $orderParams);
                    $maxPos        = -1;
                    $allParams     = [];
                    foreach ($allCheckSpecs as $checkSpec) {
                        $params = [];
                        if (1 != preg_match('/^([0-9]+|inf):([a-zA-Z]+)$/', $checkSpec, $params)) {
                            // $checkSpec = 'INVALID';
                            continue;
                        }
                        list (, $checkPos, $symbol) = $params;
                        if (! $this->isSymbol(strtolower($symbol))) {
                            $symbol = null;
                        }
                        $allParams[$checkPos] = $symbol;
                        if ($checkPos !== 'inf' && $maxPos < $checkPos) { $maxPos = $checkPos; }
                    }
                    for ($pos = 0; $pos < count($newTtokens); $pos++) {
                        foreach ($allParams as $divePos => $symbol) {
                            if ($pos == $divePos || $divePos === 'inf' && $pos > $maxPos) {
                                $res = $symbol === null ? false : $this->processSymbol($lineno, $bot, strtolower($symbol), $newTtokens[$pos]);
                                if ($res === false) {
                                    $this->addError($lineno, "$bot: invalid token '" . $newTtokens[$pos] . "' when processing $directive");
                                }
                                if ($res !== true) {
                                    $newTtokens[$pos] = $res;
                                }
                            }
                        }
                    }
                    break;
                case 'up' :
                    if ($orderParams == '') { continue; }
                    $allUpSpecs  = explode('+', $orderParams);
                    $allPoss     = [];
                    $infHappened = false;
                    $maxPos      = -1;
                    foreach ($allUpSpecs as $upSpec) {
                        $maxPos    = -1;
                        $thesePoss = [];
                        if (1 == preg_match('/^[0-9]+\.\.$/', $upSpec)) { $upSpec = "{$upSpec}inf";     }
                        if (1 == preg_match('/^[0-9]+$/',     $upSpec)) { $upSpec = "$upSpec..$upSpec"; }
                        if (1 != preg_match('/^([0-9]+)\.\.([0-9]+|inf)$/', $upSpec, $thesePoss)) {
                            // $upSpec = 'INVALID';
                            continue;
                        }
                        list (, $min, $max) = $thesePoss;
                        if ($max !== 'inf' && $maxPos < $max) { $maxPos = $max; }
                        if ($max === 'inf') { $infHappened = true; }
                        else               { for ($pos = $min; $pos <= $max; $pos++) { if (! in_array($pos, $allPoss)) { $allPoss[] = $pos; } } }
                    }
                    sort($allPoss, SORT_NUMERIC);
                    for ($pos = count($newTtokens) - 1; $pos >= 0; $pos--) {
                        if (! (in_array($pos, $allPoss) || $pos > $maxPos && $infHappened)) { continue; }
                        if (! is_array($newTtokens[$pos]))                                  { continue; }
                        array_splice($newTtokens, $pos, 1, $newTtokens[$pos]);
                    }
                    break;
                default :
                    // $orderType = 'INVALID';
                    continue;
            }
        }
        $ttoken = $wasSplitted ? $newTtokens : $newTtokens[0];   // OUT method parameter
        return $checksRes;
    }



    /////////////////////////////////////////
    // PARSER METHODS FOR EACH GRAMMAR SYMBOL
    /////////////////////////////////////////



    /**
     * Parsea un operador con M..N argumentos
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @param  int          $min            Cantidad mínima de operandos
     * @param  int|string   $max            Cantidad máxima de argumentos, o 'inf' para no especificar límite
     * @param  string       $typeSpecs      Ordenes de procesamiento para typeSpec según lo que espera parserCheckByPosition()
     * @param  string       $upSpec         Ordenes de procesamiento para upSpec según lo que espera parserCheckByPosition()
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserNary ($symbol, &$ttokens, $lineno, $bot, $min = 1, $max = 'inf', $typeSpecs = '0:x:+inf:x:', $upSpec = '')
    {
        $res = $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 1 => "split::$min..$max|type::$typeSpecs|up::$upSpec" ]);
        for ($pos = count($ttokens) - 1; $pos >= 0; $pos--) {
            if (! is_array($ttokens[$pos])) { continue; }
            array_splice($ttokens, $pos, 1, $ttokens[$pos]);
        }
        if (count($ttokens) - 1 < $min || count($ttokens)  - 1 > $max) {
            $this->addError($lineno, "$bot: bad syntax" . (isset($ttokens[0]) ? " with " . $ttokens[0] : ''));
        }
        return $res;
    }



    /**
     * Parsea un operador sin argumentos
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserOp0args ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserNary($symbol, $ttokens, $lineno, $bot, 0, 0);
    }



    /**
     * Parsea un operador de un argumento
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @param  string       $argSpec        Especificador para typeSpec sin indicar posición (el primer componente)
     * @param  bool         $up             Si se aplicará una orden "up"
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserOp1arg ($symbol, &$ttokens, $lineno, $bot, $argSpec = "x:", $up = true)
    {
        return $this->parserNary($symbol, $ttokens, $lineno, $bot, 1, 1, "0:$argSpec", $up ? '0' : '');
    }



    /**
     * Parsea un operador de un argumento
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @param  string       $arg1spec       Especificador para typeSpec para el operando 1 sin indicar posición (el primer componente)
     * @param  string       $arg2spec       Especificador para typeSpec para el operando 2 sin indicar posición (el primer componente)
     * @param  bool         $up             Si se aplicará una orden "up"
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserOp2args ($symbol, &$ttokens, $lineno, $bot, $arg1spec = "x:", $arg2spec = "x:", $up = true)
    {
        return $this->parserNary($symbol, $ttokens, $lineno, $bot, 2, 2, "0:$arg1spec+1:$arg2spec", $up ? '0..1' : '');
    }



    /**
     * Parsea un operador de un o dos argumentos
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @param  string       $arg1spec       Especificador para typeSpec para el operando 1 sin indicar posición (el primer componente)
     * @param  string       $arg2spec       Especificador para typeSpec para el operando 2 sin indicar posición (el primer componente)
     * @param  bool         $up             Si se aplicará una orden "up"
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserOp1or2args ($symbol, &$ttokens, $lineno, $bot, $arg1spec = "x:", $arg2spec = "x:", $up = true)
    {
        return $this->parserNary($symbol, $ttokens, $lineno, $bot, 1, 2, "0:$arg1spec+1:$arg2spec", $up ? '0..1' : '');
    }



    /**
     * Parsea un operador de uno a tres argumentos
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @param  string       $arg1spec       Especificador para typeSpec para el operando 1 sin indicar posición (el primer componente)
     * @param  string       $arg2spec       Especificador para typeSpec para el operando 2 sin indicar posición (el primer componente)
     * @param  string       $arg3spec       Especificador para typeSpec para el operando 3 sin indicar posición (el primer componente)
     * @param  bool         $up             Si se aplicará una orden "up"
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserOp1to3args ($symbol, &$ttokens, $lineno, $bot, $arg1spec = "x:", $arg2spec = "x:", $arg3spec = "x:", $up = true)
    {
        return $this->parserNary($symbol, $ttokens, $lineno, $bot, 1, 3, "0:$arg1spec+1:$arg2spec+2:$arg3spec", $up ? '0..2' : '');
    }



    /**
     * Parsea un operador lógico unario
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserUnaryLogicOperator ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1arg ($symbol, $ttokens, $lineno, $bot, "argument:isRvalue", "logic operator:isLogicDirective");
    }



    /**
     * Parsea un operador lógico binario
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa
     * @param  string[]     $ttokens        Tokens de la línea de código; son cambiados según parserCheckAllPoss()
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parserBinaryLogicOperator ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp2args($symbol, $ttokens, $lineno, $bot, "argument:isRvalue", "argument:isRvalue", "logic operator:isLogicDirective");
    }



    /**
     * Parser4... para EQ
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4eq ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserBinaryLogicOperator($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para NEQ
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4neq ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserBinaryLogicOperator($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para GT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4gt ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserBinaryLogicOperator($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para GTE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4gte ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserBinaryLogicOperator($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para LT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4lt ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserBinaryLogicOperator($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para LTE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4lte ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserBinaryLogicOperator($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para NOT; recursivo sobre logicPredicate
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4not ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 'NOT' => 'symbol::0:logicPredicate' ]);
    }



    /**
     * Parser4... para EMPTY
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4empty ($symbol, &$ttokens, $lineno, $bot)
    {
        if ($this->isReservedWord($ttokens[1])) { return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, []);    }
        else                                    { return $this->parserUnaryLogicOperator ($symbol, $ttokens, $lineno, $bot); }
    }



    /**
     * Parser4... para IF
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4if ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'IF'   => 'symbol::0:logicPredicate',
                'THEN' => 'symbol::0:sentence',
                'ELSE' => 'symbol::0:sentence',
            ],
            [ 'THEN', 'ELSE' ]
        );
    }



    /**
     * Parser4... para APPVERSION
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4appversion ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para RUNTIMEID
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4runtimeid ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para BOTNAME
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4botname ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para CHATAPP
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4chatapp ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para USERNAME
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4username ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para USERLOGIN
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4userlogin ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para USERLANG
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4userlang ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para ENTRYTYPE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4entrytype ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para ENTRYTEXT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4entrytext ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para ENTRYID
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4entryid ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para ERR
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4err ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para PEEK222 (alias para ERR)
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4peek222 ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parser4err($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para GOTO
     *
     * El chequeo de la validez del label se deja para el final del parsing, pues aun no han sido declarados todos los labels.
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4goto ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1arg($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para GOSUB
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4gosub ($symbol, &$ttokens, $lineno, $bot)
    {
        $res = $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                1    => 'split::1..|type::1:argument:isRvalue+inf:argument:isRvalue',
                'TO' => 'split::1..|type::1:result variable:isLvalue+inf:result variable:isLvalue',
            ],
            [ 'TO' ]
        );
        if (! isset($ttokens[1]))                               { $this->addError($lineno, "$bot: bad syntax with GOSUB"); $ttokens[] = [ null ]; }
        if (false === array_search($this->TOK('TO'), $ttokens)) { $ttokens[] = $this->TOK('TO'); $ttokens[] = null;                               }
        // el chequeo de la validez del label se deja para el final del parsing, pues aun no han sido declarados todos los labels
        $ttokens = [ $ttokens[0], $ttokens[1][0], array_slice($ttokens[1], 1), $ttokens[2], $ttokens[3] ];
        return $res;
    }



    /**
     * Parser4... para ARGS
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4args ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 1 => 'split::1..|type::0:input variable:isLvalue+inf:input variable:isLvalue' ]);
    }



    /**
     * Parser4... para RETURN
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4return ($symbol, &$ttokens, $lineno, $bot)
    {
        $res = $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 1 => 'split::1..|type::0:output value:isRvalue+inf:output value:isRvalue' ]);
        if (count($ttokens) == 1) { $ttokens[] = null; }
        return $res;
    }



    /**
     * Parser4... para CALL
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4call ($symbol, &$ttokens, $lineno, $bot)
    {
        $res = $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'CALL' => 'split::1..|type::0:primitive:isPrimitive+inf:primitive argument:isRvalue',
                'TO'   => 'split::0..|type::0:variable:isLvalueOrOptionsRW+inf:variable:isLvalue',
            ],
            [ 'TO' ]
        );
        if ($res) {
            $callArgs      =& $ttokens[1];
            $callArgs      =  [ $callArgs[0], array_slice($callArgs, 1) ];
            $primitiveName =  $callArgs[0];
            // validar cantidad de argumentos de entrada de la primitiva
            if (count($callArgs[1]) != count($this->primitives[$primitiveName][0])) {
                $this->addError($lineno, "$bot: invalid input argument count for primitive $primitiveName");
                $callArgs = false;
                // $res   = false;   // uncommenting this line prints a "malformed statement" error
            }
            // caso TO OPTIONS
            $pos = array_search($this->TOK('TO'), $ttokens);
            if ($pos !== false && $ttokens[$pos+1][0] == $this->TOK('OPTIONS')) {
                if (count($ttokens[$pos+1]) != 1) {
                    $this->addError($lineno, "$bot: TO OPTIONS doesn't admit additional arguments");
                    $ttokens[$pos+1] = false;
                    // $res          = false;   // uncommenting this line prints a "malformed statement" error
                }
                else {
                    $ttokens[$pos+1] = $this->TOK('OPTIONS');
                }
            }
            // caso TO sin variables destino
            elseif ($pos !== false && (! isset($ttokens[$pos+1]) || count($ttokens[$pos+1]) == 0)) {
                // $this->addError($lineno, "$bot: ...");   // don't print here but ...
                $ttokens[$pos+1] = false;
                $res             = false;                   // ... a "malformed statement" error
            }
            // validar cantidad de argumentos de salida de la primitiva
            $tToCount = $pos === false ? 0 : ($ttokens[$pos+1] == $this->TOK('OPTIONS') ? null : count($ttokens[$pos+1]));
            if ($tToCount !== null && $tToCount != count($this->primitives[$primitiveName][1])) {
                $this->addError($lineno, "$bot: invalid output argument count for primitive $primitiveName");
                $callArgs = false;
                // $res   = false;   // uncommenting this line prints a "malformed statement" error
            }
        }
        return $res;
    }



    /**
     * Parser4... para ON
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4on ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1to3args($symbol, $ttokens, $lineno, $bot, 'bot name:isBot', 'bmUserId:isLvalue', 'bbChannelId:isLvalue');
    }



    /**
     * Parser4... para PRINT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4print ($symbol, &$ttokens, $lineno, $bot)
    {
        $res = $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'PRINT' => 'split::1..|type::0:text expression:isRvalue+inf:text expression:isRvalue',
                'ON'    => 'split::1..3|type::0:bot name:isBotOrChannelsRW+1:variable:isLvalue+2:variable:isLvalue',
            ],
            [ 'ON' ]
        );
        $pos = array_search($this->TOK('ON'), $ttokens);
        if ($pos !== false && $ttokens[$pos+1][0] == $this->TOK('CHANNELS')) {
            if (count($ttokens[$pos+1]) != 1) {
                $this->addError($lineno, "$bot: ON CHANNELS doesn't admit additional arguments");
                $ttokens[$pos+1] = false;
            }
            $ttokens[$pos+1] = $this->TOK('CHANNELS');
        }
        return $res;
    }



    /**
     * Parser4... para DISPLAY
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4display ($symbol, &$ttokens, $lineno, $bot)
    {
        $res = $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'DISPLAY' => 'split::1..|type::0:text expression:isLvalue+inf:text expression:isLvalue',
                'TITLE'   => 'type::0:caption:isRvalue',
                'ON'      => 'split::1..3|type::0:bot name:isBotOrChannelsRW+1:variable:isLvalue+2:variable:isLvalue',
            ],
            [ 'ON' ]
        );
        $pos = array_search($this->TOK('ON'), $ttokens);
        if ($pos !== false && $ttokens[$pos+1][0] == $this->TOK('CHANNELS')) {
            if (count($ttokens[$pos+1]) != 1) {
                $this->addError($lineno, "$bot: ON CHANNELS doesn't admit additional arguments");
                $ttokens[$pos+1] = false;
            }
            $ttokens[$pos+1] = $this->TOK('CHANNELS');
        }
        return $res;
    }



    /**
     * Parser4... para END
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4end ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para REM
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4rem ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, []);
    }



    /**
     * Parser4... para OPTION
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4option ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'OPTION' => 'type::0:menu option:isRvalue',
                'AS'     => 'type::0:menu key:isRvalue',
                'GOTO'   => 'type::0:label:',
                'GOSUB'  => 'type::0:label:',
            ],
            []   // , [ 'AS', 'GOTO', 'GOSUB' ]   // vaporizar estos tokens implicaria definir una forma de indicarlo en el token OPTION
        );
    }



    /**
     * Parser4... para OPTIONS
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4options ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 1 => 'split::1..|type::0:menu option:isRvalue+inf:menu option:isRvalue' ]);
    }



    /**
     * Parser4... para TITLE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4title ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1arg($symbol, $ttokens, $lineno, $bot, 'menu title:isRvalue');
    }



    /**
     * Parser4... para PAGER
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4pager ($symbol, &$ttokens, $lineno, $bot)
    {
        $res = $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 1 => 'split::1..2|type::1:pager size:isNumber|symbol::0:pagerSpec' ]);
        if (! $this->isReservedWord($ttokens[1][0])) {
            $this->addError($lineno, "$bot: invalid pager spec {$ttokens[1][0]}");
        }
        return $res;
    }



    /**
     * Parser4... para MENU
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4menu ($symbol, &$ttokens, $lineno, $bot)
    {
        $toLimit = $this->isReservedWord($ttokens[1]) ? 2 : 'inf';
        $orders = [
            'MENU'    => 'split::0..|type::0:predefined menu name:isMenu+inf:predefined menu argument:isRvalue',
            'TITLE'   => 'type::0:menu title:isRvalue',
            'OPTIONS' => 'split::1..|type::0:menu option:isRvalue+inf:menu option:isRvalue',
            'PAGER'   => 'split::1..2|type::1:pager size:isNumber|symbol::0:pagerSpec',
            'ON'      => 'split::1..3|type::0:bot name:isBot+1:bmUserId:isLvalue+2:bbChannelId:isLvalue',
            'TO'      => "split::1..$toLimit|type::0:variable:isLvalue",
        ];
        $res = $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, $orders, [ 'TITLE', 'OPTIONS', 'PAGER', 'ON', 'TO' ]);
        if ($res) {
            if ($this->isReservedWord($ttokens[1])) {
                array_splice($ttokens, 1, 0, [ [null, []] ]);
                $menuName = null;
            }
            else {
                $menuArgs =& $ttokens[1];
                $menuArgs =  [ $menuArgs[0], array_slice($menuArgs, 1) ];
                $menuName =  $menuArgs[0];
                // validar cantidad de argumentos de entrada del menu predefinido
                if (count($menuArgs[1]) != count($this->predefmenus[$menuName][0])) {
                    $this->addError($lineno, "$bot: invalid input argument count for menu $menuName");
                    $menuArgs = false;
                    //$res      = false;   // uncommenting prints a malformed statement error
                }
            }
            // validar cantidad de argumentos de salida del menu predefinido
            $pos      = array_search($this->TOK('TO'), $ttokens);
            $tToCount = $pos === false ? 0 : count($ttokens[$pos+1]);
            if ($ttokens[1][0] !== null && $tToCount != count($this->predefmenus[$menuName][1])) {
                $this->addError($lineno, "$bot: invalid output argument count for menu $menuName");
                $menuArgs = false;
                //$res      = false;   // uncommenting prints a malformed statement error
            }
            // validar pager spec
            $pos = array_search($this->TOK('PAGER'), $ttokens);
            if ($pos !== false && ! $this->isReservedWord($ttokens[$pos+1][0])) {
                $this->addError($lineno, "$bot: invalid pager spec {$ttokens[$pos+1][0]}");
            }
        }
        return $res;
    }



    /**
     * Parser4... para WORD
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4word ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1arg($symbol, $ttokens, $lineno, $bot, 'input default word:isRvalue');
    }



    /**
     * Parser4... para INPUT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4input ($symbol, &$ttokens, $lineno, $bot)
    {
        $dataType = $ttokens[1];
        $res = $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'INPUT' => 'symbol::0:dataType',
                'TITLE' => 'type::0:input title:isLvalue',
                'ON'    => 'split::1..3|type::0:bot name:isBot+1:bmUserId:isLvalue+2:bbChannelId:isLvalue',
                'TO'    => 'split::1..3|type::0:target variable 1:isLvalue+1:target variable 2:isLvalue+2:target variable 3:isLvalue',
                'FROM'  => 'type::0:source variable:isLvalue',
            ],
            [ 'TITLE', 'ON', 'TO', 'FROM' ]
        );
        if ($ttokens[1] === false) {
            $this->addError($lineno, "$bot: invalid data type $dataType");
        }
        return $res;
    }



    /**
     * Parser4... para BLOAD
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4bload ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'BLOAD' => 'type::0:filename:isLvalue',
                'AS'    => 'symbol::0:mediaType',
                'TO'    => 'type::0:resource id:isLvalue',
            ],
            [ 'AS', 'TO' ]
        );
    }



    /**
     * Parser4... para BSAVE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4bsave ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'BSAVE' => 'type::0:resource id:isLvalue',
                'AS'    => 'type::0:filename:isLvalue',
                'TO'    => 'type::0:result status:isLvalue',
            ],
            [ 'AS', 'TO' ]
        );
    }



    /**
     * Parser4... para EXTRACT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4extract ($symbol, &$ttokens, $lineno, $bot)
    {
        $component = $ttokens[1];
        $res = $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'EXTRACT' => 'symbol::0:extractSpec',
                'FROM'    => 'type::0:source:isLvalue',
                'TO'      => 'type::0:target:isLvalue',
            ],
            [ 'FROM', 'TO' ]
        );
        if ($ttokens[1] === false) {
            $this->addError($lineno, "$bot: invalid extract spec $component");
        }
        return $res;
    }



    /**
     * Parser4... para SET
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4set ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'SET' => 'split::2..2|type::0:variable:isLvalue+1:value:isRvalue',
                'ON'  => 'split::2..2|type::0:bot name:isBot+1:bmUserId:isLvalue',
            ],
            [ 'ON' ]
        );
    }



    /**
     * Parser4... para CLEAR
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4clear ($symbol, &$ttokens, $lineno, $bot)
    {
        $orders = $this->isReservedWord($ttokens[1]) ? [] : [ 1 => 'split::0..|type::0:variable:isLvalue+inf:variable:isLvalue' ];
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, $orders);
    }



    /**
     * Parser4... para INC
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4inc ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1or2args($symbol, $ttokens, $lineno, $bot, 'variable:isLvalue', 'value:isRvalue');
    }



    /**
     * Parser4... para DEC
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4dec ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1or2args($symbol, $ttokens, $lineno, $bot, 'variable:isLvalue', 'value:isRvalue');
    }



    /**
     * Parser4... para MUL
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4mul ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp2args($symbol, $ttokens, $lineno, $bot, 'variable:isLvalue', 'value:isRvalue');
    }



    /**
     * Parser4... para DIV
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4div ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp2args($symbol, $ttokens, $lineno, $bot, 'variable:isLvalue', 'value:isRvalue');
    }



    /**
     * Parser4... para MOD
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4mod ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp2args($symbol, $ttokens, $lineno, $bot, 'variable:isLvalue', 'value:isRvalue');
    }



    /**
     * Parser4... para CONCAT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4concat ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 1 => 'split::2..|type::0:variable:isLvalue+1:variable:isRvalue+inf:variable:isRvalue' ]);
    }



    /**
     * Parser4... para SPLIT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4split ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'SPLIT' => 'split::2|type::0:splitter variable:isLvalue+1:variable:isLvalue',
                'TO'    => 'split::1..|type::0:variable:isLvalue+inf:variable:isLvalue',
            ],
            [ 'TO' ]
        );
    }



    /**
     * Parser4... para COUNT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4count ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [
                'TO'   => 'type::0:target variable:isLvalue',
            ],
            [ 'COUNT', 'OPTIONS', 'TO' ]
        );
    }



    /**
     * Parser4... para LOG
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4log ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 1 => 'split::1..|type::0:value:isRvalue+inf:value:isRvalue' ]);
    }



    /**
     * Parser4... para LOCALE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4locale ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp1arg($symbol, $ttokens, $lineno, $bot, 'locale spec:isLocale');
    }



    /**
     * Parser4... para ABORT
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4abort ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para DATA
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4data ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [
                'SET'  => 'type::0:variable containing DB key:isLvalue',
                'GET'  => 'type::0:variable containing DB key:isLvalue',
                'FROM' => 'type::0:expression:isRvalue',
                'TO'   => 'type::0:target variable:isLvalue',
            ],
            [ 'FROM', 'TO' ]
        );
    }



    /**
     * Parser4... para CHANNEL
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4channel ($symbol, &$ttokens, $lineno, $bot)
    {
        if ($ttokens[1] == $this->TOK('DELETE')) {
            $specs = $ttokens[2] == $this->TOK('ALL') ?
                     [] :
                     [ 'DELETE' => 'type::0:variable for channelId:isLvalue' ];
            $checkChannelSpec = false;
        }
        else {
            $specs = [
                'CHANNEL' => 'symbol::0:channelSpec',
                'TO'      => 'split::2|type::0:variable for channelId:isLvalue+1:variable for chatMediaBotName:isLvalue',
                'FOR'     => 'type::0:variable for channel purpose text:isLvalue',
            ];
            $checkChannelSpec = true;
        }
        $res = $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, $specs, []);
        if ($checkChannelSpec && ! $this->isReservedWord($ttokens[1])) {
            $this->addError($lineno, "$bot: invalid channel spec {$ttokens[1]}");
        }
        return $res;
    }



    /**
     * Parser4... para TUNNEL
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4tunnel ($symbol, &$ttokens, $lineno, $bot)
    {
        $res = $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'TUNNEL' => 'symbol::0:tunnelSpec',
                'FROM'   => 'type::0:variable for current bot source channelId:isLvalue',
                'TO'     => 'split::3|type::0:partner botName:isBot+1:variable for partner userId:isLvalue+2:variable for partner channelId:isLvalue',
            ],
            [ 'FROM', 'TO' ]
        );
        if (! $this->isReservedWord($ttokens[1])) {
            $this->addError($lineno, "$bot: invalid tunnel spec {$ttokens[1]}");
        }
        return $res;
    }



    /**
     * Parser4... para USERID
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4userid ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss($symbol, $ttokens, $lineno, $bot, [ 2 => 'type::0:variable:isLvalue', ], [ 'FROM', 'TO' ]);
    }



    /**
     * Parser4... para BUILD
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4build ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserCheckAllPoss(
            $symbol, $ttokens, $lineno, $bot, [
                'BUILD' => 'symbol::0:buildableType',
                'SET'   => 'split::4..4|symbol::0:buildableAttrib+2:buildableAttrib|type::1:attribute name:isLvalue:3:attribute name:isLvalue',
                'TO'    => 'type::0:target variable:isLvalue',
            ],
            [ 'SET', 'TO' ]
        );
    }



    /**
     * Parser4... para TRACE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4trace ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * Parser4... para NOTRACE
     *
     * @param  string       $symbol         Símbolo de la gramática por el cual se procesa (directiva objetivo del parser4...)
     * @param  string[]     $ttokens        Tokens de la línea de código
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Indica si el procesamiento tuvo éxito
     */
    private function parser4notrace ($symbol, &$ttokens, $lineno, $bot)
    {
        return $this->parserOp0args($symbol, $ttokens, $lineno, $bot);
    }



    /**
     * IDE spoofer
     */
    private function IDEspoofer ()
    {
        $a = $b = $c = $d = null;
        $this->IDEspoofer               ();
        $this->isMenu                   ($a);
        $this->convertLabelsToLinenos   ($a, $b, $c);
        $this->parser4if                ($a, $b, $c, $d);
        $this->parser4eq                ($a, $b, $c, $d);
        $this->parser4neq               ($a, $b, $c, $d);
        $this->parser4gt                ($a, $b, $c, $d);
        $this->parser4gte               ($a, $b, $c, $d);
        $this->parser4lt                ($a, $b, $c, $d);
        $this->parser4lte               ($a, $b, $c, $d);
        $this->parser4empty             ($a, $b, $c, $d);
        $this->parser4not               ($a, $b, $c, $d);
        $this->parser4appversion        ($a, $b, $c, $d);
        $this->parser4runtimeid         ($a, $b, $c, $d);
        $this->parser4botname           ($a, $b, $c, $d);
        $this->parser4chatapp           ($a, $b, $c, $d);
        $this->parser4username          ($a, $b, $c, $d);
        $this->parser4userlogin         ($a, $b, $c, $d);
        $this->parser4userlang          ($a, $b, $c, $d);
        $this->parser4entrytype         ($a, $b, $c, $d);
        $this->parser4entrytext         ($a, $b, $c, $d);
        $this->parser4entryid           ($a, $b, $c, $d);
        $this->parser4goto              ($a, $b, $c, $d);
        $this->parser4on                ($a, $b, $c, $d);
        $this->parser4print             ($a, $b, $c, $d);
        $this->parser4end               ($a, $b, $c, $d);
        $this->parser4rem               ($a, $b, $c, $d);
        $this->parser4gosub             ($a, $b, $c, $d);
        $this->parser4args              ($a, $b, $c, $d);
        $this->parser4return            ($a, $b, $c, $d);
        $this->parser4call              ($a, $b, $c, $d);
        $this->parser4option            ($a, $b, $c, $d);
        $this->parser4options           ($a, $b, $c, $d);
        $this->parser4title             ($a, $b, $c, $d);
        $this->parser4pager             ($a, $b, $c, $d);
        $this->parser4menu              ($a, $b, $c, $d);
        $this->parser4word              ($a, $b, $c, $d);
        $this->parser4input             ($a, $b, $c, $d);
        $this->parser4set               ($a, $b, $c, $d);
        $this->parser4clear             ($a, $b, $c, $d);
        $this->parser4inc               ($a, $b, $c, $d);
        $this->parser4dec               ($a, $b, $c, $d);
        $this->parser4mul               ($a, $b, $c, $d);
        $this->parser4div               ($a, $b, $c, $d);
        $this->parser4mod               ($a, $b, $c, $d);
        $this->parser4concat            ($a, $b, $c, $d);
        $this->parser4split             ($a, $b, $c, $d);
        $this->parser4count             ($a, $b, $c, $d);
        $this->parser4log               ($a, $b, $c, $d);
        $this->parser4locale            ($a, $b, $c, $d);
        $this->parser4channel           ($a, $b, $c, $d);
        $this->parser4tunnel            ($a, $b, $c, $d);
        $this->parser4userid            ($a, $b, $c, $d);
        $this->parser4abort             ($a, $b, $c, $d);
        $this->parser4data              ($a, $b, $c, $d);
        $this->parser4trace             ($a, $b, $c, $d);
        $this->parser4notrace           ($a, $b, $c, $d);
        $this->parser4peek222           ($a, $b, $c, $d);
        $this->parser4display           ($a, $b, $c, $d);
        $this->parser4bload             ($a, $b, $c, $d);
        $this->parser4bsave             ($a, $b, $c, $d);
        $this->parser4extract           ($a, $b, $c, $d);
        $this->parser4build             ($a, $b, $c, $d);
    }



    /////////////
    // INNER MAIN
    /////////////



    /**
     * El código en ejecución fuera de la definición de la clase llama a este método
     *
     * @param  string   $text       Texto del archivo CSV que representa el contenido del programa BotBasic
     * @param  bool     $save       Indica si se grabará el resultado del parsing en BD, en caso de que el parsing haya sido exitoso
     * @return array                Estructura que sirve para analizar las estructuras intermedias y resultados del parsing
     */
    public function enter ($text, $save)
    {
        $parsedOk = $this->parse($text);
        $savedOk  = $save ? ($parsedOk === true ? $this->writeToDB() : false) : false;
        return [
            $parsedOk,
            [ $this->errors,
              $this->bbErrors,
            ],
            [ 'tokensByName' => $this->tokensByName,
              'rules'        => $this->rules,
              'sections'     => $this->sections
            ],
            [ 'messages'     => $this->messages,
              'menus'        => $this->predefmenus,
              'magicvars'    => $this->magicvars,
              'primitives'   => $this->primitives,
              'bots'         => $this->bots
            ],
            $savedOk,
        ];
    }



    /**
     * Almacena un resultado exitoso de parsing en la BD
     *
     * @return bool|null    null en caso de error de BD; false se se intentó sobreescribir una versión existente; true en caso de éxito
     */
    private function writeToDB ()
    {
        $res = DBbroker::writeBBcode($this);
        if     ($res === null)  { $this->addError(-1, "DB error when writing parsed code", true);     return false; }
        elseif ($res === false) { $this->addError(-1, "Can't overwrite program version in DB", true); return false; }
        return true;
    }



}



/////////////
// OUTER MAIN
/////////////



if (php_sapi_name() === 'cli') {   // command-line invocation



    $die = function ($msg) { fwrite(STDERR, $msg); exit(1); };
    // validate input options
    $options = getopt("vVsn:f:");
    if ($options === false || ! isset($options['n']) || ! isset($options['f'])) {
        $die("Usage: php -f BotBasicParser.php [ -v|-V ] [ -s ] -n<codename> -f<filename>\n" .
             "       (or use code web upload form)\n");
    }
    $verbose  = isset($options['v']);
    $supverb  = isset($options['V']);
    $saveToDB = isset($options['s']);
    $codename = $options['n'];
    $filename = $options['f'];
    // check if file can be read
    $text = '';
    if (! file_exists($filename) || ($text = @file_get_contents($filename)) === false) {
        $die("Unable to open filename for reading: $filename\n");
    }
    // process
    $bb         = BotBasicParser::create($codename);
    $parsingRes = $bb->enter($text, $saveToDB);
    // show output
    list ($parsedOk, $allErrors, , $components, $savedOk) = $parsingRes;
    list ($errors, $bbErrors) = $allErrors;
    list ($messages, $menus, $magicvars, $primitives, $program) = array_values($components);
    echo 'Parsing    :: ' . ($parsedOk ? 'OK' : 'failed') . "\n";
    if ($saveToDB) {
        echo 'Saving     :: ' . ($savedOk ? 'OK' : 'failed')  . "\n";
    }
    echo 'Errors     ::' . "\n";
    $errors['botbasic'] = $bbErrors;
    foreach ($errors as $context => $subErrors) {
        echo "  $context...\n";
        foreach ($subErrors as $error) {
            echo "    " . sprintf("%.140s", $error) . "\n";
        }
    }
    if ($verbose || $supverb) {
        echo 'Bots       :: ' . join(', ', array_keys($program)) . "\n";
        echo 'Messages   ::' . "\n";
        $locales = array_keys($messages);
        if (count($locales) > 0) {
            foreach ($messages[$locales[0]] as $name => $message) {
                $all = [];
                foreach ($locales as $locale) { $all[] = isset($messages[$locale][$name]) ? $messages[$locale][$name] : ""; }
                echo "  " . sprintf("%'.32.32s", $name) . "  " . sprintf("%.108s", join(' / ', $all)) . "\n";
            }
        }
        echo 'Menus      ::' . "\n";
        foreach ($menus as $name => $content) {
            list ($inVars, $outVars) = $content;
            $ins = $outs = [];
            foreach ($inVars  as $in ) { $ins [] = BotBasic::datatypeString($in ); }
            foreach ($outVars as $out) { $outs[] = BotBasic::datatypeString($out); }
            echo "  " . sprintf("%'.32.32s", $name) . "  (" . sprintf("%-51s", join(', ', $ins)) . ")  (" . sprintf("%-51s", join(', ', $outs)) . ")\n";
        }
        echo 'Magicvars  ::' . "\n";
        foreach ($magicvars as $name) {
            echo "  " . sprintf("%'.32.32s", $name) . "\n";
        }
        echo 'Primitives ::' . "\n";
        foreach ($primitives as $name => $content) {
            list ($inVars, $outVars) = $content;
            $ins = $outs = [];
            foreach ($inVars  as $in ) { $ins [] = BotBasic::datatypeString($in ); }
            foreach ($outVars as $out) { $outs[] = BotBasic::datatypeString($out); }
            echo "  " . sprintf("%'.32.32s", $name) . "  (" . sprintf("%-51s", join(', ', $ins)) . ")  (" . sprintf("%-51s", join(', ', $outs)) . ")\n";
        }
        if ($supverb) {
            echo 'Program    ::' . "\n";
            print_r($program);
        }
    }
    exit($parsedOk && ($saveToDB ? $savedOk : true) ? 0 : 1);



} else {   // web server invocation



    header('Content-Type:text/html; charset=UTF-8');
    include(BOTBASIC_BASEDIR . '/httpdocs/krumo/class.krumo.php');
    $die = function ($msg) { echo "<p>$msg</p>\n<p><a href=\"/scripts/parser/parser_upload_form.html\">Try again...</a></p>"; exit(); };
    // validate input
    if (! isset($_POST["codename"]) || $_POST["codename"] == '' || $_POST["user_id"] == '' || $_POST["password"] == '' ||
        ! isset($_FILES["filename"]) || ! isset($_FILES["filename"]["tmp_name"])) {
        $die('All fields must be used');
    }
    $codename = $_POST["codename"];
    $filename = $_FILES["filename"]["tmp_name"];
    $saveToDB = isset($_POST["save_to_db"]);
    $userId   = $_POST["user_id"];
    $password = $_POST["password"];
    // check if file can be read
    $text = '';
    if (! file_exists($filename) || ($text = @file_get_contents($filename)) === false) {
        $die('Unable to open uploaded file for reading');
    }
    // check privileges
    $res = DBbroker::userCanUploadCode($userId, $password);
    if     ($res === null)  { $die("Can't connect to database");  }
    elseif ($res === false) { $die('Invalid user ID / password'); }
    // process
    $bb         = BotBasicParser::create($codename);
    $parsingRes = $bb->enter($text, $saveToDB);
    // show output
    echo '<html><body style="font-family: monospace">' . "\n";
    echo "<h1>BotBasic :: Parser output</h1>\n<hr>\n";
    list ($parsedOk, $allErrors, , $components, $savedOk) = $parsingRes;
    list ($errors, $bbErrors) = $allErrors;
    list ($messages, $menus, $magicvars, $primitives, $program) = array_values($components);
    echo 'Parsing :: ' . ($parsedOk ? 'OK' : 'failed') . "<br>\n";
    if ($saveToDB) {
        echo 'Saving :: ' . ($savedOk ? 'OK' : 'failed')  . "<br>\n";
    }
    echo "\n<hr><h4>Errors</h4><ol>\n";
    $errors['botbasic'] = $bbErrors;
    foreach ($errors as $context => $subErrors) {
        echo "<li>$context...\n<ul>\n";
        foreach ($subErrors as $error) {
            echo "<li>$error</li>\n";
        }
        echo "</ul></li>\n";
    }
    echo "</ol>\n";
    echo "\n<hr><h4>Bots</h4>\n" . join(', ', array_keys($program)) . "\n";
    echo "\n<hr><h4>Messages</h4>\n";
    $locales = array_keys($messages);
    if (count($locales) > 0 && count($messages) > 0) {
        echo "<table border='1'><tr><th>Name</th><th>" . join('</th><th>', $locales) . "</th></tr>\n";
        foreach ($messages[$locales[0]] as $name => $message) {
            $all = [];
            foreach ($locales as $locale) { if (isset($messages[$locale][$name])) { $all[] = $messages[$locale][$name]; } }
            echo "<tr><td>$name</td><td>" . join('</td><td>', $all) . "</td></tr>\n";
        }
    }
    echo "</table>\n";
    echo "\n<hr><h4>Menus</h4>\n";
    if (count($menus) > 0) {
        echo "<table border='1'><tr><th>Name</th><th>Input vars</th><th>Output vars</th></tr>\n";
        foreach ($menus as $name => $content) {
            list ($inVars, $outVars) = $content;
            $ins = $outs = [];
            foreach ($inVars  as $in ) { $ins [] = BotBasic::datatypeString($in ); }
            foreach ($outVars as $out) { $outs[] = BotBasic::datatypeString($out); }
            echo "<tr><td>$name</td><td>" . join(', ', $ins) . "</td><td>" . join(', ', $outs) . "</td>\n";
        }
    }
    echo "</table>\n";
    echo "\n<hr><h4>Magicvars</h4>\n";
    if (count($magicvars) > 0) {
        echo "<table border='1'><tr><th>Name</th></tr>\n";
        foreach ($magicvars as $name) {
            echo "<tr><td>$name</td>\n";
        }
    }
    echo "</table>\n";
    echo "\n<hr><h4>Primitives</h4>\n";
    if (count($primitives) > 0) {
        echo "<table border='1'><tr><th>Name</th><th>Input vars</th><th>Output vars</th></tr>\n";
        foreach ($primitives as $name => $content) {
            list ($inVars, $outVars) = $content;
            $ins = $outs = [];
            foreach ($inVars  as $in ) { $ins [] = BotBasic::datatypeString($in ); }
            foreach ($outVars as $out) { $outs[] = BotBasic::datatypeString($out); }
            echo "<tr><td>$name</td><td>" . join(', ', $ins) . "</td><td>" . join(', ', $outs) . "</td>\n";
        }
    }
    echo "</table>\n";
    echo "\n<hr><h4>Program</h4>\n<ul>\n";
    foreach ($program as $bot => $structure) {
        echo "<li>$bot:\n";
        krumo($structure);
        echo "</li>\n";
    }
    echo "</ul>\n";
    //echo "<pre>\n"; print_r($program); echo "</pre>\n";
    echo "</body></html>\n";
    exit();



}
