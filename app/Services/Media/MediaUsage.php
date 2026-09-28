<?php

namespace App\Services\Media;

use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;

/**
 * Finds and clears every place a media item is used (plan §3.6): the four
 * foreign-key columns, plus media ids stored inside pages.structured_content,
 * which the database cannot track.
 */
class MediaUsage
{
    /**
     * @return list<array{type: string, id: int, label: string, field: string}>
     */
    public function find(Media $media): array
    {
        $usages = [];

        foreach (BlogPost::where('featured_image_id', $media->id)->get(['id', 'title']) as $post) {
            $usages[] = ['type' => 'blog_post', 'id' => $post->id, 'label' => $post->title, 'field' => 'Featured image'];
        }

        foreach (Consultant::where('photo_id', $media->id)->get(['id', 'name']) as $consultant) {
            $usages[] = ['type' => 'consultant', 'id' => $consultant->id, 'label' => $consultant->name, 'field' => 'Photo'];
        }

        foreach (CoreValue::where('icon_id', $media->id)->get(['id', 'title']) as $value) {
            $usages[] = ['type' => 'core_value', 'id' => $value->id, 'label' => $value->title, 'field' => 'Icon'];
        }

        foreach (SiteSetting::where('media_id', $media->id)->get(['id', 'key']) as $setting) {
            $usages[] = ['type' => 'site_setting', 'id' => $setting->id, 'label' => $setting->key, 'field' => 'Setting image'];
        }

        foreach ($this->pagesUsing($media) as [$page, $locations]) {
            foreach ($locations as $location) {
                $usages[] = ['type' => 'page', 'id' => $page->id, 'label' => $page->title, 'field' => $location];
            }
        }

        return $usages;
    }

    /**
     * Sets every reference to null. Call inside the delete transaction.
     */
    public function clear(Media $media): void
    {
        BlogPost::where('featured_image_id', $media->id)->update(['featured_image_id' => null]);
        Consultant::where('photo_id', $media->id)->update(['photo_id' => null]);
        CoreValue::where('icon_id', $media->id)->update(['icon_id' => null]);
        SiteSetting::where('media_id', $media->id)->update(['media_id' => null]);

        foreach ($this->pagesUsing($media) as [$page]) {
            $page->structured_content = collect($page->structured_content)
                ->map(function ($block) use ($media) {
                    foreach ((array) ($block['data'] ?? []) as $key => $value) {
                        if ($this->isReference($key, $value, $media)) {
                            $block['data'][$key] = null;
                        }
                    }

                    return $block;
                })
                ->all();
            $page->save();
        }
    }

    /**
     * Pages are few, so they are scanned in PHP: JSON text differs between
     * MySQL (normalised with spaces) and SQLite, making a LIKE match unreliable.
     *
     * @return list<array{Page, list<string>}>
     */
    private function pagesUsing(Media $media): array
    {
        $found = [];

        foreach (Page::whereNotNull('structured_content')->lazyById(100, 'id') as $page) {
            $locations = [];

            foreach ((array) $page->structured_content as $index => $block) {
                foreach ((array) ($block['data'] ?? []) as $key => $value) {
                    if ($this->isReference($key, $value, $media)) {
                        $locations[] = sprintf('Section %d (%s): %s', $index + 1, $block['type'] ?? 'unknown', $key);
                    }
                }
            }

            if ($locations !== []) {
                $found[] = [$page, $locations];
            }
        }

        return $found;
    }

    private function isReference(int|string $key, mixed $value, Media $media): bool
    {
        return str_ends_with((string) $key, '_media_id') && is_numeric($value) && (int) $value === $media->id;
    }
}
