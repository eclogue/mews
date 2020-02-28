<?php


namespace Mews\Connector;


interface ConnectorInterface
{

    public function connect();

    public function execute($params, $options);

}