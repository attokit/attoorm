<?php
/**
 * CURD 操作类
 * 每次 curd 操作，将生成一个 Curd 实例
 * 操作结束后，此实例将释放
 */

namespace Atto\Box\db;

use Atto\Box\db\Dbo;
use Atto\Box\db\Model;
use Medoo\Medoo;

class Curd 
{
    //关联的数据库实例 Dbo
    public $db = null;

    //关联的 模型(数据表) 类，不是实例 ！！！
    public $table = null;

    /**
     * curd 参数
     * 采用 medoo 方法，参数为 medoo 方法的参数
     */
    //表关联，可以在 模型(数据表)类中 预定义
    public $join = [
        /*"[<]table" => [
            "pid" => "pid"
        ]*/
    ];
    //要返回值的 字段名 []
    public $field = ["*"];
    //where 参数
    public $where = [];

    /**
     * 构造 curd 操作实例
     */
    public function __construct(Dbo $db, Model $table)
    {
        $this->db = $db;
        $this->table = $table;
    }
}