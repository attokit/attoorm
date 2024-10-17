<?php
/**
 * CURD 操作类
 * 每次 curd 操作，将生成一个 Curd 实例
 * 操作结束后，此实例将释放
 */

namespace Atto\Orm;

use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Medoo\Medoo;

class Curd 
{
    //关联的数据库实例 Dbo
    public $db = null;

    //关联的 模型(数据表) 类全称
    public $model = "";

    /**
     * curd 参数
     * 采用 medoo 方法，参数为 medoo 方法的参数
     */
    //curd 操作针对的 table 表名称
    public $table = "";
    //表关联，可以在 模型(数据表)类中 预定义
    public $join = [
        /*"[<]table" => [
            "pid" => "pid"
        ]*/
    ];
    //是否 join 关联表
    public $useJoin = false;
    //要返回值的 字段名 []
    public $field = "*";
    //where 参数
    public $where = [];

    //debug 标记，用于输出 SQL
    protected $debug = false;

    /**
     * 构造 curd 操作实例
     * @param Dbo $db 数据库实例
     * @param String $model 要执行 curd 的 数据表(模型) 类全称
     */
    public function __construct($db, $model)
    {
        if (!$db instanceof Dbo || !class_exists($model) || !$db->hasModel($model::$name)) return null;
        $this->db = $db;
        $this->model = $model;
        $this->table = $model::$table;
        $this->join = $model::$join;
        //curd 操作初始化完成后，立即处理 查询字段名数组
        $this->field("*");
    }

    /**
     * curd 操作实例 是否 ready
     * 已经有 必要参数 table field
     * @return Bool
     */
    public function ready()
    {
        $db = $this->db;
        $model = $this->model;
        $table = $this->table;
        $field = $this->field;
        return 
            $db instanceof Dbo &&
            class_exists($model) &&
            $table!="" && 
            $table==$model::$table && 
            (
                is_notempty_str($field) ||
                is_notempty_arr($field)
            );
    }

    /**
     * 是否连接查询关联表
     * @param Mixed $join 参数 默认 true
     *      Bool    开启/关闭 join table
     *      String  like: '[>]table' 从 $model::$join 参数中 挑选 相应参数
     *      Array   重新指定 join 参数
     * @return Curd $this
     */
    public function join($join=true, ...$jtbs)
    {
        if (is_bool($join)) {
            $this->useJoin = $join;
        } else {
            if (is_string($join)) {
                $jtbs = array_unshift($jtbs, $join);
                $this->useJoin = $jtbs;
            } else if (is_array($join)) {
                $this->useJoin = $join;
            } else {
                $this->useJoin = true;
            }
        }

        //自动添加 join 表全部字段名 到 $this->field 查询字段名数组
        $tbs = $this->getJoinTables();
        if (!empty($tbs)) {
            $field = ["*"];
            foreach ($tbs as $i => $tbn) {
                $field[$tbn] = [$tbn.".*"];
            }
            //var_dump($field);
            $this->field($field);
        }

        return $this;
    }
    public function nojoin() {return $this->join(false);}

    /**
     * 处理关联表 join 参数
     * 根据 $this->useJoin 参数：
     *      true|false      直接返回 $this->model::$join
     *      array(string)   从 $model::$join 参数中查找并返回相应的 table 设置
     *      array(associate)    替换 $model::$join 参数
     * @return Array join 参数
     */
    public function parseJoin()
    {
        $mjoin = $this->model::$join;
        $mjoin = empty($mjoin) ? [] : $mjoin;
        $join = $this->useJoin;
        if (is_bool($join)) {
            return $mjoin;
        } else if (is_array($join)) {
            if (is_indexed($join)) {
                $nj = [];
                for ($i=0;$i<count($join);$i++) {
                    $jik = $join[$i];
                    if (isset($mjoin[$jik])) {
                        $nj[$jik] = $mjoin[$jik];
                    }
                }
                return $nj;
            } else {
                return $join;
            }
        } else {
            return $mjoin;
        }
    }

    /**
     * 从 join 参数计算所有关联表 用于自动添加查询字段到 $this->field 参数
     * @return Array [ table name, ... ]
     */
    public function getJoinTables()
    {
        $join = $this->parseJoin();
        if (empty($join)) return [];
        $tbs = array_keys($join);
        $tbs = array_map(function($i) {
            $ia = explode("]", $i);
            return $ia[1] ?? $i;
        }, $tbs);
        return $tbs;
    }

