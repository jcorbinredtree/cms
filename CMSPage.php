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
require_once 'lib/cms/CMSNode.php';
require_once 'lib/cms/CMSPageNodeLink.php';

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
 * CREATE TABLE cms_page_node(
 *     cms_page_id INTEGER UNSIGNED NOT NULL,
 *     cms_node_id INTEGER UNSIGNED NOT NULL,
 *     area VARCHAR(255) NOT NULL,
 *     weight TINYINT UNSIGNED NOT NULL,
 *     PRIMARY KEY (cms_page_id, cms_node_id, area),
 *     INDEX (cms_page_id),
 *     INDEX (cms_node_id),
 *     INDEX (cms_page_id, area),
 *     INDEX (cms_page_id, area, weight)
 * );
 *
 */
class CMSPage extends CMSDBObject
{
    // Convenience call to DatabaseObject::load until php supports late static
    // binding in 5.3.0
    public static function load($id)
    {
        return DatabaseObject::load(__CLASS__, $id);
    }

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
        $meta = DatabaseObjectMeta::forClass(__CLASS__);
        $sql = $meta->getSQL('id_for_path');
        $database->executef($sql, $path);
        $r = (bool) $database->count() > 0;
        $database->free();
        return $r;
    }

    public static function loadPath($path)
    {
        global $database;
        $meta = DatabaseObjectMeta::forClass(__CLASS__);
        $sql = $meta->getSQL('id_for_path');
        $sth = $database->executef($sql, $path);
        $r = null;
        try {
            if ($database->count() > 0) {
                $r = $sth->fetch(PDO::FETCH_NUM);
                $r = self::load($r[0]);
            }
        } catch (Exception $e) {
            $database->free();
            throw $e;
        }
        $database->free();
        return $r;
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

    private $fakeNodes;
    private $linkedNodes;
    private function loadNodes()
    {
        $list = CMSPageNodeLink::loadFor($this);
        $nodes = array();
        foreach ($list as $link) {
            $a = $link->getArea();
            if (! array_key_exists($a, $nodes)) {
                $nodes[$a] = array();
            }
            array_push($nodes[$a], $link);
        }
        $this->linkedNodes = $nodes;
    }

    public function getNodeAreas()
    {
        if (isset($this->fakeNodes)) {
            return array_keys($this->fakeNodes);
        } else {
            if (! isset($this->linkedNodes)) {
                $this->loadNodes();
            }
            return array_keys($this->linkedNodes);
        }
    }

    public function getNodes($area)
    {
        assert(is_string($area));
        if (isset($this->fakeNodes)) {
            if (! array_key_exists($area, $this->fakeNodes)) {
                return null;
            }
            return $this->fakeNodes[$area];
        } else {
            if (! isset($this->linkedNodes)) {
                $this->loadNodes();
            }

            if (! array_key_exists($area, $this->linkedNodes)) {
                return null;
            }
            $r = array();
            foreach ($this->linkedNodes[$area] as $link) {
                array_push($r, $link->getNode());
            }
            return $r;
        }
    }

    public function addNode(CMSNode $node, $area, $pos=-1)
    {
        assert(is_string($area));
        assert(is_int($pos));
        if (isset($this->fakeNodes)) {
            $nodes = array_key_exists($area, $this->fakeNodes)
                ? $this->fakeNodes[$area] : array();
            foreach ($nodes as $n) {
                if ($n === $node) {
                    throw new InvalidArgumentException(
                        'node already linked to that area'
                    );
                }
            }
            if ($pos < 0) {
                $pos = max(0, count($nodes) + $pos);
            }
            $pos = min(count($nodes)-1, $pos);
            if ($pos < 0) {
                array_push($nodes, $node);
            } else {
                array_splice($nodes, $pos, 0, $node);
            }
            $this->fakeNodes[$area] = $nodes;
        } else {
            if (! isset($this->linkedNodes)) {
                $this->loadNodes();
            }
            $links = array_key_exists($area, $this->linkedNodes)
                ? $this->linkedNodes[$area] : array();
            foreach ($links as $link) {
                if ($link->getNode() === $node) {
                    throw new InvalidArgumentException(
                        'node already linked to that area'
                    );
                }
            }
            if ($pos < 0) {
                $pos = max(0, count($links) + $pos);
            }
            $pos = min(count($links)-1, $pos);

            global $database;
            $database->transaction();
            try {
                $new = new CMSPageNodeLink($this, $node, $area);
                if ($pos < 0) {
                    array_push($links, $new);
                } else {
                    array_splice($links, $pos, 0, $new);
                    CMSPageNodeLink::reorder($links);
                }
            } catch (Exception $e) {
                $database->rollback();
                throw $e;
            }
            $database->commit();

            $this->linkedNodes[$area] = $links;
        }
    }

    public function removeNode(CMSNode $node, $area)
    {
        assert(is_string($area));
        if (isset($this->fakeNodes)) {
            $nodes = array_key_exists($area, $this->fakeNodes)
                ? $this->fakeNodes[$area] : array();

            $new = array();
            foreach ($nodes as $n) {
                if ($n !== $node) {
                    array_push($new, $n);
                }
            }
            $this->fakeNodes[$area] = $new;
        } else {
            if (! isset($this->linkedNodes)) {
                $this->loadNodes();
            }
            $links = array_key_exists($area, $this->linkedNodes)
                ? $this->linkedNodes[$area] : array();

            foreach ($links as $link) {
                if ($link->getNode() === $node) {
                    $link->delete();
                    break;
                }
            }
        }
    }

    public function setNodeOrder($nodes, $area)
    {
        assert(is_array($nodes));
        assert(is_string($area));

        if (isset($this->fakeNodes)) {
            $this->fakeNodes[$area] = $nodes;
        } else {
            if (! isset($this->linkedNodes)) {
                $this->loadNodes();
            }
            $links = array_key_exists($area, $this->linkedNodes)
                ? $this->linkedNodes[$area] : array();
            $new = array();

            foreach ($nodes as $node) {
                if (is_object($node)) {
                    if ($node instanceof CMSPageNodeLink) {
                        throw new InvalidArgumentException(
                            'element not a CMSPageNodeLink'
                        );
                    }
                    $node = $node->id;
                } elseif (! is_int($node)) {
                    throw new InvalidArgumentException('invalid element');
                }
                $link = null;
                for ($i=0; $i<count($links); $i++) {
                    if ($links[$i]->getNode()->id == $node) {
                        $link = array_splice($links, $i, 1);
                        $link = $link[0];
                        array_push($new, $link);
                        break;
                    }
                }
                if (! isset($link)) {
                    throw new InvalidArgumentException("node $node isn't linked to page $this->id");
                }
            }
            CMSPageNodeLink::reorder($new);
        }
    }

    protected function setNodes($set)
    {
        assert(is_array($set));
        if (! isset($this->linkedNodes)) {
            $this->loadNodes();
        }

        global $database;
        $database->transaction();
        try {
            foreach ($set as $area => $nodes) {
                if (! array_key_exists($area, $this->linkedNodes)) {
                    foreach ($nodes as $node) {
                        $this->addNode($node, $area);
                    }
                } else {
                    $old = $this->getNodes($area);
                    foreach (array_diff($old, $nodes) as $node) {
                        $this->removeNode($node, $area);
                    }
                    foreach (array_diff($nodes, $old) as $node) {
                        $this->addNode($node, $area);
                    }
                    $this->setNodeOrder($nodes, $area);
                }
            }
            foreach (array_diff(
                array_keys($this->linkedNodes),
                array_keys($nodes)
            ) as $goneArea) {
                $l = $this->linkedNodes[$area];
                while (count($l)) {
                    array_pop($l)->delete();
                }
            }
            $this->linkedNodes = null;
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        $database->commit();

        if (isset($this->fakeNodes)) {
            $this->fakeNodes = null;
        }
    }

    public function create()
    {
        if (! isset($this->fakeNodes)) {
            parent::create();
            return;
        }
        global $database;
        $database->transaction();
        try {
            parent::create();
            $this->setNodes($this->fakeNodes);
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        $database->commit();
    }

    public function update()
    {
        if (! isset($this->fakeNodes)) {
            parent::update();
            return;
        }
        global $database;
        $database->transaction();
        try {
            parent::update();
            $this->setNodes($this->fakeNodes);
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        $database->commit();
    }

    public function delete()
    {
        global $database;
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

    protected function selfToData()
    {
        if (! isset($this->linkedNodes)) {
            $this->loadNodes();
        }

        $data = parent::selfToData();

        $data['nodes'] = array();
        foreach ($this->linkedNodes as $area => $nodes) {
            $n = array();
            foreach ($nodes as $link) {
                array_push($n, $link->getNode()->id);
            }
            $data['nodes'][$area] = $n;
        }

        return $data;
    }

    protected function dataToSelf($data, $save=true)
    {
        parent::dataToSelf($data, $save);

        if (isset($data['nodes'])) {
            $nodes = $data['nodes'];
            foreach ($nodes as $area => $ids) {
                $nodes[$area] = array_map(
                    array('CMSNode', 'load'), $nodes[$area]
                );
            }

            if ($save) {
                $this->setNodes($nodes);
            } else {
                if (isset($this->linkedNodes)) {
                    $this->linkedNodes = null;
                }
                $this->fakeNodes = $nodes;
                return;
            }
        }
    }
}

?>
