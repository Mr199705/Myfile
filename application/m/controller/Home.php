<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\m\controller;
use app\m\controller\Base;
use app\m\model\GroupMember;
use think\Db;
class Home extends Base{
    public function index(){
        //根据节点获取系统信息（系统：,功能应用，）
        //通过节点id查询到对应的系统名称
        //查询出关联的系统 id
        //Db::name(xxxx)->where('field',['IN',$rules])->field(xxxx);
        $memberaccess = trim(session('memberaccess'));
        if(!$memberaccess){
          //  $this->redirect('login/loginout');
        }
        $res = json_decode($memberaccess);
        $ruleslist = $res->rules;
       /* $entranceNodes = $res->entrancenodes;
        if(!empty($entranceNodes)){
            $whereGAR['id'] = ['IN',$entranceNodes];
            $whereGAR['m'] = 1;
            $entrance = Db::name('group_auth_rule')->where($whereGAR)->select();
        }else{
            $entrance = [];
        }
        */
        foreach ($ruleslist as $r){
        	$rules[]=$r;
        }
        $entrance = [];
        $whereGAR['m'] = 1;    //移动端
        $whereGAR['type'] = 0; //入口
        $entranceNodes = Db::name('group_auth_rule')->where($whereGAR)->select();
        foreach ($entranceNodes as $en){
            if(in_array($en['id'],$rules)){
                $entrance[]=$en;
            }
        }
        if(count($entrance) === 1 && !session('has_jumped')){
            session('has_jumped',1);
          //  $this->redirect($entrance[0]['module'] . '/' . $entrance[0]['url']);
        }
        $gUser = $this->gUser();
        $config = $this->wxConfig();
        $this->assign('wxconfig',$config);
        $this->assign('entrance',$entrance);
        $this->assign('gUser',$gUser);
        $this->assign('groupinfo',session('groupinfo'));
        return $this->fetch('/home/index');
    }
    public function resginfo(){
        $groupInfo = $this->assign('groupinfo',session('groupinfo'));
        $gUser = $this->gUser();
        return json(['code'=>1,'msg'=>'刷个人中心新成功！','data'=>$gUser]);
    }
    private function gUser(){
        $map['gid'] = session('gid');
        $map['uid'] = session('guid');
        $map['status'] = 1;
        $gUser = Db::name('group_member')->field('uid,username,realname,mobile,img,qq,email')->where($map)->find();
        if($gUser['img']) $gUser['img']=mkurl(['url'=>$gUser['img']]);
        $gUser['exp_date'] = date('Y-m-d',session('group_account_expdate'));
        return $gUser;
    }
   //编辑用户信息
    public function updateMember(){
        $mobile = input("param.mobile");
        $realname = input("param.realname");
        $email = input("param.email");
        $qq = input("param.qq");
        if($realname) $map['realname'] = $realname;
        if($mobile) $map['mobile'] = $mobile;
        if($email) $map['email'] = $email;
        if($qq) $map['qq'] = $qq;
        $GroupMember = new GroupMember;
        $hasUser = $GroupMember->save($map,['uid' => session('guid')]);
        return json(['code' => 1, 'url' => url('index/index'), 'msg' => '更新成功']);
    }
    //编辑用户密码
    public function updatePassword(){
        $oldpwd = input("param.oldpwd");
        $pwd = trim(input("param.pwd"));
        $pwdconfirm = trim(input("param.pwdconfirm"));
        if(strlen($pwd) < 6){
            $tip= ['code'=>0,'msg'=>'密码长度必须大于等于6位！'];
            return json($tip);
        }
        if($pwd != $pwdconfirm){
            $tip= ['code'=>0,'msg'=>'修改失败！两次输入的密码不一致！'];
            return json($tip);
        }
        $GroupMember = new GroupMember;
        if(!$GroupMember->checkGroupMemberPassword(session('guid'),$oldpwd)){
            $tip= ['code'=>0,'msg'=>'修改失败！旧密码不正确！'];
            return json($tip);
        }		
        if($GroupMember->updateGroupMemberPassword(session('guid'),$pwd)){
            $tip= ['code'=>1,'url' => url('login/loginOut'),'msg'=>'修改密码成功！'];
            return json($tip);
        }	
    }
    public function updateHeadImg(){
        if($file = request()->file('file')){
            $info = $file->validate(['ext'=>'jpg,png,gif'])->move(ROOT_PATH . 'public' . config('upload_path'));
            $img = str_replace('\\','/',config('upload_path') . '/' . $info->getSaveName());
            $map = ['img'=>$img];
            $GroupMember = new GroupMember;
            $hasUser = $GroupMember->save($map,['uid' => session('guid')]);
            return json_encode(['code'=>1,'msg'=>'修改头像成功！']);
        }
    }
	
	
	
}


