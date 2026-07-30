<?php


namespace CatLab\Charon\Laravel\Contracts;


interface Response
{
    /**
     * Merge extra top-level meta entries (e.g. "$refs") into the encoded
     * response body, alongside whatever the wrapped resource/collection
     * already serializes.
     * @param array $meta
     * @return static
     */
    public function setMeta(array $meta): static;
}
