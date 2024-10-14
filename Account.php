<?php
/**
 * 多账号共同使用 io.cgy.design/db
 * 建立 db 账号，各账号拥有各自的 db 数据库，单独操作与控制
 * 
 * 接口     io.cgy.design/db/account
 *      建立账号    /create     post: []
 */

namespace Atto\Box\db;

use Atto\Box\Request;
use Atto\Box\db\Jwt;

class Account
{
    //账户数据
    public $name = "";
    public $audience = [];
    public $database = [];

    //数据库实例
    public $db = null;

    //当前登录的用户实例 数据模型(表) 实例
    public $usr = null;

    /**
     * 构造账户实例
     * @param Array $account ["name"=>"", "audience"=>[], "database"=>[]]
     * @return Account
     */
    public function __construct($account=[])
    {
        foreach ($account as $k => $v) {
            $this->$k = $v;
        }
    }


    /**
     * 验证并创建 account 实例
     */
    public static function create()
    {
        $jwt = new Account();
        $vali = $jwt->validate();
        $jwt->valiData = $vali;
        //var_dump($vali);
        if (isset($vali["status"]) && $vali["status"]=="success") {
            //Attoorm-Token 验证通过，查找 账号信息
            $usr = $vali["payload"] ?? null;
            $jwt->usrData = $usr;
            $jwt->name = $usr["name"];
            $jwt->database = $usr["database"];
            return $jwt;
        } else {
            //Attoorm-Token 验证未通过
            $stu = $vali["status"];
            if ($stu=="emptyToken") {
                //空 token 返回空账户
                return $jwt;
            }
        }
    }

    /**
     * 创建 数据库账户
     * @param Array $account 账户信息
     * @return Account 账户实例
     */
    public static function newAccount($account=[])
    {
        if (empty($account)) {
            $account = Request::input("json");
        }
        $name = $account["name"] ?? null;
        $audience = $account["audience"] ?? [];
        $database = $account["database"] ?? [];
        if (
            !is_notempty_str($name) ||
            (!is_notempty_str($audience) && !is_notempty_arr($audience)) ||
            !is_notempty_arr($database)
        ) {
            trigger_error("custom::无法创建数据库账号，缺少关键参数", E_USER_ERROR);
            exit;
        }
        if (is_notempty_str($audience)) {
            $audience = [$audience];
        }

        //为每个 audience 创建 secret json
        $jwt = new Jwt();
        for ($i=0;$i<count($audience);$i++) {
            $audi = $audience[$i];
            $jwt->createSecretByAud($audi, $account);
        }

        //创建 account 实例，并返回
        $ac = new Account($account);

        return $ac;
    }

    public function update($data=[])
    {
        $data = empty($data) ? Request::input("json") : $data;
        $data = arr_extend($this->export(), $data);
        $this->writeSecretJson([
            "account" => $data
        ]);
        return $data;
    }


    /**
     * 输出账号信息
     */
    public function export()
    {
        return [
            "name" => $this->name,
            "audience" => $this->audience,
            "database" => $this->database
        ];
    }
}