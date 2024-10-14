<?php
/**
 * 数据库类 
 * 此类可直接操作数据库
 * 
 * 创建 Db 实例：
 *      $db = Dbo::connect([ Medoo Options ])
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

    //默认的数据库文件存放路径 [app/appname]/db
    protected static $DBDIR = "db";

    /**
     * Db config
     */
    public $type = "";      //db type
    public $connectOptions = [];    //缓存的 medoo 连接参数
    public $name = "";
    public $key = "";       //md5($db->name)
    public $pathinfo = [];  //sqlite db file pathinfo
    public $config = null;  //在 config.json 中预定义的 数据库参数，Configer 实例

    //数据库 driver 类
    public $driver = "";    //数据库类型驱动类
    
    //medoo 实例
    protected $_medoo = null;

    /**
     * CURD 操作
     * 参数在每次 CURD 完成后 reset
     */
    protected $curd = [
        "table" => "",      //操作的表，必须
        "field" => "*",     //要获取的字段
    ];

    /**
     * 构造 数据库实例
     * @param Array $options Medoo实例创建参数
     */
    public function __construct($options = [])
    {
        $this->connectOptions = $options;
        $this->medooConnect();
    }

    /**
     * 输出 db 数据库信息
     * @param String $xpath 访问数据库信息
     * @return Array
     */
    public function info($xpath="")
    {
        $ks = explode(",", "type,connectOptions,name,key,pathinfo,driver");
        $info = [];
        foreach ($ks as $i => $k) {
            $info[$k] = $this->$k;
        }
        if ($xpath=="") return $info;
        return arr_item($info, $xpath);
    }




    /**
     * medoo 操作
     */

    //创建 medoo 实例
    protected function medooConnect($opt=[])
    {
        $opt = arr_extend($this->connectOptions, $opt);
        $this->_medoo = new Medoo($opt);
        return $this;
    }

    /**
     * get medoo instance  or  call medoo methods
     * @param String $method
     * @param Array $params
     * @return Mixed
     */
    public function medoo($method = null, ...$params)
    {
        if (is_null($this->_medoo)) $this->medooConnect();
        if (!is_notempty_str($method)) return $this->_medoo;
        if (method_exists($this->_medoo, $method)) return $this->_medoo->$method(...$params);
        return null;
    }

    /**
     * 创建表
     * @param String $tbname 表名称
     * @param Array $creation 表结构参数
     * @return Bool
     */
    public function medooCreateTable($tbname, $creation=[])
    {
        if (!isset($creation["id"])) {
            //自动增加 id 字段，自增主键
            $creation["id"] = [
                "INT", "NOT NULL", "AUTO_INCREMENT", "PRIMARY KEY"
            ];
        }
        if (!isset($creation["enable"])) {
            //自动增加 enable 生效字段，默认 1
            $creation["enable"] = [
                "INT", "NOT NULL", "DEFAULT 1"
            ];
        }
        var_dump($creation);
        return $this->_medoo->debug()->create($tbname, $creation);
    }



    /**
     * CURD
     */
    /**
     * 获取 模型(数据表) 类
     * @param String $tbname 表名称
     * @return Class 模型(数据表)类
     */
    public function table($tbname)
    {
        $dbname = $this->name;
        //数据库名称 foo.bar --> foo\bar
        $clsdbpre = str_replace(".", "\\", $dbname);
        $clspre = NS."\\db\\model\\".$clsdbpre."\\";
        $cls = $clspre.ucfirst($tbname);
        if (class_exists($cls)) return $cls;
        return null; 
    }

    /**
     * __call medoo method
     */
    public function __call($key, $args)
    {
        
    }



    /**
     * static
     */

    /**
     * 创建数据库实例
     * @param Array $opt 数据库连接参数
     * @return Dbo 实例
     */
    public static function connect($opt=[])
    {
        $driver = self::getDriver($opt);
        if (!empty($driver) && class_exists($driver)) {
            return $driver::connect($opt);
        }
        return null;
    }

    /**
     * 创建数据库
     * @param Array $opt 数据库连接参数
     * @return Bool
     */
    public static function create($opt=[])
    {
        $driver = self::getDriver($opt);
        if (!empty($driver) && class_exists($driver)) {
            return $driver::create($opt);
        }
        return false;
    }



    /**
     * static tools
     */

    //根据连接参数 获取 driver 类
    public static function getDriver($opt=[])
    {
        $type = $opt["type"] ?? "sqlite";
        $driver = cls("db/driver/".ucfirst($type));
        if (class_exists($driver)) return $driver;
        return null;
    }

    
}