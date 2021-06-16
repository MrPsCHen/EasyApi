<?php


namespace EasyApi\interFaces;


interface Table
{
    /**
     * SIZE: 按范围值
     * IS_NUMERIC:按数字判断
     * IS_FLOAT:浮点数
     * IS_STRING:判断是否字符串
     * STRLEN:字符长度
     * DATA:日期格式
     * TIME:时间隔日
     * DATATIME:日期时间格式
     * TIMESTAMP:时间戳
     */
    const type = [
        'tinyint'   =>['SIZE',[-128,127]],
        'smallint'  =>['SIZE',[-32768,32767]],
        'mediumint' =>['SIZE',[-8388608,8388607]],
        'int'       =>['SIZE',[-2147483648,2147483647]],
        'bigint'    =>['IS_NUMERIC'],
        'float'     =>['IS_FLOAT'],
        'double'    =>['IS_FLOAT'],
        'char'      =>['STRLEN',[0,255]],
        'varchar'   =>['STRLEN',[0,65535]],
        'tinytext'  =>['STRLEN',[0,255]],
        'text'      =>['STRLEN',[0,65535]],
        'mediumtext'=>['IS_STRING'],
        'longtext'  =>['IS_STRING'],
        'date'      =>['DATA'],
        'time'      =>['TIME'],
        'datetime'  =>['DATATIME'],
        'timestamp' =>['TIMESTAMP'],
    ];

//    public function dat();

}