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

namespace Atto\Box\db;

class Model 
{
    /**
     * 当前数据模型(表) 依赖的 数据库实例
     */
    public static $db = null;




    /**
     * 数据表 参数预设
     * 可通过 Model::defineTable([]) 方法 定义和修改
     */
    public static $options = [

    ];



    /**
     * 数据表 方法
     * 均为 静态方法
     */

    /**
     * 依赖注入
     * 所属 数据库 实例
     * @return void
     */
    public static function useDb($db)
    {
        static::$db = $db;
        return static::cls();
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
     * 返回 Model 子类
     * @return Class
     */
    public static function cls()
    {
        return static::class;
    }

}