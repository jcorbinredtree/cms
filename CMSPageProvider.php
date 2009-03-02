<?php

/**
 * CMSPageProvider definition
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

require_once 'lib/site/SitePageProvider.php';
require_once 'lib/cms/CMSPage.php';

class CMSPageProvider extends SitePageProvider
{
    public function loadPage($url)
    {
        $cpage = CMSPage::loadPath($url);
        if (! isset($cpage)) {
            return SitePageProvider::DECLINE;
        }
        $type = $cpage->getType();
        if ($type == 'text/html') {
            $spage = new HTMLPage($this->site);
            $this->populateHTMLPage($cpage, $spage);
        } else {
            $spage = new SitePage($this->site, $type);
            $spage->setDataArray($cpage->data->serialize());
        }
        return $spage;
    }

    /**
     * Example page data ala: json_encode(CMSPage->data->seralize()):
     * {
     *   layout: 'layout',
     *   doctype: 'xhtml 1.0',
     *   title: 'Page Title',
     *   description: 'Meta Description',
     *   keywords: ['meta', 'keywords'],
     *   meta: {
     *     other: 'meta values'
     *   },
     *   scripts: [
     *     'some/script.js',
     *     ['or/something/odd.huh', 'mime/type']
     *   ],
     *   stylesheets: [
     *     { href: 'some/css',
     *       title: 'Optional Title',
     *       alt: false, // default
     *       media: 'optional media'
     *     }
     *   ],
     *   links: [
     *     { href: 'some/path',
     *       type: 'mime/type',
     *       rel: 'what',
     *       title: 'What'
     *     }
     *   ]
     * }
     */
    protected function populateHTMLPage(CMSPage $cpage, HTMLPage $hpage)
    {
        $d = $cpage->data->serialize();

        if (array_key_exists('layout', $d)) {
            $hpage->setLayout($d['layout']);
            unset($d['layout']);
        }
        if (array_key_exists('doctype', $d)) {
            $hpage->doctype = $d['doctype'];
            unset($d['doctype']);
        }
        if (array_key_exists('title', $d)) {
            $hpage->title = $d['title'];
            unset($d['title']);
        }

        foreach (array('description', 'keywords') as $mk) {
            if (array_key_exists($mk, $d)) {
                $hpage->meta->set($mk, $d[$mk]);
                unset($d[$mk]);
            }
        }

        if (array_key_exists('meta', $d)) {
            $hpage->meta->setArray($d['meta']);
            unset($d['meta']);
        }

        if (array_key_exists('scripts', $d)) {
            foreach ((array) $d['scripts'] as $script) {
                $script = (array) $script;
                assert(array_key_exists('href', $script));
                $href = $script['href'];
                $type = array_key_exists('type', $script) ? $script['type'] : null;
                if (isset($type)) {
                    $a = new HTMLPageScript($href, $type);
                } else {
                    $a = new HTMLPageScript($href);
                }
                $hpage->addAsset($a);
            }
            unset($d['scripts']);
        }

        if (array_key_exists('stylesheets', $d)) {
            foreach ((array) $d['stylesheets'] as $style) {
                $style = (array) $style;
                assert(array_key_exists('href', $style));
                $hpage->addAsset(new HTMLPageStylesheet(
                    $style['href'],
                    array_key_exists('alt', $style) ? $style['alt'] : false,
                    array_key_exists('title', $style) ? $style['title'] : null,
                    array_key_exists('media', $style) ? $style['media'] : null
                ));
            }
            unset($d['stylesheets']);
        }

        if (array_key_exists('links', $d)) {
            foreach ((array) $d['links'] as $link) {
                $link = (array) $link;
                assert(array_key_exists('href', $link));
                assert(array_key_exists('type', $link));
                assert(array_key_exists('rel', $link));
                $href  = $link['href'];
                $type  = $link['type'];
                $rel   = $link['rel'];
                $title = array_key_exists('title', $link) ? $link['title'] : null;
                if ($rel == 'alternate') {
                    $a = new HTMLPageAlternateLink($href, $type, $title);
                } else {
                    $a = new HTMLPageLinkedResource($href, $type, $rel, $title);
                }
                $hpage->addAsset($a);
            }
            unset($d['links']);
        }

        if (count($d)) {
            $hpage->setDataArray($d);
        }
    }
}

?>
