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

namespace kuriousagency\usermanual\variables;

use kuriousagency\usermanual\UserManual;

use Craft;

/**
 * @author    Rob Erskine
 * @package   Usermanual
 * @since     2.0.0
 */
class UserManualVariable
{
    // Public Methods
    // =========================================================================

    public function getName()
    {
        return UserManual::$plugin->getName();
    }

    public function getSettings()
    {
        return UserManual::$plugin->getSettings();
    }

    public function getNavBar()
    {
        return UserManual::$plugin->service->getNavBar();
    }

    public function getHelpDocument()
    {
        return UserManual::$plugin->service->getHelpDocument();
    }
}
