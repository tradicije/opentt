<?php
/**
 * Plugin Name: OpenTT Tournaments
 * Description: Tournament engine addon for OpenTT, with standalone-ready bootstrap.
 * Version: 0.1.0
 * Author: Aleksa Dimitrijević
 * License: AGPL-3.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Plugin.php';

\OpenTT\Tournaments\Plugin::boot(__FILE__);

