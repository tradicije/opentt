<?php
if (!defined('ABSPATH')) {
    exit;
}

echo \OpenTT\Tournaments\WordPress\Templates::renderBlockTemplateContent(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

