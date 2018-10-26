<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\m\controller;
use think\Controller;
use think\Db;
class Login extends Controller
{
    //登录页面
    public function index(){
    	session('gid',133);
        session('guid',390);
        session('username','张思耕');
        session('memberaccess','');
        session('group_copyright','老窖客系统2.0');
        session('groupinfo',Db::name('group')->where('gid',133)->find());
        return $this->redirect('/m/index/index');
    }
}