<?php
use think\facade\Db;
include "./../vendor/autoload.php";

Db::setConfig(include "./database.php");

//$controller = new \EasyApi\Controller();
//$model =new \EasyApi\Model();

class app_user extends \EasyApi\Model{}

//查询表
$user = new app_user();

//聚合表
$user->ploy('group','app_','group_id')->setAlias(['group_name'=>'name']);
//查询数据
$user->where(['name'=>'管理员组'],'app_group');
print_r($user->select()->getBack());



//print_r($user->getBack());

//条件查询
//$user->autoParam();
//
//$user->select();

//聚合查找
//$user->autoParam(['username'=>2,'id'=>4]);