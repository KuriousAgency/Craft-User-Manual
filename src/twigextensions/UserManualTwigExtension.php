<?php

/**
 * usermanual plugin for Craft CMS 3.x
 *
 * Craft User Manual allows developers (or even content editors) to provide CMS
 * documentation using Craft's built-in sections (singles, channels, or structures)
 * to create a `User Manual` or `Help` section directly in the control panel.
 *
 * @link      https://twitter.com/erskinerob
 * @copyright Copyright (c) 2018 Rob Erskine
 */

namespace hillholliday\usermanual\twigextensions;

use hillholliday\usermanual\UserManual;

use Craft;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\web\View;
use Twig_Extension;
use Twig_SimpleFunction;
use Twig_SimpleFilter;
use GuzzleHttp;

/**
 * @author    Rob Erskine
 * @package   Usermanual
 * @since     2.0.0
 */
class UserManualTwigExtension extends Twig_Extension
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'User Manual Twig Extension';
    }

    /**
     * @inheritdoc
     */
    public function getFunctions(): array
    {
        return [
            new Twig_SimpleFunction('getHelpDocument', [$this, 'getHelpDocument']),
            new Twig_SimpleFunction('getExternalNav', [$this, 'getExternalNav']),
            new Twig_SimpleFunction('getExternalDocument', [$this, 'getExternalDocument']),
            new Twig_SimpleFunction('getHomepage', [$this, 'getHomepage'])
        ];
    }

    /**
     * Render an entry in the given section using the nominated template
     *
     * @return string
     */
    public function getHelpDocument()
    {
        $settings = UserManual::$plugin->getSettings();
        $query = Entry::find();

        $segments = Craft::$app->request->segments;
        $segment = end($segments);
        $sectionId = $settings->section;

        if (count($segments) === 1 && $segment === 'usermanual') {
            $slug = null;
        } else {
            $slug = $segment;
        }

        $criteria = [
            'sectionId' => $sectionId,
            'slug' => $slug,
        ];


        Craft::configure($query, $criteria);
        $entry = $query->one();

        
        // Craft::dd($sectionId);

        // If the app has not been set up at all or there are no entires,
        // redirect to the settings page
        if (!$sectionId || !$entry) {
            Craft::$app->controller->redirect(UrlHelper::cpUrl('settings/plugins/usermanual/'))->send();
        } else {
            if ($settings->templateOverride) {
                // Setting the mode also sets the templatepath to the default for that mode
                Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
                $template = $settings->templateOverride;
            } else {
                $template = 'usermanual/_body.twig';
            }
            $output = Craft::$app->view->renderTemplate($template, [
                'entry' => $entry,
            ]);

            // Ensure template mode is set back to control panel
            Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_CP);

            return $output;
        }
    }

    // KA addition - Mike

    public function getExternalNav()
    {
        $url = 'nav';
        $res = $this->apiCall($url);

        return json_decode($res->getBody(), true);
    }

    public function getExternalDocument()
    {
        $segments = Craft::$app->request->segments;
        $segment = end($segments);
        if ($segment == 'usermanual') {
            $res = $this->getExternalNav();

            if (!count($res)) {
                Craft::$app->session->setFlash('No Manuals found, please check Remote Source URL');
                Craft::$app->controller->redirect(UrlHelper::cpUrl('settings/plugins/usermanual/'))->send();

                return false;

            } else {
                $segment = $res[0]['slug'];
            }
            
        }
        $url = 'doc.html?doc='.$segment;
        $res = $this->apiCall($url);   


        return $res->getBody();
    }
    public function getHomepage()
    {
        $url = 'home.html';
        $res = $this->apiCall($url);

        return $res->getBody();

    }

    public function apiCall($url)
    {
        $client = new GuzzleHttp\Client();
        $settings = UserManual::$plugin->getSettings();
        $baseUrl = $settings->remoteSourceUrl;
        $res = $client->request('GET', $baseUrl.$url);

        return $res;
    }
}
