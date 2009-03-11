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
        if (! preg_match(
            '~^(page|node)/(\w+)(?:/(\d+)(?:/(.+))?)?$~',
            $url, $matches
        )) {
            return null;
        }

        try {
            $what = $matches[1];
            $action = $matches[2];
            if (count($matches) > 3) {
                $id = (int) $matches[3];
            } else {
                $id = null;
            }
            if (count($matches) > 4) {
                $extra = $matches[4];
            } else {
                $extra = null;
            }
            return new self($site, $what, $action, $id, $extra);
        } catch (BadMethodCallException $e) {
            return null;
        }
    }

    protected $response;

    public function __construct(Site $site, $what, $action, $id, $extra)
    {
        $method = 'do'.ucfirst($what).ucfirst($action);
        if (! method_exists(__CLASS__, $method)) {
            throw new BadMethodCallException('no such method');
        }

        parent::__construct($site, 'application/json', false);
        $this->response = array();

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

        try {
            if (isset($subject)) {
                $this->$method($subject, $extra);
            } else {
                $this->$method();
            }
        } catch (Exception $e) {
            $this->doException($e);
        }
    }

    public function doException(Exception $e)
    {
        $this->headers->setStatus(500, 'Unhandled Exception');
        $this->respones = array();
        $this->response['type'] = get_class($e);
        $this->response['message'] = $e->getMessage();
    }

    public function doPageCreate()
    {
        $data = $this->getJsonBody();
        $page = new CMSPage();
        $page->unserialize($data, true);
        $this->response = $page->serialize();
    }

    public function doPageDelete(CMSPage $page, $extra)
    {
        $page->delete();
    }

    public function doPageSave(CMSPage $page, $extra)
    {
        $data = $this->getJsonBody();
        if (! array_key_exists($data['id']) || $data['id'] != $page->id) {
            throw new InvalidArgumentException('page id mismatch');
        }
        $page->unserialize($data);
        $this->response = $page->serialize();
    }

    public function doPageLoad(CMSPage $page, $extra)
    {
        $this->response = $page->serialize();
    }

    public function doNodeCreate()
    {
        $data = $this->getJsonBody();
        $node = new CMSNode();
        $node->unserialize($data, true);
        $this->response = $node->serialize();
    }

    public function doNodeDelete(CMSNode $node, $extra)
    {
        $node->delete();
    }

    public function doNodeSave(CMSNode $node, $extra)
    {
        $data = $this->getJsonBody();
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

    protected function getJsonBody()
    {
        throw new RuntimeException('unimplemented');
    }

    protected function onRender()
    {
        return json_encode($this->response);
    }
}

?>
