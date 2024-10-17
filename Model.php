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
use Atto\Orm\ModelSet;

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
    public static $table = "";  //数据表(模型)类 在数据库中的 表名称，通常是 model::$name 的全小写
    public static $title = "";
    public static $desc = "";
    public static $xpath = "";  // Appname/dbname/Tbname  -->  \Atto\Orm\Appname\model\dbname\Tbname
    //表结构
    public static $creation = [
        //...
    ];
    //字段 meta 数据
    public static $meta = [];
    //关联表预设，medoo 方法的 join 参数形式
    public static $join = [

    ];
    //每次查询必须包含的字段
    public static $includes = ["id","enable"];

    //预设参数解析对象 ModelConfiger 实例
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
        //$data = arr_extend(static::$default, $data);
        $this->context = $data;

        $aif = static::aif();
        $this->isNew = !isset($data[$aif]);
    }

    /**
     * __get
     */
    public function __get($key)
    {
        //$rs->fieldname 返回字段值
        if (static::hasField($key)) {
            return $this->context[$key];
        }

        //$rs->Modelname 返回 数据表(模型) 类
        if (static::$db instanceof Dbo && static::$db->hasModel($key)) {
            return static::$db->getModelCls($key, false);
        }

        //$rs->Model 返回当前 数据表(模型) 类
        if ($key=="Model") {
            return static::cls();
        }

        return null;
    }






    /**
     * 数据表 方法
     * 均为 静态方法
     */

    /**
     * 解析 数据表(模型) 预设参数
     * @return String 类全称
     */
    public static function parseConfig()
    {
        $cls = static::cls();
        //使用 ModelConfiger 解析表预设
        static::$configer = new ModelConfiger($cls);
        return $cls;
    }

    /**
     * 依赖注入
     * @param Array $di 要注入 模型(表) 类的依赖对象，应包含：
     *  [
     *      "db" => 此 模型(表) 所在的数据库实例
     *      
     *  ]
     * @return String 类全称
     */
    public static function dependency($di=[])
    {
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
        $rs = $db->curdQuery("select");
        var_dump($rs);
        //create record set
        $rso  = [];
        foreach ($rs as $i => $rsi) {
            $rso[$i] = new static($rsi);
        }
        return $rso;
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
     * 包裹 curd 操作得到的 结果
     * 根据不同的 $rst 返回不同的数据：
     *      PDOStatement                    根据 $method 返回 Model 实例  or  ModelSet 记录集
     *      null,false,true,string,number   直接返回
     *      indexed array                   包裹成为 ModelSet 记录集
     *      associate array                 包裹成为 Model 实例
     * @param Mixed $rst 由 medoo 查询操作得到的结果
     * @param String $method 由 medoo 执行的查询方法，select / insert / ...
     * @param Curd $curd curd 操作实例
     * @return Mixed 
     */
    public static function wrap($rst, $method, &$curd)
    {
        $db = static::$db;  //数据库实例
        if ($rst instanceof \PDOStatement) {
            //通常 insert/update/delete 方法返回 PDOStatement
            if ($method=="insert") {
                //返回 刚添加的 Model 实例
                //使用 medoo 实例的 id() 方法，返回最后 insert 的 id
                $id = $db->medoo("id");
                $idf = static::idf();
                //再次 curd 查询，查询完不销毁 curd 实例
                $rst = $curd->where([
                    $idf => $id
                ])->get(false);
                $curd->where = [];
                return $rst;
            } else if ($method=="update") {
                //返回 刚修改的 ModelSet 记录集
                //再次 curd 查询，使用当前的 curd->where 参数
                $rst = $curd->select(false);
                return $rst;
            } else if ($method=="delete") {
                //返回 删除的行数
                $rcs = $rst->rowCount();
                return $rcs;
            } else {
                return $rst;
            }
        } else if (is_notempty_arr($rst)) {
            //返回的是 记录 / 记录集
            if (is_indexed($rst)) {
                //记录集 通常 select/rand 方法 返回记录集
                //包裹为 ModelSet 记录集对象
                $rst = array_map(function($rsi) {
                    return new static($rsi);
                }, $rst);
                $mrs = new ModelSet($rst);
                $mrs->db = $db;
                $mrs->model = static::cls();
                return $mrs;
            } else if (is_associate($rst)) {
                //单条记录 通常 get 方法 返回单条记录
                //包裹为 Model 实例
                $rst = new static($rst);
                return $rst;
            }
        } else {
            return $rst;
        }
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
    public static function aif()
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
    //also can use idf()
    public static function idf() {return static::aif();}



    





    /**
     * 获取 数据表(模型) 类全称
     * @param String $model 表名，不指定 则 返回当前 Model
     * @return Class 类全称 or null
     */
    public static function cls($model="")
    {
        //当前 类全称
        $cls = static::class;
        if (substr($cls, 0,1)!="\\") $cls = "\\".$cls;
        if (!is_notempty_str($model)) {
            //不指定 model 返回当前 数据表(模型) 类全称
            return $cls;
        } else {
            //指定了 model
            if (strpos($model, "/")!==false) {
                $ma = explode("/", $model);
                if (count($ma)==2) {
                    //model == dbn/tbn 访问当前 DbApp 下的 其他数据表 类
                    $dbn = $ma[0];
                    $tbn = $ma[1];
                    $ncls = static::$db->getDbo($dbn)->getModel(ucfirst($tbn));
                } else if (count($ma)==3) {
                    //model == appname/dbn/tbn  访问其他 DbApp 下的 数据表 类
                    $apn = $ma[0];
                    $dbn = $ma[1];
                    $tbn = $ma[2];
                    $appcls = cls("app/".ucfirst($apn));
                    if (class_exists($appcls)) {
                        $app = new $appcls();
                        $dbk = $dbn."Db";
                        $dbo = $app->$dbk;
                        if ($dbo instanceof Dbo) {
                            $ncls = $dbo->getModel(ucfirst($tbn));
                        } else {
                            return null;
                        }
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }
            } else {
                //model == tbn
                $cla = explode("\\", $cls);
                array_pop($cla);
                $cla[] = ucfirst($model);
                $ncls = implode("\\", $cla);
            }
            if (class_exists($ncls)) return $ncls;
            return null;
        }
    }

}