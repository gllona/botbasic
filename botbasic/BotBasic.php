<?php
/**
 * Definiciones comunes a parser y runtime de BotBasic
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase BotBasic
 *
 * Superclase de Parser y Runtime. Contiene especificaciones comunes, la lista de diretivas y de tokens, y otras definiciones editables
 * al cambiar detalles del funcionamiento de BotBasic en sus interacciones conversacionales.
 *
 * Cada vez que se modifica la gramática del lenguaje, se deben copiar los tokens y las directivas aquí, así como implementar los métodos
 * BotBasicParser::parser4... y BotBasicRuntime::runner4...
 *
 * @package botbasic
 */
abstract class BotBasic
{



    ///////////////////////////////
    // BOOTBASIC GRAMMAR DEFINITION
    ///////////////////////////////



    /** @var array[] Placeholder para el copy-paste de las directivas de BotBasic tal como se definen en la hoja de cálculo de la gramática */
    public $rules = [
        [ 'ROOT', '<sentence>' ],
        [ 'sentence', '<if>' ],
        [ 'if', 'IF <logicPredicate> THEN <sentence1> ELSE <sentence2>' ],
        [ 'if', 'IF <logicPredicate> THEN <sentence>' ],
        [ 'logicPredicate', 'EQ <expr1> <expr2>' ],
        [ 'logicPredicate', 'NEQ <expr1> <expr2>' ],
        [ 'logicPredicate', 'GT <expr1> <expr2>' ],
        [ 'logicPredicate', 'GTE <expr1> <expr2>' ],
        [ 'logicPredicate', 'LT <expr1> <expr2>' ],
        [ 'logicPredicate', 'LTE <expr1> <expr2>' ],
        [ 'logicPredicate', 'EMPTY DATA' ],
        [ 'logicPredicate', 'EMPTY OPTIONS' ],
        [ 'logicPredicate', 'EMPTY <expr>' ],
        [ 'logicPredicate', 'NOT <logicPredicate>' ],
        [ 'logicPredicate', '<LogicPrimitive>' ],
        [ 'primitive', '<LogicPrimitive>' ],
        [ 'primitive', '<NonLogicPrimitive>' ],
        [ 'expr', 'APPVERSION' ],
        [ 'expr', 'RUNTIMEID' ],
        [ 'expr', 'BOTNAME' ],
        [ 'expr', 'CHATAPP' ],
        [ 'expr', 'USERNAME' ],
        [ 'expr', 'USERLOGIN' ],
        [ 'expr', 'USERLANG' ],
        [ 'expr', 'ENTRYTYPE' ],
        [ 'expr', 'ENTRYTEXT' ],
        [ 'expr', 'ENTRYID' ],
        [ 'expr', 'ERR' ],
        [ 'expr', 'PEEK222' ],
        [ 'expr', '<Number>' ],
        [ 'expr', '<MessageName>' ],
        [ 'expr', '<primitive>' ],
        [ 'expr', '<variable>' ],
        [ 'variable', '<MagicVar>' ],
        [ 'variable', '<CommonVar>' ],
        [ 'sentence', 'GOTO <label>' ],
        [ 'sentence', '<gosub>' ],
        [ 'gosub', 'GOSUB <label> <expr1> ... TO <variable1> ...' ],
        [ 'gosub', 'GOSUB <label> <expr1> ...' ],
        [ 'gosub', 'GOSUB <label>' ],
        [ 'sentence', 'ARGS <variable1> ...' ],
        [ 'sentence', 'RETURN <expr1opt> ...' ],
        [ 'sentence', '<call>' ],
        [ 'call', 'CALL <primitive> <variable1opt> ... TO OPTIONS' ],
        [ 'call', 'CALL <primitive> <variable1opt> ... TO <variable2> ...' ],
        [ 'call', 'CALL <primitive> <variable1opt> ...' ],
        [ 'sentence', 'ON <BotName> <variable1opt> <variable2opt>' ],
        [ 'sentence', '<print>' ],
        [ 'print', 'PRINT <expr1> ... ON CHANNELS' ],
        [ 'print', 'PRINT <expr1> ... ON <BotName>  <variable1opt> <variable2opt>' ],
        [ 'print', 'PRINT <expr1> ...' ],
        [ 'sentence', 'END' ],
        [ 'sentence', 'REM <expr1> ...' ],
        [ 'sentence', '<option>' ],
        [ 'option', 'OPTION <variable1> AS <variable2> GOTO <label>' ],
        [ 'option', 'OPTION <variable1> AS <variable2> GOSUB <label>' ],
        [ 'option', 'OPTION <variable1> AS <variable2>' ],
        [ 'option', 'OPTION <variable> GOTO <label>' ],
        [ 'option', 'OPTION <variable> GOSUB <label>' ],
        [ 'sentence', 'OPTIONS <variable1> ...' ],
        [ 'sentence', 'TITLE <MessageName>' ],
        [ 'sentence', 'PAGER <pagerSpec>' ],
        [ 'pagerSpec', 'pagerLong  <Number>' ],
        [ 'pagerSpec', 'pagerShort  <Number>' ],
        [ 'sentence', '<menu>' ],
        [ 'menu', 'MENU TITLE <variable1> OPTIONS <variable2> ... PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU TITLE <variable1> OPTIONS <variable2> ... PAGER <pagerSpec> TO <variable4>' ],
        [ 'menu', 'MENU TITLE <variable1> OPTIONS <variable2> ... ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU TITLE <variable1> OPTIONS <variable2> ... TO <variable4>' ],
        [ 'menu', 'MENU TITLE <variable1> PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU TITLE <variable1> PAGER <pagerSpec> TO <variable4>' ],
        [ 'menu', 'MENU TITLE <variable1> ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU TITLE <variable1> TO <variable4>' ],
        [ 'menu', 'MENU OPTIONS <variable2> ... PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU OPTIONS <variable2> ... PAGER <pagerSpec> TO <variable4>' ],
        [ 'menu', 'MENU OPTIONS <variable2> ... ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU OPTIONS <variable2> ... TO <variable4>' ],
        [ 'menu', 'MENU PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU PAGER <pagerSpec> TO <variable4>' ],
        [ 'menu', 'MENU ON <BotName> <variable3opt> <variable5opt> TO <variable4>' ],
        [ 'menu', 'MENU TO <variable4>' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> OPTIONS <variable2> ... PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> OPTIONS <variable2> ... PAGER <pagerSpec> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> OPTIONS <variable2> ... ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> OPTIONS <variable2> ... TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> PAGER <pagerSpec> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TITLE <variable1> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... OPTIONS <variable2> ... PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... OPTIONS <variable2> ... PAGER <pagerSpec> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... OPTIONS <variable2> ... ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... OPTIONS <variable2> ... TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... PAGER <pagerSpec> ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... PAGER <pagerSpec> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... ON <BotName> <variable3opt> <variable5opt> TO <variable4> ...' ],
        [ 'menu', 'MENU <Menu> <variable0opt> ... TO <variable4> ...' ],
        [ 'sentence', 'WORD <variable>' ],
        [ 'sentence', '<input>' ],
        [ 'input', 'INPUT <dataType> TITLE <MessageName> ON <BotName> <variable1opt> <variable2opt> TO <variable3> <variable4opt> <variable5opt> FROM <variable6>' ],
        [ 'input', 'INPUT <dataType> TITLE <MessageName> TO <variable1> <variable2opt> <variable3opt> FROM <variable4>' ],
        [ 'input', 'INPUT <dataType> TITLE <MessageName> ON <BotName> <variable1opt> <variable2opt> TO <variable3> <variable4opt> <variable5opt>' ],
        [ 'input', 'INPUT <dataType> TITLE <MessageName> TO <variable1> <variable2opt> <variable3opt> ' ],
        [ 'input', 'INPUT <dataType> ON <BotName> <variable1opt> <variable2opt> TO <variable3> <variable4opt> <variable5opt> FROM <variable4>' ],
        [ 'input', 'INPUT <dataType> TO <variable3> <variable4opt> <variable5opt> FROM <variable6>' ],
        [ 'input', 'INPUT <dataType> ON <BotName> <variable1opt> <variable2opt> TO <variable3> <variable4opt> <variable5opt>' ],
        [ 'input', 'INPUT <dataType> TO <variable3> <variable4opt> <variable5opt>' ],
        [ 'dataType', 'date' ],
        [ 'dataType', 'positiveInteger' ],
        [ 'dataType', 'positiveDecimal' ],
        [ 'dataType', 'string' ],
        [ 'dataType', 'phone' ],
        [ 'dataType', 'email' ],
        [ 'dataType', 'integer' ],
        [ 'dataType', 'decimal' ],
        [ 'dataType', 'arrobaUsername' ],
        [ 'dataType', 'image' ],
        [ 'dataType', 'audio' ],
        [ 'dataType', 'voice' ],
        [ 'dataType', 'video' ],
        [ 'dataType', 'videonote' ],
        [ 'dataType', 'document' ],
        [ 'dataType', 'location' ],
        [ 'dataType', 'any' ],
        [ 'dataType', 'sound' ],
        [ 'dataType', 'clip' ],
        [ 'dataType', 'visual' ],
        [ 'dataType', 'media' ],
        [ 'sentence', '<set>' ],
        [ 'set', 'SET <variable1> <expr> ON <BotName> <variable2>' ],
        [ 'set', 'SET <variable> <expr>' ],
        [ 'sentence', '<clear>' ],
        [ 'clear', 'CLEAR ON' ],
        [ 'clear', 'CLEAR OPTIONS' ],
        [ 'clear', 'CLEAR WORD' ],
        [ 'clear', 'CLEAR ALL CHANNEL' ],
        [ 'clear', 'CLEAR ALL' ],
        [ 'clear', 'CLEAR <variable1> ... CHANNEL' ],
        [ 'clear', 'CLEAR <variable1> ...' ],
        [ 'sentence', 'INC <variable> <Number>' ],
        [ 'sentence', 'INC <variable>' ],
        [ 'sentence', 'DEC <variable> <Number>' ],
        [ 'sentence', 'DEC <variable>' ],
        [ 'sentence', 'MUL <variable> <Number>' ],
        [ 'sentence', 'DIV <variable> <Number>' ],
        [ 'sentence', 'MOD <variable> <Number>' ],
        [ 'sentence', 'CONCAT <variable1> <variable2> ...' ],
        [ 'sentence', 'SPLIT <variable1> <variable2> TO <variable3> ...' ],
        [ 'sentence', 'COUNT OPTIONS TO <variable>' ],
        [ 'sentence', 'LOG <variable> ...' ],
        [ 'sentence', 'LOCALE <variable>' ],
        [ 'sentence', 'ABORT' ],
        [ 'sentence', 'DATA SET <variable> FROM <expr>' ],
        [ 'sentence', 'DATA GET <variable1> TO <variable2>' ],
        [ 'sentence', '<channel>' ],
        [ 'channel', 'CHANNEL DELETE ALL' ],
        [ 'channel', 'CHANNEL DELETE <variable>' ],
        [ 'channel', 'CHANNEL <channelSpec> TO <variable1> <variable2> FOR <variable3>' ],
        [ 'channel', 'CHANNEL <channelSpec> TO <variable1> <variable2>' ],
        [ 'channelSpec', 'current' ],
        [ 'channelSpec', 'new' ],
        [ 'sentence', 'TUNNEL tunnelSpec FROM <variable1> TO <variable2> <variable3> <variable4>' ],
        [ 'tunnelSpec', 'text' ],
        [ 'tunnelSpec', 'all' ],
        [ 'tunnelSpec', 'allButText' ],
        [ 'tunnelSpec', 'nothing' ],
        [ 'tunnelSpec', 'image' ],
        [ 'tunnelSpec', 'audio' ],
        [ 'tunnelSpec', 'voice' ],
        [ 'tunnelSpec', 'video' ],
        [ 'tunnelSpec', 'videonote' ],
        [ 'tunnelSpec', 'document' ],
        [ 'tunnelSpec', 'location' ],
        [ 'sentence', 'USERID FROM <variable>' ],
        [ 'sentence', 'USERID TO <variable>' ],
        [ 'sentence', 'TRACE' ],
        [ 'sentence', 'NOTRACE' ],
        [ 'sentence', 'DISPLAY <variable1> TITLE <variable2> ON <BotName> <variable3opt> <variable4opt>' ],
        [ 'sentence', 'DISPLAY <variable1> ON <BotName> <variable2opt> <variable3opt>' ],
        [ 'sentence', 'BLOAD <variable1> TO <variable2>' ],
        [ 'sentence', 'BSAVE <variable1> AS <variable2>' ],
        [ 'sentence', 'EXTRACT <extractSpec> FROM <variable1> TO <variable2>' ],
        [ 'extractSpec', 'sound' ],
        [ 'extractSpec', 'latitude' ],
        [ 'extractSpec', 'longitude' ],
        [ 'extractSpec', 'width' ],
        [ 'extractSpec', 'height' ],
        [ 'extractSpec', 'format' ],
        [ 'extractSpec', 'length' ],
    ];



    /** @var array[] Placeholder para el copy-paste de los tokens de BotBasic tal como se definen en la hoja de cálculo de la gramática */
    public $tokens = [
        101 => [ ':', 'sequence' ],
        102 => [ 'IF', 'if' ],
        103 => [ 'THEN', '' ],
        104 => [ 'ELSE', '' ],
        105 => [ 'EQ', 'eq' ],
        106 => [ 'NEQ', 'neq' ],
        107 => [ 'GT', 'gt' ],
        108 => [ 'GTE', 'gte' ],
        109 => [ 'LT', 'lt' ],
        110 => [ 'LTE', 'lte' ],
        111 => [ 'NOT', 'not' ],
        112 => [ 'EMPTY', 'empty' ],
        113 => [ 'ENTRYTYPE', 'entrytype' ],
        114 => [ 'ENTRYTEXT', 'entrytext' ],
        115 => [ 'ENTRYID', 'entryid' ],
        116 => [ 'GOTO', 'goto' ],
        117 => [ 'ON', 'on' ],
        118 => [ 'PRINT', 'print' ],
        119 => [ 'END', 'end' ],
        120 => [ 'REM', 'rem' ],
        121 => [ 'GOSUB', 'gosub' ],
        122 => [ 'ARGS', 'args' ],
        123 => [ 'RETURN', 'return' ],
        124 => [ 'CALL', 'call' ],
        125 => [ 'TO', '' ],
        126 => [ 'MENU', 'menu' ],
        127 => [ 'OPTION', 'option' ],
        128 => [ 'OPTIONS', 'options' ],
        129 => [ 'WORD', 'word' ],
        130 => [ 'TITLE', 'title' ],
        131 => [ 'PAGER', 'pager' ],
        132 => [ 'pagerShort', '' ],
        133 => [ 'pagerLong', '' ],
        134 => [ 'INPUT', 'input' ],
        135 => [ 'FROM', '' ],
        136 => [ 'date', '' ],
        137 => [ 'positiveInteger', '' ],
        138 => [ 'positiveDecimal', '' ],
        139 => [ 'string', '' ],
        140 => [ 'phone', '' ],
        141 => [ 'email', '' ],
        142 => [ 'SET', 'set' ],
        143 => [ 'CLEAR', 'clear' ],
        144 => [ 'ALL', '' ],
        145 => [ 'INC', 'inc' ],
        146 => [ 'DEC', 'dec' ],
        147 => [ 'MUL', 'mul' ],
        148 => [ 'DIV', 'div' ],
        149 => [ 'MOD', 'mod' ],
        150 => [ 'CONCAT', 'concat' ],
        151 => [ 'USERID', 'userid' ],
        152 => [ 'SPLIT', 'split' ],
        153 => [ 'LOG', 'log' ],
        154 => [ 'BOTNAME', 'botname' ],
        155 => [ 'LOCALE', 'locale' ],
        156 => [ 'ALL', '' ],
        157 => [ 'CHANNELS', '' ],
        158 => [ 'CHANNEL', 'channel' ],
        159 => [ 'DELETE', '' ],
        160 => [ 'TUNNEL', 'tunnel' ],
        161 => [ 'current', '' ],
        162 => [ 'new', '' ],
        163 => [ 'text', '' ],
        164 => [ 'all', '' ],
        165 => [ 'allButText', '' ],
        166 => [ 'nothing', '' ],
        167 => [ 'image', '' ],
        168 => [ 'audio', '' ],
        169 => [ 'voice', '' ],
        170 => [ 'video', '' ],
        171 => [ 'document', '' ],
        172 => [ 'location', '' ],
        173 => [ 'ABORT', 'abort' ],
        174 => [ 'DATA', 'data' ],
        175 => [ 'GET', '' ],
        176 => [ 'TRACE', 'trace' ],
        177 => [ 'NOTRACE', 'notrace' ],
        178 => [ 'FOR', '' ],
        179 => [ 'NEXT', '' ],
        180 => [ 'AS', '' ],
        181 => [ 'COUNT', 'count' ],
        182 => [ 'APPVERSION', 'appversion' ],
        183 => [ 'RUNTIMEID', 'runtimeid' ],
        184 => [ 'any', '' ],
        185 => [ 'sound', '' ],
        186 => [ 'clip', '' ],
        187 => [ 'visual', '' ],
        188 => [ 'media', '' ],
        189 => [ 'integer', '' ],
        190 => [ 'decimal', '' ],
        191 => [ 'arrobaUsername', '' ],
        192 => [ 'ERR', 'err' ],
        193 => [ 'PEEK222', 'peek222' ],
        194 => [ 'DISPLAY', 'display' ],
        195 => [ 'BLOAD', 'bload' ],
        196 => [ 'BSAVE', 'bsave' ],
        197 => [ 'EXTRACT', 'extract' ],
        198 => [ 'latitude', '' ],
        199 => [ 'longitude', '' ],
        200 => [ 'width', '' ],
        201 => [ 'height', '' ],
        202 => [ 'format', '' ],
        203 => [ 'length', '' ],
        204 => [ 'CHATAPP', 'chatapp' ],
        205 => [ 'USERNAME', 'username' ],
        206 => [ 'USERLOGIN', 'userlogin' ],
        207 => [ 'USERLANG', 'userlang' ],
    ];



    /** @const Texto que en un código de BotBasic define el EntryHook */
    const BBCODEHOOK_ENTRY = 'ENTRYHOOK';

    /** @const Texto que en un código de BotBasic define el MenuHook */
    const BBCODEHOOK_MENU  = 'MENUHOOK';

    /** @const Texto que en un código de BotBasic define el InputHook */
    const BBCODEHOOK_INPUT = 'INPUTHOOK';

    /** @const Texto que en un código de BotBasic define el EventHook */
    const BBCODEHOOK_EVENT = 'EVENTHOOK';

    /** @const Separador de texto por defecto */
    const SEP = ' ';

    /** @const Especificador de un valor nulo en BotBasic; el predicado lógico EMPTY compara con este valor */
    const NOTHING = '';



    /**
     * Utility para futura no-tan-posible localización de los tokens de BotBasic permitiendo manejo genérico
     *
     * @param  string   $tokenStr   Texto del token localizado
     * @return mixed                Texto del token genérico
     */
    protected function TOK ($tokenStr)
    {
        return $tokenStr;   // trivial, just for now; in the future BotBasic directives could be localized
    }



    ///////////////////////////////////////////
    // BB COMMON DEFINITIONS (PARSER & RUNTIME)
    ///////////////////////////////////////////



    /** Prefijo con el que deben comenzar todos los métodos de acceso a variables mágicas implementadas en PHP */
    const MAGICVARS_PHPACCESSOR_PREFIX      = "mv_";

    /** Sufijo con el que deben terminar el getter de todas las variables mágicas implementadas en PHP */
    const MAGICVARS_PHPACCESSOR_POSTFIX_GET = "_get";

    /** Sufijo con el que deben terminar el setter de todas las variables mágicas implementadas en PHP */
    const MAGICVARS_PHPACCESSOR_POSTFIX_SET = "_set";

    /** Prefijo con el que deben comenzar todos los métodos que implentan primitivas de BotBasic en PHP */
    const PRIMITIVES_PHPACCESSOR_PREFIX     = "pr_";

    /** Prefijo con el que deben comenzar todos los métodos que implentan menús predefinidos de BotBasic en PHP */
    const MENUS_PHPACCESSOR_PREFIX          = "mn_";



    /** @const Meta-tipo de datos manejados en métodos de BizModelAdapter: indica lista de parámetros de cualquier longitud y tipo */
    const TYPE_FREELIST = 90;

    /** @const Tipo de datos manejados en métodos de BizModelAdapter: indica una primitiva de BotBasic que no retorna un valor */
    const TYPE_VOID     = 91;

    /** @const Tipo de datos manejados en métodos de BizModelAdapter: indica tipo booleano */
    const TYPE_BOOLEAN  = 92;

    /** @const Tipo de datos manejados en métodos de BizModelAdapter: indica tipo entero */
    const TYPE_INTEGER  = 93;

    /** @const Tipo de datos manejados en métodos de BizModelAdapter: indica tipo número de punto flotante */
    const TYPE_DECIMAL  = 94;

    /** @const Tipo de datos manejados en métodos de BizModelAdapter: indica tipo cadena de texto */
    const TYPE_STRING   = 95;

    /** @const Tipo de datos manejados en métodos de BizModelAdapter: indica tipo variable;
     *         al incorporar un valor de retorno de este tipo en BotBasic se tratará de convertir a número o string */
    const TYPE_VARIANT  = 96;

    /** @var array Prefijos de los nombres de los parámetros IN/OUT de primitivas y menús predefinidos,
     *             tal como aparecen en las secciones de programas BotBasic */
    static protected $types = [
        self::TYPE_FREELIST => "...",
        self::TYPE_VOID     => "nul",
        self::TYPE_BOOLEAN  => "bol",
        self::TYPE_INTEGER  => "int",
        self::TYPE_DECIMAL  => "dec",
        self::TYPE_STRING   => "str",
        self::TYPE_VARIANT  => "vrn",
    ];



    //////////////////////////////
    // DEFINITIONS AND CONSTRUCTOR
    //////////////////////////////



    /** @var array Mapa de tokens inverso al especificado con $tokens */
    protected $tokensByName = [];

    /** @var array Contenido de la sección "Messages" de un programa BotBasic */
    protected $messages     = [];

    /** @var array Contenido de la sección "Menus" de un programa BotBasic */
    protected $predefmenus  = [];

    /** @var array Contenido de la sección "MagicVars" de un programa BotBasic */
    protected $magicvars    = [];

    /** @var array Contenido de la sección "Primitives" de un programa BotBasic */
    protected $primitives   = [];

    /** @var array Contenido de la sección "Program" de un programa BotBasic */
    protected $bots         = [];



    /**
     * BotBasic constructor
     *
     * Genera estructuras de datos comunes a las subclases.
     */
    protected function __construct ()
    {
        // nueva estructura de datos para reglas agrupada por symbol
        $rulesBySymbol = [];
        foreach ($this->rules as $pair) {
            list ($symbol, $spec) = $pair;
            if (! isset($rules[$symbol])) { $rules[$symbol] = []; }
            // las reglas de la gramatica sufren una transformación asi:
            // IN  : CALL <primitiveFunction> <optVariable1a> ... TO <optVariable1b> ...
            // OUT : CALL primitivefunction variable ... TO variable ...
            $symbol = strtolower($symbol);
            $parts  = $this->splitCell($spec);
            foreach ($parts as &$part) {
                if (1 == preg_match('/^<.*>$/', $part)) {
                    $part = substr($part, 1, strlen($part) - 2);
                    $matches = [];
                    $res = preg_match('/^(.+)[0-9]+[a-z]*$/', $part, $matches);   // eliminar los sufijos tipo 1a, 12b, 3opt, etc
                    if ($res == 1) { $part = $matches[1]; }
                    if (1 == preg_match('/^[a-z]/', $part)) { $part = strtolower($part); }
                }
            }
            if (! isset($rulesBySymbol[$symbol])) { $rulesBySymbol[$symbol] = []; }
            $rulesBySymbol[$symbol][] = join(self::SEP, $parts);
        }
        $this->rules = $rulesBySymbol;

        // nueva estructura de datos para reglas/tokens
        $this->tokensByName = [];
        foreach ($this->tokens as $id => $vals) { $this->tokensByName[$vals[0]] = [ $id, $vals[1] ]; }
        // incorporar para cada symbol un arreglo con todos los stopwords (palabras reservadas que aparecen en cada una de las definiciones del simbolo)
        foreach ($this->rules as $symbol => $rules) {
            $this->rules[$symbol]['_stopwords'] = [];
            $stopwords =& $this->rules[$symbol]['_stopwords'];
            foreach ($rules as $rule) {
                $rtokens = $this->splitCell($rule);
                foreach ($rtokens as $rtoken) {
                    if (! $this->isReservedWord($rtoken)) { continue; }
                    if (! in_array($rtoken, $stopwords)) { $stopwords[] = $rtoken; }
                }
            }
        }
    }



    /**
     * Retorna los nombres de los bots del programa BotBasic
     *
     * @return string[]     Nombres de los bots
     */
    public function getBBbotNames ()
    {
        return array_keys($this->getProgram());
    }



    /**
     * Retorna una estructura de datos parseada desde el código BotBasic: mensajes
     *
     * @return array        Estructura interna para mensajes
     */
    public function getMessages ()
    {
        return $this->messages;
    }



    /**
     * Retorna una estructura de datos parseada desde el código BotBasic: menús
     *
     * @return array        Estructura interna para menús
     */
    public function getPredefmenus ()
    {
        return $this->predefmenus;
    }



    /**
     * Retorna una estructura de datos parseada desde el código BotBasic: variables mágicas
     *
     * @return array        Estructura interna para variables mágicas
     */
    public function getMagicvars ()
    {
        return $this->magicvars;
    }



    /**
     * Retorna una estructura de datos parseada desde el código BotBasic: primitivas
     *
     * @return array        Estructura interna para primitivas
     */
    public function getPrimitives ()
    {
        return $this->primitives;
    }



    /**
     * Retorna una estructura de datos parseada desde el código BotBasic: programa para cada bot
     *
     * @return array        Estructura interna para el programa, para cada bot
     */
    public function getProgram ()
    {
        return $this->bots;
    }



    /**
     * Retorna el primer componente de una versión de programa BotBasic
     *
     * @param  string   $codeVersion    Versión (ej. 1.2.3bis)
     * @return string                   Componente de la versión (ej. 1)
     */
    static public function getMajorCodeVersionFor ($codeVersion)
    {
        return explode('.', $codeVersion)[0];
    }



    /**
     * Retorna el segundo componente de una versión de programa BotBasic
     *
     * @param  string   $codeVersion    Versión (ej. 1.2.3bis)
     * @return string                   Componente de la versión (ej. 2)
     */
    static public function getMinorCodeVersionFor ($codeVersion)
    {
        $parts = explode('.', $codeVersion);
        return count($parts) == 1 ? null : $parts[1];
    }



    /**
     * Retorna el tercer componente de una versión de programa BotBasic
     *
     * @param  string   $codeVersion    Versión (ej. 1.2.3bis)
     * @return string                   Componente de la versión (ej. 3bis)
     */
    static public function getSubminorCodeVersionFor ($codeVersion)
    {
        $parts = explode('.', $codeVersion, 3);
        return count($parts) <= 2 ? null : $parts[2];
    }



    ////////////
    // UTILITIES
    ////////////



    /**
     * Separa una cadena de texto en sus componentes usando el separador self::SEP como delimitador
     *
     * @param  string   $cell               String a separar
     * @param  bool     $preserveEmpties    Si se preservarán componentes para cadenas de texto vacías
     * @return string[]                     Segmentos separados
     */
    protected function splitCell ($cell, $preserveEmpties = true) {
        $cell = trim($cell, self::SEP);
        return $cell == '' && ! $preserveEmpties ? [] : preg_split('/ +/', $cell);
    }



    /**
     * Retorna una representación textual del tipo de dato interno (entero) manejado por BotBasic para argumentos de entrada y salida de menus y
     * primitivas, el cual coincide con los prefijos que deben poseer los argumentos en las definiciones expresadas en el programa BotBasic
     *
     * @param  int      $type   Tipo de dato interno
     * @return string           Prefijo de 4 caracteres
     */
    static public function datatypeString ($type)
    {
        return self::$types[$type];
    }



    /////////////////////
    // CHECKING FUNCTIONS
    /////////////////////



    /**
     * Determina si el argumento es un identificador que comienza por letra minúscula
     *
     * @param  string   $text   Identificador
     * @return bool
     */
    protected function isLowercase ($text)
    {
        return 1 === preg_match('/^[a-z][a-zA-Z0-9]*$/', $text);
    }



    /**
     * Determina si el argumento es un identificador que no posee letras minúsculas
     *
     * @param  string   $text
     * @return bool
     */
    protected function isUppercase ($text)
    {
        return 1 === preg_match('/^[A-Z][A-Z0-9]*$/', $text);
    }



    /**
     * Determina si el argumento es un identificador que comienza por letra mayúscula y no es palabra clave por poseer un número o una minúscula
     *
     * @param  string   $text
     * @return bool
     */
    protected function isCapitalized  ($text)
    {
        return 1 === preg_match('/^[A-Z][a-zA-Z0-9]*[a-z][a-zA-Z0-9]*$/', $text);
    }



    /**
     * Determina si el argumento es un número positivo entero o decimal (usa "." como separador decimal)
     *
     * @param  string   $text
     * @return bool
     */
    protected function isNumber ($text)
    {
        return 1 === preg_match('/^-?(0|[1-9][0-9]*)(\.[0-9]+)?$/', $text);
    }



    /**
     * Determina si el argumento tiene formato de código de versión de programa BotBasic
     *
     * Ejemplos de números de versión   correctos: 4.5.10 , 0.0.0 ;
     * Ejemplos de números de versión incorrectos: 4.5.10b , 04.5.10 , 4.05.10 , 4.5.010 , 4.5.10bis
     *
     * @param  string   $text
     * @return bool
     */
    protected function isCodeVersion ($text)
    {
        return 1 === preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/', $text);
        // return 1 === preg_match('/^[0-9]+\.[0-9]+\.[0-9]+[a-z]*$/', $text);   // para componentes de numeros de version tipo texto y no entero
    }



    /**
     * Determina si el argumento tiene formato de comando de Telegram: comienzo con "/" y después minúsculas, dígitos y guiones
     *
     * @param  string   $text
     * @return bool
     */
    protected function isCommand ($text)
    {
        return 1 === preg_match('/^\/[a-z0-9-]*$/', $text);
    }



    /**
     * Determina si el argumento es una directiva lógica de BotBasic (que evalúa a true/false y se usa en IF's)
     *
     * @param  string   $text
     * @return bool
     */
    protected function isLogicDirective ($text)
    {
        return in_array($text, [ 'EQ', 'NEQ', 'GT', 'GTE', 'LT', 'LTE', 'NOT', 'EMPTY' ]);
    }



    /**
     * Determina si el argumento una directiva de tipo expresión (que evalúa a un valor string o número)
     *
     * @param  string   $text
     * @return bool
     */
    protected function isExpressionDirective ($text)
    {
        return in_array($text, [ 'APPVERSION', 'RUNTIMEID', 'BOTNAME', 'CHATAPP', 'USERNAME', 'USERLOGIN', 'USERLANG', 'ENTRYTYPE', 'ENTRYTEXT', 'ENTRYID', 'ERR', 'PEEK222' ]);
    }



    /**
     * Determina si el argumento es una directiva (palabra reservada) de BotBasic
     *
     * @param  string   $text
     * @return bool
     */
    protected function isReservedWord ($text)
    {
        return is_string($text) && isset($this->tokensByName[$text]);
    }



    /**
     * Determina si el argumento es un símbolo de la gramática de BotBasic (ej. "dataType")
     *
     * @param  string   $text
     * @return bool
     */
    protected function isSymbol ($text)
    {
        return is_string($text) && isset($this->rules[$text]);
    }



    /**
     * Determina si el argumento es valor que puede ser asignado a una variable
     *
     * @param  string   $text
     * @return bool
     */
    protected function isRvalue ($text)
    {
        return $this->isNumber($text) || $this->isLvalue($text) || $this->isExpressionDirective($text);
    }



    /**
     * Determina si el argumento es un nombre de variable
     *
     * @param  string   $text
     * @return bool
     */
    protected function isLvalue ($text)
    {
        return $this->isLowercase($text);
    }



    /**
     * Determina si el argumento es una variable o la palabra reservada OPTIONS (para parsear CALL ... TO OPTIONS)
     *
     * @param  string   $text
     * @return bool
     */
    protected function isLvalueOrOptionsRW ($text)
    {
        return $this->isLowercase($text) || $text == 'OPTIONS';
    }



    /**
     * Determina si el argumento es una variable mágica, definida así en el código BotBasic
     *
     * No se valida aún la existencia de los métodos de PHP en la clase BizModelAdapter
     *
     * @param  string   $text
     * @return bool
     */
    public function isMagicVar ($text)
    {
        return is_string($text) && in_array($text, $this->magicvars);
    }



    /**
     * Determina si el argumento es una variable común (no mágica)
     *
     * @param  string   $text
     * @return bool
     */
    protected function isCommonVar ($text)
    {
        return (! $this->isMagicVar($text)) && $this->isLvalue($text);
    }



    /**
     * Devuelve true
     *
     * @param  string   $text
     * @return bool
     */
    protected function isAnything ($text)
    {
        unset($text);   // a kind of doDummy()
        return true;
    }



    /**
     * Determina si el argumento es un locale válido para el sistema (no necesariamente implementado en el programa BotBasic)
     *
     * @param  string   $text
     * @return bool
     */
    protected function isLocale ($text)
    {
        $locales = BotConfig::validLocales();
        return in_array($text, $locales);
    }



    /**
     * Determina si el argumento no debe ser expandido como símbolo de la gramática durante el parsing
     *
     * @param  string   $text
     * @return bool
     */
    protected function isFinal ($text)
    {
        return $this->isLvalue($text) || $this->isRvalue($text) || $this->isReservedWord($text);
    }



    /**
     * Determina si el argumento es un bot de BotBasic definido así en el código
     *
     * @param  string   $text
     * @return bool
     */
    protected function isBot ($text)
    {
        return is_string($text) && isset($this->bots[$text]);
    }



    /**
     * Determina si el argumento es un bot de BotBasic o la palabra reservada CHANNELS (para parsear PRINT ... ON CHANNELS)
     *
     * @param  string   $text
     * @return bool
     */
    protected function isBotOrChannelsRW ($text)
    {
        return is_string($text) && (isset($this->bots[$text]) || $text == 'CHANNELS');
    }



    /**
     * Determina si el argumento es un nombre de mensaje (variable con valor prefijado) definido así en el código de BotBasic
     *
     * @param  string   $text
     * @return bool
     */
    protected function isMessageName ($text)
    {
        return is_string($text) && isset($this->messages[$text]);
    }



    /**
     * Determina si el argumento es un menú predefinido de BotBasic (según código del programa)
     *
     * No se valida aún la existencia del método de PHP en la clase BizModelAdapter
     *
     * @param  string   $text
     * @return bool
     */
    protected function isMenu ($text)
    {
        return is_string($text) && isset($this->predefmenus[$text]);
    }



    /**
     * Determina si el argumento es una primitiva de BotBasic, definida así en el programa
     *
     * No se valida aún la existencia del método de PHP en la clase BizModelAdapter
     *
     * @param  string   $text
     * @return bool
     */
    protected function isPrimitive ($text)
    {
        return is_string($text) && isset($this->primitives[$text]);
    }



    /**
     * Determina si el argumento es una etiqueta equivalente a número de línea en el programa BotBasic de un bot específico
     *
     * @param  string   $text   Texto a evaluar
     * @param  string   $bot    Nombre del bot
     * @return bool
     */
    protected function isLabel ($text, $bot)
    {
        return isset($this->bots[$bot]['labels'][$text]);
    }



    /**
     * Determina si el argumento tiene formato correcto para ser evaluado como Rvalue, incluyendo la verificación de entidades entre llaves
     * como {variables} y {PrimitivasQueRecibanNingunParametroYdevuelvanUno}
     *
     * @param  string   $spec
     * @return bool
     */
    protected function isMessageSpec ($spec)
    {
        while (($pos1 = strrpos($spec, '{')) !== false) {
            $pos2 = strpos($spec, '}', $pos1 + 1);
            if ($pos2 === false)                                                            { return false; }
            if ($pos2 - 1 < $pos1 + 1)                                                      { return false; }
            $name = substr($spec, $pos1 + 1, $pos2 - $pos1 - 1);
            if (! ($this->isPrimitive($name) || $this->isLvalue($name)))                    { return false; }
            if ($this->isPrimitive($name) && ! $this->checkPrimitiveArgcounts($name, 0, 1)) { return false; }
            $spec = substr_replace($spec, '<', $pos1, 1); $spec = substr_replace($spec, '>', $pos2, 1);   // avoid matching of {} in the next iteration
        }
        return true;
    }



    /**
     * Indica si un nombre de variable es apto para ser parametro de entrada o salida de un MENU (TO) o de un CALL <Primitive> (ARGS / TO)
     *
     * No se aplica a nombres de variables del programa BotBasic sino a la definición de las primitivas y menús en sus respectivas secciones
     * del código BotBasic.
     *
     * @param  string   $text   Nombre de la variable, que debe contener un prefijo de tipo de los especificados en $this->types (ej. intgAbc)
     * @return bool|int         false si la variable no tiene un prefijo válido de tipo; de otro modo, el tipo (numérico) de la variable
     */
    protected function isMenuOrPrimitiveInOutVar ($text)
    {
        for ($i = 0; $i < count(self::$types); $i++) {
            $typeId     = array_keys(self::$types)[$i];
            $type       = self::$types[$typeId];
            $hintedType = substr($text, 0, strlen($type));
            if ($hintedType == $type) {
                if ($typeId != self::TYPE_FREELIST && strlen($type) == strlen($text) ||   // fue especificado un nombre de tipo sin caracteres adicionales
                    $typeId == self::TYPE_FREELIST && strlen($type) != strlen($text)) {   // a excepcion de cuando es ... en cuyo caso no deben estar presentes
                    return false;
                }
                return $typeId;
            }
        }
        return false;
    }



    /**
     * Determina si la primitiva cuyo nombre se pasa como argumento puede recibir un número variable de parámetros
     *
     * @param  string   $name
     * @return bool
     */
    protected function isFreeargsPrimitive ($name)
    {
        return $this->primitives[$name][0][ count($this->primitives[$name][0])-1 ] == self::TYPE_FREELIST;
    }



    /**
     * Determina si la primitiva cuyo nombre se pasa como argumento recibe ningún parámetro y devuelve exactamente uno
     *
     * @param  string   $name
     * @return bool
     */
    protected function isNoargsPrimitive ($name)
    {
        return $this->isPrimitive($name) && $this->checkPrimitiveArgcounts($name, 0, 1);
    }



    /**
     * Determina si un menú predefinido tiene un número específico de parámetros de entrada y de salida
     *
     * @param  string       $menu       Nombre del menú predefinido
     * @param  null|int     $inCount    Cantidad correcta de parámetros de entrada, o null para no verificar
     * @param  null|int     $outCount   Cantidad correcta de parámetros de salida, o null para no verificar
     * @return bool
     */
    protected function checkMenuArgcounts ($menu, $inCount = null, $outCount = null)
    {
        if (! $this->isMenu($menu)) { return false; }
        $res = true;
        if ($inCount  !== null) { $res &= count($this->predefmenus[$menu][0]) == $inCount;  }
        if ($outCount !== null) { $res &= count($this->predefmenus[$menu][1]) == $outCount; }
        return $res;
    }



    /**
     * Determina si una primitiva tiene un número específico de parámetros de entrada y de salida
     *
     * @param  string       $primitive  Nombre de la primitiva
     * @param  null|int     $inCount    Cantidad correcta de parámetros de entrada, o null para no verificar
     * @param  null|int     $outCount   Cantidad correcta de parámetros de salida, o null para no verificar
     * @return bool
     */
    protected function checkPrimitiveArgcounts ($primitive, $inCount = null, $outCount = null)
    {
        if (! $this->isPrimitive($primitive)) { return false; }
        $res = true;
        if ($inCount  !== null) { $res &= count($this->primitives[$primitive][0]) == $inCount;  }
        if ($outCount !== null) { $res &= count($this->primitives[$primitive][1]) == $outCount; }
        return $res;
    }



    /////////////////
    // TREE DESCENDER
    /////////////////



    /**
     * T3procesador genérico del código de BotBasic (específicamente la sección PROGRAM) para el parser y el interpretador (runtime)
     *
     * El procesador se ayuda con un subprocesador ($processor) que es una función que es invocada para cada directiva encontrada de BotBasic.
     * Debido a que se invoca para cada una de las líneas del código, el $processor recibe su contenido en forma cruda: tokens separados por
     * delimitadores (espacio) para el parser, y arreglos estructurados según la gramática para el runtime.
     *
     * El primer token de cada línea de código determina el submétodo que será invocado. $processor es "parser" para el parser y "runner" para
     * el runtime. Para una línea de código que comience con PRINT, se invocarán los métodos parser4print() y runner4print(). La excepción es, para
     * el runtime, la invocación para líneas que tengan múltiples instrucciones separadas con ":"; en este caso se invocará runner4sequence().
     *
     * Modo estatico (parser):
     * * Procesa las lineas del programa de la primera a la ultima, en secuencia.
     * * Procesa todos los subcomponentes de todas las sentencias, incluyendo todas las partes de un IF/THEN/ELSE.
     * * No es necesario implementar parser4...() para: ':', IF, NOT; pero se debe hacer para los predicados logicos.
     *
     * Modo semantico (para el interprete):
     * * Toma en cuenta el flow control (IF, GOTO, GOSUB).
     * * No es necesario implementar reglas de procesamiento dentro de $processor() para: ':', IF, NOT.
     * * $processor() no es invocado para las directivas de predicados logicos; éstas son evaluadas con evalLogicPredicate().
     *
     * @param string    $bot                    Nombre del bot que permite identificar el código
     * @param bool      $inSemanticMode         false para hacer parsing; true para corridas del código en el runtime
     * @param string    $processor              "parser" o "runner"
     * @param null      $startAtLinenoOrLabel   Número de línea a partir del cual empieza el procesamiento, o la respectiva etiqueta
     */
    protected function runWith ($bot, $inSemanticMode, $processor, $startAtLinenoOrLabel = null)
    {
        // following line is commented out because each runtime::runPart() would reset prints[], inputs[] and menus[], so now the call is in runtime::execute()
        // $this->initRunStructs();
        //
        // ]RUN<ENTER>
        if (count($this->bots[$bot]['sentences']) == 0) { return; }   // empty program
        $linenos     = array_keys($this->bots[$bot]['sentences']);
        $finalLineno = $linenos[ count($this->bots[$bot]['sentences']) - 1 ];
        $lineno      = $startAtLinenoOrLabel === null || ! $inSemanticMode ? $linenos[0] : (
                            $this->isNumber($startAtLinenoOrLabel) ? $startAtLinenoOrLabel : $this->bots['labels'][$startAtLinenoOrLabel]
                       );
        $profilerMark = "L$lineno"; if ($inSemanticMode) { Log::profilerStart($profilerMark, '', "entering BotBasic::runWith()"); }
        $timeLimit = $inSemanticMode ? BOTBASIC_MAX_EXEC_TIME_SECONDS : BOTBASIC_MAX_PARSE_TIME_SECONDS;
        $startTime = microtime(true);
        $this->running($bot, true);
        while ($this->running($bot)) {
            $parsedStatement =& $this->bots[$bot]['sentences'][$lineno];
            $nextLineno = $lineno == $finalLineno ? -999 : $linenos[ array_search($lineno, $linenos) + 1 ];
            if ($parsedStatement === false) {
                if ($lineno == $finalLineno) { $this->running($bot, false);     }
                else                         { $lineno = $nextLineno; continue; }   // a parsing error occured previously
            }
            elseif (! is_array($parsedStatement)) { continue; }

            // execute a sentence
            Log::profilerStep($profilerMark, "running descender() on line $lineno...");
            //Log::register(Log::TYPE_DEBUG, "]RUN: on $lineno...");
            $jump2lineno = $this->descender($bot, $lineno, $parsedStatement, $inSemanticMode, $processor);

            if ($inSemanticMode) {

                if (is_bool($jump2lineno)) {
                    $jump2lineno = -1;
                    $content     = null;
                    Log::register(Log::TYPE_RUNTIME, "BB1164 BB statement retorna bool en vez de lineno", $this, $lineno, $parsedStatement[0]);
                }
                elseif (! is_integer($jump2lineno)) {
                    $jump2lineno = -1;
                    $content     = null;
                    Log::register(Log::TYPE_RUNTIME, "BB1170 BB statement retorna string o valor distinto de entero en vez de lineno", $this, $lineno, $parsedStatement[0]);
                }
                if ($jump2lineno == -1) {
                    if ($lineno == $finalLineno) { $this->running($bot, false); } else { $lineno = $nextLineno; }
                }
                else {
                    $lineno = $jump2lineno;
                }

            } else {   // parsing mode

                if ($lineno == $finalLineno) { $this->running($bot, false); }
                else                         { $lineno = $nextLineno;       }

            }
            $lastTime = microtime(true);
            if ($lastTime - $startTime > $timeLimit && ! BOTBASIC_DEBUG) {
                if ($inSemanticMode) {
                    Log::register(Log::TYPE_BBCODE, "BB1188 Posible ciclo infinito en codigo de BB; interrumpiendo ejecucion...", $this, $lineno);
                } else {
                    $this->addError($lineno, "$bot: parsing of program exceeded process allowed time");
                }
                $this->running($bot, false);
            }
        }
        if ($inSemanticMode) { Log::profilerStop($profilerMark, "finishing BotBasic::runWith()"); }

        // flush the content of the buffers as splashes (runtime) or do nothing (parsing)
        // $this->flushEverything();   // not now; this will be invoked manually after returning from BBRT:route()
    }



    /**
     * Inicializa estructuras de datos que no se inicializan en el constructor de esta clase
     *
     * Las subclases deben implementar este método para permitir la ejecución de runWith().
     *
     * @return void
     */
    abstract protected function initRunStructs ();



    /**
     * Método auxiliar invocado por runWith() que procesa exactamente una línea de código o una sentencia anidada en otra línea
     *
     * @param  string   $bot                Nombre del bot de BotBasic
     * @param  int      $lineno             Número de linea actual
     * @param  array    $parsedContent      Producto del separador de tokens (parser) o estructura compleja (parser); esta estructura es alterada
     *                                      como producto de la ejecución de este método
     * @param  bool     $inSemanticMode     false al invocar desde el parser; true cuando se llama desde el interpretador
     * @param  string   $processor          Nombre de metodo de subclases de BotBasic ("parser4..." o "runner4...")
     * @return bool|int                     modo parsing: bool (success status);
     *                                      modo runtime: bool para predicados logicos de sentencias IF; newlineno para statements que cambian el flujo
     *                                                    de ejecución; -1 si no hay cambio en flujo de ejecución
     */
    protected function descender ($bot, $lineno, &$parsedContent, $inSemanticMode, $processor)
    {
        $directive = $parsedContent[0];

        if ($inSemanticMode) {

            if ($directive == $this->TOK('IF')) {
                $logicResult = $this->descender($bot, $lineno, $parsedContent[1], $inSemanticMode, $processor);
                if ($logicResult === true)                                  { return $this->descender($bot, $lineno, $parsedContent[3], $inSemanticMode, $processor); }
                elseif ($logicResult === false && isset($parsedContent[5])) { return $this->descender($bot, $lineno, $parsedContent[5], $inSemanticMode, $processor); }
                elseif ($logicResult === false)                             { return -1; }   // no ELSE clause
                else                                                        { return -1; }   // esto no deberia pasar (un logic statement no retorno un bool)
            }
            elseif ($directive == $this->TOK('NOT')) {
                $res = $this->descender($bot, $lineno, $parsedContent[1], $inSemanticMode, $processor);
                return ! $res ? true : false;
            }
            elseif ($this->isLogicDirective($directive)) {
                $args = isset($parsedContent[1]) && is_array($parsedContent[1]) ? $parsedContent[1] : array_slice($parsedContent, 1);
                return $this->evalLogicPredicate($directive, $args, $lineno, $bot);   // bool
            }
            elseif ($directive == $this->TOK(':')) {
                for ($i = 1; $i < count($parsedContent); $i++) {
                    $newLineno = $this->descender($bot, $lineno, $parsedContent[$i], $inSemanticMode, $processor);
                    if ($newLineno != -1) { return $newLineno; }   // control flow changed; don't execute the rest of the sequence's sentences
                }
                return -1;   // don't change control flow
            }
            else {   // common statement
                $newLineno = $this->$processor($bot, $lineno, $parsedContent);
                return $newLineno;   // will be -1 for no flow control change
            }

        } else {   // parse mode

            $res = true;
            if ($directive == $this->TOK('IF')) {
                $res &= $this->descender($bot, $lineno, $parsedContent[1], $inSemanticMode, $processor);
                $res &= $this->descender($bot, $lineno, $parsedContent[3], $inSemanticMode, $processor);
                if (isset($parsedContent[5])) { $res &= $this->descender($bot, $lineno, $parsedContent[5], $inSemanticMode, $processor); }
            }
            elseif ($directive == $this->TOK('NOT')) {
                $parsedContent = array_slice($parsedContent, 1);
                $res = $this->descender($bot, $lineno, $parsedContent, $inSemanticMode, $processor);
                array_unshift($parsedContent, $directive);
            }
            elseif ($directive == $this->TOK(':')) {
                for ($i = 1; $i < count($parsedContent); $i++) { $res &= $this->descender($bot, $lineno, $parsedContent[$i], $inSemanticMode, $processor); }
            }
            else {   // common statement
                $res = $this->$processor($bot, $lineno, $parsedContent);
            }
            return $res;

        }
    }



    /**
     * Evalúa un predicado lógico; es llamado de forma trivial en el caso del parser
     *
     * @param  string   $directive      Primera palabra de la instrucción BotBasic
     * @param  array    $args           Argumentos del predicado lógico; puede ser una estructura anidada como para IF NOT NOT NOT EQ 1 2 THEN ...
     * @param  int      $lineno         Número de línea del programa BotBasic
     * @param  string   $bot            Nombre del bot del programa BotBasic
     * @return bool                     Resultado de la ejecución del predicado lógico
     */
    abstract protected function evalLogicPredicate ($directive, &$args, $lineno, $bot);



    /**
     * Refleja un error de parsing
     *
     * Usar solamente en el parser; en el runtime usar la clase Log
     *
     * @param int       $lineno     Número de línea asociado al mensaje
     * @param string    $message    Mensaje
     */
    abstract protected function addError ($lineno, $message);



    /**
     * Fija o consulta el status de funcionamiento de runWith() sobre las líneas de código que debe procesar
     *
     * Nota de implementación: no es necesario $bot a menos que desde la corrida de un bot (webhook hit) se quiera activar la corrida
     * de otro bot (func call) a partir de una linea especifica
     *
     * @param  string       $bot        Nombre del bot de BotBasic
     * @param  null|bool    $status     null para consultar; true/false para fijar el status de funcionamiento
     * @return bool                     Status de funcionamiento consultado o recién fijado
     */
    protected function running ($bot, $status = null)
    {
        static $runStatus = [];
        if ($status !== null) { $runStatus[$bot] = $status; }
        return $runStatus[$bot];
    }



    /**
     * IDE spoofer
     *
     * @param mixed     $arg
     */
    protected function doDummy ($arg) {}



}
