<?php
/**
 * 数据库类 
 * 此类可直接操作数据库
 * 
 * 创建 Db 实例：
 *      $db = Dbo::connect([ Medoo Options ])
 *      $db = Dbo::Sqlite('DbName', [ Extra Options ])     --> [Db Path]/DbName.db
 *      $db = Dbo::MySql([ host=>'', database=>'', username=>'', password=>'' ], [ Extra Options])
 * 
 */

namespace Atto\Box\db;

use Medoo\Medoo;

class Dbo
{
    //缓存已创建的 数据库实例
    public static $CACHE = [/*
        "DB_KEY" => Dbo instance,
    */];

    //medoo 实例
    protected $_medoo = null;

    /**
     * 构造 数据库实例
     * @param Array $options Medoo实例创建参数
     */
    public function __construct($options = [])
    {
        $this->_medoo = new Medoo($options);
    }

    /**
     * __call medoo method
     */
    public function __call($key, $args)
    {
        
    }



    /**
     * drivers
     */

    /**
     * connect to Sqlite
     */
    public static function Sqlite($dbf = "")
    {
        $opts = [
            "type" => "sqlite",
            "database" => ""
        ];
        
    }

    /**
     * 数据库参数
     * 可通过 Dbo::defineDbo() 创建和修改
     */

    public function foobar()
    {
        var_export("attoorm db foobar");
    }
}