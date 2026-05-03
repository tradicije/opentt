<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
echo \OpenTT\Tournaments\WordPress\Templates::fallbackContent(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
get_footer();

