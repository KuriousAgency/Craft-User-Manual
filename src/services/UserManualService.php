<?php
/**
 * usermanual plugin for Craft CMS 3.x
 *
 * Edit emails
 *
 * @link      kurious.agency
 * @copyright Copyright (c) 2021 Kurious Agency
 */

namespace kuriousagency\usermanual\services;

use kuriousagency\usermanual\UserManual;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\web\View;

use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;

/**
 * @author    Kurious Agency
 * @package   Usermanual
 * @since     2.1.3
 */
class UserManualService extends Component
{

    // Properties
    // =========================================================================

    private $_settings;
    
    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->_settings = UserManual::$plugin->getSettings();
        if (!$this->_settings->section) {
            Craft::$app->controller->redirect(UrlHelper::cpUrl('settings/plugins/usermanual/'))->send();
        }
    }

    public function getNavBar()
    {
        // Get the external items first
        if ($this->_settings->remoteSourceUrl) {
            $remote = $this->_getRemoteNav();
        }
        // Get the local items
        $local = $this->_getLocalNav();

        $nav = array_merge($remote ?? [],$local);
        return $nav;
    }

    public function getHelpDocument()
    {
        $segments = Craft::$app->getRequest()->segments;
        $segment = end($segments);
        if (in_array('remote',$segments)) {
            return $this->_getRemoteDocument($segment);
        } elseif (in_array('local',$segments)) {
            return $this->_getLocalDocument($segments);
        } else {
            return $this->_showIntroPage();
        }
    }

    private function _showIntroPage()
    {
        return Craft::$app->getView()->renderTemplate('usermanual/_intro.twig',[],View::TEMPLATE_MODE_CP);
    }

    private function _getLocalDocument($segment)
    {
        $entry = Entry::find()
            ->sectionId($this->_settings->section)
            ->slug($segment)
            ->one();
        if ($this->_settings->templateOverride) {
            $template = $this->_settings->templateOverride;
            $mode = View::TEMPLATE_MODE_SITE;
        } else {
            $template = 'usermanual/_body.twig';
            $mode = View::TEMPLATE_MODE_CP;
        }
        return Craft::$app->getView()->renderTemplate($template,['entry' => $entry],$mode);

    }

    private function _getLocalNav()
    {
        $query = Entry::find()
            ->sectionId($this->_settings->section)
            ->level(1)
            ->with('children')
            ->all();
        $entries = [];
        // KD specific -- lets match the external format.
        foreach ($query as $entry) {
            $children = [];
            foreach ($entry->children as $child) {
                $children[] = [
                    'title' => $child->title,
                    'uri' => $child->uri,
                    'slug' => $child->slug,
                    'hasDescendants' => $child->hasDescendants,
                    'level' => $child->level,
                    'children' => [],
                    'link' => UrlHelper::cpUrl('usermanual/local/' . ($child->uri ?? $child->slug))
                ];
            }
            $entries[] = [
                'title' => $entry->title,
                'uri' => $entry->uri,
                'slug' => $entry->slug,
                'hasDescendants' => $entry->hasDescendants,
                'level' => $entry->level,
                'children' => $children,
                'link' => UrlHelper::cpUrl('usermanual/local/' . ($entry->uri ?? $entry->slug))
            ];
        }

        return $entries;
    }

    private function _getRemoteDocument($segment)
    {
        $url = 'doc.html?doc=' . $segment;
        $response = $this->_apiCall($url);
        try {
            $response = $this->_apiCall($url);
            return $response->getBody();
        } catch (\Throwable $th) {
            Craft::$app->session->setError('Could not retrieve remote document please contat administrator.');
            return 'Could not retrieve remote document please contat administrator.';
        }
    }

    private function _getRemoteNav()
    {
        $url = 'nav';
        $response = $this->_apiCall($url);
        if ($response) {
            $nav = json_decode($response->getBody(), true);
            foreach ($nav as $key => $value) {
                $nav[$key]['link'] = UrlHelper::cpUrl('usermanual/remote/' . $value['uri']);
                foreach ($nav[$key]['children'] as $k => $child) {
                    $nav[$key]['children'][$k]['link'] = UrlHelper::cpUrl('usermanual/remote/' . $child['uri']);
                }
            }
            return $nav;
        } else {
            Craft::$app->session->setError('No Manuals found, please check Remote and Local');
            return false;
        }
        
    }

    private function _apiCall($url)
    {
        $client = new GuzzleHttp\Client();
        $baseUrl = $this->_settings->remoteSourceUrl;

        try {
            return $client->request('GET', $baseUrl.$url);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return false;
            }
        }
    }

}
