<?php

/**
 * CMSConnector definition
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

class CMSConnector extends Page
{
    public static function resolve(Site $site, $url)
    {
        $matches = array();
        if ($url == 'test') {
            $cms = $site->modules->get('CMS');
            $oldPath = CurrentPath::set($cms->getDir());
            $prefix = $cms->getPrefix();
            try {
                $page = new HTMLPage(
                    $site,
                    null,
                    "$prefix/cmstest.html",
                    array('cms' => $cms)
                );
            } catch (Exception $e) {
                CurrentPath::set($oldPath);
                throw $e;
            }
            CurrentPath::set($oldPath);
            return $page;
        } elseif (! preg_match(
            '~^(page|node)/(\w+)(?:/(.+))?$~',
            $url, $matches
        )) {
            return null;
        }

        try {
            $what = $matches[1];
            $action = $matches[2];
            if (count($matches) > 3) {
                $extra = $matches[3];
            } else {
                $extra = null;
            }
            return new self($site, $what, $action, $extra);
        } catch (BadMethodCallException $e) {
            return null;
        }
    }

    protected $response;

    public function __construct(Site $site, $what, $action, $extra)
    {
        $method = 'do'.ucfirst($what).ucfirst($action);
        if (! method_exists(__CLASS__, $method)) {
            throw new BadMethodCallException('no such method');
        }

        parent::__construct($site, 'application/json', false);
        $this->response = array();

        if (array_key_exists('id', $_REQUEST)) {
            $id = $_REQUEST['id'];
        }

        try {
            if (isset($id)) {
                switch ($what) {
                case 'page':
                    $subject = CMSPage::load($id);
                    break;
                case 'node':
                    $subject = CMSNode::load($id);
                    break;
                default:
                    throw new RuntimeException('shouldn\'t happen');
                    break;
                }
            }
            $rclass = new ReflectionClass(get_class($this));
            $rmeth = $rclass->getMethod($method);

            if (isset($subject)) {
                $this->$method($subject, $extra);
            } else {
                if ($rmeth->getNumberOfRequiredParameters() > 0) {
                    throw new InvalidArgumentException('subject required');
                }
                $this->$method();
            }
        } catch (StopException $e) {
            // noop
        }
    }

    public function doPageList()
    {
        $this->response = array();
        CMSPage::getList(array($this, 'pageListItem'));
    }
    public function pageListItem($id, $path)
    {
        array_push($this->response, array(
            'id'   => $id,
            'path' => $path
        ));
    }

    public function doPageCreate()
    {
        $data = $this->getJSON();
        $page = new CMSPage();
        $page->unserialize($data, true);
        $this->response = $page->serialize();
    }

    public function doPageDelete(CMSPage $page, $extra)
    {
        $page->delete();
    }

    public function doPageUpdate(CMSPage $page, $extra)
    {
        $data = $this->getJSON();
        if (! array_key_exists($data['id']) || $data['id'] != $page->id) {
            throw new InvalidArgumentException('page id mismatch');
        }
        $page->unserialize($data);
        $this->response = $page->serialize();
    }

    public function doPageLoad()
    {
        $args = func_get_args();
        if (count($args) && $args[0] instanceof CMSPage) {
            $page = $args[0];
        }

        if (! isset($page) && $this->hasJSON()) {
            $data = $this->getJSON();
            if (array_key_exists('url', $data)) {
                $url = $data['url'];
                throw new RuntimeException('load for url '.$url);
            }
        }

        if (! isset($page)) {
            throw new InvalidArgumentException('no page specified');
        }

        $this->response = $page->serialize();
    }

    public function doNodeList()
    {
        $this->response = array();
        CMSNode::getList(array($this, 'nodeListItem'));
    }
    public function nodeListItem($id, $res)
    {
        array_push($this->response, array(
            'id'      => $id,
            'resName' => $res
        ));
    }

    public function doNodeCreate()
    {
        $data = $this->getJSON();
        $node = new CMSNode();
        $node->unserialize($data, true);
        $this->response = $node->serialize();
    }

    public function doNodeDelete(CMSNode $node, $extra)
    {
        $node->delete();
    }

    public function doNodeUpdate(CMSNode $node, $extra)
    {
        $data = $this->getJSON();
        if (! array_key_exists($data['id']) || $data['id'] != $node->id) {
            throw new InvalidArgumentException('node id mismatch');
        }
        $node->unserialize($data);
        $this->response = $node->serialize();
    }

    public function doNodeLoad(CMSNode $node, $extra)
    {
        $this->response = $node->serialize();
    }

    protected function hasJSON()
    {
        if (isset($this->json)) {
            return $this->json === false ? false : true;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            return $this->json = false;
        }

        $type = $_SERVER['CONTENT_TYPE'];
        if (($i = strpos($type, ';')) !== false) {
            // TODO deal with charset?
            $type = substr($type, 0, $i);
        }
        if ($type != 'application/json') {
            return $this->json = false;
        }

        $input = trim(file_get_contents('php://input'));
        if (! strlen($input)) {
            return $this->json = false;
        }

        $data = json_decode($input);
        if (! $data) {
            if (function_exists('json_last_error')) { // >= 5.3.0
                $code = json_last_error();
                switch ($code) {
                case JSON_ERROR_NONE:
                    $err = 'No error has occured';
                    break;
                case JSON_ERROR_DEPTH:
                    $err = 'The maximum stack depth has been exceeded';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $err = 'Control character error, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_SYNTAX:
                    $err = 'Syntax error';
                    break;
                default:
                    $err = 'unknown';
                }
                throw new RuntimeException("invalid json input: $err ($code)");
            } else {
                throw new RuntimeException('invalid json input');
            }
        }
        $this->json = $data;

        return true;
    }

    protected function getJSON()
    {
        if (! $this->hasJSON()) {
            throw new RuntimeException('no json input');
        }

        return $this->json;
    }

    protected function onRender()
    {
        return json_encode($this->response);
    }
}

?>
