<?php

namespace Silber\Bouncer\Conductors;

use Exception;
use InvalidArgumentException;
use Silber\Bouncer\Database\Role;
use Silber\Bouncer\Database\Ability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class GivesAbility
{
    /**
     * The model to be given abilities.
     *
     * @var \Illuminate\Database\Eloquent\Model|string
     */
    protected $model;

    /**
     * Constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model|string  $model
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Give the abilities to the model.
     *
     * @param  mixed  $abilities
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return bool
     */
    public function to($abilities, $model = null)
    {
        $ids = $this->getAbilityIds($abilities, $model);

        $this->getModel()->abilities()->attach($ids);

        return true;
    }

    /**
     * Get the model or create a role.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getModel()
    {
        if ($this->model instanceof Model) {
            return $this->model;
        }

        return Role::firstOrCreate(['title' => $this->model]);
    }

    /**
     * Get the IDs of the provided abilities.
     *
     * @param  \Silber\Bouncer\Database\Ability|array|int  $abilities
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return array
     */
    protected function getAbilityIds($abilities, $model)
    {
        if ($abilities instanceof Ability) {
            return [$abilities->getKey()];
        }

        if ( ! is_null($model)) {
            return [$this->getModelAbility($abilities, $model)->getKey()];
        }

        return $this->abilitiesByTitle($abilities)->pluck('id')->all();
    }

    /**
     * Get an ability for the given entity.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string  $entity
     * @return \Silber\Bouncer\Database\Ability
     */
    protected function getModelAbility($ability, $entity)
    {
        $entity = $this->getEntityInstance($entity);

        $model = Ability::where('title', $ability)->forModel($entity)->first();

        return $model ?: Ability::createForModel($entity, $ability);
    }

    /**
     * Get an instance of the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @return \Illuminate\Database\Eloquent\Mo
     */
    protected function getEntityInstance($model)
    {
        if ( ! $model instanceof Model) {
            return new $model;
        }

        // Creating an ability for a model that doesn't exist gives the user the
        // ability on all instances of that model. If the developer passed in
        // a model instance that does not exist, it is probably a mistake.
        if ( ! $model->exists) {
            throw new InvalidArgumentException(
                'The model does not exist. To allow access to all models, use the class name instead'
            );
        }

        return $model;
    }

    /**
     * Get or create abilities by their title.
     *
     * @param  array|string  $ability
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function abilitiesByTitle($ability)
    {
        $abilities = array_unique(is_array($ability) ? $ability : [$ability]);

        $models = Ability::simpleAbility()->whereIn('title', $abilities)->get();

        $created = $this->createMissingAbilities($models, $abilities);

        return $models->merge($created);
    }

    /**
     * Create abilities whose title is not in the given list.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @param  array  $abilities
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function createMissingAbilities(Collection $models, array $abilities)
    {
        $missing = array_diff($abilities, $models->pluck('title')->all());

        $created = [];

        foreach ($missing as $ability) {
            $created[] = Ability::create(['title' => $ability]);
        }

        return $created;
    }
}
