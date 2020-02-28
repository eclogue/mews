<?php


namespace Mews;


interface ConnectorInterface
{

    public function connect(array $config);

    public function query();

    public function execute();
}