<?php

/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Roman Morenko <stephen@nielson.org>
 * @copyright Copyright (c) 2025 Roman Morenko <morenko83@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\NewoAI;


/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\NewoAI\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

/**
 * @global EventDispatcher $eventDispatcher Injected by the OpenEMR module loader;
 */

$bootstrap = new Bootstrap($eventDispatcher);
$bootstrap->subscribeToEvents();
