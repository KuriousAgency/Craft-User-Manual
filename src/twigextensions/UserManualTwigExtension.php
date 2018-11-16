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
            new Twig_SimpleFunction('getExternalDocs', [$this, 'getExternalDocs']),
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
        // if (!$entry) {
            $remoteQuery = $this->getExternalDocs();
            foreach ($remoteQuery as $manual) {
                if ($manual['slug'] == $slug) {
                    $entry = $manual;
                }
            }
            // Craft::dd($entry);
        // }
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

    public function getExternalDocs()
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', 'http://kurious-digital.test/api/manuals/nav');

        return json_decode($res->getBody(), true);

    }
}
