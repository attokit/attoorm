<?php
/**
 * 数据库类 
 * 此类可直接操作数据库
 * 
 * 创建 Db 实例：
 *      $db = Dbo::connect([ Medoo Options ])
 * 依赖注入：
 *      $db->setDbApp($app)     关联到 DbApp
 * 获取数据表(模型)类：
 *      $table = $db->Tablename
 * 
 */

namespace Atto\Orm;

use Atto\Orm\Orm;
use Atto\Orm\DbApp;
use Atto\Orm\Model;
use Atto\Orm\Curd;
use Medoo\Medoo;
use Atto\Box\Request;
use Atto\Box\Response;

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
     * Curd 操作类实例
     * 在每次 Curd 完成后 销毁
     */
    protected $curd = null;

    //此数据库实例 挂载到的 dbapp 实例，即：$dbapp->mainDb == $this
    protected $app = null;

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
     * 依赖注入
     * @param Array $di 要注入 数据库实例 的依赖对象，应包含：
     *  [
     *      "app" => 此 数据库实例 所关联到的 DbApp 实例
     *      
     *  ]
     * @return void
     */
    public function dependency($di=[])
    {
        //注入 关联 DbApp 实例
        $app = $di["app"] ?? null;
        if ($app instanceof DbApp) {
            $this->app = $app;
        }

        return $this;
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
        if ($this->app instanceof DbApp) {
            $info["app"] = $this->app->name;
        }
        if ($xpath=="") return $info;
        return arr_item($info, $xpath);
    }

    /**
     * 获取当前数据库中 数据表(模型)类 全称
     * 并对此 数据表(模型) 类 做预处理，注入依赖 等
     * @param String $model 表(模型)名称 如：Usr
     * @return String 类全称
     */
    public function getModel($model)
    {
        if (!$this->app instanceof DbApp) return null;
        $appname = $this->app->name;
        $mpre = $appname."/model";
        $dpre = $mpre."/".$this->name;
        $mcls = Orm::cls($dpre."/".$model);
        if (!class_exists($mcls)) return null;
        if (empty($mcls::$db) || !$mcls::$db instanceof Dbo) {
            //解析表预设参数
            $mcls::parseConfig();
            //依赖注入
            $mcls::dependency([
                //将当前 数据库实例 注入 数据表(模型) 类
                "db" => $this
            ]);
        }
        //if ($initCurd) {
            //初始化一个 curd 操作，并将 $mcls 表(模型)名称 作为 curd 操作对象 table
        //    $this->curdInit($mcls::$name);
        //}
        return $mcls;
    }

    /**
     * 判断 数据表(模型) 是否存在
     * @param String $model 表(模型)名称 如：Usr
     * @return Bool
     */
    public function hasModel($model)
    {
        $mcls = $this->getModel($model);
        return !empty($mcls);
    }

    /**
     * __get 方法
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {
        /**
         * $db->Usr 初始化一个 curd 操作 针对 数据表(模型) 类 Usr
         * 链式调用 curd 操作，直至操作完成：
         *      $db->Usr->join()->field([field1,field2])->where([...]);
         *      $db->Usr->where([...])->select();
         */
        if ($this->hasModel($key)) {
            if ($this->curdInited()!=true || $this->curd->model::$name!=$key) {
                //仅当 curd 操作未初始化，或 当前 curd 操作为针对 此 数据表(模型) 类 时，重新初始化 curd
                $this->curdInit($key);
            }
            return $this->curd;
        }
        if (substr($key, 0, 3)=="new") {
            /**
             * 以 $db->newUsr 方式，获取一个 数据表(模型) 的实例
             * 相当于 新建一条记录，但不写入数据库
             * 返回 数据表(模型) 实例，相当于一条记录
             */
            $model = substr($key, 3);
            $mcls = $this->getModel($model);
            if (empty($mcls)) return null;
            return $mcls::new();
        }
    }

    /**
     * __call medoo method
     */
    public function __call($key, $args)
    {
        // 1    $db->Table->find(...)
        if ($this->curdInited()==true) {
            //如果 已经有一个 curd 操作被初始化，则首先查找 curd 目标 table 表(模型) 的静态方法
            $model = $this->curd["table"];
            $mcls = $this->getModel($model, false);
            if (method_exists($mcls, $key)) {
                //要调用的方法 在 表(模型)类 中存在
                $rst = call_user_func_array([$mcls, $key], $args);
                if ($rst == $mcls) {
                    //如果返回 表(模型) 类，则返回 数据库实例
                    return $this;
                } else {
                    return $rst;
                }
            }
        }
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
     * 初始化一个 curd 操作
     * @param String $tbn 表(模型) 名称
     * @return $this
     */
    public function curdInit($model)
    {
        $model = $this->getModel($model);
        //var_dump($model);
        if (!empty($model)) {
            $this->curd = new Curd($this, $model);
            //var_dump($this->curd);
        }
        return $this;
    }

    /**
     * 销毁当前 curd 操作实例
     * @return Dbo $this
     */
    public function curdDestory()
    {
        if ($this->curdInited()==true) {
            $this->curd = null;
        }
        return $this;
    }

    /**
     * 执行 curd 操作
     * @param String $method medoo method
     * @param Bool $initCurd 是否重新初始化 curd，默认 true
     * @return Mixed
     */
    public function curdQuery($method, $initCurd=true)
    {
        if (!$this->curdInited()) return false;
        $table = $this->curd["table"];
        $field = $this->curd["field"];

        $rst = $this->medoo($method, $table, $field);
        if ($initCurd) $this->curdInit();
        
        return $rst;
    }

    /**
     * 判断 curd 是否已被 inited
     * @return Bool
     */
    public function curdInited()
    {
        return !empty($this->curd) && $this->curd instanceof Curd && $this->curd->db->key == $this->key;
    }

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
        $cls = Orm::cls("model/".ucfirst($tbname));
        if (class_exists($cls)) return $cls;
        return null; 
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
        //var_dump($driver);
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
        $driver = Orm::cls("driver/".ucfirst($type));
        if (class_exists($driver)) return $driver;
        return null;
    }

    
}