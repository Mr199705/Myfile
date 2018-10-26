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
use think\wechatsdk\Api;
use think\Db;
class Base extends Controller
{
    protected $gid;
    protected $guid;
    protected $rules;
    public function __construct(\think\Request $request = null) {
        $this->gid = session('gid');
        $this->guid = session('guid');
        $this->jtid = 1;
        parent::__construct($request);
    }
}