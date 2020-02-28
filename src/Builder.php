<?php
/**
 * @license   MIT
 * @copyright Copyright (c) 2017
 * @author    : bugbear
 * @date      : 2017/3/12
 * @time      : 下午2:40
 */

namespace Mews;


use Mews\Builder\Mongo;
use Mews\Builder\Mysql;
use Mews\Connector\ConnectorInterface;
use Mews\Builder\BuilderInterface;

class Builder
{

    public $connector;

    public $transId;

   public function __construct(ConnectorInterface $connector, $transId)
   {
       $this->connector = $connector;
       $this->transId = $transId;
   }

   public function getBuilder($type): BuilderInterface
   {
       if ($type === 'mysql') {
           return new Mysql($this->connector);
       } else if ($type === 'mongodb') {
           return new Mongo($this->connector);
       }
   }
}