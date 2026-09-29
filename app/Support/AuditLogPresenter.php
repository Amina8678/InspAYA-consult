<?php

namespace App\Support;

use App\Models\AuditLog;

/**
 * Turns a raw audit_logs row into display text: who did it (actor), and
 * what it was done to (entity), with a link to the entity's edit page when
 * it still exists. Static, like the other presentation-only Support classes
 * (Slug, SafeUrl): no state, safe to call from a Blade view directly.
 */
class AuditLogPresenter
{
    /**
     * Filter dropdown labels and detail-page headings; entity types never
     * written by AuditLogger (contact_submission_note, site_setting, role,
     * permission, audit_log itself) are left out on purpose.
     *
     * @var array<string, string>
     */
    public const ENTITY_TYPES = [
        'blog_post' => 'Blog post',
        'category' => 'Category',
        'consultant' => 'Consultant',
        'contact_submission' => 'Enquiry',
        'core_value' => 'Core value',
        'media' => 'Media file',
        'page' => 'Page',
        'service' => 'Service',
        'tag' => 'Tag',
        'user' => 'User',
    ];

    /** Every action string AuditLogger::record() has been called with. */
    public const ACTIONS = [
        'login', 'login_failed', 'login_throttled', 'logout',
        'password_changed', 'password_reset_requested', 'password_reset',
        'created', 'updated', 'deleted', 'reordered',
        'published', 'unpublished', 'deactivated', 'reactivated',
        'note_created', 'note_updated', 'note_deleted', 'settings_updated',
    ];

    /** route() name for each entity type's edit (or, for an enquiry, show) page. */
    private const EDIT_ROUTES = [
        'blog_post' => 'admin.posts.edit',
        'category' => 'admin.categories.edit',
        'consultant' => 'admin.consultants.edit',
        'contact_submission' => 'admin.enquiries.show',
        'core_value' => 'admin.core-values.edit',
        'media' => 'admin.media.edit',
        'page' => 'admin.pages.edit',
        'service' => 'admin.services.edit',
        'tag' => 'admin.tags.edit',
        'user' => 'admin.users.edit',
    ];

    /**
     * Which stored field is that entity's display name. An enquiry has none:
     * the visitor's name/subject is deliberately never copied into the audit
     * log (stage 7), so it can only ever show as "Enquiry #<id>".
     */
    private const NAME_FIELD = [
        'blog_post' => 'title',
        'page' => 'title',
        'service' => 'title',
        'core_value' => 'title',
        'category' => 'name',
        'tag' => 'name',
        'consultant' => 'name',
        'user' => 'name',
        'media' => 'file_name',
    ];

    /** Actions performed by a visitor who was never signed in, even when a targeted account is recorded. */
    private const GUEST_ACTIONS = ['login_failed', 'password_reset_requested', 'password_reset'];

    public static function entityTypeLabel(?string $type): string
    {
        return $type === null ? 'None' : (self::ENTITY_TYPES[$type] ?? str($type)->headline());
    }

    /**
     * $log->auditable must already be eager loaded (lazy loading is
     * disallowed outside production, see AppServiceProvider).
     *
     * @return array{label: string, url: ?string}
     */
    public static function entity(AuditLog $log): array
    {
        if ($log->entity_type === null) {
            return ['label' => 'None', 'url' => null];
        }

        $typeLabel = self::entityTypeLabel($log->entity_type);
        $nameField = self::NAME_FIELD[$log->entity_type] ?? null;

        if ($log->auditable !== null) {
            $name = $nameField ? $log->auditable->{$nameField} : null;
            $route = self::EDIT_ROUTES[$log->entity_type] ?? null;

            return [
                'label' => $name ? "{$typeLabel}: {$name}" : "{$typeLabel} #{$log->entity_id}",
                'url' => $route ? route($route, $log->auditable) : null,
            ];
        }

        // Gone: fall back to a name-ish field the stored diff happens to have.
        $name = $nameField ? ($log->new_values[$nameField] ?? $log->old_values[$nameField] ?? null) : null;

        return [
            'label' => ($name ? "{$typeLabel}: {$name}" : "{$typeLabel} #{$log->entity_id}").' (deleted)',
            'url' => null,
        ];
    }

    /**
     * $log->user must already be eager loaded, with its role.
     */
    public static function actor(AuditLog $log): string
    {
        if (in_array($log->action, self::GUEST_ACTIONS, true)) {
            return 'Guest';
        }

        if ($log->user === null) {
            return 'System';
        }

        return $log->user->name.' ('.($log->user->role?->name ?? 'no role').')';
    }
}
