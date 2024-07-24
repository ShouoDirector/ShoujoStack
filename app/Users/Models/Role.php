<?php

namespace BookStack\Users\Models;

use BookStack\Activity\Models\Loggable;
use BookStack\App\Model;
use BookStack\Permissions\Models\EntityPermission;
use BookStack\Permissions\Models\JointPermission;
use BookStack\Permissions\Models\RolePermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Role.
 *
 * @property int        $id
 * @property string     $display_name
 * @property string     $description
 * @property string     $external_auth_id
 * @property string     $system_name
 * @property bool       $mfa_enforced
 * @property Collection $users
 */
class Role extends Model implements Loggable
{
    use HasFactory;

    protected $fillable = ['display_name', 'description', 'external_auth_id', 'mfa_enforced'];

    protected $hidden = ['pivot'];

    protected $casts = [
        'mfa_enforced' => 'boolean',
    ];

    /**
     * The roles that belong to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->orderBy('name', 'asc');
    }

    /**
     * Get all related JointPermissions.
     */
    public function jointPermissions(): HasMany
    {
        return $this->hasMany(JointPermission::class);
    }

    /**
     * The RolePermissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(RolePermission::class, 'permission_role', 'role_id', 'permission_id');
    }

    /**
     * Get the entity permissions assigned to this role.
     */
    public function entityPermissions(): HasMany
    {
        return $this->hasMany(EntityPermission::class);
    }

    /**
     * Check if this role has a permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        $permissions = $this->getRelationValue('permissions');
        foreach ($permissions as $permission) {
            if ($permission->getRawAttribute('name') === $permissionName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a permission to this role.
     */
    public function attachPermission(RolePermission $permission)
    {
        $this->permissions()->attach($permission->id);
    }

    /**
     * Detach a single permission from this role.
     */
    public function detachPermission(RolePermission $permission)
    {
        $this->permissions()->detach([$permission->id]);
    }

    /**
     * Get the role of the specified display name.
     */
    public static function getRole(string $displayName): ?self
    {
        return static::query()->where('display_name', '=', $displayName)->first();
    }

    /**
     * Get the role object for the specified system role.
     */
    public static function getSystemRole(string $systemName): ?self
    {
        static $cache = [];

        if (!isset($cache[$systemName])) {
            $cache[$systemName] = static::query()->where('system_name', '=', $systemName)->first();
        }

        return $cache[$systemName];
    }

    /**
     * {@inheritdoc}
     */
    public function logDescriptor(): string
    {
        return "({$this->id}) {$this->display_name}";
    }
}
