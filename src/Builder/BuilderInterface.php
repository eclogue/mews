<?php


namespace Mews\Builder;


interface BuilderInterface
{

    public function select(array $options=[]);

    public function update(array $update, array $options);

    public function insert(array $data);

    public function delete();
}