    /**
     * 构造 medoo 查询参数
     * 指定要返回值的 字段名 or 字段名数组 
     * 
     * medoo return data mapping 可构造返回的记录数据格式
     *      字段名数组，自动添加 输出格式 数据表(模型) 预定义的 字段类型：
     *          $field = [ "fieldname [JSON]", "tablename.fieldname [Int]", ... ]
     *          $field = func_get_args()
     * 
     * 
     * @param Mixed $field 与 medoo field 参数格式一致
     * @return Curd $this
     */
    public function field(...$args)
    {
        if (empty($args)) {
            $field = ["*"];
        } else if (count($args)==1) {
            $field = is_array($args[0]) ? $args[0] : $args;
        } else {
            $field = [];
            for ($i=0;$i<count($args);$i++) {
                $argi = $args[$i];
                if (is_string($argi)) {
                    $field[] = $argi;
                } else if (is_array($argi)) {
                    $field = array_merge($field, $argi);
                } else {
                    continue;
                }
            }
        }
        if (is_notempty_arr($field)) {
            $field = $this->addPhpTypeToFieldNameArr($field);
        } else {
            return $this;
        }
        $this->field = $field;
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * 直接编辑 where 参数 
     * @param Array $where 与 medoo where 参数格式一致
     * @return Curd $this
     */
    public function where($where=[])
    {
        $ow = $this->where;
        $this->where = arr_extend($ow, $where);
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * limit 参数 
     * @param Array $limit 与 medoo limit 参数格式一致
     * @return Curd $this
     */
    public function limit($limit=[])
    {
        if (
            (is_numeric($limit) && $limit>0) ||
            (is_notempty_arr($limit) && is_indexed($limit))
        ) {
            $this->where["LIMIT"] = $limit;
        }
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * order 参数 
     * @param Array $order 与 medoo order 参数格式一致
     * @return Curd $this
     */
    public function order($order=[])
    {
        if (
            is_notempty_str($order) ||
            (is_notempty_arr($order) && is_associate($order))
        ) {
            $this->where["ORDER"] = $order;
        }
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * match 参数 全文搜索
     * @param Array $match 与 medoo match 参数格式一致
     * @return Curd $this
     */
    public function match($match=[])
    {
        if (!empty($match)) {
            $this->where["MATCH"] = $match;
        }
        return $this;
    }

    /**
     * 为查询字段名数组 中的 字段名 增加 [字段类型]
     * @param String $fdn 字段名  or  表名.字段名
     * @return String 表名.字段名 [类型]
     */
    protected function addPhpTypeToFieldName($fdn)
    {
        if ($fdn=="*") return $this->addPhpTypeToFieldNameAll();
        if (substr($fdn, -2)==".*") {
            // table.*
            $mdn = ucfirst(str_replace(".*","",$fdn));
            return $this->addPhpTypeToFieldNameAll($mdn);
        }
        $db = $this->db;
        $model = $this->model;
        $fds = $model::$fields;
        $fdc = $model::$field;
        $useJoin = $this->useJoin!==false;
        if (strpos($fdn, ".")===false) {
            //字段名  -->  表名.字段名 [类型]
            if (in_array($fdn, $fds)) {
                //读取预设的 字段类型
                $type = $fdc[$fdn]["phptype"];
                //if ($useJoin) $fdn = $model::$table.".".$fdn." (".$model::$table."_".$fdn.")";
                if ($useJoin) $fdn = $model::$table.".".$fdn;
                if ($type!="String") {
                    return $fdn." [".$type."]";
                }
            }
        } else {
            //表名.字段名  -->  表名.字段名 [类型]
            $fda = explode(".", $fdn);
            $tbn = $fda[0];
            $nfdn = $fda[1];
            $nmodel = $db->getModel(ucfirst($tbn));
            if (!empty($nmodel)) {
                $nfds = $nmodel::$fields;
                $nfdc = $nmodel::$field;
                if (in_array($nfdn, $nfds)) {
                    //读取预设的 字段类型
                    $ntype = $nfdc[$nfdn]["phptype"];
                    //$fdn = $fdn." (".str_replace(".","_",$fdn).")";
                    if ($ntype!="String") {
                        return $fdn." [".$ntype."]";
                    }
                }
            }
        }
        return $fdn;
    }

    /**
     * 以递归方式处理输入的 查询字段名数组
     * @param Array $field 与 medoo field 参数格式一致
     * @return Array 返回处理后的数组
     */
    protected function addPhpTypeToFieldNameArr($field=[])
    {
        if (!is_notempty_arr($field)) return $field;
        $fixed = [];
        foreach ($field as $k => $v) {
            if (is_notempty_arr($v)) {
                $fixed[$k] = $this->addPhpTypeToFieldNameArr($v);
            } else if (is_notempty_str($v)) {
                $v = $this->addPhpTypeToFieldName($v);
                if (!is_array($v)) $v = [ $v ];
                $fixed = array_merge($fixed, $v);
            } else {
                $fixed = array_merge($fixed, [ $v ]);
            }
        }
        return $fixed;
    }

    /**
     * 将 * 转换为 $model::$fields
     * @param String $model 指定要查询的 fields 的 数据表(模型) 类，不指定则为当前 $this->model
     * @return Array [ 表名.字段名 [类型], ... ]
     */
    protected function addPhpTypeToFieldNameAll($model=null)
    {
        $model = empty($model) ? $this->model : $this->db->getModel($model);
        if (empty($model)) return [];
        $useJoin = $this->useJoin!==false;
        $fds = $model::$fields;
        if ($useJoin) {
            $fds = array_map(function ($i) use ($model) {
                return $model::$table.".".$i;
            }, $fds);
        }
        return $this->addPhpTypeToFieldNameArr($fds);
    }

    /**
     * 在执行查询之前，处理并返回最终 field 参数
     *      必须包含 $model::$includes 数组中指定的 字段
     * @return Array $field 与 medoo field 参数格式一致
     */
    public function parseField()
    {
        $field = $this->field;
        if (!is_array($field)) $field = [$field];
        $includes = $this->model::$includes;
        $incs = $this->addPhpTypeToFieldNameArr($includes);
        foreach ($incs as $i => $fi) {
            if (!in_array($fi, $field)) {
                $field[] = $fi;
            }
        }
        return $field;
    }

    /**
     * 在执行查询前，生成最终需要的 medoo 查询参数
     * 在查询时，可根据 method 组装成 medoo 方法的 参数 args[]
     * @return Array [ "table"=>"", "join"=>[], "field"=>[], "where"=>[] ]
     */
    public function parseArguments()
    {
        $args = [];
        $args["table"] = $this->table;
        $args["join"] = $this->parseJoin();
        $args["field"] = $this->parseField();
        $args["where"] = empty($this->where) ? [] : $this->where;
        return $args;
    }



    /**
     * 执行 medoo 查询
     * 使用 __call 方法
     * @param String $method medoo 查询方法
     * @param Array $args 输入参数
     */
    public function __call($method, $args)
    {
        $ms = explode(",", "select,insert,update,delete,replace,get,has,rand,count,max,min,avg,sum");
        if (in_array($method, $ms)) {
            //调用 medoo 查询方法
            if (!$this->ready()) return null;
            //准备查询参数
            $ag = $this->parseArguments();
            //join
            $join = $ag["join"] ?? [];
            $canJoin = $this->useJoin!==false && !empty($join);
            //field
            $field = $ag["field"] ?? [];
            //where
            $where = $ag["where"] ?? [];
            //准备 medoo 方法参数
            $ps = [];
            $ps[] = $ag["table"];
            switch ($method) {
                case "select":
                case "get":
                case "rand":
                case "count":
                case "max":
                case "min":
                case "avg":
                case "sum":
                    if ($canJoin) $ps[] = $join;
                    $ps[] = $field;
                    if (!empty($where)) $ps[] = $where;
                    break;
                case "insert":
                case "update":
                    if (is_notempty_arr($args) && is_notempty_arr($args[0])) {
                        $ps[] = array_shift($args);
                    } else {
                        return null;
                    }
                    if ($method=="update" && !empty($where)) $ps[] = $where;
                    break;
                case "delete":
                    if (!empty($where)) {
                        $ps[] = $where;
                    } else {
                        return null;
                    }
                    break;
                case "replace":
                    $ps[] = $field;
                    if (!empty($where)) $ps[] = $where;
                    break;
                case "has":
                    if ($canJoin) $ps[] = $join;
                    if (!empty($where)) $ps[] = $where;
                    break;
            }

            //debug 输出 SQL
            if ($this->debug==true) {
                $this->db->medoo("debug")->$method(...$ps);
                //从缓冲区读取 sql
                $sql = ob_get_contents();
                //清空缓冲区
                ob_clean();
                return [
                    "args" => $ag,
                    //"argsQueue" => $ps,
                    "SQL" => $sql
                ];
            }

            //执行 medoo 方法
            $rst = $this->db->medoo($method, ...$ps);
            //var_dump($rst);

            //包裹 查询结果
            $rst = $this->model::wrap($rst, $method, $this);

            //销毁当前 curd 操作
            $unset = true;
            if (is_notempty_arr($args) && is_bool($args[0])) {
                $unset = array_unshift($args);
            }
            if ($unset) $this->db->curdDestory();
            
            return $rst;
        }
    }

    /**
     * debug 输出 SQL
     * $curd->debug()->select() 输出 根据当前查询参数 得到的 SQL
     * @param Bool $debug 默认 true
     * @return Curd $this
     */
    public function debug($debug=true)
    {
        $this->debug = $debug;
        return $this;
    }


    
}