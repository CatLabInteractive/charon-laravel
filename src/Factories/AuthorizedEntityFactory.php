<?php

namespace CatLab\Charon\Laravel\Factories;

use Illuminate\Contracts\Auth\Access\Gate;

/**
 * This EntityFactory calls a policy method for each Entity that is fetched.
 * This way it will not be possible for users to link/fetch entities they have no right to use.
 *
 * This class heavily relies on Laravels default Authorization module.
 */
class AuthorizedEntityFactory extends EntityFactory
{
    /**
     * @var string
     */
    protected $authorizationMethod;

    /**
     *
     */
    public function __construct(string $authorizationMethod = 'view')
    {
        $this->authorizationMethod = $authorizationMethod;
    }

    /**
     * @param $entity
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function getAuthorizedResolvedEntity($entity)
    {
        if ($entity) {
            app(Gate::class)->authorize($this->authorizationMethod, $entity);
        }

        return $entity;
    }
}
