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
namespace app\admin\controller;
use think\Controller;
use think\Request;
use think\Db;
class Base extends Controller
{
    protected $jsName;
    protected $requestFunc;
    protected $sign;
    protected $requestUrl;
    protected $cUrl = '';
    protected $rows = 10;
    protected $initData = [];
    protected $gid;//当前登录账号组
    protected $guid;//当前登录账号编号
    public function __construct(Request $request = null) {        
        session('gid',133);
        $this->gid = session('gid');
//        $this->guid = session('guid');
        $this->guid =390;
        parent::__construct($request);
        $request=Request::instance();
        $this->jsName = strtolower($request->controller());
        $this->requestFunc = strtolower($request->controller()).ucfirst($request->action());
        $this->sign = strtolower($request->controller()).ucfirst($request->action());
        $this->requestUrl = url();
        $this->cUrl = substr($this->requestUrl, 0, strrpos($this->requestUrl,'/'));
    }
    public function  _initialize(){
        $groupinfo=Db::name('group')->where('gid',133)->find();
        $this->assign('groupinfo',$groupinfo);

    }
    public function tips($msgarr = array()){
        $msgarr['msg']=array_key_exists('msg', $msgarr)?$msgarr['msg']:'温馨提示';
        //返回按钮
        $msgarr['back_url']=array_key_exists('back_url', $msgarr)?$msgarr['back_url']:'';
        $msgarr['back_sign']=array_key_exists('back_sign', $msgarr)?$msgarr['back_sign']:false;
        $msgarr['back_content']=array_key_exists('back_content', $msgarr)?$msgarr['back_content']:'返回';
        //详情按钮
        $msgarr['detail_sign']=array_key_exists('detail_sign', $msgarr)?$msgarr['detail_sign']:false;
        $msgarr['detail_content']=array_key_exists('detail_content', $msgarr)?$msgarr['detail_content']:'详情';
        $msgarr['detail_url']=array_key_exists('detail_url', $msgarr)?$msgarr['detail_url']:'';
        //继续按钮
        $msgarr['continue_sign']=array_key_exists('continue_sign', $msgarr)?$msgarr['continue_sign']:false;
        $msgarr['continue_content']=array_key_exists('continue_content', $msgarr)?$msgarr['continue_content']:'继续';
        $msgarr['continue_url']=array_key_exists('continue_url', $msgarr)?$msgarr['continue_url']:'';
        //跳转
        $msgarr['jump_sign']=array_key_exists('jump_sign', $msgarr)?$msgarr['jump_sign']:false;
        $msgarr['jump_url']=array_key_exists('jump_url', $msgarr)?$msgarr['jump_url']:'';

        //下一步按钮
        $msgarr['next_action']=array_key_exists('next_action', $msgarr)?$msgarr['next_action']:'';
        $msgarr['next_sign']=array_key_exists('next_sign', $msgarr)?$msgarr['next_sign']:true;
        //刷新
        $msgarr['refresh_sign']=array_key_exists('refresh_sign', $msgarr)?$msgarr['refresh_sign']:false;
        //自动关闭
        $msgarr['autoclose_sign']=array_key_exists('autoclose_sign', $msgarr)?$msgarr['autoclose_sign']:false;

        $message = $msgarr['msg'];

        $back = array('sign'=>$msgarr['back_sign'],'content'=>$msgarr['back_content'],'url'=>$msgarr['back_url']);
        $detail = array('sign'=>$msgarr['detail_sign'],'content'=>$msgarr['detail_content'],'url'=>$msgarr['detail_url']);
        $_continue = array('sign'=>$msgarr['continue_sign'],'content'=>$msgarr['continue_content'],'url'=>$msgarr['continue_url']);
        $close = array('sign'=>true,'content'=>'关闭');
        $autoClose = array('sign'=>$msgarr['autoclose_sign'],'time'=>1000);
        $nextAct = array('sign'=>$msgarr['next_sign'],'action'=>$msgarr['next_action']);
        $refresh = array('sign'=>$msgarr['refresh_sign'],'time'=>2000);
        $jump = array('sign'=>$msgarr['jump_sign'],'url'=>$msgarr['jump_url'],'time'=>2000);
        $this->assign('message',$message);
        $this->assign('back',$back);
        $this->assign('detail',$detail);
        $this->assign('_continue',$_continue);
        $this->assign('refresh',$refresh);
        $this->assign('jump',$jump);
        $this->assign('close',$close);
        $this->assign('autoClose',$autoClose);
        $this->assign('nextAct',$nextAct);
        echo $this->fetch('/common/atip');
    }
}