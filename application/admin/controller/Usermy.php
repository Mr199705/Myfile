<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\admin\controller;
use think\Db;
/**
 * Description of BaoBiao
 *
 * @author Administrator
 */
class Usermy extends Base{
    //put your code here
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
        $this->initData = [
            'sign'=>$this->sign,
            'requestFunc'=>$this->requestFunc,
            'requestUrl'=>$this->requestUrl,
            'cUrl'=>$this->cUrl,
            'jsName'=>$this->jsName,
        ];
        $this->assign('initData',$this->initData);
    }
    /**
     * 拜访类型操作
     * @return mixed|void
     */
    public function uservisit(){
        $action = '';
        if(request()->isAjax() && request()->isPost()){
            //这里是获取ajax列表数据
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->showUservisitList($input);
                case 'addvisit':return $this->addVisitList($input);
                case 'editvisit':return $this->editVisitList($input);
                case 'visitdel':return $this->delVisitList($input);

                default:return $this->showUservisittpl();
            }
        }else{
            $input = input();
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                default:return $this->showUservisittpl();
            }
        }
    }
    private function addVisitList($input = []){
        $title=isset($input['title']) ? trim($input['title']) : false;
        $FormData=[
            'gid'=>$this->gid,
            'type'=>0,
            'title'=>$title,
            'displayorder'=>0,
            'status'=>0
        ];
        Db::name('user_visit')->insert($FormData);
        $arrtips['autoclose_sign'] = true;
        $arrtips['msg'] = '添加成功，页面即将刷新！';
        $arrtips['close'] = ['sign'=>true];
        $arrtips['next_action'] = "getList({sign:'userUservisit'})";
        return $this->tips($arrtips);
    }
    private function editVisitList($input = []){
        $id=isset($input['id']) ? trim($input['id']) : false;
        $title=isset($input['title']) ? trim($input['title']) : false;
        $whereP= [];
        $whereP['gid']=$this->gid;
        $whereP['id']=$id;
        Db::name('user_visit')->where($whereP)->setField('title',$title);
        $arrtips['autoclose_sign'] = true;
        $arrtips['msg'] = '编辑成功，页面即将刷新！';
        $arrtips['close'] = ['sign'=>true];
        $arrtips['next_action'] = "getList({sign:'userUservisit'})";
        return $this->tips($arrtips);
    }
    private function delVisitList(){
        $whereP= [];
        $whereP['gid']=$this->gid;
        $data[]=input('displayorder/a');
        $data[]=input('delete/a');
        $re=$data[0];
        $del=$data[1];
        if(empty($re) || empty($del)){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '未选择要更新数据！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        switch ($data){
            case $del!=null:
                foreach($del as $k=>$v){
                    $whereP['id'] = ['IN' ,$del[$k]];
                    $whereP['gid']=$this->gid;
                    Db::name('user_visit')->where($whereP)->delete();
                }
                foreach($re as $k=>$v){
                   Db::name('user_visit')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                    $arrtips['close'] = ['sign'=>true];
                    $arrtips['next_action'] = "getList({sign:'userUservisit'})";
                    return $this->tips($arrtips);
                break;
            default:
                foreach($re as $k=>$v){
                    Db::name('user_visit')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                    $arrtips['close'] = ['sign'=>true];
                    $arrtips['next_action'] = "getList({sign:'userUservisit'})";
                    return $this->tips($arrtips);
                break;
        }
    }
    private function userVisitList($input = [],$p = true){
        $whereP = [];
        $whereP['gid']=$this->gid;
        $kw = isset($input['keywords']) ? trim($input['keywords']) : false;
        if($kw){
            $whereP['title']=['LIKE','%'.$kw.'%'];
        }
        //接收关键词
        if($p === true){
            $res = Db::name('user_visit')->where($whereP)->order('displayorder asc')->paginate($this->rows);
            $pageInfo = pageInfo($res);
            $data = $res->items();
            $page = $res->currentPage();
        }else{
            $data = Db::name('user_visit')->where($whereP)->order('displayorder asc')->select();
            $pageInfo = false;
        }
        return ['pageInfo' => $pageInfo,'data' => $data];
    }
    private function showUservisitList($input = []){
        $res = $this->userVisitList($input);
        $this->assign('pageInfo',$res['pageInfo']);
        $this->assign('visits',$res['data']);
        return $this->fetch('/user/ajax/user_visit');
    }
    private function showUservisittpl(){
        $whereP['gid']=$this->gid;
        $res=Db::name('user_visit')->where($whereP)->select();
        $this->assign('visits',$res);
        return $this->fetch('user/act/user_visit');
    }
    /**
     * 客户类型操作
     * @return mixed|void
     */
    public function usertype(){
        $action = '';
        if(request()->isAjax() && request()->isPost()){
            //这里是获取ajax列表数据
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->showUsertypeList($input);break;
                case 'addtype':return $this->addTypeList($input);break;
                case 'edittype':return $this->editTypeList($input);break;
                case 'typedel':return $this->delTypeList($input);break;

                default:return $this->showUsertypetpl();break;
            }
        }else{
            $input = input();
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                default:return $this->showUsertypetpl();
            }
        }
    }
    private function addTypeList($input = []){
        $title=isset($input['title']) ? trim($input['title']) : false;
        $FormData=[
            'gid'=>$this->gid,
            'type'=>0,
            'title'=>$title,
            'displayorder'=>0,
            'status'=>0
        ];
        Db::name('user_type')->insert($FormData);
        $arrtips['autoclose_sign'] = true;
        $arrtips['msg'] = '添加成功，页面即将刷新！';
        $arrtips['close'] = ['sign'=>true];
        $arrtips['next_action'] = "getList({sign:'userUsertype'})";
        return $this->tips($arrtips);
    }
    private function editTypeList($input = []){
        $id=isset($input['id']) ? trim($input['id']) : false;
        $title=isset($input['title']) ? trim($input['title']) : false;
        $whereP= [];
        $whereP['gid']=$this->gid;
        $whereP['id']=$id;
        Db::name('user_type')->where($whereP)->setField('title',$title);
        $arrtips['autoclose_sign'] = true;
        $arrtips['msg'] = '编辑成功，页面即将刷新！';
        $arrtips['close'] = ['sign'=>true];
        $arrtips['next_action'] = "getList({sign:'userUsertype'})";
        return $this->tips($arrtips);
    }
    private function delTypeList(){
        $whereP= [];
        $whereP['gid']=$this->gid;
        $data[]=input('displayorder/a');
        $data[]=input('delete/a');
        $re=$data[0];
        $del=$data[1];
        if(empty($re) || empty($del)){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '未选择要更新数据！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        switch ($data){
            case $del!=null:
                foreach($del as $k=>$v){
                    $whereP['id'] = ['IN' ,$del[$k]];
                    $whereP['gid']=$this->gid;
                    Db::name('user_type')->where($whereP)->delete();
                }
                foreach($re as $k=>$v){
                    Db::name('user_type')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'userUsertype'})";
                return $this->tips($arrtips);
                break;
            default:
                foreach($re as $k=>$v){
                    Db::name('user_type')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'userUsertype'})";
                return $this->tips($arrtips);
                break;
        }
    }
    private function userTypeList($input = [],$p = true){
        $whereP = [];
        $whereP['gid']=$this->gid;
        $kw = isset($input['keywords']) ? trim($input['keywords']) : false;
        if($kw){
            $whereP['title']=['LIKE','%'.$kw.'%'];
        }
        //接收关键词
        if($p === true){
            $res = Db::name('user_type')->where($whereP)->order('displayorder asc')->paginate($this->rows);
            $pageInfo = pageInfo($res);
            $data = $res->items();
            $page = $res->currentPage();
        }else{
            $data = Db::name('user_type')->where($whereP)->order('displayorder asc')->select();
            $pageInfo = false;
        }
        return ['pageInfo' => $pageInfo,'data' => $data];
    }
    private function showUsertypeList($input = []){
        $res = $this->userTypeList($input);
        $this->assign('pageInfo',$res['pageInfo']);
        $this->assign('visits',$res['data']);
        return $this->fetch('/user/ajax/user_type');
    }
    private function showUsertypetpl(){
        $whereP['gid']=$this->gid;
        $res=Db::name('user_type')->where($whereP)->order('displayorder asc')->select();
        $this->assign('visits',$res);
        return $this->fetch('user/act/user_type');
    }
    /***
     * 客户等级操作
     * @return mixed|void
     */
    public function userrank(){
        $action = '';
        if(request()->isAjax() && request()->isPost()){
            //这里是获取ajax列表数据
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->showUserrankList($input);
                case 'addrank':return $this->addRankList($input);
                case 'editrank':return $this->editRankList($input);
                case 'rankdel':return $this->delRankList($input);

                default:return $this->showUserranktpl();
            }
        }else{
            $input = input();
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                default:return $this->showUserranktpl();
            }
        }
    }
    private function addRankList($input = []){
        $title=isset($input['title']) ? trim($input['title']) : false;
        $FormData=[
            'gid'=>$this->gid,
            'min_points'=>0,
            'max_points'=>0,
            'discount'=>0,
            'rank_name'=>$title,
            'displayorder'=>0,
            'status'=>0
        ];
        Db::name('user_rank')->insert($FormData);
        $arrtips['autoclose_sign'] = true;
        $arrtips['msg'] = '添加成功，页面即将刷新！';
        $arrtips['close'] = ['sign'=>true];
        $arrtips['next_action'] = "getList({sign:'userUserrank'})";
        return $this->tips($arrtips);
    }
    private function editRankList($input = []){
        $id=isset($input['id']) ? trim($input['id']) : false;
        $title=isset($input['title']) ? trim($input['title']) : false;
        $whereP= [];
        $whereP['gid']=$this->gid;
        $whereP['id']=$id;
        Db::name('user_rank')->where($whereP)->setField('rank_name',$title);
        $arrtips['autoclose_sign'] = true;
        $arrtips['msg'] = '编辑成功，页面即将刷新！';
        $arrtips['close'] = ['sign'=>true];
        $arrtips['next_action'] = "getList({sign:'userUserrank'})";
        return $this->tips($arrtips);
    }
    private function delRankList(){
        $whereP= [];
        $whereP['gid']=$this->gid;
        $data[]=input('displayorder/a');
        $data[]=input('delete/a');
        $re=$data[0];
        $del=$data[1];
        if(empty($re) || empty($del)){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '未选择要更新数据！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        switch ($data){
            case $del!=null:
                foreach($del as $k=>$v){
                    $whereP['id'] = ['IN' ,$del[$k]];
                    $whereP['gid']=$this->gid;
                    Db::name('user_rank')->where($whereP)->delete();
                }
                foreach($re as $k=>$v){
                    Db::name('user_rank')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'userUserrank'})";
                return $this->tips($arrtips);
                break;
            default:
                foreach($re as $k=>$v){
                    Db::name('user_rank')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'userUserrank'})";
                return $this->tips($arrtips);
                break;
        }
    }
    private function userRankList($input = [],$p = true){
        $whereP = [];
        $whereP['gid']=$this->gid;
        $kw = isset($input['keywords']) ? trim($input['keywords']) : false;
        if($kw){
            $whereP['rank_name']=['LIKE','%'.$kw.'%'];
        }
        //接收关键词
        if($p === true){
            $res = Db::name('user_rank')->where($whereP)->order('displayorder asc')->paginate($this->rows);
            $pageInfo = pageInfo($res);
            $data = $res->items();
            $page = $res->currentPage();
        }else{
            $data = Db::name('user_rank')->where($whereP)->order('displayorder asc')->select();
            $pageInfo = false;
        }
        return ['pageInfo' => $pageInfo,'data' => $data];
    }
    private function showUserrankList($input = []){
        $res = $this->userRankList($input);
        $this->assign('pageInfo',$res['pageInfo']);
        $this->assign('visits',$res['data']);
        return $this->fetch('/user/ajax/user_rank');
    }
    private function showUserranktpl(){
        $whereP['gid']=$this->gid;
        $res=Db::name('user_rank')->where($whereP)->select();
        $this->assign('visits',$res);
        return $this->fetch('user/act/user_rank');
    }

    /**
     * 客户跟进进度分类操作
     * @return mixed|void
     */
    public function userStage(){
        $action = '';
        if(request()->isAjax() && request()->isPost()){
            //这里是获取ajax列表数据
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->showUserStageList($input);break;
                case 'addsta':return $this->addStageList($input);break;
                case 'editsta':return $this->editStageList($input);break;
                case 'delsta':return $this->delStageList($input);break;
                case 'edittag':return $this->editTagList($input);break;

                default:return $this->showUsersStagetpl();break;
            }
        }else{
            $input = input();
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                default:return $this->showUserStagetpl();
            }
        }
    }
    //阶段标题 编辑
    private function editTagList($input = []){
        $id=isset($input['id']) ? trim($input['id']) : false;
        $title=isset($input['title']) ? trim($input['title']) : false;
        $whereBt['gid']=$this->gid;
        $tag_count=Db::name('stage_tag')->where($whereBt)->select();
        if(count($tag_count)==0){
            //显示的默认标题 修改后就添加
            $tag=[
                'gid'=>$this->gid,
                'title'=>$title,
                'status'=>1
            ];
            Db::name('stage_tag')->insert($tag);
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '编辑成功，页面即将刷新！';
            $arrtips['close'] = ['sign' => true];
            $arrtips['next_action'] = "window.location.reload();";
            return $this->tips($arrtips);
        }else if(count($tag_count)==1){
            //编辑
            $whereBt['id'] = $id;
            Db::name('stage_tag')->where($whereBt)->setField('title', $title);
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '编辑成功，页面即将刷新！';
            $arrtips['close'] = ['sign' => true];
            $arrtips['next_action'] = "window.location.reload();";
            return $this->tips($arrtips);
        }else{
            //不能 重复
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '操作失误，至多一个标题！';
            $arrtips['close'] = ['sign' => true];
            $arrtips['next_action'] = "window.location.reload();";
            return $this->tips($arrtips);
        }
    }
    //阶段列表标题 添加，编辑，删除
    private function addStageList($input = []){
        $title=isset($input['title']) ? trim($input['title']) : false;
        $displayorder=isset($input['displayorder']) ? intval($input['displayorder']) : 0;
        $whereUt['gid']=$this->gid;
        $whereUt['title']=$title;
        $count=Db::name('user_stage')->where($whereUt)->count();
        if($count>0){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '操作失败，存在相同名称！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }else{
            $FormData=[
                'gid'=>$this->gid,
                'title'=>$title,
                'displayorder'=>$displayorder,
                'status'=>1
            ];
            Db::name('user_stage')->insert($FormData);
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '添加成功，页面即将刷新！';
            $arrtips['close'] = ['sign'=>true];
            $arrtips['next_action'] = "getList({sign:'userUserstage'},1);";
            return $this->tips($arrtips);
        }
    }
    private function editStageList($input = []){
        $id=isset($input['id']) ? trim($input['id']) : false;
        $title=isset($input['title']) ? trim($input['title']) : false;
        $displayorder=isset($input['displayorder']) ? trim($input['displayorder']) : 0;
        $whereP['gid']=$this->gid;
        $whereP['title']=$title;
        $whereP['id']=['neq',$id];
        $count=Db::name('user_stage')->where($whereP)->count();
        if($count >0){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '操作失败，已存在该名称！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }else {
            $whereUt['gid']=$this->gid;
            $whereUt['id'] = $id;
            $tag=[
                'title'=>$title,
                'displayorder'=>$displayorder
            ];
            Db::name('user_stage')->where($whereUt)->setField($tag);
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '编辑成功，页面即将刷新！';
            $arrtips['close'] = ['sign' => true];
            $arrtips['next_action'] = "getList({sign:'userUserstage'},1);";
            return $this->tips($arrtips);
        }
    }
    private function delStageList(){
        $whereP= [];
        $whereP['gid']=$this->gid;
        $data[]=input('displayorder/a');
        $data[]=input('delete/a');
        $re=$data[0];
        $del=$data[1];
        if(empty($re) || empty($del)){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '未选择要更新数据！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        switch ($data){
            case $del!=null:
                foreach($del as $k=>$v){
                    $whereP['id'] = ['IN' ,$del[$k]];
                    $whereP['gid']=$this->gid;
                    Db::name('user_stage')->where($whereP)->delete();
                }
                foreach($re as $k=>$v){
                    Db::name('user_stage')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'userUserstage'},1);";
                return $this->tips($arrtips);
                break;
            default:
                foreach($re as $k=>$v){
                    Db::name('user_stage')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'userUserstage'},1);";
                return $this->tips($arrtips);
                break;
        }
    }
    private function userStageList($input = [],$p = true){
        $whereP = [];
        $whereP['gid']=$this->gid;
        $kw = isset($input['keywords']) ? trim($input['keywords']) : false;
        if($kw){
            $whereP['title']=['LIKE','%'.$kw.'%'];
        }
        //接收关键词
        if($p === true){
            $res = Db::name('user_stage')->where($whereP)->order('displayorder desc')->paginate($this->rows);
            $pageInfo = pageInfo($res);
            $data = $res->items();
            $page = $res->currentPage();
        }else{
            $data = Db::name('user_stage')->where($whereP)->order('displayorder desc')->select();
            $pageInfo = false;
        }
        return ['pageInfo' => $pageInfo,'data' => $data];
    }
    private function showUserStageList($input = []){
        $res = $this->userStageList($input);
        $this->assign('pageInfo',$res['pageInfo']);
        $this->assign('visits',$res['data']);
        return $this->fetch('/user/ajax/user_stage');
    }
    private function showUserStagetpl(){
        $whereP['gid']=$this->gid;
        $res=Db::name('user_stage')->where($whereP)->order('displayorder asc')->select();
        $this->assign('visits',$res);
        $list=Db::name('stage_tag')->where($whereP)->find();
        $this->assign('list',$list);
        return $this->fetch('user/act/user_stage');
    }
}