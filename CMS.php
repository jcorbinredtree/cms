<?php

/**
 * CMS
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

class CMS extends SiteModule
{
    public static $RequiredModules = array(
        'Database',
        'PageSystem'
    );
    /*
     * TODO: someday...
     *   public static $OptionalModules = array(
     *       'Login'
     *   );
     */

    public function initialize()
    {
        parent::initialize();

        require_once "$this->dir/CMSPageProvider.php";
        require_once "$this->dir/CMSNodeTemplateProvider.php";

        $this->site->addCallback('onPostConfig', array($this, 'onPostConfig'));
    }

    public function onPostConfig(Site $site)
    {
        $tsys = $this->site->modules->get('TemplateSystem');
        $pstl = $tsys->getPHPSTL();
        $pstl->addProvider(new CMSNodeTemplateProvider($this->site, $pstl));

        $pagesys = $this->site->modules->get('PageSystem');
        new CMSPageProvider($pagesys);
    }
}

?>
