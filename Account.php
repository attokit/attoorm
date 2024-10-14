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

class Account extends Jwt
{
    //可自定义 headers 字段名，默认 Authorization
    public $requestHeader = "Attoorm-Token";

    //自定义 jwt-secret.json 的保存位置，默认 [webroot|app/appname]/library/jwt/secret
    public $secretDir = "app/db/accounts";

    //账户数据
    public $valiData = [];
    public $usrData = [];
    public $name = "";
    public $database = [];

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
    public static function createAccount($account=[])
    {
        if (empty($account)) {
            $account = Request::input("json");
        }
        $account = arr_extend([
            "name" => "",       //账户名称，需要在 url 中提供，如：qypms
            "audience" => "",   //允许的访问来源，如：qy.cgy.design
            "database" => [],   //此数据库账户使用的 数据库连接参数
            //... 更多数据
        ], $account);

        //实例化 jwt
        $jwt = new Account();
        //创建
        $tko = $jwt->generate($account);
        return $tko;
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
            "database" => $this->database
        ];
    }
}