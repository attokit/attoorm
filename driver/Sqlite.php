<?php
/**
 * sqlite 类型数据库 驱动
 */

namespace Atto\Orm\driver;

use Atto\Orm\Dbo;
use Atto\Orm\Driver;
use Atto\Orm\Configer;

class Sqlite extends Driver 
{
    //数据库文件后缀名
    public static $ext = ".db";

    //默认 数据库文件 保存路径，默认 [webroot | app/appname]/db
    public static $DBDIR = "db";



    /**
     * !! 必须实现 !!
     */
    
    /**
     * 数据库连接方法
     * @param Array $opt medoo 连接参数
     * @return Dbo 数据库实例
     */
    public static function connect($opt=[])
    {
        //数据库文件
        $dbf = self::getDbPath($opt);
        //var_dump($dbf);
        if (!file_exists($dbf)) return null;
        $dbf = path_fix($dbf);
        $pathinfo = pathinfo($dbf);
        $dbname = $pathinfo["filename"];
        $dbkey = "DB_".md5(path_fix($dbf));
        //检查是否存在缓存的数据库实例
        if (isset(Dbo::$CACHE[$dbkey]) && Dbo::$CACHE[$dbkey] instanceof Dbo) {
            return Dbo::$CACHE[$dbkey];
        }
        //创建数据库实例
        $db = new Dbo([
            "type" => "sqlite",
            "database" => $dbf
        ]);
        //写入参数
        $db->type = "sqlite";
        $db->name = $dbname;
        $db->key = $dbkey;
        $db->pathinfo = $pathinfo;
        $db->config = Configer::parse($db);
        $db->driver = cls("db/driver/Sqlite");
        //缓存
        Dbo::$CACHE[$dbkey] = $db;
        return $db;
    }

    /**
     * 创建数据库
     * @param Array $opt 数据库创建参数
     * @return Bool
     */
    public static function create($opt=[])
    {
        $dbf = self::getDbPath($opt);
        if (file_exists($dbf)) return false;    //已创建
        $fh = @fopen($dbf, "w");
        fclose($fh);
        return true;
    }



    /**
     * tools
     */

    //根据 连接参数中 获取 数据库文件路径
    public static function getDbPath($opt=[])
    {
        return $opt["database"];

        
        $database = $opt["database"] ?? null;
        if (empty($database) || !is_notempty_str($database)) return null;
        //路径分隔符设为 DS
        $database = str_replace("/", DS, trim($database, "/"));
        //统一添加 后缀名
        if (strtolower(substr($database, strlen(self::$ext)*-1))!==self::$ext) $database .= self::$ext;
        //获取数据库路径
        $path = $opt["path"] ?? null;
        if (is_notempty_str($path)) {
            $path = path_find($path, ["checkDir"=>true]);
            if (empty($path)) {
                $path = self::dftDbDir();
            }
        } else {
            $path = self::dftDbDir();
        }
        //数据库文件
        $dbf = $path.DS."sqlite".DS.$database;
        return $dbf;
    }

    //获取默认数据库文件存放位置
    public static function dftDbDir()
    {
        return __DIR__.DS."..".DS."..".DS.trim(self::$DBDIR);
    }
}