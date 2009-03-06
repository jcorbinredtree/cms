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
require_once 'lib/cms/CMSPageNodeLink.php';

/**
 * A node in the CMS:
 *   Nodes are essentially database-based php-stl templates, retreiving and
 *   rendering them is not a sepecial task. They can optionally have a resource
 *   name associated with them, if so then they will be available to the larger
 *   templating system under that name instead of only under the ordinary
 *   CMSNode://ID resource string.
 *
 *   They become special only with regards to the admin interface, which is
 *   responsible for creating and updating the template content of a node type.
 *
 *   The default node type 'content' is just a block of arbitrary content
 *   directly entered by the user.
 *
 *   Subclasses can add as much complication and/or restrction to their
 *   delegated type as they desire.
 *
 *   Subclasses must be registered statically so that they can be proxy
 *   instantiated by CMSNode based on the type column, like so:
 *     class CMSMumbleNode extends CMSNode
 *     {
 *       ...
 *     }
 *     CMSNode::registerType('mytype', 'CMSMumbleNode');
 *
 * TODO history
 *
 * CREATE TABLE cms_node (
 *     cms_node_id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
 *     created DATETIME NOT NULL,
 *     modified TIMESTAMP NOT NULL,
 *     type VARCHAR(255) NOT NULL DEFAULT 'content',
 *     res_name VARCHAR(255),
 *     content TEXT,
 *     UNIQUE KEY(res_name),
 *     INDEX(created),
 *     INDEX(modified)
 * );
 * CREATE TABLE cms_node_data(
 *     cms_node_id INTEGER UNSIGNED NOT NULL,
 *     `key` VARCHAR(255) NOT NULL,
 *     type ENUM ('string', 'json'),
 *     value TEXT NOT NULL,
 *     PRIMARY KEY (cms_node_id, `key`),
 *     INDEX(cms_node_id),
 *     INDEX(`key`)
 * );
 *
 */
class CMSNode extends CMSDBObject
{
    protected $created;
    protected $type = 'content';
    protected $resName;
    protected $content;

    public static $ManualColumns = array(
        'content'
    );

    public static $CustomSQL = array(
        'get_type' => 'SELECT type FROM {table} WHERE {key}=?',
        'get_content' => 'SELECT content FROM {table} WHERE {key}=?',
        'set_content' => 'UPDATE {table} SET content=? WHERE {key}=?',
        'resource_key' => 'SELECT {key} FROM {table} WHERE res_name=?'
    );

    public static $table = 'cms_node';
    public static $key = 'cms_node_id';

    private static $RegisteredTypes = array(
        'content' => __CLASS__
    );

    /**
     * Registeres a node type
     *
     * @param type string
     * @param class string
     * @return void
     */
    public static function registerType($type, $class)
    {
        assert(class_exists($class));
        assert(is_subclass_of($class, __CLASS__));
        if (array_key_exists($type, self::$RegisteredTypes)) {
            throw new RuntimeException('type already regisistered');
        }
        self::$RegisteredTypes[$type] = $class;
    }

    /**
     * Resolves a type to the registered class if any, throws a RuntimeException
     * if type in unclaimed
     *
     * @param type string
     * @return string the class
     */
    public static function getTypeClass($type)
    {
        if (! array_key_exists($type, self::$RegisteredTypes)) {
            throw new RuntimeException("unknown CMSNode type $type");
        }
        return self::$RegisteredTypes[$type];
    }

    // Convenience call to DatabaseObject::load until php supports late static
    // binding in 5.3.0
    public static function load($id)
    {
        return DatabaseObject::load(__CLASS__, $id);
    }

    /**
     * Loads a CMSNode by res_name column
     *
     * @param resource string
     * @return CMSNode or null if the resource doesn't exist
     */
    public static function loadResource($resource)
    {
        if (! is_string($resource)) {
            return null;
        }
        $key = null;
        $database = $this->getDatabase();
        $meta = DatabaseObjectMeta::forClass(__CLASS__);
        $sql = $meta->getSQL('resource_key');
        $sth = $database->execute($sql, $resource);
        $sth->bindColumn(1, $key);
        $r = $sth->fetch();

        if ($r && isset($id)) {
            return self::load($id);
        } else {
            return null;
        }
    }

    /**
     * Used by DatabaseObject::load
     *
     * @param id integer
     * @return CMSNode or null
     */
    public static function factory($id)
    {
        assert(is_int($id));
        $type = null;
        $meta = DatabaseObjectMeta::forClass(__CLASS__);
        $database = $meta->getDatabase();
        $sql = $meta->getSQL('get_type');
        $sth = $database->execute($sql, $id);
        $sth->bindColumn(1, $type);
        $r = $sth->fetch();

        if ($r && isset($type)) {
            $class = self::getTypeClass($type);
            return new $class($type);
        } else {
            return null;
        }
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        assert(array_Key_exists($this->type, self::$RegisteredTypes));
        assert(self::$RegisteredTypes[$this->type] == get_class($this));
        parent::__construct();
    }

    public function fetch($id)
    {
        $r = parent::fetch($id);
        assert(array_Key_exists($this->type, self::$RegisteredTypes));
        assert(self::$RegisteredTypes[$this->type] == get_class($this));
        return $r;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getResName()
    {
        return $this->resName;
    }

    public function setResName($resName)
    {
        // TODO unique check
        $this->resName = $resName;
    }

    public function getContent()
    {
        if (! isset($this->content) && isset($this->id)) {
            $database = $this->getDatabase();
            $sql = $this->meta()->getSQL('get_content');
            $sth = $database->execute($sql, $this->id);
            $sth->bindColumn(1, $this->content);
            $sth->fetch();
        }
        return $this->content;
    }

    public function setContent(&$content)
    {
        $this->content = $content;
        $database = $this->getDatabase();
        $sql = $this->meta->getSQL('set_content');
        $sth = $database->prepare($sql);
        $sth->bindParam(1, $this->content);
        $sth->bindParam(2, $this->id);
        $sth->execute();
    }

    protected function dataToSelf($data, $save)
    {
        parent::dataToSelf($data, $save);
        $content = array_key_exists('content', $data) ? $data['content'] : null;
        if ($save) {
            $this->setContent($data);
        } else {
            $self->content = $content;
        }
    }

    protected function selfToData()
    {
        $data = parent::selfToData();
        $data['content'] = $self->getContent();
        return $data;
    }

    public function delete()
    {
        $database = $this->getDatabase();
        $database->transaction();
        try {
            CMSPageNodeLink::deleteFor($this);
            parent::delete();
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        $databsae->commit();
    }
}

?>
