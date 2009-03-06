<?php

/**
 * CMSPage definition
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

/**
 * Implemnts a data table
 *   CREATE TABLE DBO_data(
 *       DBO_id INTEGER NOT NULL,
 *       `key` VARCHAR(255) NOT NULL,
 *       `type` ENUM ('string', 'json'),
 *       value TEXT NOT NULL,
 *       PRIMARY KEY (DBO_id, `key`),
 *       INDEX(DBO_id),
 *       INDEX(`key`)
 *   );
 */
class CMSDBObjectDataTable
{
    public static $CustomSQL = array(
        'datatable_keys' =>
            'SELECT `key` FROM {table}_data WHERE {key}=? ORDER BY `key`',
        'datatable_has' =>
            'SELECT `key` FROM {table}_data WHERE {key}=? AND `key`=?',
        'datatable_get' =>
            'SELECT `type`, value FROM {table}_data WHERE {key}=? AND `key`=?',
        'datatable_set' =>
            'REPLACE INTO {table}_data ({key}, `key`, `type`, value) VALUES (?, ?, ?, ?)',
        'datatable_clear' =>
            'DELETE FROM {table}_data WHERE {key}=? AND `key`=?',
        'datatable_clear_all' =>
            'DELETE FROM {table}_data WHERE {key}=?'
    );

    protected $dbo;

    public function __construct(CMSDBObject $dbo)
    {
        $this->dbo = $dbo;
    }

    /**
     * @return array of strings
     */
    public function keys()
    {
        if (isset($this->fake)) {
            return array_keys($this->fake);
        } else {
            assert(isset($this->dbo->id));

            $database = $this->getDatabase();
            $sql = $this->dbo->meta()->getSQL('datatable_keys');
            $sth = $database->execute($sql, $this->dbo->id);
            $key = null;
            $sth->bindColumn(1, $key);
            $r = array();
            while ($sth->fetch()) {
                array_push($r, $key);
            }
            return $r;
        }
    }

    /**
     * @param key string
     * @return bool
     */
    public function has($key)
    {
        if (isset($this->fake)) {
            return array_key_exists($key, $this->fake);
        } else {
            assert(is_string($key));
            assert(isset($this->dbo->id));

            $database = $this->getDatabase();
            $sql = $this->dbo->meta()->getSQL('datatable_has');
            $sth = $database->execute($sql, $this->dbo->id, $key);
            $r = (bool) $sth->rowCount() > 0;
            return $r;
        }
    }

    /**
     * @param key string
     * @return mixed
     */
    public function get($key)
    {
        if (isset($this->fake)) {
            if (array_key_exists($key, $this->fake)) {
                return $this->fake[$key];
            } else {
                return null;
            }
        } else {
            assert(is_string($key));
            assert(isset($this->dbo->id));

            $database = $this->getDatabase();
            $sql = $this->dbo->meta()->getSQL('datatable_get');
            $sth = $database->execute($sql, $this->dbo->id, $key);
            $r = null;
            if ($sth->rowCount() > 0) {
                $r = $sth->fetch(PDO::FETCH_NUM);
                if ($r[0] == 'json') {
                    $r = json_decode($r[1]);
                } else {
                    $r = $r[1];
                }
            }
            return $r;
        }
    }

    /**
     * @param key string
     * @param value mixed
     * @return void
     */
    public function set($key, $value)
    {
        if (isset($this->fake)) {
            $this->fake[$key] = $value;
        } else {
            assert(is_string($key));
            assert(isset($this->dbo->id));
            if (! isset($value)) {
                $this->clear($key);
            }

            $type = 'string';
            if (is_object($value) || is_array($value)) {
                $type = 'json';
                $value = json_encode($value);
            } elseif (! is_string($value)) {
                throw new InvalidArgumentException(
                    "don't know how to handle that type of value"
                );
            }

            $database = $this->getDatabase();
            $sql = $this->dbo->meta()->getSQL('datatable_set');
            $database->execute($sql, $this->dbo->id, $key, $type, $value);
            $this->dbo->setModified();
        }
    }

    public function setArray($data)
    {
        if (isset($this->fake)) {
            $this->fake = $data;
        } else {
            assert(is_array($data));
            assert(isset($this->dbo->id));
            if (! count($data)) {
                return;
            }

            $d = array();
            foreach ($data as $value) {
                $type = 'string';
                if (is_object($value) || is_array($value)) {
                    $type = 'json';
                    $value = json_encode($value);
                } elseif (! is_string($value)) {
                    throw new InvalidArgumentException(
                        "don't know how to handle that type of value"
                    );
                }
                array_push($d, $type, $value);
            }

            $database = $this->getDatabase();
            $database->transaction();
            try {
                $sql = $this->dbo->meta()->getSQL('datatable_set');
                $sth = $database->prepare($sql);
                for ($i=0; $i<count($d); $i+=2) {
                    $sth->execute($this->dbo->id, $key, $d[$i], $d[$i+1]);
                }
            } catch (Exception $e) {
                $database->rollback();
                throw $e;
            }
            $database->commit();
            $this->dbo->setModified();
        }
    }

    public function clear($key)
    {
        if (isset($this->fake)) {
            if (array_key_exists($key, $this->fake)) {
                unset($this->fake[$key]);
            }
        } else {
            assert(is_string($key));
            assert(isset($this->dbo->id));

            $database = $this->getDatabase();
            $sql = $this->dbo->meta()->getSQL('datatable_clear');
            $database->execute($sql, $this->dbo->id, $key);
            $this->dbo->setModified();
        }
    }

    public function clearAll()
    {
        if (isset($this->fake)) {
            $this->fake = array();
        } else {
            assert(isset($this->dbo->id));

            $database = $this->getDatabase();
            $sql = $this->dbo->meta()->getSQL('datatable_clear_all');
            $database->execute($sql, $this->dbo->id);
            $this->dbo->setModified();
        }
    }

    public function serialize()
    {
        if (isset($this->fake)) {
            return $this->fake;
        } else {
            assert(isset($this->dbo->id));
            $dboid = $this->dbo->id;

            $data = array();
            $database = $this->dbo->getDatabase();
            $sql = $this->dbo->meta()->getSQL('datatable_keys');
            $keys = $database->execute($sql, $dboid);
            if ($keys->rowCount() > 0) {
                $key = null;
                $sql = $this->dbo->meta()->getSQL('datatable_get');
                $get = $database->prepare($sql);
                $keys->bindColumn(1, $key);
                $r = array();
                while ($keys->fetch()) {
                    $get->execute(array($dboid, $key));
                    $r = $get->fetch(PDO::FETCH_NUM);
                    if ($r[0] == 'json') {
                        $json = $r[1];
                        $val = json_decode($json);
                        if (! isset($val)) {
                            throw new RuntimeException(
                                "bad json string: $json"
                            );
                        }
                        $data[$key] = $val;
                    } else {
                        $data[$key] = $r[1];
                    }
                }
            }
            return $data;
        }
    }

    public function unserialize($data, $save=true)
    {
        if ($save) {
            $this->clearAll();
            $this->setArray($data);
        } else {
            $this->fake = $data;
        }
    }
}

?>
