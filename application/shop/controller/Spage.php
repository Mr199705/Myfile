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
namespace app\shop\controller;
use think\Db;
class Spage extends Base{
    public function index(){
        //  
    }
    public function p(){
		$whereA = [];
		$whereA['gid'] = $this->gid;
		//$whereA['shopid'] = $this->shopid;
		$whereA['id'] = input('aid');
		$pInfo = Db::name('article')->where($whereA)->find();
        if( empty($pInfo) ){
            return view('/spage/prev_item');
        }
        
        $pInfo['content'] = htmlspecialchars_decode($pInfo['content']);
        $pInfo['ctime_text'] = date( 'Y-m-d H:i', $pInfo['ctime']);
        if( $pInfo['type'] === 1 ){         //  页面
            $this->assign( 'pInfo', $pInfo);
            return view('/spage/page');
        } else {
            $pInfo['guid_text'] = Db::name('group_member')->where([ 'gid' => $this->gid, 'uid' => $pInfo['guid'] ])->value('realname');
            $this->assign( 'artInfo', $pInfo);
            return view('/spage/article_item');
        }
	}
}
