<?php
/**
 * 数据模型(表) 实例组成的 数组
 * 包裹为 ModelSet 记录集
 */

namespace Atto\Orm;

use Atto\Orm\Dbo;
use Atto\Orm\Model;

class ModelSet 
{
    //关联的数据库实例 Dbo
    public $db = null;

    //关联的 模型(数据表) 类全称
    public $model = "";

    /**
     * 数据模型(表) 实例 数组
     */
    public $context = [];

    public function __construct($rs=[])
    {
        $this->context = $rs;
    }
}