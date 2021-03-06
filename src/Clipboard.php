<?php

namespace Silber\Bouncer;

use Silber\Bouncer\Database\Ability;
use Illuminate\Database\Eloquent\Model;

class Clipboard
{
    /**
     * Holds the cache of user's abilities.
     *
     * @var array
     */
    protected $cache = [
        'abilities' => [],
        'roles' => [],
    ];

    /**
     * Determine if the given user has the given ability.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return bool
     */
    public function check(Model $user, $ability, $model = null)
    {
        $abilities = $this->getUserAbilities($user);

        foreach ($this->compileRequestedAbility($ability, $model) as $ability) {
            if ($abilities->contains($ability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user has the given role.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @param  array|string  $role
     * @param  string  $boolean
     * @return bool
     */
    public function checkUserRole(Model $user, $role, $boolean = 'or')
    {
        $roles = $this->getUserRoles($user)->intersect($role);

        if ($boolean == 'or') {
            return $roles->count() > 0;
        }

        return $roles->count() == count((array) $role);
    }

    /**
     * Compile a list of abilities that match the provided parameters.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return array
     */
    protected function compileRequestedAbility($ability, $model)
    {
        if (is_null($model)) {
            return [$ability];
        }

        return $this->compileModelAbilities($ability, $model);
    }

    /**
     * Compile a list of abilities that match the given model.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return array
     */
    protected function compileModelAbilities($ability, $model)
    {
        $model = $model instanceof Model ? $model : new $model;

        $ability = [
            'title'       => $ability,
            'entity_id'   => null,
            'entity_type' => $model->getMorphClass(),
        ];

        // If the provided model does not exist, we will only look for abilities
        // where the "entity_id" is null. If the model does exist, we'll also
        // look for the abilities whose "entity_id" matches the model key.
        if ( ! $model->exists) {
            return [$ability];
        }

        return [
            $ability,
            array_merge($ability, ['entity_id' => $model->getKey()])
        ];
    }

    /**
     * Get the given user's abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @param  bool  $fresh
     * @return \Illuminate\Support\Collection
     */
    public function getUserAbilities(Model $user, $fresh = false)
    {
        $id = $user->getKey();

        if ( ! isset($this->cache['abilities'][$id]) || $fresh) {
            $this->cache['abilities'][$id] = $this->getFreshUserAbilities($user);
        }

        return $this->cache['abilities'][$id];
    }

    /**
     * Get the given user's roles.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @param  bool  $fresh
     * @return \Illuminate\Support\Collection
     */
    public function getUserRoles(Model $user, $fresh = false)
    {
        $id = $user->getKey();

        if ( ! isset($this->cache['roles'][$id]) || $fresh) {
            $this->cache['roles'][$id] = $this->getFreshUserRoles($user);
        }

        return $this->cache['roles'][$id];
    }

    /**
     * Clear the cache.
     *
     * @return $this
     */
    public function refresh()
    {
        $this->cache['abilities'] = [];

        $this->cache['roles'] = [];

        return $this;
    }

    /**
     * Clear the cache for the given user.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @return $this
     */
    public function refreshForUser(Model $user)
    {
        unset($this->cache['abilities'][$user->getKey()]);

        unset($this->cache['roles'][$user->getKey()]);

        return $this;
    }

    /**
     * Get a fresh list of the given user's abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @return \Illuminate\Support\Collection
     */
    protected function getFreshUserAbilities(Model $user)
    {
        return collect($this->fetchUserAbilities($user))->map(function ($ability) {
            $isSimpleAbility = is_null($ability->entity_id) && is_null($ability->entity_type);

            return $isSimpleAbility ? $ability->title : (array) $ability;
        });
    }

    /**
     * Fetch a list of the user's abilities from the database.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @return stdClass[]
     */
    protected function fetchUserAbilities(Model $user)
    {
        $query = Ability::whereHas('roles', $this->getRoleUsersConstraint($user));

        $query->orWhereHas('users', $this->getUserConstraint($user));

        return $query->getQuery()->select('title', 'entity_id', 'entity_type')->get();
    }

    /**
     * Constrain a roles query by the given user.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @return \Closure
     */
    protected function getRoleUsersConstraint(Model $user)
    {
        return function ($query) use ($user) {
            $query->whereHas('users', $this->getUserConstraint($user));
        };
    }

    /**
     * Constrain a related query to the given user.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @return \Closure
     */
    protected function getUserConstraint(Model $user)
    {
        return function ($query) use ($user) {
            $column = "{$user->getTable()}.{$user->getKeyName()}";

            $query->where($column, $user->getKey());
        };
    }

    /**
     * Get a fresh list of the given user's roles.
     *
     * @param  array|string  $role
     * @return \Illuminate\Support\Collection
     */
    protected function getFreshUserRoles(Model $user)
    {
        return $user->roles()->lists('title');
    }
}
