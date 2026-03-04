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

namespace OpenTT\Unified\WordPress;

final class ImportPayloadInspector
{
    public static function parseFromUpload($fileField)
    {
        $fileField = (string) $fileField;
        if ($fileField === '' || empty($_FILES[$fileField]) || !isset($_FILES[$fileField]['tmp_name'])) {
            return [null, 'Nije pronađen JSON fajl za uvoz.'];
        }

        $file = $_FILES[$fileField];
        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $code = (int) $file['error'];
            if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                $max_upload = (string) ini_get('upload_max_filesize');
                $max_post = (string) ini_get('post_max_size');
                $size = isset($file['size']) ? (int) $file['size'] : 0;
                $size_mb = number_format($size / 1024 / 1024, 2, '.', '');
                return [null, 'Upload je odbijen jer je fajl prevelik (kod: ' . $code . ', veličina: ' . $size_mb . ' MB). Povećaj PHP limite `upload_max_filesize` (trenutno ' . $max_upload . ') i `post_max_size` (trenutno ' . $max_post . ').'];
            }
            return [null, 'Greška pri upload-u fajla (kod: ' . $code . ').'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return [null, 'Neispravan upload fajla.'];
        }

        $raw = file_get_contents($tmp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if (!is_string($raw) || trim($raw) === '') {
            return [null, 'Fajl je prazan ili nečitljiv.'];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [null, 'JSON format nije validan.'];
        }
        if (($payload['format'] ?? '') !== 'opentt-data-transfer') {
            return [null, 'Fajl nije OpenTT data transfer paket.'];
        }
        if (empty($payload['data']) || !is_array($payload['data'])) {
            return [null, 'JSON nema sekciju data.'];
        }

        return [$payload, ''];
    }

    public static function summarize($payload, $sections)
    {
        $payload = is_array($payload) ? $payload : [];
        $sections = is_array($sections) ? $sections : [];
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $summary = [];

        foreach ($sections as $section) {
            $section = (string) $section;
            if ($section === '') {
                continue;
            }
            $rows = isset($data[$section]) && is_array($data[$section]) ? $data[$section] : [];
            $summary[$section] = count($rows);
        }

        return $summary;
    }

    public static function validate($payload, $sections)
    {
        $payload = is_array($payload) ? $payload : [];
        $sections = is_array($sections) ? $sections : [];
        $issues = [];
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $summary = self::summarize($payload, $sections);

        if (in_array('games', $sections, true) && !in_array('matches', $sections, true)) {
            $issues[] = 'Upozorenje: Partije se uvoze bez sekcije Utakmice. Veze će koristiti postojeće utakmice po mapiranju.';
        }
        if (in_array('sets', $sections, true) && !in_array('games', $sections, true)) {
            $issues[] = 'Upozorenje: Setovi se uvoze bez sekcije Partije. Veze će koristiti postojeće partije po mapiranju.';
        }

        foreach ($sections as $section) {
            $section = (string) $section;
            if ($section === '') {
                continue;
            }
            if (empty($data[$section]) || !is_array($data[$section])) {
                $issues[] = 'Sekcija "' . $section . '" je prazna ili ne postoji u JSON-u.';
            }
        }

        $valid = true;
        foreach ($sections as $section) {
            $section = (string) $section;
            if ($section === '') {
                continue;
            }
            if (intval($summary[$section] ?? 0) === 0) {
                $valid = false;
            }
        }

        return [
            'valid' => $valid,
            'summary' => $summary,
            'issues' => $issues,
        ];
    }
}
