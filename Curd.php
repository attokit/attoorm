<?php
/**
 * CURD 操作类
 * 每次 curd 操作，将生成一个 Curd 实例
 * 操作结束后，此实例将释放
 */

namespace Atto\Orm;

use Atto\Orm\Orm;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Orm\curd\JoinParser;
use Atto\Orm\curd\ColumnParser;
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
    //public $join = [
        /*"[<]table" => [
            "pid" => "pid"
        ]*/
    //];
    //是否 join 关联表
    //public $useJoin = false;
    //要返回值的 字段名 []
    //public $field = "*";
    //where 参数
    public $where = [];

    //debug 标记，用于输出 SQL
    protected $debug = false;

    /**
     * 使用 curd 参数处理工具类
     */
    public $joinParser = null;
    public $columnParser = null;
    public $whereParser = null;

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
        
        //使用 curd 参数处理工具，初始化/编辑 curd 参数
        $this->joinParser = new JoinParser($this);
        $this->columnParser = new ColumnParser($this);

        //$this->join = $model::$join;
        //curd 操作初始化完成后，立即处理 查询字段名数组
        //$this->field("*");
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
        $cfg = $model::$configer;
        $table = $this->table;
        return 
            $db instanceof Dbo &&
            class_exists($model) &&
            $table!="" && 
            $table==$cfg->table;
    }

    /**
     * 构造 medoo 查询参数
     * join 关联表查询参数
     * 符合 medoo join 参数形式
     * 调用 $this->joinParser->setParam() 方法
     * 
     * @param Mixed
     *      Bool        开启/关闭 join table
     *      String, ... like: '[>]table' 从 $model::$join 参数中 挑选 相应参数
     *      Array       重新指定 join 参数
     * @return Curd $this
     */
    public function join(...$args)
    {
        $jp = $this->joinParser;
        if ($jp instanceof JoinParser) {
            $jp->setParam(...$args);
        }

        return $this;
    }
    public function nojoin() {return $this->join(false);}

    /**
     * 构造 medoo 查询参数
     * 指定要返回值的 字段名 or 字段名数组 
     * 符合 medoo column 参数形式
     * 调用 $this->columnParser->setParam() 方法
     * 
     * @param Mixed
     *      "*"
     *      "field name","table.field",...
     *      [ "*", "table.*", "fieldname [JSON]", "tablename.fieldname [Int]", ... ]
     *      [ "table.*", "map" => [ "fieldname [JSON]", "tablename.fieldname [Int]", ... ] ]
     * @return Curd $this
     */
    public function column(...$args)
    {
        $cp = $this->columnParser;
        if ($cp instanceof ColumnParser) {
            $cp->setParam(...$args);
        }

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
     * 在执行查询前，生成最终需要的 medoo 查询参数
     * 在查询时，可根据 method 组装成 medoo 方法的 参数 args[]
     * @return Array [ "table"=>"", "join"=>[], "field"=>[], "where"=>[] ]
     */
    public function parseArguments()
    {
        $args = [];
        $args["table"] = $this->table;
        $args["join"] = $this->joinParser->getParam();      //$this->parseJoin();
        $args["column"] = $this->columnParser->getParam();   //$this->parseField();
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
            $jp = $this->joinParser;
            $canJoin = $jp->use!==false && $jp->available==true;    //$this->useJoin!==false && !empty($join);
            //column
            $column = $ag["column"] ?? [];
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
                    $ps[] = $column;
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
                    $ps[] = $column;
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