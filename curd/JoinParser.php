<?php
/**
 * Curd 操作类 join 参数处理
 */

namespace Atto\Orm\curd;

use Atto\Orm\Orm;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Orm\Curd;
use Atto\Orm\curd\Parser;

class JoinParser extends Parser 
{

    /**
     * 初始化 curd 参数
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function initParam()
    {

    }

    /**
     * 设置 curd 参数
     * !! 子类必须实现 !!
     * @param Mixed $param 要设置的 curd 参数
     * @return Parser $this
     */
    public function setParam($param=null)
    {

    }

    /**
     * 执行 curd 操作前 返回处理后的 curd 参数
     * !! 子类必须实现 !!
     * @return Mixed curd 操作 medoo 参数，应符合 medoo 参数要求
     */
    public function getParam()
    {

    }
}