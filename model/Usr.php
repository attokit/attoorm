<?php
/**
 * Usr 用户模型(表)
 * Attoorm 数据库服务 usr 表都采用相同的数据模型，因为需要进行统一的 uac 权限控制
 * 所有 dbAccount 账户数据库中的 usr 表，都是相同结构的
 * 如果需要保存 自定义的 usr 信息，可单独建立用户信息表，然后与 usr 表 通过 uid 字段关联起来
 */

namespace Atto\Orm\model;

use Atto\Orm\Model;

class Usr extends Model 
{
    /**
     * 数据模型(表)静态参数
     */
    public static $name = "usr";
    public static $title = "用户表";
    public static $creation = [
        "uid"       => ["VARCHAR(10)", "NOT NULL"],
        "openid"    => ["VARCHAR(30)"],
        "name"      => ["VARCHAR(10)", "NOT NULL"],
        "pwd"       => ["VARCHAR(32)"],
        "role"      => ["VARCHAR", "NOT NULL", "DEFAULT '[]'"],
        "auth"      => ["VARCHAR", "NOT NULL", "DEFAULT '[]'"],
        "info"      => ["VARCHAR"],
        "extra"     => ["VARCHAR", "NOT NULL", "DEFAULT '{}'"],
    ];



    /**
     * 数据表 方法
     * 均为 静态方法
     */

    /**
     * 创建表
     * !! 子类必须实现 !!
     * 
     * @return Bool
     */
    public static function createTable()
    {
        $db = self::$db;
        var_dump($db->info());
        if (empty($db)) return false;
        $name = self::$name;
        $creation = self::$creation;
        return $db->medooCreateTable($name, $creation);
    }
}