<?php

namespace App\Support;

/**
 * The section types a page can contain: exactly the ones the public views
 * render (resources/views/public/partials/sections.blade.php and the home
 * hero; docs/frontend-contract.md §3 "CMS page"), with every field and limit.
 *
 * The editor works on flat form rows (sections[i][heading], …); storage is
 * [{type, data: {…}}] with buttons as {label, url} and images as
 * *_media_id. Only plain text is stored; the views escape it on output.
 */
class PageSections
{
    public const MAX_SECTIONS = 30;

    public const HEADING_MAX = 200;

    public const BUTTON_LABEL_MAX = 60;

    public const URL_MAX = 500;

    /**
     * @var array<string, array{label: string, hint: string, fields: list<string>, body_max: int}>
     */
    public const TYPES = [
        'hero' => [
            'label' => 'Hero banner',
            'hint' => 'Large banner. On the home page the first hero becomes the page heading and top banner.',
            'fields' => ['heading', 'body', 'primary_cta', 'secondary_cta', 'background_media_id'],
            'body_max' => 1000,
        ],
        'intro' => [
            'label' => 'Introduction',
            'hint' => 'Centred introductory text.',
            'fields' => ['heading', 'body'],
            'body_max' => 2000,
        ],
        'feature' => [
            'label' => 'Feature with image',
            'hint' => 'Image beside text (the "Why choose us" layout).',
            'fields' => ['heading', 'body', 'primary_cta', 'secondary_cta', 'background_media_id'],
            'body_max' => 2000,
        ],
        'text' => [
            'label' => 'Text',
            'hint' => 'A heading and paragraphs. Blank lines separate paragraphs.',
            'fields' => ['heading', 'body'],
            'body_max' => 10000,
        ],
    ];

    /**
     * Form keys for a type (buttons become label + url pairs).
     *
     * @return list<string>
     */
    public static function formKeys(string $type): array
    {
        $keys = ['type'];
        foreach (self::TYPES[$type]['fields'] ?? [] as $field) {
            if (in_array($field, ['primary_cta', 'secondary_cta'], true)) {
                $keys[] = $field.'_label';
                $keys[] = $field.'_url';
            } else {
                $keys[] = $field;
            }
        }

        return $keys;
    }

    /**
     * Stored blocks as editable form rows. Unsupported types are left out
     * (the public site doesn't render them either).
     *
     * @param  array<int, mixed>|null  $blocks
     * @return list<array<string, mixed>>
     */
    public static function toForm(?array $blocks): array
    {
        $rows = [];
        foreach ($blocks ?? [] as $block) {
            $type = $block['type'] ?? null;
            if (! is_string($type) || ! isset(self::TYPES[$type])) {
                continue;
            }

            $data = (array) ($block['data'] ?? []);
            $row = ['type' => $type];
            foreach (self::formKeys($type) as $key) {
                if ($key === 'type') {
                    continue;
                }
                if (preg_match('/^(primary_cta|secondary_cta)_(label|url)$/', $key, $m)) {
                    $row[$key] = $data[$m[1]][$m[2]] ?? null;
                } else {
                    $row[$key] = $data[$key] ?? null;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Validated form rows as stored blocks: trimmed, empty values dropped,
     * buttons kept only when they have a link.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function fromForm(array $rows): array
    {
        $blocks = [];
        foreach (array_values($rows) as $row) {
            $type = $row['type'];
            $data = [];

            foreach (self::TYPES[$type]['fields'] as $field) {
                if (in_array($field, ['primary_cta', 'secondary_cta'], true)) {
                    $url = trim((string) ($row[$field.'_url'] ?? ''));
                    if ($url !== '') {
                        $data[$field] = ['label' => trim((string) ($row[$field.'_label'] ?? '')), 'url' => $url];
                    }
                } elseif ($field === 'background_media_id') {
                    if (! empty($row[$field])) {
                        $data[$field] = (int) $row[$field];
                    }
                } else {
                    $value = trim((string) ($row[$field] ?? ''));
                    if ($value !== '') {
                        $data[$field] = $value;
                    }
                }
            }

            $blocks[] = ['type' => $type, 'data' => $data];
        }

        return $blocks;
    }

    /**
     * Add, remove or move a section in the (unsaved) form rows.
     * $action: "add", "remove:<i>", "up:<i>" or "down:<i>".
     *
     * @param  array<int, mixed>  $rows
     * @return array{rows: list<mixed>, message: string}
     */
    public static function apply(array $rows, string $action, ?string $newType): array
    {
        $rows = array_values($rows);
        [$verb, $index] = array_pad(explode(':', $action, 2), 2, null);
        $i = is_numeric($index) ? (int) $index : -1;

        switch ($verb) {
            case 'add':
                if (! isset(self::TYPES[(string) $newType])) {
                    return ['rows' => $rows, 'message' => 'Choose a section type to add.'];
                }
                if (count($rows) >= self::MAX_SECTIONS) {
                    return ['rows' => $rows, 'message' => 'A page can have at most '.self::MAX_SECTIONS.' sections.'];
                }
                $rows[] = ['type' => $newType];

                return ['rows' => $rows, 'message' => self::TYPES[$newType]['label'].' section added at the end. Fill it in, then save.'];

            case 'remove':
                if (isset($rows[$i])) {
                    array_splice($rows, $i, 1);

                    return ['rows' => $rows, 'message' => 'Section '.($i + 1).' removed. Save to keep this change.'];
                }
                break;

            case 'up':
            case 'down':
                $j = $verb === 'up' ? $i - 1 : $i + 1;
                if (isset($rows[$i], $rows[$j])) {
                    [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];

                    return ['rows' => $rows, 'message' => 'Section moved to position '.($j + 1).'. Save to keep this change.'];
                }
                break;
        }

        return ['rows' => $rows, 'message' => 'Nothing to change.'];
    }
}
