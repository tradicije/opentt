<?php
/**
 * OpenTT - Table Tennis Management Plugin
 * Copyright (C) 2026 Aleksa Dimitrijević
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 */

namespace OpenTT\Unified\WordPress\Shortcodes;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class MatchesGridAltShortcode
{
    public static function render($atts, array $deps)
    {
        $renderDefault = isset($deps['render_default']) && is_callable($deps['render_default'])
            ? $deps['render_default']
            : null;

        if ($renderDefault === null) {
            return '';
        }

        $baseHtml = (string) $renderDefault($atts);
        if ($baseHtml === '') {
            return '';
        }

        $transformed = self::transformMarkup($baseHtml);
        return self::assetsOnce() . '<div class="opentt-matches-grid-alt">' . $transformed . '</div>';
    }

    private static function transformMarkup($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return $html;
        }

        if (!class_exists(DOMDocument::class)) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><body><div id="opentt-alt-root">' . $html . '</div></body></html>';
        $ok = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if (!$ok) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $xpath = new DOMXPath($dom);
        $items = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " opentt-item ")]');
        if (!$items || $items->length === 0) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $lastRound = '';
        $roundIndex = 0;

        foreach ($items as $itemNode) {
            if (!$itemNode instanceof DOMElement) {
                continue;
            }

            $roundSlug = (string) $itemNode->getAttribute('data-kolo-slug');
            if ($roundSlug !== $lastRound) {
                $lastRound = $roundSlug;
                $roundIndex = 0;
            }
            $roundIndex++;

            $teams = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " team ")]', $itemNode);
            if (!$teams || $teams->length < 2) {
                continue;
            }

            $teamOne = $teams->item(0);
            $teamTwo = $teams->item(1);
            if (!$teamOne instanceof DOMElement || !$teamTwo instanceof DOMElement) {
                continue;
            }

            $nameOne = self::normalizeDisplayText((string) $xpath->evaluate('string(.//span[1])', $teamOne));
            $nameTwo = self::normalizeDisplayText((string) $xpath->evaluate('string(.//span[1])', $teamTwo));
            $scoreOneRaw = trim((string) $xpath->evaluate('string(.//strong[1])', $teamOne));
            $scoreTwoRaw = trim((string) $xpath->evaluate('string(.//strong[1])', $teamTwo));

            $scoreOne = is_numeric($scoreOneRaw) ? intval($scoreOneRaw) : null;
            $scoreTwo = is_numeric($scoreTwoRaw) ? intval($scoreTwoRaw) : null;

            $scoreText = '- : -';
            if ($scoreOne !== null && $scoreTwo !== null) {
                $scoreText = $scoreOne . ' : ' . $scoreTwo;
            }

            $classOne = '';
            $classTwo = '';
            if ($scoreOne !== null && $scoreTwo !== null) {
                if ($scoreOne > $scoreTwo) {
                    $classOne = 'opentt-alt-win';
                    $classTwo = 'opentt-alt-lose';
                } elseif ($scoreTwo > $scoreOne) {
                    $classOne = 'opentt-alt-lose';
                    $classTwo = 'opentt-alt-win';
                }
            }

            $matchLink = '#';
            $linkNode = $xpath->query('.//a[1]', $itemNode);
            if ($linkNode && $linkNode->length > 0) {
                $a = $linkNode->item(0);
                if ($a instanceof DOMElement && $a->hasAttribute('href')) {
                    $matchLink = (string) $a->getAttribute('href');
                }
            }

            $dateText = self::formatShortDate(
                (string) $itemNode->getAttribute('data-match-date-display'),
                (string) $itemNode->getAttribute('data-match-date')
            );

            while ($itemNode->firstChild) {
                $itemNode->removeChild($itemNode->firstChild);
            }

            $link = $dom->createElement('a');
            $link->setAttribute('class', 'opentt-alt-link');
            $link->setAttribute('href', $matchLink);

            $card = $dom->createElement('article');
            $card->setAttribute('class', 'opentt-alt-card');

            $top = $dom->createElement('div');
            $top->setAttribute('class', 'opentt-alt-card-top');

            $matchNo = $dom->createElement('span', 'Utakmica ' . self::roman($roundIndex));
            $matchNo->setAttribute('class', 'opentt-alt-match-no');
            $top->appendChild($matchNo);

            $date = $dom->createElement('span', $dateText);
            $date->setAttribute('class', 'opentt-alt-date');
            $top->appendChild($date);

            $score = $dom->createElement('div', $scoreText);
            $score->setAttribute('class', 'opentt-alt-score');

            $teamsLine = $dom->createElement('div');
            $teamsLine->setAttribute('class', 'opentt-alt-teams');

            $home = $dom->createElement('span', $nameOne !== '' ? $nameOne : 'Domaćin');
            if ($classOne !== '') {
                $home->setAttribute('class', $classOne);
            }
            $teamsLine->appendChild($home);
            $teamsLine->appendChild($dom->createTextNode(' ~ '));

            $away = $dom->createElement('span', $nameTwo !== '' ? $nameTwo : 'Gost');
            if ($classTwo !== '') {
                $away->setAttribute('class', $classTwo);
            }
            $teamsLine->appendChild($away);

            $sep = $dom->createElement('div');
            $sep->setAttribute('class', 'opentt-alt-sep');

            $sets = $dom->createElement('div', 'Setovi: —');
            $sets->setAttribute('class', 'opentt-alt-sets');

            $card->appendChild($top);
            $card->appendChild($score);
            $card->appendChild($teamsLine);
            $card->appendChild($sep);
            $card->appendChild($sets);

            $link->appendChild($card);
            $itemNode->appendChild($link);
        }

        $root = $dom->getElementById('opentt-alt-root');
        if (!$root) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $out !== '' ? $out : $html;
    }

    private static function roman($number)
    {
        $number = max(1, intval($number));
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
            11 => 'XI', 12 => 'XII', 13 => 'XIII', 14 => 'XIV', 15 => 'XV', 16 => 'XVI', 17 => 'XVII', 18 => 'XVIII', 19 => 'XIX', 20 => 'XX',
            21 => 'XXI', 22 => 'XXII', 23 => 'XXIII', 24 => 'XXIV', 25 => 'XXV', 26 => 'XXVI', 27 => 'XXVII', 28 => 'XXVIII', 29 => 'XXIX', 30 => 'XXX',
        ];

        return isset($map[$number]) ? $map[$number] : (string) $number;
    }

    private static function formatShortDate($dateDisplay, $dateIso = '')
    {
        $dateDisplay = trim((string) $dateDisplay);
        $months = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'Mart',
            4 => 'April',
            5 => 'Maj',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Avgust',
            9 => 'Septembar',
            10 => 'Oktobar',
            11 => 'Novembar',
            12 => 'Decembar',
        ];

        if ($dateDisplay !== '' && preg_match('/^(\d{1,2})\.(\d{1,2})\./', $dateDisplay, $m)) {
            $day = intval($m[1]);
            $month = intval($m[2]);
            return sprintf('%02d. %s', max(1, $day), ($months[$month] ?? ''));
        }

        $dateIso = trim((string) $dateIso);
        if ($dateIso !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateIso, $m)) {
            $month = intval($m[2]);
            $day = intval($m[3]);
            return sprintf('%02d. %s', max(1, $day), ($months[$month] ?? ''));
        }

        return $dateDisplay;
    }

    private static function normalizeDisplayText($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        // Decode HTML entities (single or double encoded).
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }

        // Targeted mojibake recovery for Serbian latin letters (safe, no re-encoding side effects).
        $map = [
            // single-broken sequences
            'Å¡' => 'š',
            'Å¾' => 'ž',
            'Ä‡' => 'ć',
            'Ä' => 'č',
            'Ä‘' => 'đ',
            'Å ' => 'Š',
            'Å½' => 'Ž',
            'Ä†' => 'Ć',
            'ÄŒ' => 'Č',
            'Ä' => 'Đ',
            // double-broken and common variants
            'ÅÅ¡' => 'š',
            'ÅÅ¾' => 'ž',
            'Ã…¡' => 'š',
            'Ã…¾' => 'ž',
            'Ã„‡' => 'ć',
            'Ã„' => 'č',
            'Ã„‘' => 'đ',
            'Ã… ' => 'Š',
            'Ã…½' => 'Ž',
            'Ã„†' => 'Ć',
            'Ã„Œ' => 'Č',
            'Ã„' => 'Đ',
        ];

        // Repeat a few passes because some values are doubly encoded.
        for ($i = 0; $i < 3; $i++) {
            $next = strtr($value, $map);
            if ($next === $value) {
                break;
            }
            $value = $next;
        }

        return $value;
    }

    private static function assetsOnce()
    {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;

        ob_start();
        ?>
        <style id="opentt-matches-grid-alt-style">
        .opentt-matches-grid-alt .opentt-grid,
        .opentt-matches-grid-alt .opentt-grid.cols-2,
        .opentt-matches-grid-alt .opentt-grid.cols-3,
        .opentt-matches-grid-alt .opentt-grid.cols-4,
        .opentt-matches-grid-alt .opentt-grid.cols-5,
        .opentt-matches-grid-alt .opentt-grid.cols-6 {
          display: grid !important;
          grid-template-columns: 1fr !important;
          gap: 14px !important;
        }

        .opentt-matches-grid-alt .opentt-item {
          background: transparent !important;
          border: 0 !important;
          padding: 0 !important;
          margin: 0 !important;
          border-radius: 0 !important;
        }

        .opentt-matches-grid-alt .opentt-alt-card {
          position: relative;
          border: 1px solid #FED7AA26;
          border-radius: 0;
          background: transparent;
          padding: 14px 14px 12px;
        }

        .opentt-matches-grid-alt .opentt-alt-card-top {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 8px;
          font-family: "DM Sans", sans-serif;
          font-size: 12px;
          color: #FED7AA;
        }

        .opentt-matches-grid-alt .opentt-alt-match-no { font-weight: 600; }
        .opentt-matches-grid-alt .opentt-alt-date {
          opacity: .92;
          display: inline-block !important;
          visibility: visible !important;
        }

        .opentt-matches-grid-alt .opentt-alt-score {
          font-family: "Lora", serif;
          font-size: 34px;
          line-height: 1;
          color: #FBBF24;
          font-weight: 700;
          text-align: center;
          margin: 6px 0 8px;
        }

        .opentt-matches-grid-alt .opentt-alt-teams {
          font-family: "Lora", serif;
          font-size: 22px;
          line-height: 1.25;
          text-align: center;
          color: #FED7AA;
        }

        .opentt-matches-grid-alt .opentt-alt-sep {
          height: 1px;
          background: #FED7AA26;
          margin: 10px 0 8px;
        }

        .opentt-matches-grid-alt .opentt-alt-sets {
          font-family: "DM Sans", sans-serif;
          font-size: 13px;
          line-height: 1.35;
          color: #FED7AA;
          text-align: center;
        }

        .opentt-matches-grid-alt .opentt-alt-win {
          font-family: "Lora", serif;
          font-weight: 700;
          color: #FBBF24;
        }

        .opentt-matches-grid-alt .opentt-alt-lose {
          font-family: "Lora", serif;
          font-style: italic;
          font-weight: 400;
          color: rgba(254, 215, 170, .72);
        }

        .opentt-matches-grid-alt .opentt-alt-link {
          text-decoration: none;
          display: block;
        }

        .opentt-matches-grid-alt .opentt-item img,
        .opentt-matches-grid-alt .team img,
        .opentt-matches-grid-alt .opentt-club-fallback-image,
        .opentt-matches-grid-alt .opentt-item-side,
        .opentt-matches-grid-alt .opentt-item-side-top,
        .opentt-matches-grid-alt .opentt-item-side-bottom {
          display: none !important;
        }
        </style>
        <?php

        return (string) ob_get_clean();
    }
}
