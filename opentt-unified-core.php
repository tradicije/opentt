<?php
/**
 * Plugin Name: OpenTT
 * Description: OpenTT sistem za vođenje i prikaz stonoteniskih takmičenja, klubova i igrača: admin meni, DB tabele i kompatibilno učitavanje postojećih shortcode modula.
 * Version: 1.0.0
 * Author: Aleksa Dimitrijević
 * Author URI: https://instagram.com/tradicije
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!class_exists('OpenTT\\Unified\\Plugin')) {
    require_once __DIR__ . '/src/Plugin.php';
}

\OpenTT\Unified\Plugin::boot(__FILE__);
