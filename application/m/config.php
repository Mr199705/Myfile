<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// $Id$
return [
    //模板参数替换
    'view_replace_str'       => array(
        '__CSS__'    => '/static/m/css',
        '__JS__'     => '/static/m/js',
        '__COMMON__'     => '/static/common/js',
        '__IMG__' => '/static/m/images',
        '__PUBLIC__' => '/static/m',
        '__STATIC__' => '/static',
        '__GOODSIMG__' => 'http://sx.img.ljk.cc/'
    ),
	//配置session
	'session'                => [
		'id'             => '',
		// session_ID的提交变量,解决flash上传跨域
		'var_session_id' => 'ljk_wap',
		// session 前缀
		'prefix'         => 'index',
		// 驱动方式 支持redis memcache memcached
		'type'           => '',
		// 是否自动开启 session
		'auto_start'     => true,
	],
        // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => 'common/dispatch_jump',
    'dispatch_error_tmpl'    => 'common/dispatch_jump',

    //管理员状态
    'user_status' => [
        '1' => '正常',
        '2' => '禁止登录'
    ],   
    //订单确认状态
    'order_status_status' => [
        '0' => '无效',
        '1' => '有效'
    ],
    'order_confirm_status' => [
        '0'=>'无效订单',
        '1' => '未确认',
        '2' => '已确认',
    ],
    //订单商品状态
    'order_goods_status' => [
        '1' => '销售',
        '2' => '赠品'
    ],
	 //订单配送状态
    'order_dispatch_status' => [
        '0' => '未配送',
        '1' => '配送中',
        '2' => '配送完成'
    ],
    //订单付款状态
    'order_pay_status' => [
        '0' => '未付款',
        '1' => '已付款'
    ],
    'order_trade_status' => [
        '0' => '未结束',
        '1' => '已结束'
    ],
    //客户认证状态
    'rz_status' => [
        '0' => '未认证',
        '1' => '已认证'
    ],
    //角色状态
    'role_status' => [
        '1' => '启用',
        '2' => '禁用'
    ],
    'order_type'=>[
        //0 微信客户自己下单 1呼叫中心 2业务员移动端 3分销商 4业务员PC端 5 客户pc商城 
        '0'=>'微信商城',
        '1'=>'呼叫中心',
        '2'=>'业务员移动端',
        '3'=>'分销商',
        '4'=>'业务员PC端',
        '5'=>'PC商城'
    ],
    //车销订单状态
    'storage_order_confirm' => [
    '-1' => '已作废',
    '0' => '未审核',
    '1' => '已锁定',
    '2' => '已通过'
    		],
    'storage_order_finish' => [
    		'0' => '未完成',
    		'1' => '已完成',
    		],
    'upload_path' => '/uploads/m',
    'upload_path_x' => '/uploads/m/',
    'app_trace' => false,
    'weixin_config' => [
        'appId' => 'wxe1384431e4f3e8f2',
        'appSecret' => '04e921b247e1e81a223a8bb9d4946d3d',
        'token' => 'yipinbao',
        'encodingAESKey' => 'rTp3nvTRvuy3eFwzOTe5YMEl072vHYAS1vxSoyZbzaV'
    ],
    'weixin_pay_config' => [
        'mchId' => 1393117602,
        'key' => '142b6c389ca0bdc4b1a27becb3e842ed'
    ],
];
