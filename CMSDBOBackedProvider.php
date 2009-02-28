<?php

/**
 * CMSDBOBackedProvider definition
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

abstract class CMSDBOBackedProvider extends PHPSTLTemplateProvider
{
    /**
     * Subclasses implement this to do basic CMSDBObject based resolution
     * @param resource string
     * @return CMSDBObject the object that will populate the template
     * @see load
     */
    abstract protected function dboForResource($resource);

    /**
     * @var Site
     */
    protected $site;

    /**
     * Creats a new CMSDBObject backed provider
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
        $dbo = $this->dboForResource($resource);
        if (isset($dbo)) {
            return $this->createTemplate($resource, $dbo);
        } else {
            return PHPSTLTemplateProvider::DECLINE;
        }
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
        $dbo = $template->providerData;
        assert($dbo instanceof CMSDBObject);
        return $dbo->getModified();
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
        $dbo = $template->providerData;
        assert($dbo instanceof CMSDBObject);
        return $dbo->getContent();
    }
}

?>
