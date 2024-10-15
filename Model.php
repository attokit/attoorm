<?php
/**
 * cgy-orm model base class
 * 数据表(数据模型)类 基类
 * 
 * 类   == 数据表 table
 * 实例 == 某条记录
 * 
 * 数据表方法 == static 静态方法
 * 数据记录方法 == 实例方法 
 * 
 */

namespace Atto\Orm;

use Atto\Orm\Orm;
use Atto\Orm\Dbo;

class Model 
{
    /**
     * 当前数据模型(表) 依赖的 数据库实例
     */
    public static $db = null;

    /**
     * 数据表 预设参数
     * 子类覆盖
     */
    public static $name = "";
    public static $title = "";
    public static $xpath = "";  // Appname/dbname/Tbname  -->  \Atto\Orm\Appname\model\dbname\Tbname
    //表结构
    public static $creation = [
        //...
    ];

    //依赖：预设参数解析对象 ModelConfiger 实例
    public static $configer = null;

    //通过解析上方预设参数 得到的 config 数据
    public static $fields = [];     //全部字段名数组
    public static $field = [];      //各字段 属性
    public static $default = [];    //各字段 默认值



    /**
     * 数据表(模型) 实例参数
     */
    //记录内容
    public $context = [];
    //是否新建 记录
    protected $isNew = false;

    //依赖：字段值转换对象 FieldConvertor 实例
    public $convertor = null;



    /**
     * 数据表(模型) 实例方法
     * 针对 一条记录
     */
    
    /**
     * 构造
     * @param Array $data 记录内容
     * @return Model instance
     */
    public function __construct($data=[])
    {
        $this->context = [];
        $data = arr_extend(static::$default, $data);
        $this->context = $data;

        $aif = static::aiField();
        $this->isNew = !isset($data[$aif]);
    }






    /**
     * 数据表 方法
     * 均为 静态方法
     */

    /**
     * 依赖注入
     * @param Array $di 要注入 模型(表) 类的依赖对象，应包含：
     *  [
     *      "db" => 此 模型(表) 所在的数据库实例
     *      
     *  ]
     * @return void
     */
    public static function dependency($di=[])
    {
        //依赖：数据表预设参数解析对象
        static::$configer = new ModelConfiger(static::cls());

        //依赖：此表所在数据库实例
        $db = $di["db"] ?? null;
        if (!empty($db) && $db instanceof Dbo) {
            static::$db = $db;
        }

        

        return static::cls();
    }

    /**
     * curd 操作
     */
    //r
    public static function find(...$args)
    {
        $tb = static::$name;
        $db = static::$db;
        if (!$db instanceof Dbo) return static::cls();
        $rs = $db->medoo("select", ...$args);
        return $rs;
    }

    /**
     * 创建表
     * !! 子类必须实现 !!
     * @return Bool
     */
    public static function createTable()
    {
        //... 子类实现

        return true;
    }

    /**
     * 创建一条新记录，但不写入数据库
     * @param Array $data 记录初始值
     * @return Model 实例
     */
    public static function new($data=[])
    {
        return new static($data);
    }

    /**
     * 判断 表 是否包含字段 $field
     * @param String $field
     * @return Bool
     */
    public static function hasField($field)
    {
        $fds = static::$fields;
        return in_array($field, $fds);
    }

    /**
     * 获取此表的自增字段
     * @return String 字段名
     */
    public static function aiField()
    {
        $fdc = static::$field;
        $rtn = "id";
        foreach ($fdc as $fdn => $c) {
            if ($c["ai"]==true) {
                $rtn = $fdn;
                break;
            }
        }
        return $rtn;
    }



    





    /**
     * 返回 Model 子类
     * @return Class
     */
    public static function cls()
    {
        return static::class;
    }

}