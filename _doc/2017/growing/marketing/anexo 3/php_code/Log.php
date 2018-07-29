<?php



use \DateTime, \DateTimeZone;



class Log
{

    public const TYPE_GENERIC    = 100;
    public const TYPE_DATABASE   = 101;   // attrib :: null
    public const TYPE_RUNTIME    = 102;   // attrib :: userid
    public const TYPE_BBCODE     = 103;   //
    public const TYPE_CHATMEDIUM = 104;

    static private $types = [
        self::TYPE_GENERIC    => "generic",
        self::TYPE_DATABASE   => "database",
        self::TYPE_RUNTIME    => "runtime",
        self::TYPE_BBCODE     => "bbcode",
        self::TYPE_CHATMEDIUM => "chatmedium",
    ];

    public const ATTRIB_DUMMY              = 200;
    public const ATTRIB_EXCEPTION          = 201;
    public const ATTRIB_DOMAIN_USERID      = 202;
    public const ATTRIB_CHATMEDIUM_USERID  = 203;
    public const ATTRIB_CHATMEDIUM_NAME    = 204;
    public const ATTRIB_CHATMEDIUM_CHANNEL = 205;
    public const ATTRIB_RESOURCE           = 206;
    public const ATTRIB_BB_BOT             = 207;
    public const ATTRIB_BB_LINENO          = 208;
    public const ATTRIB_BB_SYMBOL          = 209;

    static private $attribNames = [
        self::ATTRIB_CHATMEDIUM_NAME    => "/chatmedium_name",       // '/' before name logs it before the message
        self::ATTRIB_CHATMEDIUM_CHANNEL => "/chatmedium_channel",
        self::ATTRIB_CHATMEDIUM_USERID  => "/chatmedium_user",
        self::ATTRIB_DOMAIN_USERID      => "/domain_user",
        self::ATTRIB_RESOURCE           => "resource/",              // '/' after name logs it after the message
        self::ATTRIB_BB_BOT             => "bot/",
        self::ATTRIB_BB_LINENO          => "lineno/",
        self::ATTRIB_BB_SYMBOL          => "symbol/",
        self::ATTRIB_EXCEPTION          => "exception/",
    ];

    static private $dontLogThese = [ self::ATTRIB_DUMMY ];   // ATTRIB... elements

    static private $logToDB = false;

    static private $fh = null;



    /**
     * @param $type     int         type constant
     * @param $message  string      free text
     * @param $attribs  array       [ [ attribNameConstant, content(string|resource|exception) ], ... ]
     */
    static public function register ($type, $message, $attribs)
    {
        if ($attribs !== null) {
            if (count($attribs) > 0 && ! is_array($attribs[0])) { $attribs = [ $attribs ]; }
        } else {
            $attribs = [];
        }
        // attempt to include runtime specific data in the log entry
        // any NULL included and duplicated attribute won't affect previous ones because read order is left-to-right in $attribs
        $bbr = BotBasicRuntime::getInstance();
        $attribs[] = [ self::ATTRIB_CHATMEDIUM_NAME,    $bbr->getChatMedium()->getName()        ];
        $attribs[] = [ self::ATTRIB_CHATMEDIUM_CHANNEL, $bbr->getChatMedium()->getChannelName() ];
        $attribs[] = [ self::ATTRIB_CHATMEDIUM_USERID,  $bbr->getChatMedium()->getUserId()      ];
        $attribs[] = [ self::ATTRIB_DOMAIN_USERID,      $bbr->getDomainUser()->getId()          ];
        $attribs[] = [ self::ATTRIB_BB_BOT,             $bbr->getBot()                          ];
        // build the message
        $message = self::makeFullMessage($type, $message, $attribs);
        // write in logfile
        if (self::$fh === null) {
            $fh = fopen(BOTBASIC_LOGFILE, "a");
            if ($fh === false) { echo "CAN'T OPEN LOGFILE FOR WRITING... \n$message"; }   // log to console
            else               { self::$fh = $fh;                                     }
        }
        if (self::$fh !== null) {
            $res = fwrite(self::$fh, $message);
            if ($res === false) { echo "CAN'T WRITE INTO LOGFILE... \n$message"; }   // log to console
            else                { fflush(self::$fh);                             }
        }
        // optionally write in DB
        if (self::$logToDB) { DBbroker::DBlogger($message); }
    }



    static private function makeFullMessage ($type, $message, $attribs)
    {
        if (! is_string($message)) { $message = "BAD_MESSAGE_ASSIGNED"; }
        $now  = self::makeCurrentDatetimeString();
        $text = "[$now] " . strtoupper(self::$types[$type]);
        foreach (array_keys(self::$attribNames) as $ak => $an) {
            list ($a, $putAfterMessage) = self::getAttrib($attribs, $ak);
            if (! $putAfterMessage) { $text .= ' ' . $an . ': ' . $a; }
        }
        $text .= ' ' . $message;
        foreach (array_keys(self::$attribNames) as $ak => $an) {
            list ($a, $putAfterMessage) = self::getAttrib($attribs, $ak);
            if ($putAfterMessage) { $text .= ' ' . $an . ': ' . $a; }
        }
        return $text;
    }


    /**
     * @param $attribs  array       [ [ attribNameConstant, content(string|resource|exception) ], ... ]
     * @param $type     int         attribNameConstant for filtering $attribs
     * @return          string|null
     */
    static private function getAttrib ($attribs, $type)
    {
        if ($attribs === null)                    { return null; }
        if (in_array($type, self::$dontLogThese)) { return null; }
        $res = [];
        foreach ($attribs as $attrib) {
            list ($t, $content) = $attrib;
            if ($type === $t && $content !== null) {
                if     (is_object($content) && (get_class($content) == "Exception" || is_subclass_of($content, "Exception"))) {
                    $content = '<' . $content->getFile() . ':' . $content->getLine() . ':' . $content->getMessage() . '>';
                }
                elseif (is_object($content) && get_class($content) == "Resource") {
                    $content = $content->serializeBrief();
                }
                elseif (! is_string($content)) {
                    $content = "INVALID_CONTENT";
                }
                $res[] = $content;
            }
        }
        return count($res) == 0 ? null : $res[0];
    }



    static private function makeCurrentDatetimeString ($withMicroSeconds = true)
    {
        $mt = microtime(true);
        $ms = sprintf("%06d", ($mt - floor($mt)) * 1000000);
        $dt = new DateTime( date('Y-m-d@H:i:s.' . $ms, $mt) );
        $tz = new DateTimeZone(BOTBASIC_TIMEZONE);
        $dt->setTimezone($tz);
        return $dt->format("Y-m-d H:i:s" . ($withMicroSeconds ? ".u" : ""));
    }



    static public function logAlsoOnDB ($onOff = true)
    {
        self::$logToDB = $onOff;
    }



    static public function alertOnMobile ($types, $resourcesToMatch)
    {
    }



}
