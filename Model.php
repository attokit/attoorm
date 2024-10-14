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
     * 创建新数据表
     * 
     */
    public static function defineTable($opt = [])
    {
        
    }

}