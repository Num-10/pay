<?php

/* *
 * 支付宝接口配置文件
 */
$alipay_config　= array();

//　合作身份者id，以2088开头的16位纯数字
$alipay_config['partner'] = '2088421328524995';

// 收款支付宝账号，一般情况下收款账号就是签约账号
$alipay_config['seller_email'] = '56002158@qq.com';

// 安全检验码，以数字和字母组成的32位字符
$alipay_config['key'] = '62mwjr26wvoxi8w6aasolzdz3wp8qqe3';

// 签名方式 不需修改
$alipay_config['sign_type'] = strtoupper('MD5');

//字符编码格式 目前支持 gbk 或 utf-8
$alipay_config['input_charset'] = strtolower('utf-8');

//ca证书路径地址，用于curl中ssl校验
//请保证cacert.pem文件在当前文件夹目录中
$alipay_config['cacert'] = getcwd().'\\cacert.pem';

//访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
$alipay_config['transport'] = 'http';

?>