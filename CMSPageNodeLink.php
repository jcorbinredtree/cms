<?php

/**
 * CMSPageNodeLink definition
 *
 * PHP version 5
 *
 * LICENSE: The contents of this file are subject to the Mozilla Public License Version 1.1
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 *
 * The Original Code is Red Tree Systems Code.
 *
 * The Initial Developer of the Original Code is Red Tree Systems, LLC. All Rights Reserved.
 *
 * @category     CMS
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2009 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      2.0
 */

require_once 'lib/database/DatabaseObjectLink.php';

class CMSPageNodeLink extends DatabaseObjectLink
{
    public static $table = 'cms_page_node';
    public static $FromClass = 'CMSPage';
    public static $ToClass = 'CMSNode';
    public static $AdditionalKey = array('area');
    public static $LinkOrderClause = array('weight', '{to_key}');
    public static $CustomSQL = array(
        'new_link_weight' =>
            'SELECT IFNULL(MAX(weight)+1, 0) FROM {table} WHERE {keyspec}',
        'fixsel' =>
            'SELECT {to_key} FROM {table} WHERE {from_key}=? AND area=? ORDER BY weight',
        'fixupd' =>
            'UPDATE {table} SET weight=:weight WHERE {keyspec}',
        'pagecount' =>
            'SELECT COUNT({to_key}) WHERE {from_key}=? AND area=?'
    );

    protected $area;
    protected $weight;

    private static $cache=array();

    static public function loadFor(DatabaseObject $for)
    {
        return DatabaseObjectLink::loadFor(__CLASS__, $for);
    }

    static public function deleteFor(DatabaseObject $for)
    {
        return DatabaseObjectLink::deleteFor(__CLASS__, $for);
    }

    static protected function cacheKey($from, $to, $area)
    {
        if ($from instanceof DatabaseObject) {
            $from = $from->id;
        }
        if ($to instanceof DatabaseObject) {
            $to = $to->id;
        }
        if (! is_int($from)) {
            throw new InvalidArgumentException('invalid from');
        }
        if (! is_int($to)) {
            throw new InvalidArgumentException('invalid to');
        }
        assert(is_string($area));
        return $from.'_'.$to.'_'.$area;
    }

    static protected function factory($data)
    {
        $cacheKey = self::cacheKey($data['from'], $data['to'], $data['area']);
        if (! array_key_exists($cacheKey, self::$cache)) {
            self::$cache[$cacheKey] = new self($data, null, null);
        }
        return self::$cache[$cacheKey];
    }

    protected function fixWeights($from, $area)
    {
        $database = $this->getDatabase();
        $meta = $this->meta();

        $list = $database->prepare($meta->getSQL('fixsel'));
        $upd = $database->prepare($meta->getSQL('fixupd'));

        $fromkey = $meta->getFromKey();
        $tokey = $meta->getToKey();
        $w = 0;

        $kv = array();
        $kv[$fromkey] = $from->id;
        $kv['area'] = $area;

        $nodeid=null;
        $list->execute($kv);
        $list->bindColumn(1, $nodeid);

        $upd->bindParam($fromkey, $from->id);
        $upd->bindParam($tokey, $nodeid);
        $upd->bindParam('area', $area);
        $upd->bindParam('weight', $w);
        while ($list->fetch) {
            $upd->execute();
            $key = self::cacheKey($from, $nodeid, $area);
            if (array_key_exists($key, self::$cached)) {
                self::$cached[$key]->weight = $w;
            }
            $w++;
        }
    }

    static public function reorder($links)
    {
        if (! is_array($links)) {
            throw new InvalidArgumentException('invalid link list');
        }
        $page = null;
        $area = null;
        foreach ($links as $link) {
            if (! is_object($link) || ! $link instanceof self) {
                throw new InvalidArgumentException(
                    'list item not a CMSPageNodeLink'
                );
            }
            if (isset($page)) {
                if ($link->from != $page || $link->area != $area) {
                    throw new InvalidArgumentException('heterogenous list');
                }
            } else {
                $page = $link->from;
                $area = $link->area;
            }
        }

        $meta = DatabaseObjectLinkMeta::forClass(__CLASS__);
        $database = $meta->getDatabase();
        $sql = $meta->getSQL('pagecount');
        $sth = $database->execute($sql, $page->id, $area);
        $c = $sth->fetch(PDO::FETCH_NUM);
        $c = $c[0];
        if ($c != count($links)) {
            throw new InvalidArgumentException('will not partially reorder');
        }

        $upd = $database->prepare($meta->getSQL('fixupd'));
        $this->lockTables();
        $database->transaction();
        try {
            $w=0;
            $nodeid=null;
            $upd->bindParam($meta->getFromKey(), $page->id);
            $upd->bindParam($meta->getToKey(), $nodeid);
            $upd->bindParam('area', $area);
            $upd->bindParam('weight', $w);
            foreach ($links as $link) {
                $nodeid = $link->to->id;
                $link->weight = $w;
                $upd->execute();
                $w++;
            }
        } catch (Exception $e) {
            $database->rollback();
            $database->unlock();
            throw $e;
        }
        $database->commit();
        $database->unlock();
    }

    public function __construct($page, $node, $area)
    {
        if (! is_array($page)) {
            if (! is_string($area)) {
                throw new InvalidArgumentException('area not a string');
            }
            $this->area = $area;
        }
        parent::__construct($page, $node);
    }

    public function doInsert()
    {
        $database = $this->getDatabase();
        $sql = $this->meta()->getSQL('new_link_weight');
        $sth = $database->prepare($sql)->execute($this->keyValue());
        $r = $sth->fetch(PDO::FETCH_NUM);
        $this->weight = (int) $r[0];
        return parent::doInsert();
    }

    public function getArea()
    {
        return $this->area;
    }

    public function getPage()
    {
        return $this->from;
    }

    public function getNode()
    {
        return $this->to;
    }

    public function delete()
    {
        $database = $this->getDatabase();
        $this->lockTables();
        $database->transaction();
        try {
            $from = $this->from;
            $area = $this->area;
            parent::delete();
            $this->fixWeights($from, $area);
            $key = self::cacheKey($this->from, $this->to, $this->area);
            unset(self::$cached[$key]);
        } catch (Exception $e) {
            $database->rollback();
            $database->unlock();
            throw $e;
        }
        $database->commit();
        $database->unlock();
    }
}

?>
