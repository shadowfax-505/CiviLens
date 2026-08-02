<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'avatar_path', 'is_active', 'locked_at', 'notification_preferences', 'last_login_at', 'password_changed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locked_at' => 'datetime',
            'notification_preferences' => 'array',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Agency, $this>
     */
    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class)->withPivot('relationship')->withTimestamps();
    }

    /**
     * @return BelongsToMany<District, $this>
     */
    public function preferredDistricts(): BelongsToMany
    {
        return $this->belongsToMany(District::class, 'user_district_preferences')->withTimestamps();
    }

    /**
     * @return HasMany<UserNotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    public function canManageUsers(): bool
    {
        return $this->hasRole(config('civiclens.roles.admin'));
    }

    /**
     * @return HasMany<AccountActivity, $this>
     */
    public function accountActivities(): HasMany
    {
        return $this->hasMany(AccountActivity::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function canAccessApplication(): bool
    {
        return $this->is_active && ! $this->isLocked();
    }
}
