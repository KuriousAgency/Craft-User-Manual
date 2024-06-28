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

namespace kuriousagency\usermanual;

use kuriousagency\usermanual\variables\UserManualVariable;
use kuriousagency\usermanual\models\Settings;
use kuriousagency\usermanual\services\UserManualService;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use yii\base\Event;

/**
 * Class Usermanual
 *
 * @author    Rob Erskine
 * @package   Usermanual
 * @since     2.0.0
 *
 */
class UserManual extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var UserManual
     */
    public static $plugin;

    // Public Properties
    // =========================================================================
    public string $schemaVersion = '3.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'service' => UserManualService::class,
        ]);

        $this->name = $this->getName();

        // Register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            fn(\craft\events\RegisterUrlRulesEvent $event) => $this->registerCpUrlRules($event)
        );

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event) : void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('userManual', UserManualVariable::class);
            }
        );

        // Plugin Install event
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            fn(\craft\events\PluginEvent $event) => $this->afterInstallPlugin($event)
        );

        Craft::info(
            Craft::t(
                'usermanual',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Returns the user-facing name of the plugin, which can override the name
     * in composer.json
     *
     * @return string
     */
    public function getName()
    {
        $pluginName = Craft::t('usermanual', 'User Manual');
        $pluginNameOverride = $this->getSettings()->pluginNameOverride;

        return $pluginNameOverride ?: $pluginName;
    }

    public function registerCpUrlRules(RegisterUrlRulesEvent $event): void
    {
        $rules = [
            'usermanual/remote/<userManualPath:([a-zéñåA-Z0-9\-\_\/]+)?>' => ['template' => 'usermanual/index'],
            'usermanual/<userManualPath:([a-zéñåA-Z0-9\-\_\/]+)?>' => ['template' => 'usermanual/index'],
        ];

        $event->rules = array_merge($event->rules, $rules);
    }

    public function afterInstallPlugin(PluginEvent $event): void
    {
        $isCpRequest = Craft::$app->getRequest()->isCpRequest;

        if ($event->plugin === $this && $isCpRequest) {
            Craft::$app->controller->redirect(UrlHelper::cpUrl('settings/plugins/usermanual/'))->send();
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        $options = [[
            'label' => 'Not Required',
            'value' => '',
        ]];

        $allSections = Craft::$app->sections->getAllSections();
        Craft::dd($allSections);

        foreach (Craft::$app->sections->getAllSections() as $section) {
            $options[] = [
                'label' => $section['name'],
                'value' => $section['id'],
            ];
        }

        // Get override settings from config file.
        $overrides = Craft::$app->getConfig()->getConfigFromFile(strtolower($this->handle));

        return Craft::$app->view->renderTemplate(
            'usermanual/settings',
            [
                'settings' => $this->getSettings(),
                'overrides' => array_keys($overrides),
                'options' => $options,
                'siteTemplatesPath' => Craft::$app->getPath()->getSiteTemplatesPath(),
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettings(): ?\craft\base\Model
    {
        $settings = parent::getSettings();
        $config = Craft::$app->config->getConfigFromFile('usermanual');

        foreach ($settings as $settingName => $settingValue) {
            $settingValueOverride = null;
            $settingValueOverride = $config[$settingName] ?? $settingValueOverride;
            $settings->$settingName = $settingValueOverride ?? $settingValue;
        }

        return $settings;
    }

}
