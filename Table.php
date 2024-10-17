<?php
/**
 * 数据表类
 * 定义数据表参数
 * 单例模式，一次会话只实例化一次
 */

namespace Atto\Orm;

use Atto\Orm\Orm;
use Atto\Orm\DbApp;
use Atto\Orm\Dbo;

class Table 
{
    /**
     * 当前数据模型(表) 依赖的 数据库实例
     */
    public $db = null;

    /**
     * 数据表 预设参数
     * 子类覆盖
     */
    public $name = "";
    public $table = "";  //数据表(模型)类 在数据库中的 表名称，通常是 model::$name 的全小写
    public $title = "";
    public $desc = "";
    public $xpath = "";  // Appname/dbname/Tbname  -->  \Atto\Orm\Appname\model\dbname\Tbname
    //表结构
    public $creation = [
        //...
    ];
    //字段 meta 数据
    public $meta = [];
    //关联表预设，medoo 方法的 join 参数形式
    public $join = [

    ];
    //每次查询必须包含的字段
    public $includes = ["id","enable"];

    //预设参数解析对象 ModelConfiger 实例
    public $configer = null;

    //通过解析上方预设参数 得到的 config 数据
    public $fields = [];     //全部字段名数组
    public $field = [];      //各字段 属性
    public $default = [];    //各字段 默认值

    /**
     * 构造
     * @param Dbo $db 数据库实例
     * @return Table
     */
    public function __construct($db)
    {
        if (!$db instanceof Dbo) return null;
        if ($db->tableInsed($this->name)) return $db->table($this->name);
    }
}