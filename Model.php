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
use Atto\Orm\model\Configer;
use Atto\Orm\model\Exporter;
use Atto\Orm\model\ModelSet;

class Model 
{
    /**
     * 当前数据模型(表) 依赖的 数据库实例
     */
    public static $db = null;

    //此 数据表(模型) 类全称
    public static $cls = "";

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
    public static $meta = [
        "fieldname" => ["产品编码", "此库存成品SKU的产品编码，在系统中唯一", 10],
    ];
    //特殊字段参数
    public static $special = [

    ];
    //关联表预设，medoo 方法的 join 参数形式
    public static $join = [

    ];
    //默认每次查询是否使用 join 表
    public static $useJoin = false;
    //每次查询必须包含的字段
    public static $includes = ["id","enable"];

    //预设参数解析对象 ModelConfiger 实例
    public static $configer = null;

    //通过解析上方预设参数 得到的 config 数据
    //public static $fields = [];     //全部字段名数组
    //public static $field = [];      //各字段 属性
    //public static $default = [];    //各字段 默认值



    /**
     * 数据表(模型) 实例参数
     */
    //数据表记录条目内容，不含 关联表 记录内容
    public $context = [];
    //join 关联表 记录实例
    public $joined = [
        //"Tablename" => model instance,
        //...
    ];
    //是否新建 记录
    protected $isNew = false;

    /**
     * 依赖
     */
    //数据记录实例输出工具
    public $exporter = null;

    //依赖：字段值转换对象 FieldConvertor 实例
    public $convertor = null;



    /**
     * 数据表(模型) 实例方法
     * 针对 一条记录
     */
    
    /**
     * 构造
     * 使用 $model::createIns() 方法创建 数据表记录实例
     * @return Model instance
     */
    public function __construct()
    {
        
    }

    /**
     * 构造
     * 执行 constructFunc
     */

    /**
     * 构造
     * 根据 curd 返回的数据 创建 $rs->context 以及 join 关联表实例
     * @param Array $data curd 操作返回的数据，可能包含关联表数据
     * @return Model $this
     */
    protected function parseCurdRtnData($data=[])
    {
        //var_dump($data);
        if (empty($data)) return $this;
        $jtbs = static::$configer->join["tables"] ?? [];
        $mdata = [];
        $jdata = [];
        if (empty($jtbs)) $mdata = $data;
        foreach ($jtbs as $i => $tbn) {
            $tbn = strtolower($tbn);
            $jdi = [];
            foreach ($data as $f => $v) {
                if (substr($f, 0, strlen($tbn)+1)==$tbn."_") {
                    $jdi[substr($f, strlen($tbn)+1)] = $v;
                    //unset($data[$f]);
                } else {
                    $mdata[$f] = $v;
                }
            }
            if (!empty($jdi)) {
                $jdata[$tbn] = $jdi;
            }
        }
        //var_dump($jdata);

        //当前主表数据写入 context
        $this->context = $mdata;
        //创建 join 关联表 实例
        if (!empty($jdata)) {
            foreach ($jdata as $tbi => $tdi) {
                $tbk = ucfirst($tbi);
                $tcls = static::$db->$tbk->cls;
                $this->joined[$tbk] = $tcls::createIns($tdi);
            }
        }
        
        return $this;
    }

    /**
     * __get
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {
        /**
         * 通过 $rs->exporter->export($key) 方法，返回数据记录 字段值/关联表字段值
         * 
         * $rs->fieldname       --> $rs->context["fieldname"]
         * $rs->getterFunc      --> $rs->getterFuncGetter()
         * $rs->_               --> $rs->exporter->export()
         * $rs->Table           --> $rs->joined["Table"] 关联表实例
         * $rs->table_          --> $rs->joined["Table"]->exporter->export()
         * $rs->table_foo_bar   --> $rs->joined["Table"]->foo_bar
         */
        $exper = $this->exporter;
        if ($exper instanceof Exporter) {
            $rst = $exper->export($key);
            if (!is_null($rst)) return $rst;
        }


        //要求此 数据表(模型) 类必须经过初始化
        if (!static::$db instanceof Dbo) return null;

        /**
         * $rs->db
         * 返回 $model::$db
         */
        if ($key=="Db" || $key==ucfirst(static::$db->name)) {
            return static::$db;
        }

        /**
         * $rs->Model
         * 相当于 $db->Model
         */
        if ($key=="Model" || $key==static::$name) {
            $tbn = ucfirst(static::$name);
            return static::$db->$tbn;
        }

        /**
         * $rs->Othermodel
         * 访问 当前 数据库下 其他 数据表(模型) 类
         * 相当于 $db->Other
         */
        if (static::$db->hasModel($key)) {
            return static::$db->$key;
        }

        /**
         * $rs->conf
         * 访问 $model::$configer
         */
        if ($key=="conf") {
            return static::$configer;
        }

        return null;
    }

    /**
     * __call
     * @param String $method
     * @param Array $args
     * @return Mixed
     */
    public function __call($method, $args)
    {
        /**
         * $rs->getterFunc()
         * 调用 数据表(模型) 实例 getter 方法
         */
        $gfds = static::$configer->getterFields;
        if (in_array($method, $gfds)) {
            $getter = $method."Getter";
            if (method_exists($this, $getter)) {
                return $this->$getter();
            }
        }
    }

    /**
     * 输出 字段值
     * 调用 $exporter->export() 方法 输出 字段内容
     * @param String $args 查看 export 方法参数
     * @return Array [ field=>val, field=>val ]
     */
    public function ctx(...$args)
    {
        return $this->exporter->export(...$args);
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
     * 解析 数据表(模型) 预设参数
     * @return String 类全称
     */
    public static function parseConfig()
    {
        $cls = static::$cls;
        //使用 model\Configer 解析表预设
        static::$configer = new Configer($cls);
        return $cls;
    }

    /**
     * 根据 curd 返回数据 创建 数据表记录实例
     * @param Array $data curd 返回的数据
     * @return Model 数据表记录实例
     */
    public static function createIns($data=[])
    {
        $rs = new static();
        //解析 $data
        $rs->parseCurdRtnData($data);

        //建立输出工具实例
        $rs->exporter = new Exporter($rs);
        
        //是否新建记录，还未写入数据库
        $idf = static::idf();
        $rs->isNew = !empty($rs->context) && !isset($rs->context[$idf]);

        return $rs;
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
     * 创建一个 数据表(模型) 实例
     * 用于以实例方式调用 类方法
     * @return Model 实例
     */
    public static function ___ins()
    {
        return new static([]);
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
        $mcls = static::$cls;   //数据表(模型) 类全称，== static::class
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
        } else if (is_array($rst)) {
            //返回的是 记录 / 记录集
            if (empty($rst)) {
                if ($method=="get") {
                    return static::createIns($rst);
                } else {
                    return new ModelSet($mcls, $rst);
                }
            }
            if (is_indexed($rst)) {
                //记录集 通常 select/rand 方法 返回记录集
                //包裹为 ModelSet 记录集对象
                return new ModelSet($mcls, $rst);
            } else if (is_associate($rst)) {
                //单条记录 通常 get 方法 返回单条记录
                //包裹为 Model 实例
                return static::createIns($rst);
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