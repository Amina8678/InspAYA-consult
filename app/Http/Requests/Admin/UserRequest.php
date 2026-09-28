<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or edit a CMS user (FR-ADM-11). Creating and editing both need
 * users.create/users.update; role and status changes are further narrowed by
 * UserPolicy in the controller (nobody changes their own role, deactivates
 * themselves, or acts on a Super Admin unless they are one — plan §6 rows
 * 1-6, the Administrator "Limited" interpretation from stage 3).
 *
 * There is no password field: a new account gets a password-setup link
 * through the existing queued reset flow, never a password typed here.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'username' => [
                'required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($target?->id),
            ],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Creating a Super Admin account needs an existing Super Admin: unlike
     * editing (where UserPolicy::assignRole silently keeps the current role
     * for a forbidden choice), a brand new account has no "current role" to
     * fall back to, so this is a hard validation error instead.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('user') instanceof User) {
                    return;
                }

                $role = Role::find($this->input('role_id'));
                if ($role?->slug === 'super-admin' && ! $this->user()->isSuperAdmin()) {
                    $validator->errors()->add('role_id', 'Only a Super Admin can create another Super Admin account.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain letters, numbers, dots, underscores and hyphens.',
            'username.unique' => 'Another user already has this username.',
            'email.unique' => 'Another user already uses this email address.',
            'role_id.exists' => 'Choose a role from the list.',
        ];
    }
}
