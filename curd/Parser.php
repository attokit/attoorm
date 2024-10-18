<?php
/**
 * Curd 操作类 参数处理工具类 基类
 * 处理用于 medoo 查询方法的 参数处理：
 *      table, join, columns, where
 * 
 * 参数形式满足 medoo 方法参数要求
 */

namespace Atto\Orm\curd;

use Atto\Orm\Orm;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Orm\Curd;

abstract class Parser 
{
    /**
     * 依赖：
     * Curd 操作实例
     */
    public $curd = null;

    /**
     * 构造
     * @param Curd $curd 操作实例
     * @return void
     */
    public function __construct($curd)
    {
        if (!$curd instanceof Curd) return null;
        $this->curd = $curd;

        //使用初始化方法
        $this->initParam();
    }

    /**
     * 初始化 curd 参数
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    abstract public function initParam();

    /**
     * 设置 curd 参数
     * !! 子类必须实现 !!
     * @param Mixed $param 要设置的 curd 参数
     * @return Parser $this
     */
    abstract public function setParam($param=null);

    /**
     * 执行 curd 操作前 返回处理后的 curd 参数
     * !! 子类必须实现 !!
     * @return Mixed curd 操作 medoo 参数，应符合 medoo 参数要求
     */
    abstract public function getParam();
}