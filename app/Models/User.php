<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'ai_provider',
        'ai_api_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'ai_api_key',
    ];

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
            'ai_api_key' => 'encrypted',
        ];
    }

    /**
     * Whether this user has both an AI provider and an API key configured —
     * the precondition for every AI feature in the app.
     */
    public function hasAiConfigured(): bool
    {
        return filled($this->ai_provider) && filled($this->ai_api_key);
    }

    /**
     * A masked form of the stored key for display (e.g. "••••skyw"),
     * revealing only the last four characters. Null when no key is set.
     */
    public function maskedAiKey(): ?string
    {
        if (blank($this->ai_api_key)) {
            return null;
        }

        return '••••'.substr($this->ai_api_key, -4);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('role', TeamMember::ROLE_OWNER);
    }

    public function isOwnerOf(Team $team): bool
    {
        return $this->teams()
            ->wherePivot('role', TeamMember::ROLE_OWNER)
            ->where('teams.id', $team->id)
            ->exists();
    }

    public function isMemberOf(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }
}
