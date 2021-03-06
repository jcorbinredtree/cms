<?php
/** * CMSPage definition
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

require_once 'lib/database/DatabaseObject.php';
require_once 'lib/cms/CMSDBObjectDataTable.php';

/**
 * Common base class for CMSPage and CMSNode
 */
abstract class CMSDBObject extends DatabaseObject
{
    public static $ManualColumns = array(
        'modified'
    );

    public static $CustomSQL = array(
        'get_modified' =>
            'SELECT UNIX_TIMESTAMP(modified) FROM {table} WHERE {key}=?',
        'set_modified' =>
            'UPDATE {table} SET modified=FROM_UNIXTIME(?) WHERE {key}=?'
    );

    protected $created;
    protected $modified=null;

    public $data;

    public function __construct()
    {
        parent::__construct();
        $this->data = new CMSDBObjectDataTable($this);
    }

    public function create()
    {
        $this->created = time();
        parent::create();
        $this->modified = null;
    }

    public function update()
    {
        parent::update();
    }

    public function delete()
    {
        $database = $this->getDatabase();
        $database->transaction();
        try {
            $this->data->clearAll();
            parent::delete();
        } catch (Exception $e) {
            $database->rollback();
        }
        $database->commit();
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getModified()
    {
        if (! isset($this->modified)) {
            if (isset($this->id)) {
                $database = $this->getDatabase();
                $sql = $this->meta()->getSQL('get_modified');
                $sth = $database->execute($sql, $this->id);
                $r = $sth->fetch(PDO::FETCH_NUM);
                $this->modified = $r[0];
            }
        }
        return $this->modified;
    }

    public function setModified($when=null)
    {
        if ($this->unserializing) {
            return;
        }
        if (! isset($when)) {
            $when = time();
        }
        assert(is_int($when));
        if ($when < $this->modified) {
            throw new InvalidArgumentException("Won't roll back modified time");
        }

        if (isset($this->id)) {
            $database = $this->getDatabase();
            $meta = $this->meta();
            $sql = $this->meta()->getSQL('set_modified');
            $database->execute($sql, $when, $this->id);
        }
        $this->modified = $when;
    }

    protected function selfToData()
    {
        $data = parent::selfToData();
        $data['datatable'] = $this->data->serialize();
        return $data;
    }

    protected function dataToSelf($data, $save=true)
    {
        parent::dataToSelf($data, $save);
        if (
            array_key_exists('datatable', $data) &&
            is_array($data['datatable'])
        ) {
            $this->data->unserialize($data['datatable'], $save);
        }
    }
}

CMSDBObject::$CustomSQL = array_merge(
    CMSDBObject::$CustomSQL,
    CMSDBObjectDataTable::$CustomSQL
);

?>
