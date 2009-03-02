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

require_once 'lib/cms/CMSDBObject.php';

/**
 * All CMSPage represent data to populate an HTMLPage, but are not themselves
 * HTMLPages
 *
 * CREATE TABLE cms_page (
 *     cms_page_id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
 *     created DATETIME NOT NULL,
 *     modified TIMESTAMP NOT NULL,
 *     path VARCHAR(255) NOT NULL,
 *     type VARCHAR(255) NOT NULL DEFAULT 'text/html',
 *     UNIQUE KEY(path),
 *     INDEX(created),
 *     INDEX(modified)
 * );
 * CREATE TABLE cms_page_data(
 *     cms_page_id INTEGER UNSIGNED NOT NULL,
 *     `key` VARCHAR(255) NOT NULL,
 *     type ENUM ('string', 'json'),
 *     value TEXT NOT NULL,
 *     PRIMARY KEY (cms_page_id, `key`),
 *     INDEX(cms_page_id),
 *     INDEX(`key`)
 * );
 *
 */
class CMSPage extends CMSDBObject
{
    protected $created;
    protected $path;
    protected $type;

    public static $table = 'cms_page';
    public static $key = 'cms_page_id';

    public static $CustomSQL = array(
        'id_for_path' =>
            'SELECT {key} FROM {table} WHERE path=?'
    );

    public static function pathExists($path)
    {
        global $database;
        $meta = DatabaseObject_Meta::forClass('CMSPage');
        $sql = $meta->getCustomSQL('id_for_path');
        $database->executef($sql, $path);
        $r = (bool) $database->count() > 0;
        $database->free();
        return $r;
    }

    public static function loadPath($path)
    {
        global $database;
        $meta = DatabaseObject_Meta::forClass('CMSPage');
        $sql = $meta->getCustomSQL('id_for_path');
        $sth = $database->executef($sql, $path);
        $r = null;
        try {
            if ($database->count() > 0) {
                $r = $sth->fetch(PDO::FETCH_NUM);
                $o = new self();
                $o->fetch($r[0]);
                $r = $o;
            }
        } catch (Exception $e) {
            $database->free();
            throw $e;
        }
        $database->free();
        return $r;
    }

    public function create()
    {
        $this->created = time();
        return parent::create();
    }

    public function getCreated()
    {
        return $created;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setPath($path)
    {
        // TODO make sure its unique
        $this->path = $path;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }
}

?>
