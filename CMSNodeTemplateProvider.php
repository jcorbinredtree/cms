<?php

/**
 * CMSNodeTemplateProvider definition
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

require_once 'lib/cms/CMSNode.php';

class CMSNodeTemplateProvider extends PHPSTLTemplateProvider
{
    static public $Prefix = 'CMSNode://';

    /**
     * @var Site
     */
    protected $site;

    /**
     * Creats a new CMSNode template provider
     *
     * @param site Site
     * @param pstl PHPSTL
     */
    public function __construct(Site $site, PHPSTL $pstl)
    {
        parent::__construct($pstl);
        $this->site = $site;
    }

    /**
     * Loads a template resource
     *
     * @param resource string
     * @return mixed PHPSTLTemplate, PHPSTLTemplateProvider::DECLINE, or
     * PHPSTLTemplateProvider::FAIL
     * @see PHPSTL::load
     */
    public function load($resource)
    {
        $pl = strlen(self::$Prefix);
        if (strlen($resource) > $pl) {
            $id = (int) substr($resource, $pl);
            if ($id == 0) {
                return PHPSTLTemplateProvider::FAIL;
            }
            $node = CMSNode::load((int) $id);
            if (! isset($node)) {
                return PHPSTLTemplateProvider::FAIL;
            }
        } else {
            $node = CMSNode::loadResource($resource);
            if (! isset($node)) {
                return PHPSTLTemplateProvider::DECLINE;
            }
        }
        return $this->createTemplate($resource, $node);
    }

    /**
     * Returns a unix timestamp indicating when the template resource was last
     * modified.
     *
     * @param template PHPSTLTemplate
     * @return int timestamp
     */
    public function getLastModified(PHPSTLTemplate $template)
    {
        assert(is_a($template->getProvider(), get_class($this)));
        $node = $template->providerData;
        assert($node instanceof CMSNode);
        return $node->getModified();
    }

    /**
     * Gets raw template content
     *
     * @param template PHPSTLTemplate
     * @return string
     */
    public function getContent(PHPSTLTemplate $template)
    {
        assert(is_a($template->getProvider(), get_class($this)));
        $node = $template->providerData;
        assert($node instanceof CMSNode);
        return $node->getContent();
    }

    private $dbid;

    public function __tostring()
    {
        if (!isset($this->dbid)) {
            $database = $this->site->modules->get('Database');
            $this->dbid = $database->getDSN();
        }
        return self::$Prefix.$this->dbid;
    }
}

?>
