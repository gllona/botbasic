<?php
/**
 * GROMM: in a single-class component, GROMM is a Relational-Object Object Mapper for the Masses
 *
 * @author      Gorka G LLona                               <gorka@gmail.com>
 * @license     https://opensource.org/licenses/MIT         Licencia MIT
 * @see         https://github.com/gllona/gromm             github repo
 * @version     1.0 - 03.apr.2017
 * @since       0.0.alpha1 - 06.oct.2014
 */



namespace T3;   // put here your own namespace

use ReflectionClass;



/**
 * Class Persistable
 *
 * @method int|null id     (bool|int $arg = null, bool $taint = false)
 */
abstract class Persistable
{



    const NULLIFY_REFERENCES_DEFAULT = false;

    const FORCE_WRITE = true;   // experimental (set to false)



    static private $store = [];

    private $members = [];



    /**
     * @param  array        $record
     * @param  Persistable  $over
     * @param  bool         $clone
     * @param  array        $values
     * @return Persistable
     */
    static protected function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        error_log("PERSISTABLE: SHOULD REDEFINE factory() IN SUBCLASSES");
        self::doDummy([ $record, $over, $clone, $values ]);
        return $over;
    }

    static public function factoryHelper ($record, $over, $clone, $values, $className)
    {
        /** @var Persistable $over */   /** @var Persistable $className */
        if ($record === true)                               { $record = null;                               }   // in old code: first arg can be true so obj will be DB-writen and ID generated (INACTIVE BEHAVIOUR)
        if (self::isAbstract($className) && $over === null) { return null;                                  }
        if ($clone && $over === null)                       { return null;                                  }
        if ($clone)                                         { ($obj = clone $over)->setTainted()->id(null); }
        elseif (! is_string($className))                    { return null;                                  }
        elseif (! $clone && $over !== null)                 { $obj = $over;                                 }   /** @var Persistable $obj */
        else {
            if (__NAMESPACE__ != '' && strpos($className, '\\') === false) { $className = __NAMESPACE__ . '\\' . $className; }
            $obj = new $className();
        }
        $parentClassName = get_parent_class($className);   /** @var Persistable $parentClassName */
        if ($parentClassName != __CLASS__) { $parentClassName::factory($record, $obj, false, $values); }
        $obj->setTainted();
        return $obj;
        // old code:
        //if ($record === true) { $record = null; /*$getId = true;*/ } else { /*$getId = false;*/ }   // first arg can be true so obj will be DB-writen and ID generated (INACTIVE BEHAVIOUR)
        //if ($over === null && self::isAbstract($className)) { return null; }
        //if ($over === null || $over !== null && $clone && get_class($over) != $className) {
        //    if (__NAMESPACE__ != '' && strpos($className, '\\') === false) { $className = __NAMESPACE__ . '\\' . $className; }   /** @var Persistable $className */
        //    $obj = new $className();                           /** @var Persistable $obj             */
        //    $parentClassName = get_parent_class($className);   /** @var Persistable $parentClassName */
        //    if ($parentClassName != __CLASS__) { $parentClassName::factory($record, $obj, false, $values); }
        //    $obj->setTainted();
        //    if ($over !== null) { self::migrate($over, $obj); }
        //}
        //else {   // $over is same $class (or) not cloning so don't check class matching
        //    if (! $clone) {  $obj = $over;                                }
        //    else          { ($obj = clone $over)->setTainted()->id(null); }
        //}
        //// if ($getId) { $obj->DBwrite($obj); }   // (commented out in old code)
        //return $obj;
    }



    static protected function DBread ($criterium, $factory = null)
    {
        error_log("PERSISTABLE: SHOULD REDEFINE DBread() IN SUBCLASSES");
        self::doDummy([ $criterium, $factory ]);
        return null;
    }

    static protected function DBreadHelper ($criterium, $className, $factory = null)
    {
        $getClass = function ($sqlEnumItem)
        {
            return preg_replace_callback('/(^|_)([a-z])/', function ($matches) { return strtoupper($matches[2]); }, $sqlEnumItem);
        };
        $getDBname = function ($classOrFieldName)
        {
            $tmp = preg_replace_callback('/[A-Z]/', function ($matches) { return '_' . strtolower($matches[0]); }, $classOrFieldName);
            $tmp = substr($tmp, ($pos = strrpos($tmp, '\\')) === false ? 0 : $pos+1);
            return preg_replace('/^_/', '', $tmp);
        };
        $getClassesAndTables = function ($className) use ($getDBname)
        {
            $res = [ [ $className, $getDBname($className) ] ];
            while (($className = get_parent_class($className)) !== false && $className !== __CLASS__) { $res[] = [ $className, $getDBname($className) ]; }
            return $res;
        };
        $getFields = function ($className)
        {
            $res = [];   /** @var Persistable $className */
            foreach ($className::membersList() as $item) { $res[] = is_array($item) ? $item[0] : $item; }
            return $res;
        };
        $theFactory = function ($row, $className) use ($factory)
        {
            /** @var Persistable $className */ /** @var Callable $factory */
            if (__NAMESPACE__ != '' && strpos($className, '\\') === false) { $className = __NAMESPACE__ . '\\' . $className; }
            $obj = $factory === null ? $className::factory($row) : $factory($row, $className);
            if ($obj !== null) { $obj->setLoaded(); }
            return $obj;
        };

        // make WHERE part
        if (is_string($criterium) && ! is_numeric($criterium) || $criterium === true) {
            $sqlWhere = $criterium === true ? "TRUE" : $criterium;
            $asArray  = true;
        }
        else {
            if     (is_numeric($criterium)) { $in = [ $criterium ];                         $asArray = false; }
            elseif (is_array($criterium))   { $in = array_unique($criterium, SORT_NUMERIC); $asArray = true;  }
            else                            { return null;                                                    }
            $sqlWhere = $getDBname($className) . '.id IN (' . implode(', ', $in) . ')';
        }

        // make SELECT part
        $pairs  = $getClassesAndTables($className);
        $fields = [];
        $first  = true;
        foreach ($pairs as $pair) {
            list ($class, $table) = $pair;
            foreach ($getFields($class) as $field) {
                if (! $first && $field == 'id') { continue; }
                $fields[] = $first && $field == 'id' ? $table . '.id' : $getDBname($field);
            }
            $first = false;
        }
        $selectPart = implode(', ', $fields);

        // make FROM part
        $baseTable = $pairs[0][1];
        $joins     = [];
        for ($i = 1; $i < count($pairs); $i++) {
            list (, $table) = $pairs[$i];
            $joins[] = "JOIN $table ON $baseTable.id = $table.id";
        }
        $fromPart = $baseTable . ' ' . implode(' ', $joins);

        // make WHERE part
        $wheres = [];
        for ($i = 0; $i < count($pairs); $i++) {
            list ($class, $table) = $pairs[$i];   /** @var Persistable $class */
            if (__NAMESPACE__ != '' && strpos($class, '\\') === false) { $class = __NAMESPACE__ . '\\' . $class; }
            if ($class::DBsoftDeletions()) { $wheres[] = "AND $table.deleted IS NULL"; }
        }
        $wherePart = "($sqlWhere) " . implode(' ', $wheres);

        // read from DB
        $limitPart = $asArray ? '' : 'LIMIT 1';
        $sql = <<<END
            SELECT $selectPart
              FROM $fromPart
             WHERE $wherePart
             $limitPart; 
END;
        $rows = DBbroker::query($sql);
        if ($rows === false) { return null; }

        // assemble, avoiding instance duplication
        $res = [];
        foreach ($rows as $row) {
            if (isset($row['tipo'])) { $className = ! isset($row['subtipo']) ? $getClass($row['tipo']) : $getClass($row['subtipo']); }   // FIXME wired!
            if (($obj = self::instance($className, $row['id'])) !== null) { $res[] = $obj;                                           }
            else                                                          { $res[] = $theFactory($row, $className);                  }
        }

        // return according to expectations
        return $asArray ? $res : (count($res) == 0 ? false : $res[0]);
    }



    static protected function DBwrite ($obj, $innerCall = false)
    {
        error_log("PERSISTABLE: SHOULD REDEFINE DBwrite() IN SUBCLASSES");
        self::doDummy([ $obj, $innerCall ]);
        return null;
    }

    /**
     * @param  Persistable          $obj
     * @param  Persistable|string   $className
     * @param  array                $values
     * @param  bool                 $innerCall
     * @return bool|null
     */
    static protected function DBwriteHelper ($obj, $values, $innerCall, $className)
    {
        $getDBname = function ($classOrFieldName)
        {
            $tmp = preg_replace_callback('/[A-Z]/', function ($matches) { return '_' . strtolower($matches[0]); }, $classOrFieldName);
            $tmp = substr($tmp, ($pos = strrpos($tmp, '\\')) === false ? 0 : $pos+1);
            return preg_replace('/^_/', '', $tmp);
        };
        $getFields = function ($className)
        {
            $res = [];   /** @var Persistable $className */
            foreach ($className::membersList() as $item) { $res[] = is_array($item) ? $item[0] : $item; }
            return $res;
        };
        $magicize = function ($value)
        {
            // flip date component ordering
            $matches = [];
            if (0 === preg_match('/^\'([0-9]{2})-([0-9]{2})-([0-9]{4})(( [0-9]{2}:[0-9]{2}:[0-9]{2})?)\'$/', $value, $matches)) { return $value; }   // FIXME wired!
            list (, $dd, $mm, $yyyy, $hhmmss) = $matches;
            return "'$yyyy-$mm-$dd$hhmmss'";
        };

        // return if object is already saved or just loaded without changes
        if ($obj->getStatus() === null && ! self::FORCE_WRITE) {
            return true;
        }

        // start transaction
        if (! $innerCall) {
            $res = DBbroker::exec('START TRANSACTION;');
            if ($res === false) { return null; }
        }

        // parent class' call
        if (($parentClassName = get_parent_class($className)) !== false && $parentClassName !== __CLASS__) {   /** @var Persistable $parentClassName */
            if ($parentClassName::DBwrite($obj, true) === null) { return null; }
        }

        // prepare SET part
        if (($fields = $getFields($className)) === null) { return null; }
        $sets = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $values)) { continue; }
            $value = is_a($values[$field], __CLASS__) ? $values[$field]->id() : $magicize($values[$field]);
            $sets[] = $getDBname($field) . " = " . ($value);
        }
        $setPart = implode(', ', $sets);

        // do either DB insert or update
        $tableName = $getDBname($className);
        $id        = $obj->id();
        if ($id === null) {
            $sql = <<<END
                INSERT INTO $tableName
                   SET $setPart;
END;
            $id = DBbroker::exec($sql, true);
            if ($id === false) { DBbroker::exec('ROLLBACK;'); return null; }
            $obj->id($id, false);   // don't taint
        }
        else {
            $sql = <<<END
                UPDATE $tableName
                   SET $setPart
                 WHERE id = $id;
END;
            $res = DBbroker::exec($sql);
            if ($res === false) { DBbroker::exec('ROLLBACK;'); return null; }
        }

        // commit
        if (! $innerCall) {
            DBbroker::exec('COMMIT;');
        }

        // ready
        return true;
    }



    static protected function DBdelete ($obj)
    {
        error_log("PERSISTABLE: SHOULD REDEFINE DBdelete() IN SUBCLASSES");
        self::doDummy($obj);
        return null;
    }

    static protected function DBsoftDeletions ()
    {
        error_log("PERSISTABLE: SHOULD REDEFINE DBsoftDeletions() IN SUBCLASSES");
        return false;
    }

    /**
     * @param  Persistable          $obj
     * @param  Persistable|string   $className
     * @param  bool                 $innerCall
     * @return bool|null
     */
    static protected function DBdeleteHelper ($obj, $innerCall, $className)
    {
        $getDBname = function ($classOrFieldName)
        {
            $tmp = preg_replace_callback('/[A-Z]/', function ($matches) { return '_' . strtolower($matches[0]); }, $classOrFieldName);
            $tmp = substr($tmp, ($pos = strrpos($tmp, '\\')) === false ? 0 : $pos+1);
            return preg_replace('/^_/', '', $tmp);
        };

        // start transaction
        if (! $innerCall) {
            $res = DBbroker::exec('START TRANSACTION;');
            if ($res === false) { return null; }
        }

        // parent class' call
        if (($parentClassName = get_parent_class($className)) !== false && $parentClassName !== __CLASS__) {   /** @var Persistable $parentClassName */
            // if (self::DBdeleteHelper($obj, $parentClassName, true) === null) { return null; }   // old way
            if ($parentClassName::DBdelete($obj) === null) { return null; }
        }

        // get ID as in DB
        $id = $obj->oldId();

        // delete from DB
        $tableName = $getDBname($className);
        if ($className::DBsoftDeletions()) {
            $sql = <<<END
                UPDATE $tableName
                   SET deleted = NOW()
                 WHERE id = $id;
END;
        }
        else {
            $sql = <<<END
                DELETE FROM $tableName
                 WHERE id = $id;
END;
        }
        $res = DBbroker::exec($sql);
        if ($res === false) { DBbroker::exec('ROLLBACK;'); return null; }

        // commit
        if (! $innerCall) {
            DBbroker::exec('COMMIT;');
        }

        // ready
        return true;
    }



    static protected function membersList () { return []; }   // redefine in subclasses



    static private function isint ($value)
    {
        if (! (is_int($value) || is_string($value))) { return false; }
        return 1 === preg_match('/^[0-9]+$/', $value);
    }

    static protected function isAbstract ($className)
    {
        if (__NAMESPACE__ != '' && strpos($className, '\\') === false) { $className = __NAMESPACE__ . '\\' . $className; }
        $info = new ReflectionClass($className);
        return $info === null ? null : $info->isAbstract();
    }

    static protected function doDummy ($something) { return null; }



    private function get ($field)
    {
        return ! isset($this->members[$field]) ? null : $this->members[$field];
    }

    private function set ($field, $value, $taint = true)
    {
        if ($taint) { $this->setTainted(); }
        return $this->members[$field] = $value;   // assign & return for chaining
    }



    final public function __call ($name, $arguments)
    {
        $getFullMembersList = function ($obj)
        {
            /** @var Persistable $className */
            $className = get_class($obj);
            $res       = $className::membersList();
            while (($className = get_parent_class($className)) !== false && $className !== __CLASS__) { $res = array_merge($res, $className::membersList()); }
            return $res;
        };

        // prepare values
        if (! (count($arguments) == 0 || count($arguments) == 1 || count($arguments) == 2 && $arguments[1] === false)) { return null; }
        $found = false;
        $class = null;   /** @var Persistable $class */
        foreach ($getFullMembersList($this) as $item) {
            if (is_array($item))  { list ($aField, $aClass) = $item;           }
            else                  { list ($aField, $aClass) = [ $item, null ]; }
            if ($name == $aField) { $found = true; $class = $aClass; break;    }
        }
        if (! $found) { return null; }
        $set       =   array_key_exists(0, $arguments) && ! is_bool($arguments[0]);   // isset($arguments[0]) && ! is_bool($arguments[0]);   // wrong because arg[0] can be === null
        $setTo     = ! isset($arguments[0]) || isset($arguments[0]) && is_bool($arguments[0])  ? null  : $arguments[0];
        $onlyId    =   isset($arguments[0])                         && $arguments[0] === true  ? true  : false;
        $onlyOldId =   isset($arguments[0])                         && $arguments[0] === false ? true  : false;
        $taint     =   isset($arguments[1])                         && $arguments[1] === false ? false : true;
        if ($class !== null && __NAMESPACE__ != '') { $class = __NAMESPACE__ . '\\' . $class; }

        // get (returns null, int (object's id) if attribute is object and argument is true, attribute value (object, scalar, array, ...) if no argument is passed)
        if (! $set) {
            $value = $this->get($name);   /** @var Persistable|int|null $value */
            if ($class === null) {   // attribute
                return $value;
            }
            else {   // reference
                if     ($onlyId)    { return $value === null || self::isint($value) ? $value : $value->id();                                        }
                elseif ($onlyOldId) { return $value === null || self::isint($value) ? $value : $value->oldId();                                     }
                else                { return $value === null ? null : (! self::isint($value) ? $value : $this->set($name, $class::DBread($value))); }
            }
        }

        // set (returns assigned value, for chaining) (can set to null, int or object)
        else {
            if ($class === null) {   // attribute
                return $this->set($name, $setTo);
            }
            else {   // reference
                if (! ($setTo === null || self::isint($setTo) || is_a($setTo, $class))) { return null; }
                return $this->set($name, $setTo, $taint);
            }
        }
    }



    private function setStatus ($status)
    {
        foreach (self::$store as &$pair) {
            if ($this === $pair[1]) { $pair[0] = $status; return; }
        }
        self::$store[] = [ $status, $this ];   // [ null4nonTainted_OR_true4tainted_OR_intOldId4pendingDelete_OR_false4alreadyDeleted, instance ]
    }



    private function getStatus ()
    {
        foreach (self::$store as &$pair) {
            if ($this === $pair[1]) { return $pair[0]; }
        }
        return -1;   // because 'null' is already used as status
    }



    /**
     * @param Persistable   $object
     */
    static private function updateReferences ($object = null)
    {
        // update (nullify if needed) all objects' references                               // general case
        if ($object === null) {
            foreach (self::$store as &$pair) {
                $status =& $pair[0]; $obj = $pair[1];   /** @var Persistable $obj */
                if (! (self::isint($status) || $status === false)) {                        // not deleted
                    self::updateReferences($obj);                                           // process $obj
                }
            }
        }

        // update (nullify if needed) this object's references                              // particular case
        else {
            foreach ($object->membersList() as $item) {
                // get a member that is a reference and the target object
                if (! is_array($item)) { continue; }                                        // not a reference
                list ($field, $class) = $item;
                $refId = $object->$field(true);
                $deleted = false;
                if ($refId === null) {                                                      // try to grab the ref's ID previous to its possible deletion
                    $refId = $object->$field(false);
                    if ($refId !== null) { $deleted = true; }
                }
                if (self::instance($class, $refId, $deleted) === null) { continue; }        // target not loaded, so not deleted
                $ref = $object->$field();   /** @var Persistable $ref */
                if ($ref === null) { continue; }                                            // already nullified
                // check if the target was deleted so update the reference to null
                foreach (self::$store as &$pair) {
                    $status =& $pair[0]; $obj = $pair[1];   /** @var Persistable $obj */
                    if (! (self::isint($status) || $status === false)) { continue; }        // not deleted
                    if ($obj !== $ref)                                 { continue; }        // not the same reference
                    $object->$field(null);
                }
            }
        }
    }



    static final protected function setNullifyReferences ($yesOrNot = null)
    {
        static $status = self::NULLIFY_REFERENCES_DEFAULT;
        if ($yesOrNot !== null) { $status = $yesOrNot; }
        return $status;
    }

    final protected function setLoaded ($nullifyReferences = null)
    {
        $this->setStatus(null);
        if ($nullifyReferences !== null ? $nullifyReferences : self::setNullifyReferences()) { self::updateReferences($this); }
        return $this;
    }

    final protected function setTainted ($nullifyReferences = null)
    {
        $this->setStatus(true);
        if ($nullifyReferences !== null ? $nullifyReferences : self::setNullifyReferences()) { self::updateReferences($this); }
        return $this;
    }

    final protected function setDeleted ($nullifyReferences = null)
    {
        $id = $this->id();
        $this->id(null);
        if (self::isint($id)) { $this->setStatus($id); }   // when status is an int, it represents the DB id of record to delete
        if ($nullifyReferences !== null ? $nullifyReferences : self::setNullifyReferences()) { self::updateReferences(); }
        return $this;
    }



    final static protected function instance ($className, $id, $grabIfDeleted = false)
    {
        if ($id === null) { return null; }
        if (__NAMESPACE__ != '') { $className = __NAMESPACE__ . '\\' . $className; }
        foreach (self::$store as $pair) {
            list ($status, $obj) = $pair;   /** @var Persistable $obj */
            if (self::isint($status) && ! $grabIfDeleted) { continue; }
            if (is_a($obj, $className) && (self::isint($status) ? $obj->oldId() : $obj->id()) == $id) { return $obj; }
        }
        return null;
    }

    final static protected function recover ($className, $idOrIds)   // no se garantiza que el orden del resultset sea igual al del argumento
    {
        /** @var Persistable $className */
        $ids = is_array($idOrIds) ? $idOrIds : [ $idOrIds ];
        $res = $toRead = [];
        foreach ($ids as $id) {
            $obj = self::instance($className, $id);
            if ($obj !== null) { $res[] = $obj; } else { $toRead[] = $id; }
        }
        if (count($toRead) > 0) {
            if (__NAMESPACE__ != '' && strpos($className, '\\') === false) { $className = __NAMESPACE__ . '\\' . $className; }
            $fetched = $className::DBread($toRead);
            if ($fetched !== null) { $res = array_merge($res, $fetched); }
        }
        return is_array($idOrIds) ? $res : (count($res) == 0 ? null : $res[0]);
    }



    static public function DBquery ($className, $fieldCriteria, $sqlWhereAnd = null, $fieldToDerive = null, $loadWhenDeriving = true)
    {
        $getDBname = function ($classOrFieldName)
        {
            $tmp = preg_replace_callback('/[A-Z]/', function ($matches) { return '_' . strtolower($matches[0]); }, $classOrFieldName);
            $tmp = substr($tmp, ($pos = strrpos($tmp, '\\')) === false ? 0 : $pos+1);
            return preg_replace('/^_/', '', $tmp);
        };

        // build WHERE part
        $parts = [];
        foreach ($fieldCriteria as $field => $criterium) {
            $parts[] = $criterium === null ? 'TRUE' : $getDBname($field) . ' IN (' . implode(', ', array_merge([ -1 ], self::grabIds($criterium))) . ')';
        }
        if (__NAMESPACE__ != '' && strpos($className, '\\') === false) { $className = __NAMESPACE__ . '\\' . $className; }   /** @var Persistable $className */
        $wherePart = ($sqlWhereAnd === null ? '' : "($sqlWhereAnd) AND ") . implode(' AND ', $parts) . (! $className::DBsoftDeletions() ? '' : ' AND deleted IS NULL');

        // fetch IDs from DB
        $sql = <<<END
            SELECT id
              FROM test_pelea
             WHERE $wherePart;
END;
        $rows = DBbroker::query($sql);   // note the double-fetch over the table (1st: get IDs; 2nd, next lines: get data over table hierarchy)
        if ($rows === false) { return null; }
        $ids = [];
        foreach ($rows as $row) { $ids[] = $row['id']; }

        // recover (DB-reading if not in cache) objects from IDs
        $objs = self::recover($className, $ids);
        if ($objs === null) { return null; }

        // optionally consider fetched objects as bridges and derive the result to targets (indexed by a field of the bridge class/table)
        if ($fieldToDerive !== null) {
            $objs = array_map(function ($obj) use ($fieldToDerive, $loadWhenDeriving) { return $loadWhenDeriving ? $obj->$fieldToDerive() : $obj->$fieldToDerive(true); }, $objs);
        }

        // ready
        return $objs;
    }



    final protected function oldId ()
    {
        foreach (self::$store as $pair) {
            list ($status, $obj) = $pair;   /** @var Persistable $obj */
            if ($this === $obj && self::isint($status)) { return $status; }   // deleted flag saves old (currently in-DB) id
        }
        return null;
    }



    final static public function persist ()
    {
        $force = self::FORCE_WRITE;   // force writing of non-tainted objects? --- experimental!!!
        $magic = -888;                // COBOL-like magic tmp status for force writing of non-tainted objects already writen
        /** @var Persistable $callingClass */
        for ($changed = true; $changed; ) {
            $changed = false;
            foreach (self::$store as &$pair) {
                $status =& $pair[0]; $obj = $pair[1];   /** @var Persistable $obj */
                if ($status === ($force ? $magic : null) || $status === false) { continue; }   // object is not tainted or already DB-deleted
                $hasPendingRefs = false;
                foreach ($obj->membersList() as $item) {
                    if (! is_array($item)) { continue; }   // not a reference
                    list ($fieldName, $className) = $item;
                    $refId = $obj->$fieldName(true);
                    if ($refId === null) { continue; }
                    if (self::instance($className, $refId) === null) { continue; }   // target not loaded
                    $ref = $obj->$fieldName();   /** @var Persistable $ref */
                    if ($ref->id() === null && ! self::isint($ref->oldId())) {       // a newly created target
                        $hasPendingRefs = true;
                        break;
                    }
                }
                if ($hasPendingRefs) { continue; }   // process in next for(;;) iteration
                if ($status === true || ($force ? $status === null : false)) { $obj->DBwrite($obj); $status = $force ? $magic : null; }   // null  == not tainted
                elseif (self::isint($status)) { $obj->DBdelete($obj); $status = false; }   // false == already DB-deleted
                $persisted[] = $obj;
                $changed     = true;
            }
        }
        if ($force) { foreach (self::$store as &$pair) { if ($pair[0] === $magic) { $pair[0] = null; } } }
    }



    final protected function fillMember ($objFieldName, $record, $recordFieldName = null, $defaultValue = null, $jsonDecode = false)
    {
        if (is_array($record) && $recordFieldName === null) { return; }
        $found = ! is_array($record) || is_array($record) && array_key_exists($recordFieldName, $record);
        $value = ! is_array($record) ? ($defaultValue !== null ? $defaultValue : $record) : (! $found ? $defaultValue : $record[$recordFieldName]);
        // Log::register("Persistable:fillMember: [$objFieldName|$record|$recordFieldName|$defaultValue|$jsonDecode] [found=" . ($found ? 1 : 0) . '|' . ($value === null ? 'null' : ($value === false ? 'false' : ($value === true ? 'true' : $value))) . ']');
        if ($found || $defaultValue !== null) { $this->$objFieldName(! $jsonDecode ? $value : json_decode($value)); }
    }

    final static protected function grabIds ($collection)
    {
        if ($collection === null)    { return [];                     }
        if (! is_array($collection)) { $collection = [ $collection ]; }
        $res = [];
        foreach ($collection as $elem) {
            if ($elem === null) { continue; }
            $res[] = self::isint($elem) ? $elem : $elem->id();
        }
        return $res;
    }

    final protected function migrate ($from, $to)
    {
        /** @var Persistable $from */ /** @var Persistable $to */
        foreach ($from->membersList() as $item) {
            if (is_array($item))  { list ($field, $class) = $item;           }
            else                  { list ($field, $class) = [ $item, null ]; }
            if ($class === null) { $to->$field($from->$field()); }
            else {
                $id = $from->$field(true);
                if (self::instance($class, $id) === null) { $to->$field($id);             }
                else                                      { $to->$field($from->$field()); }
            }
        }
    }



}
