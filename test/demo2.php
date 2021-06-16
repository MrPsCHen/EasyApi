<?php
namespace a;
use EasyApi\Model;
use think\facade\Db;
include "./../vendor/autoload.php";

Db::setConfig(include "./database.php");

class user extends Model{
}

class UserController extends \EasyApi\Controller
{

}

$user = new UserController();

$user->setModel(new user('user','app_'))->choseTable()->filter(['password']);
print_r($user->show());