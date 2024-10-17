<?php
/**
 * common DbApp for Attokit/Attoorm framework
 */

namespace Atto\Orm;

use Atto\Box\App;
use Atto\Orm\Orm;
use Atto\Orm\Dbo;

class DbApp extends App 
{
    //app info
    public $intr = "atto-orm通用数据库App";  //app说明，子类覆盖
    public $name = "DbApp";  //app名称，子类覆盖
    public $key = "Atto/Orm/DbApp";   //app调用路径

    /**
     * 与 当前 dbapp 关联的数据库连接参数
     * !! 可以有多个关联数据库 !!
     * 子类覆盖
     */
    protected $dbOptions = [
        //... 子类覆盖
        //必须指定 main 数据库
        //"main" => [
        //    "type" => "sqlite",
        //    "database" => "uac.db"  //保存在默认位置，如果要保存在其他位置，使用 ../ 起始为 app/appname/db/sqlite
        //],
    ];
    //默认的 sqlite 数据库保存位置
    protected $dftSqliteDir = "db/sqlite";

    /**
     * 缓存实例
     */
    //当前 dbapp 关联的数据库实例
    public $dbs = [];
    //当前登录到系统的 用户 model 实例
    public $usr = null;

    /**
     * 当前 dbapp 提供的数据库服务 初始化
     * 在 DbApp 实例化后立即执行
     */
    protected function init() 
    {
        // 1    连接并创建数据库实例
        $this->initDb();


        //缓存这个 DbApp 实例到 Orm::$APP
        Orm::cacheApp($this);

        return $this;
    }

    /**
     * 初始化工具
     * 连接数据库 创建数据库实例
     * @return Dbo instances []
     */
    protected function initDb()
    {
        $dbns = $this->dbns();
        foreach ($dbns as $i => $dbn) {
            //如果还未创建数据库实例的，连接数据库，创建并数据库实例
            $this->dbConnect($dbn);
        }
        return $this;
    }



    /**
     * __GET 方法
     * @param String $key 
     */
    public function __get($key)
    {
        /**
         * $app->mainDb
         * 获取当前 DbApp 下的数据库实例
         */
        if (substr($key, -2)=="Db") {
            $dbn = substr($key, 0, -2);
            return $this->db($dbn);
        }
    }



    /**
     * 数据库工具
     */

    /**
     * 连接数据库，创建并返回数据库实例
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Dbo instance || null
     */
    public function dbConnect($dbn)
    {
        $dbi = $this->db($dbn);
        if (empty($dbi)) {
            //还未创建数据库实例，则连接并创建
            $opti = $this->fixDbPath($dbn);
            if (empty($opti)) $opti = $this->dbOptions[$dbn];
            $dbi = Dbo::connect($opti);
            if ($dbi instanceof Dbo) {
                //依赖注入
                $dbi->dependency([
                    //将当前 dbapp 注入 数据库实例
                    "app" => $this,
                    //注入数据库 键名
                    "keyInApp" => $dbn,
                ]);
                $this->dbs[$dbn] = $dbi;
                return $dbi;
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * 获取已创建的数据库实例
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Dbo instance || null
     */
    protected function db($dbn="")
    {
        $dbn = $dbn=="" ? "main" : $dbn;    //默认返回 main 数据库
        if (!is_notempty_str($dbn) || !isset($this->dbOptions[$dbn])) return null;
        $dbs = $this->dbs;
        if (empty($dbs) || !isset($dbs[$dbn]) || !$dbs[$dbn] instanceof Dbo) return null;
        return $dbs[$dbn];
    }

    /**
     * 获取与此 dbapp 关联的所有 数据库键名
     * @return Array [ 键名, 键名, ... ]
     */
    protected function dbns()
    {
        return array_keys($this->dbOptions);
    }

    /**
     * 判断是否包含 Dbo 数据库
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Bool
     */
    public function hasDb($dbn)
    {
        return isset($this->dbOptions[$dbn]);
    }

    /**
     * 判断数据库是否已经实例化
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Bool
     */
    public function dbConnected($dbn)
    {
        $db = $this->db($dbn);
        return !empty($db) && $db instanceof Dbo;
    }

    /**
     * fix sqlite 数据库路径
     * 默认的存放路径：[app/appname]/[$this->dftSqliteDir]
     * @param String $dbn 数据库键名
     * @return Array 处理后的数据库连接参数
     */
    protected function fixDbPath($dbn="")
    {   
        $dbn = $dbn=="" ? "main" : $dbn;
        $dbs = $this->dbOptions;
        if (!is_notempty_str($dbn) || !isset($dbs[$dbn])) return null;
        $opt = $dbs[$dbn];
        $type = $opt["type"] ?? "sqlite";
        if ($type!="sqlite") return null;
        $database = $opt["database"] ?? null;
        $parr = [];
        $parr[] = $this->dftSqliteDir;
        if (is_notempty_str($database)) {
            $parr[] = $database;
        } else {
            $parr[] = $dbn;
        }
        $dbp = implode("/", $parr);
        if (substr($dbp, -3)!=".db") $dbp .= ".db";
        return [
            "type" => "sqlite",
            "database" => path_fix($this->path($dbp))
        ];
    }


}