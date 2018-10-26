<?php
namespace app\admin\controller;
use think\Db;
class Myuser extends Base{
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
    public function user(){
        $whereP =[];
        $whereP['gid']=$this->gid;
        $whereP['status']=['EQ',1];
        $shop=Db::name('group_shop')->field('id,name')->where($whereP)->select();
        $this->assign('shop',$shop);
        $rank=Db::name('user_rank')->field('id,rank_name')->where($whereP)->select();
        $this->assign('rank',$rank);
        $type=Db::name('user_type')->field('id,title')->where($whereP)->select();
        $this->assign('type',$type);
        $stages=Db::name('user_stage')->field('id,title')->where($whereP)->select();
        $this->assign('stages',$stages);
        $tag_title=Db::name('user_stage_tag')->where($whereP)->value('title');
        $this->assign('tag_title',$tag_title);
        $visit=Db::name('user_visit')->field('id,title')->where($whereP)->select();
        $this->assign('visit',$visit);
        $action = '';
        if(request()->isAjax() && request()->isPost()){
            //这里是获取ajax列表数据
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->showUserList($input);break;
                case 'pluupdate':return $this->pluupdate($input);break;
                default:return $this->showUsertpl();
            }
        }else{
            $input = input();
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                default:return $this->showUsertpl();
            }
        }
    }
    private function UserList($input = [],$p = true){
        $whereP = [];
        $whereP['a.gid']=$this->gid;
        $whereP['a.guid']=$this->guid;
        /**
         * 有效 无效
         */
        $status=isset($input['status']) ? trim($input['status']) :false;
        if($status){
            if($status==1){
                $whereP['b.status']=['=',1];
            }else if($status==2){
                $whereP['b.status']=['=',0];
            }
        }
        /**
         * 根据 来自哪里
         */
        $shopid=isset($input['shopid']) ? trim($input['shopid']) :false;
        if($shopid){
            $whereP['shopid']=['=',$shopid];
        }
        /**
         * 客户等级
         */
        $rankid=isset($input['rankid']) ? trim($input['rankid']) :false;
        if($rankid){
            $whereP['rankid']=['=',$rankid];
        }
        /**
         * 客户类型
         */
        $tpid=isset($input['tpid']) ? trim($input['tpid']) :false;
        if($tpid){
            $whereP['tpid']=['=',$tpid];
        }
        /**
         * 客户阶段
         */
        $stage_id=isset($input['stage_id']) ? trim($input['stage_id']) :false;
        if($stage_id){
            $whereP['stage_id']=['=',$stage_id];
        }
        /**
         * 拜访周期 7 14 30 60 90 120
         */
        $bdate=isset($input['bdate']) ? trim($input['bdate']) :false;
        if($bdate){
            //当前时间戳
            $now=strtotime('now');
            if($bdate==7){
                $whereP['b.bdate']=['ELT',($now-24*3600*7)];
            }else if($bdate==14){
                $whereP['b.bdate']=['ELT',($now-24*3600*14)];
            }else if($bdate==30){
                $whereP['b.bdate']=['ELT',($now-24*3600*30)];
            }else if($bdate==60){
                $whereP['b.bdate']=['ELT',($now-24*3600*60)];
            }else if($bdate==90){
                $whereP['b.bdate']=['ELT',($now-24*3600*90)];
            }else if($bdate==120){
                $whereP['b.bdate']=['ELT',($now-24*3600*120)];
            }
        }
        /**
         * 订单周期 7 14 30 60 90 120
         */
        $odate=isset($input['odate']) ? trim($input['odate']) :false;
        if($odate){
            //当前时间戳
            $now=strtotime('now');
            if($odate==7){
                $whereP['b.odate']=['ELT',($now-24*3600*7)];
            }else if($odate==14){
                $whereP['b.odate']=['ELT',($now-24*3600*14)];
            }else if($odate==30){
                $whereP['b.odate']=['ELT',($now-24*3600*30)];
            }else if($odate==60){
                $whereP['b.odate']=['ELT',($now-24*3600*60)];
            }else if($odate==90){
                $whereP['b.odate']=['ELT',($now-24*3600*90)];
            }else if($odate==120){
                $whereP['b.odate']=['ELT',($now-24*3600*120)];
            }
        }
        /**
         * 关键词查询
         */
        $type=isset($input['type']) ? trim($input['type']) :false;
        $kw=isset($input['keyword']) ? trim($input['keyword']) :false;
        if($type || $kw){
            if($type=="realname"){
                $whereP['b.realname']=['LIKE','%'.$kw.'%'];
            }else if($type=="address"){
                $whereP['b.address']=['LIKE','%'.$kw.'%'];
            }else if($type=="contact"){
                $whereP['b.contact']=['LIKE','%'.$kw.'%'];
            }else if($type=="mobile"){
                $whereP['b.mobile']=['LIKE','%'.$kw.'%'];
            }else if($type=='content'){
                $whereUp['gid']=['=',$this->gid];
                $whereUp['content']=['LIKE','%'.$kw.'%'];
                if(!empty($whereUp)){
                    $up=Db::name('user_post')->field('id,uid,content')->where($whereUp)->select();
                }
                foreach($up as $m=>$n){
                    $arr[]=$n['uid'];
                }
                $whereP['b.uid']=['IN',$arr];
            }else if($type=="summary"){
                $whereP['b.summary']=['LIKE','%'.$kw.'%'];
            }
        }
        /**
         * 拜访路线
         */
        $visitid=isset($input['visitid']) ? trim($input['visitid']) :false;
        if($visitid){
            $whereP['visitid']=['=',$visitid];
        }
        /**
         * 认证状态
         */
        $trust=isset($input['trust']) ? trim($input['trust']) :false;
        if($trust){
            if($trust==1){
                $whereP['b.trust']=['=',1];
            }else if($trust==2){
                $whereP['b.trust']=['=',0];
            }
        }
        /**
         * 地图标注
         */
        $x=isset($input['x']) ? trim($input['x']) :false;
        if($x){
            if($x==1){
                $whereP['b.x']=['EQ',' '];
                $whereP['b.y']=['EQ',' '];
            }else if($x==2){
                $whereP['b.x']=['NEQ',' '];
                $whereP['b.y']=['NEQ',' '];
            }
        }
        /**
         * 用户资料更新 一周 两周 一个月 半年 一年内 一年前
         */
        $lastvisit=isset($input['lastvisit']) ? trim($input['lastvisit']) :false;
        if($lastvisit){
            //当前时间戳
            $now=strtotime('now');
            if($lastvisit==1){
                $whereP['b.lastvisit']=['EGT',($now-24*3600*7)];
            }else if($lastvisit==2){
                $whereP['b.lastvisit']=['EGT',($now-24*3600*14)];
            }else if($lastvisit==3){
                $whereP['b.lastvisit']=['EGT',($now-24*3600*30)];
            }else if($lastvisit==4){
                $whereP['b.lastvisit']=['EGT',($now-24*3600*182)];
            }else if($lastvisit==5){
                $whereP['b.lastvisit']=['EGT',($now-24*3600*365)];
            }else if($lastvisit==6){
                $whereP['b.lastvisit']=['ELT',($now-24*3600*365)];
            }
        }
        /**
         * 地图标注
         */
        $weixin=isset($input['weixin']) ? trim($input['weixin']) :false;
        if($weixin){
            if($weixin==1){
                $whereP['b.weixin']=['NEQ',' '];
            }else if($weixin==2){
                $whereP['b.weixin']=['EQ',' '];
            }
        }
        /**
         * 注册时间
         */
        $csdate=isset($input['csdate']) ? trim($input['csdate']) :false;
        $cedate=isset($input['cedate']) ? trim($input['cedate']) :false;
        if($csdate || $cedate){
            if($csdate && !$cedate){
                $whereP['b.regtime'] = ['EGT',strtotime($csdate)];
            }else if(!$csdate && $cedate){
                $whereP['b.regtime'] = ['ELT',strtotime($cedate)];
            }else{
                $whereP['b.regtime'] = ['BETWEEN',[strtotime($csdate),strtotime($cedate)]];
            }
        }
        if($p === true){
            $res = Db::name('user_gm')->alias('a')
                ->field('a.id,a.gid,a.uid,a.guid,b.*')
                ->join('ljk_user_member b','a.uid=b.uid','LEFT')
                ->where($whereP)
                ->order('a.adate desc')
                ->paginate($this->rows);
            $pageInfo = pageInfo($res);
            $data = $res->items();
        }else {
            $data = Db::name('user_gm')->alias('a')
                ->field('a.id,a.gid,a.uid,a.guid,b.*')
                ->join('ljk_user_member b', 'a.uid.b.uid', 'LEFT')
                ->where($whereP)->order('a.adate desc')->select();
            $pageInfo = false;
        }
        $gm = [];
        $ut = [];
        $ur = [];
        $uv = [];
        $or = [];
        $sh= [];
        $lbsinfolist = [];
        foreach ($data as $k=>$v){
            if($input['keyword']!='' && $input['type']=='summary'){
                $v['summary']=str_ireplace($input['keyword'], "<strong style='color:#f00;'>".$input['keyword']."</strong>", $v['summary']);
            }
            if(!isset($gm[$v['guid']])){
                $whereGm = [];
                $whereGm['gid']=$this->gid;
                $whereGm['uid']=['=',$v['guid']];
                $gm[$v['guid']]=Db::name('group_member')->field('uid,gid,realname')->where($whereGm)->find();
            }
            if(!isset($ut[$v['tpid']])){
                $whereUt= [];
                $whereUt['gid']=$this->gid;
                $whereUt['id']=['=',$v['tpid']];
                $whereUt['status']=['=',1];
                $ut[$v['tpid']]=Db::name('user_type')->field('id,title')->where($whereUt)->find();
            }
            if(!isset($ur[$v['rankid']])){
                $whereUr=[];
                $whereUr['gid']=$this->gid;
                $whereUr['id']=['=',$v['rankid']];
                $whereUr['status']=['=',1];
                $ur[$v['rankid']]=Db::name('user_rank')->field('id,rank_name')->where($whereUr)->find();
            }
            if(!isset($uv[$v['visitid']])){
                $wherUv = [];
                $wherUv['gid']=$this->gid;
                $wherUv['id']=['=',$v['visitid']];
                $wherUv['status']=['=',1];
                $uv[$v['visitid']]=Db::name('user_visit')->field('id,title')->where($wherUv)->find();

            }
            if(!isset($sh[$v['shopid']])){
                $whereSh = [];
                $whereSh['gid']=$this->gid;
                $whereSh['id']=['=',$v['shopid']];
                $whereSh['status']=['=',1];
                $sh[$v['shopid']]=Db::name('group_shop')->field('id,name')->where($whereSh)->find();
            }


            $v['group_member']=$gm[$v['guid']];
            $v['user_type']=$ut[$v['tpid']];
            $v['user_rank']=$ur[$v['rankid']];
            $v['user_visit']=$uv[$v['visitid']];

            if($v['odate']!=0 && !empty($v['odate']) && isset($v['odate'])){
                $otime=round((time()-$v['odate'])/24/3600);
            }else if($v['lastvisit']!=0 && !empty($v['lastvisit']) && isset($v['lastvisit'])){
                $otime=round((time()-$v['lastvisit'])/24/3600);
            }else if($v['regtime']!=0 && !empty($v['regtime']) && isset($v['regtime'])){
                $otime=round((time()-$v['regtime'])/24/3600);
            }else{
                $otime=0;
            }
            $v['order_days']=$otime;
            if($v['bdate']!=0 && !empty($v['bdate']) && isset($v['bdate'])){
                $btime=round((time()-$v['bdate'])/24/3600,0);
            }else if($v['lastvisit']!=0 && !empty($v['lastvisit']) && isset($v['lastvisit'])){
                $btime=round((time()-$v['lastvisit'])/24/3600,0);
            } else if($v['regtime']!=0 && !empty($v['regtime']) && isset($v['regtime'])){
                $btime=round((time()-$v['regtime'])/24/3600,0);
            }else{
                $btime=0;
            }
            $v['baifang_days']=$btime;
            $v['grouop_shop']=$sh[$v['shopid']];
            $lbsinfolist[]=$v;
        }
        return ['pageInfo' => $pageInfo,'data' => $lbsinfolist];
    }
    private function pluupdate($input= []){
        $ids=input('ids/a');
        if(!isset($ids)){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '请选择要修改的客户！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $tpid=isset($input['tpid']) ? trim($input['tpid']) :false;
        $rankid=isset($input['rankid']) ? trim($input['rankid']) :false;
        $stage_id=isset($input['stage_id']) ? trim($input['stage_id']) :false;
        $trust=isset($input['trust']) ? trim($input['trust']) :false;
        $status=isset($input['status']) ? trim($input['status']) :false;
        $whereP=[];
        $whereP['b.gid']=['=',$this->gid];
        $whereP['b.guid']=['=',$this->guid];
        $whereP['b.uid']=['IN',$ids];

        $FormData=[];
        if($tpid !="")$FormData['b.tpid']=$tpid;
        if($stage_id !="")$FormData['b.stage_id']=$stage_id;
        if($rankid !="")$FormData['b.rankid']=$rankid;
        if($status !="")$FormData['b.status']=$status;
        if($trust !="")$FormData['b.trust']=$trust;

        if($FormData){
            Db::name('user_gm')->alias('a')
                ->field('a.id,a.gid,a.uid,a.guid,b.*')
                ->join('ljk_user_member b','a.uid=b.uid','LEFT')
                ->where($whereP)->update($FormData);
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '批量修改信息成功，即将刷新页面！';
            $arrtips['close'] = ['sign'=>true];
            $arrtips['next_action'] = "getList({sign:'myuserUser'})";
            return $this->tips($arrtips);
        }else{
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '请选择修改条件！';
            $arrtips['close'] = ['sign'=>true];
            $arrtips['next_action'] = "getList({sign:'myuserUser'})";
            return $this->tips($arrtips);
        }
    }
    private function showUserList($input = []){
        $res = $this->UserList($input);
        $this->assign('pageInfo',$res['pageInfo']);
        $this->assign('user_gm',$res['data']);
        return $this->fetch('/myuser/ajax/user');
    }
    private function showUsertpl(){
        $data=Db::name('user_gm')->order('id asc')->select();
        $this->assign('user_gm',$data);
        return $this->fetch('/myuser/user');
    }
    public function detail(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'edit':return $this->editUser($input);
                case 'remove':return $this->removecontact($input);
                case 'select':return $this->selectecontact($input);
                case 'hs':return $this->hssearch($input);
                case 'stock':return $this->stocksearch($input);
                case 'ord':return $this->ordsearch($input);
                case 'follow':return $this->followsearch($input);
                case 'ch':return $this->chsearch($input);
                case 'addpost':return $this->addpost($input);
                default:            
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['msg'] = '无效的操作！';
                    $arrtips['close'] = ['sign'=>true];
                    return $this->tips($arrtips);
            }
        }
        $input = input();
        $uid = $input['uid'] ? $input['uid'] : (session('uid') ? session('uid') : 0);
        $re = $this->checkGm($uid); 
        if(empty($re)){
            $this->assign('message','客户资料不存在！');
            return $this->fetch('common/forbidden');
        }
        session('uid',$uid);
        $whereUm['uid'] = $uid;
        $whereUm['gid'] = $this->gid;
        $one = Db::name('user_member')->where($whereUm)->find();
        if(empty($one)){
            $this->assign('message','客户资料不存在！');
            return $this->fetch('common/forbidden');
        }
        if(!empty($one['mtz'])){
            $whereF['gid'] = $this->gid;
            $whereF['id'] = $one['mtz'];
            $mtzurl = Db::name('file')->where($whereF)->find();
            if(!!$mtzurl){
                $one['mtzimg'] = $this->mkgoodsimgurl($mtzurl);
            }
        }
        $whereUt['gid'] = $this->gid;
        $whereUt['status'] = 1;
        $type = Db::name('user_type')->where($whereUt)->column('id,status,title');
        $whereUr['gid'] = $this->gid;
        $whereUr['status'] = 1;
        $rank = Db::name('user_rank')->where($whereUr)->column('id,status,rank_name');
        $whereUv['gid'] = $this->gid;
        $whereUv['status'] = 1;
        $visit = Db::name('user_visit')->where($whereUv)->column('id,status,title');
        if(isset($rank[$one['rankid']])){
            $one['rankt'] = $rank[$one['rankid']]['rank_name'];
        }
        if(isset($visit[$one['visitid']])){
            $one['visitt'] = $visit[$one['visitid']]['title'];
        }
        if(isset($type[$one['tpid']])){
            $one['typet'] = $type[$one['tpid']]['title'];
        }
        if($one['x'] && $one['y']){
            $x= $one['x'];
            $one['x'] = $one['y'];
            $one['y']= $x;
        }
        //处理$type
        $whereGm['gid'] = $this->gid;
        $whereGm['uid'] = $this->guid;
        $whereGm['status']  = 1;
        $members =Db::name('group_member')->where($whereGm)->select();
        $whereUp['gid'] = $this->gid;
        $whereUp['guid'] = $this->guid;
        $whereUp['uid'] = $uid;
        $followCount =Db::name('user_post')->where($whereUp)->count();
        $csdate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) - 7 * 3600 * 24);
        $cedate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) + 3600 * 24 - 1);
        //联系人多个号码
        $whereCon['gid']=['=',$this->gid];
        $whereCon['uid']=['=',input('uid')];
        $contact=Db::name('user_contact')->where($whereCon)->select();
        $contact_count=Db::name('user_contact')->where($whereCon)->count();
        $this->assign('csdate',$csdate);
        $this->assign('cedate',$cedate);
        $this->assign('followCount',$followCount);
        $this->assign('user',$one);
        $this->assign('contact',json_encode($contact));
        $this->assign('contact_count',$contact_count);
        $this->assign('ginfo',session('groupinfo'));
        $this->assign('members',$members);
        $whereCC=[
            'gid'=>$this->gid,
            'uid'=>input('uid'),
            'guid'=>$this->guid
        ];
        $bftotal=Db::name('user_post')->where($whereCC)->count();
        $this->assign('bftotal',$bftotal);
        $this->assign('ranks',$rank);
        $this->assign('types',$type);
        $this->assign('visits',$visit);
        return $this->fetch('/myuser/detail');
    }
    public function add(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
            }
            if($action=='add'){
                $param=$input;
                if($param['xy']){
                    $xy=explode(',',$param['xy']);
                    $param['y']=$xy[0];
                    $param['x']=$xy[1];
                }else{
                    $param['y']=0;
                    $param['x']=0;
                }
                if(Db::name('user_member')->where('gid',$this->gid)->where('mobile',$param['mobile'])->count()===1){
                    // 验证失败 输出错误信息
                    $arrtips['msg'] = '手机主号码已经存在，请检查客户信息是否重复，可以修改手机号码后重新提交!';
                    return $this->tips($arrtips);
                }
                unset($param['uid']);
                unset($param['action']);
                unset($param['xy']);
                $param['gid']=$this->gid;
                $param['regtime']=time();
                $param['regip']=get_client_ip();
                $param['guid'] = $this->guid;
                $param['bd'] = '0000-00-00';
                $param['taobao'] = '';
                $param['weixin'] = '';
                $param['alipay'] = '';
                $param['yhzh'] = '';
                $param['hdate'] = 0;
                try{
                    $uid =  Db::name('user_member')->insertGetId($param);
                }catch(\think\Exception $e){
                    echo $e->getMessage();
                }
                if($uid){
                    $arrtips['msg'] = '！';
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['msg'] = '新增客户成功，请前往客户列表中查看！本页面即将刷新！';
                    $arrtips['close'] = ['sign'=>true];
                    $arrtips['next_action'] = "setTimeout(function(){window.location.href=window.location.href;},2000)";
                    if($param['guid']!=0){
                        $this->dgjr($uid,$param['guid']);
                    }
                }else{
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['msg'] = '新增客户资料失败！';
                    $arrtips['close'] = ['sign'=>true];
                }
                return $this->tips($arrtips);
            }
        }else{
            return $this->showAddForm();
        }
    }
    private function dgjr($uid,$guid){
     	if($guid){
            $isguid=Db::name('user_gm')->where('gid',session('gid'))->where('uid',$uid)->where('guid',$guid)->count();
            if(!$isguid){
                $data['gid']=session('gid');
                $data['uid']=$uid;
                $data['guid']=$guid;
                $data['adate']=time();
                Db::name('user_gm')->insert($data);
            }
     	}
    }
    public function showAddForm(){
        $members =Db::name('group_member')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$members) $members='';
    	$this->assign('members',$members);
    	$Ranks =Db::name('user_rank')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$Ranks) $Ranks='';
    	$this->assign('Ranks',$Ranks);
    	$types =Db::name('user_type')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$types) $types='';
    	$this->assign('types',$types);
    	 
    	$Visits =Db::name('user_visit')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$Visits) $Visits='';
    	$this->assign('Visits',$Visits);
    	$this->assign('ginfo',session('groupinfo'));
    	return $this->fetch('/myuser/act/user_add');
    }
    private function editUser($param = []){
        $uid = $param['uid'] ? $param['uid'] : (session('uid') ? session('uid') : 0);
        if(!$uid || empty($this->checkGm($uid))){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        if($param['xy']){
            $xy=explode(',',$param['xy']);
            $param['x']=$xy[1];
            $param['y']=$xy[0];
        }else{
            unset($param['x']);
            unset($param['y']);
        }
        unset($param['uid']);
        unset($param['action']);
        unset($param['xy']);
        if(sizeof($param['areas'])!=1){
            if($param['areas']){
                $param['areaids'] = '';
                $param['areaname'] = '';
                foreach ($param['areas'] as $area){
                    $ar=explode(":",$area);
                    $param['areaids'].=$ar[0].',';
                    $param['areaname'].=$ar[1];
                    if(!($ar[1]=='县' || $ar[1] == '市辖区')){
                        $param['address']=trim($param['address']);
                    }
                }
                if($param['areaids']){
                    $param['areaids']=trim($param['areaids'],',');
                }
                unset($param['areas']);
            }
        }else{
            unset($param['areas']);
        }
        $uploadImg = controller('uploadImg','event');
        $file = request()->file('mtzimg');
        $path='/uploads/mtz';
        if($file) {
            $imginfo = $uploadImg->index($file, $path);
            $imgid = $imginfo['id'];
            if ($imgid === -1) {
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '请上传图片文件！';
                $arrtips['close'] = ['sign' => true];
                return $this->tips($arrtips);
            } else if ($imgid === -2) {
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '系统繁忙，请稍候再试！';
                $arrtips['close'] = ['sign' => true];
                return $this->tips($arrtips);
            } else if ($imgid === -3) {
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '上传文件不得超过1M！';
                $arrtips['close'] = ['sign' => true];
                return $this->tips($arrtips);
            } else {
                $thumburl = $imginfo['url'];
            }
            $param['mtz'] = $imgid;
        }
        if(isset($param['mtzimg'])){
            unset($param['mtzimg']);
        }
        if($param['birthdaytype']==1){
            //公历生日
            $param['birthday']=$param['birthday'];
        }else if($param['birthdaytype']==2){
            //阳历生日
            $param['birthday']=$param['birthday'];
        }

        if(isset($param['mtzimg'])){
            unset($param['mtzimg']);
        }
        if(isset($param['gender'])){
            $param['gender']=trim($param['gender']);
        }else{
            unset($param['gender']);
        }
        //多选联系人电话
        if(isset($param['contacts'])){
            $contact=$param['contacts'];
            if($contact===false){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '联系人目前无多个联系电话！';
                $arrtips['close'] = ['sign'=>true];
                return $this->tips($arrtips);
            }
            //数据发生改变  添加
            foreach ($contact as $k=>$v){
                if($v['id']==-1){
                    $contact1['uid']=input('uid');
                    $contact1['gid']=$this->gid;
                    $contact1['birthday']=$param['birthday'];
                    $contact1['realname']=$v['realname'];
                    $contact1['address']=$v['address'];
                    $contact1['gender']=$v['gender'];
                    $contact1['mobile']=$v['mobile'];
                    $contact1['areaids']=$param['areaids'];
                    $contact1['areaname']=$param['areaname'];
                    $contact1['settime']=time();
                    $contact1['status']=1;
                        Db::name('user_contact')->insert($contact1);
                    }else{
                    $contacts['realname']=$v['realname'];
                    $contacts['address']=$v['address'];
                    $contacts['gender']=$v['gender'];
                    $contacts['mobile']=$v['mobile'];
                    $contacts['areaids']=$param['areaids'];
                    $contacts['areaname']=$param['areaname'];
                    $contacts['settime']=time();
                    $contacts['status']=1;
                    Db::name('user_contact')
                        ->where(array('id'=>$v['id'],'uid'=>input('uid'),'gid'=>$this->gid))
                        ->update($contacts);
                }
            }
        }else{
            unset($param['contacts']);
        }
        unset($param['contacts']);
        unset($param['id']);
        if(isset($param['trust'])){
            $param['trust']=trim($param['trust']);
        }else{
            unset($param['trust']);
        }
        if(isset($param['status'])){
            $param['status']=trim($param['status']);
        }else{
            unset($param['status']);
        }
        if($param['summary']){
            $param['summary']=strip_tags($param['summary']);
        }else{
            unset($param['summary']);
        }
        $param['tpid']=trim($param['tpid']);
        $param['rankid']=trim($param['rankid']);
        $param['visitid']=trim($param['visitid']);
        $param['contact']=$param['contact'];
        $param['mobile']=strip_tags($param['mobile']);
        $param['phone']=strip_tags($param['phone']);
        $param['lastvisit']=time();
        //更新时间
        $whereUum['uid'] = $uid;
        $whereUum['gid'] = $this->gid;
        $num2=Db::name('user_member')->where($whereUum)->update($param);
        if($num2>0){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '编辑用户成功，页面即将刷新！';
            $arrtips['close'] = ['sign'=>true];
            $arrtips['next_action'] = "setTimeout(function(){window.location.href=window.location.href;},1000)";
            return $this->tips($arrtips);
        }else{
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '编辑用户失败，页面即将刷新！';
            $arrtips['close'] = ['sign'=>true];
            $arrtips['next_action'] = "setTimeout(function(){window.location.href=window.location.href;},1000)";
            return $this->tips($arrtips);
        }
    }
    private function removecontact($param = [])
    {
        //移除联系人
        $whereP['id'] = $param['id'];
        $whereP['uid'] = $param['uid'];
        $res = Db::name('user_contact')->where($whereP)->delete();
        if ($res > 0) {
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '移除成功，页面即将刷新！';
            $arrtips['close'] = ['sign' => true];
            $arrtips['next_action'] = "setTimeout(function(){window.location.href=window.location.href;},1000)";
            return $this->tips($arrtips);
        }
    }
    private function selectecontact($param = []){
        $uid=$param['uid'];
        $data=Db::name('user_contact')->where(array('uid'=>$uid))->count();
        return $data;
    }
    private function followsearch($input = []){
        $uid = $input['uid'] ? $input['uid'] : (session('uid') ? session('uid') : 0);
        if(!$uid || empty($this->checkGm())){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $followArr = [];
        $followArr['uid'] =$uid;
        $followArr['gid'] =$this->gid;
        //设置开始结束时间
        $interval = $this->setInterval();
        $limit = input('limit')?input('limit'):20;
        if(input('keyword')){
            $kw=input('keyword');
            $followArr['content']=['LIKE','%'.$kw.'%'];
        }
        $res = Db::name('user_post')
            ->where($followArr)->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->order('adate desc')->paginate($limit);
        $pageInfo = [
            'currentPage' => $res->currentPage(),
            'total' => $res->total(),
            'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
            'listRows' => $res->listRows(),
            'lastPage' => $res->lastPage(),
            'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
            'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
            't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
        ];
        $followList = $res->items();
        foreach($followList as $k=>$follow){
            $followList[$k]['thumb'] = explode('||',$followList[$k]['thumb']);
            if(isset($follow['guid'])){
                $followList[$k]['host_realname'] = Db::name('group_member')->where('uid',$follow['guid'])->value('realname');
            }else{
                $followList[$k]['host_realname'] ='';
            }
            if(isset($follow['imgsid']) && strlen(trim(str_replace('_',',',$follow['imgsid']),','))){
                $imgsids = trim(str_replace('_',',',$follow['imgsid']),',');
                $imgsid = explode(',',$imgsids);
                $unique  = array_values(array_unique($imgsid));
                for($i=0;$i<count($unique);$i++){
                    $urll=Db::name('file')->where('id',$unique[$i])->value('url');
                    $thumb['url']=$urll;
                    if($urll) $imgsurlu[$unique[$i]] = $this->mkgoodsimgurl($thumb);
                    else $imgsurlu[$unique[$i]] ='';
                }
                for($i=0;$i<count($imgsid);$i++){
                    $imgsurl[] = $imgsurlu[$imgsid[$i]];
                }
                $followList[$k]['imgsurl'] = $imgsurl;
                unset($imgsurl);
            }
            $memberlbs=Db::name('group_memberlbs')->where('pid',$follow['id'])->where('zid',$follow['uid'])->where('uid',$follow['guid'])->find();
            if($memberlbs){
                $followList[$k]['x'] =$memberlbs['x'];
                $followList[$k]['y'] =$memberlbs['y'];
                $followList[$k]['address'] = $memberlbs['city'].$memberlbs['district'].$memberlbs['street'].$memberlbs['street_number'];
                $memberinfo=Db::name('user_member')->field('x,y')->where('uid',$uid)->find();
                if($memberinfo['x']){
                    $juli=$this->GetDistance($memberinfo['x'],$memberinfo['y'],$memberlbs['x'],$memberlbs['y'],1,0);
                    if($juli<500) $followList[$k]['juli']='<font color="#006600">'.$juli.'米</font>';
                    if($juli<1000 and $juli>500) $followList[$k]['juli']='<font color="#009900">'.$juli.'米</font>';
                    if($juli<3000 and $juli>1000) $followList[$k]['juli']='<font color="#996600">'.$juli.'米</font>';
                    if($juli>3000) $followList[$k]['juli']='<font color="#FF0000">'.$juli.'米</font>';
                }
            }else{
                $followList[$k]['address'] ='';
            }
            $followList[$k]['sign'] = 1;
            $followList[$k]['contentr'] = preg_replace('/(\{\[\%){1}(.*)(\%\]\}){1}/','【<span style="color:red;">$2</span>】', $followList[$k]['content']);            
        }
        $this->assign('actName', 'follow');
        $this->assign('pageInfo',$pageInfo);
        $this->assign('follows', $followList);
        return $this->fetch('/myuser/ajax/follow_index');
    }
    private function stocksearch($input = []){
        $uid = $input['uid'] ? $input['uid'] : (session('uid') ? session('uid') : 0);
        if(!$uid || empty($this->checkGm())){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $followArr = array();
        $followArr['uid'] =$uid;
        $followArr['gid'] =$this->gid;
        $followArr['guid'] =$this->guid;
        //设置开始结束时间
        $interval = $this->setInterval();
        $limit = input('limit')?input('limit'):$this->rows;
        $res =Db::name('user_stock')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->order('adate desc')->paginate($limit);
        $pageInfo = [
            'currentPage' => $res->currentPage(),
            'total' => $res->total(),
            'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
            'listRows' => $res->listRows(),
            'lastPage' => $res->lastPage(),
            'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
            'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
            't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
        ];
        $stocklist = $res->items();
        foreach($stocklist as $k=>$stock){
            $stocklist[$k]['goodsname']=Db::name('goods')->where('goods_id',$stock['goodsid'])->value('goods_name');
        }
        $this->assign('actName', 'follow');
        $this->assign('pageInfo',$pageInfo);
        $this->assign('stocklist', $stocklist);
        return $this->fetch('/myuser/ajax/user_stock');
    }
    private function hssearch($input = []){
        $uid = $input['uid'] ? $input['uid'] : (session('uid') ? session('uid') : 0);
        if(!$uid || empty($this->checkGm())){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $followArr = array();
        $followArr['uid'] =$uid;
        $followArr['gid'] =$this->gid;
        $followArr['guid'] =$this->guid;
        //设置开始结束时间
        $interval = $this->setInterval();
        $limit = input('limit')?input('limit'):$this->rows;
        //$limit =2;
        $res =Db::name('goods_snum')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->order('adate desc')->paginate($limit);;
        $pageInfo = [
            'currentPage' => $res->currentPage(),
            'total' => $res->total(),
            'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
            'listRows' => $res->listRows(),
            'lastPage' => $res->lastPage(),
            'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
            'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
            't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
        ];
        $hslist = $res->items();
        foreach($hslist as $k=>$hs){
            $hslist[$k]['goodsname']=Db::name('goods')->where('goods_id',$hs['goodsid'])->value('goods_name');
        }
        $this->assign('actName', 'follow');
        $this->assign('pageInfo',$pageInfo);
        $this->assign('hslist', $hslist);
        return $this->fetch('/myuser/ajax/user_hs');
    }
    private function ordsearch($input = []){
        $uid = $input['uid'] ? $input['uid'] : (session('uid') ? session('uid') : 0);
        if(!$uid || empty($this->checkGm())){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $followArr = array();
        $followArr['uid'] =$uid;
        $followArr['gid'] =$this->gid;
        $followArr['guid'] =$this->guid;
        //设置开始结束时间
        $interval = $this->setInterval();
        $limit = input('limit')?input('limit'):$this->rows;
        //$limit =2;
        //留言列表
        //[$interval['start']
        $res =Db::name('order')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->order('adate desc')->paginate($limit);
        $pageInfo = [
            'currentPage' => $res->currentPage(),
            'total' => $res->total(),
            'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
            'listRows' => $res->listRows(),
            'lastPage' => $res->lastPage(),
            'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
            'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
            't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
        ];
        $orderlist = $res->items();
        foreach($orderlist as $k=>$order){
            $orderlist[$k]['host'] = Db::name('user_member')->where('uid',$order['uid'])->where('guid',$this->guid)->find();
            if($order['ppsid']){
                $orderlist[$k]['distributor'] = Db::name('distributor')->where('id',$order['ppsid'])->where('gid',$this->gid)->value('title');
            }
        }
        $orderjine=Db::name('order')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->sum('total');
        $this->assign('orderjine',$orderjine);
        $this->assign('pageInfo',$pageInfo);
        $this->assign('orderlist', $orderlist);
        return $this->fetch('/myuser/ajax/user_order');
       
    }
    private function chsearch($input = []){
        $uid = $input['uid'] ? $input['uid'] : (session('uid') ? session('uid') : 0);
        if(!$uid || empty($this->checkGm())){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $followArr = array();
        if($uid){
            $followArr['uid'] =$uid;
            $followArr['gid'] =$this->gid;
            //设置开始结束时间
            $interval = $this->setInterval();
            $limit = input('limit')?input('limit'):$this->rows;

            //其他数量 和金额 名字
            $res = Db::name('order_goods g')
                ->field('o.adate,g.oid,g.name,g.goodid,g.num,g.amount,g.unit,g.unitgid,u.coefficient,ut.coefficient ucoefficient,ut.uname')
                ->join('ljk_order o','g.oid=o.oid')
                ->join('ljk_unit_group ug','g.unitgid=ug.id','LEFT')
                ->join('ljk_unit u','g.unitid=u.id','LEFT')
                ->join('ljk_unit ut','ut.id=ug.minunitid','LEFT')
                ->where('o.uid',$uid)
                ->where('o.gid',$this->gid)
                ->where('o.confirm','EGT',0)
                ->where('o.status',1)
                ->where('o.adate','BETWEEN', $interval['start']. ',' .  $interval['stop'])
                ->order('o.adate DESC')
                ->paginate($limit);

            $pageInfo = [
                'currentPage' => $res->currentPage(),
                'total' => $res->total(),
                'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
                'listRows' => $res->listRows(),
                'lastPage' => $res->lastPage(),
                'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
                'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
                't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
            ];
            $orders = $res->items();
            $orderjine=0;
            $ch = [];

            foreach($orders as $k=>$v){
                $orderjine += $v['amount'];
                //首先判断$v['goodid']是否已经存在于 $ch的下标中
                if(array_key_exists($v['goodid'], $ch)){
                    //存在那么就不新增，而是去更新里面的num值以及price,以及平均价格
                    $num = $ch[$v['goodid']]['num'] = ($v['coefficient'] / $v['ucoefficient']) * $v['num'] + $ch[$v['goodid']]['num'];
                    $amount = $ch[$v['goodid']]['amount'] = $ch[$v['goodid']]['amount'] +  $v['amount'];
                    $ch[$v['goodid']]['average'] = round($amount/$num,2);
                }else{
                    if(!$v['ucoefficient'] || !$v['num']){
                        continue;
                    }
                    $num = ($v['coefficient'] / $v['ucoefficient']) * $v['num'];
                    if($num) $average = round($v['amount'] / $num,2);
                    $ch[$v['goodid']] = [
                        'amount'=>$v['amount'],
                        'name'=>$v['name'],
                        'uname'=>$v['uname'],
                        'unit'=>$v['unit'],
                        'num'=>$num,
                        'average'=>$average,
                        'adate'=>$v['adate'],
                    ];
                }
            }
            $this->assign('orderjine',$orderjine);
            $this->assign('pageInfo',$pageInfo);
            $this->assign('chlist', $ch);
            return $this->fetch('/myuser/ajax/user_ch');
        }else{
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
    }
    private function addpost($input = []){
        $uid = $input['uid'] ? $input['uid'] : (session('uid') ? session('uid') : 0);
        if(!$uid || empty($this->checkGm())){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '客户不存在或该客户不在您的名下！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $post = request()->post();
        $content = $post['content'];
        $file=input('preview_img1/a');
        if(!empty($file)){
            if(sizeof($file)>=9){
                for($i=0;$i<9;$i++){
                    $file[$i]=$this->saveBase64Image($file[$i]);
                    $imgsid[]= $file[$i]['id'];
                }
            }else{
                for($i=0;$i<sizeof($file);$i++){
                    $file[$i]=$this->saveBase64Image($file[$i]);
                    $imgsid[]= $file[$i]['id'];
                }
            }
            //组合为字符串
            $imgsids=implode('_',$imgsid);
        }else{
            $imgsids='';
        }
        $userPost = [
            'gid'=>session('gid'),
            'content'=>$content,
            'uid'=>$uid,
            'guid'=>$this->guid,
            'imgsid'=>$imgsids,
            'adate'=>time(),
            'ip'=>get_client_ip(),
            'user_agent'=>$_SERVER['HTTP_USER_AGENT']
        ];
        if($imgsids){
            $imgsid = array_count_values(explode('_',$imgsids));
            foreach($imgsid as $k=>$v){
                Db::name('file')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
            }
        }
        Db::name('user_post')->insert($userPost);
        $fromid = Db::name('user_post')->getLastInsID();
        if($fromid){
            Db::name('user_member')->where('uid',$userPost['uid'])->where('guid',$this->guid)->update(['bdate'=>time()]);
            session('uid',null);
            $arrtips['msg'] = '添加跟进记录成功！';
            $arrtips['close'] = ['sign'=>true];
            $arrtips['next_action'] = "hiddenFm('myuserFollowsearchSearchForm');getList({sign:'myuserFollowsearch'},'1');";
            return $this->tips($arrtips);
        }else{
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '添加跟进记录失败！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
    }
    private function saveBase64Image($base64_image_content){
        
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){
            //图片后缀
            $type = $result[2];
            //保存位置--图片名
            $image_name=date('YmdHis').str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $image_url = '/uploads/m/'.date('Ymd').'/'.md5($image_name).".".$type;
            if(!is_dir(dirname('.'.$image_url))){
                mkdir(dirname('.'.$image_url));
                chmod(dirname('.'.$image_url), 0777);
            }
            //解码
            $decode=base64_decode(str_replace($result[1], '', $base64_image_content));
            if (file_put_contents('.'.$image_url, $decode)){
                $url=getimagesize(".".$image_url);
                $size= filesize(".".$image_url);
                $FormaData=[
                    'gid'=>$this->gid,
                    'guid'=>$this->guid,
                    'uid'=>input('uid'),
                    'create_time'=>time(),
                    'url'=>$image_url,
                    'name'=>md5($image_name).".".$type,
                    'savename'=>md5($image_name),
                    'savepath'=>date('Ymd'),
                    'ext'=>$type,
                    'mime'=>$url['mime'],
                    'size'=>$size,
                    'md5'=>md5($image_name),
                    'sha1'=>Sha1($image_name),
                    'location'=>'0',
                ];
                $d['id'] = Db::name('file')->insertGetId($FormaData);
                return $d;
            }
        }
    }
    private function setInterval(){
        //时间以csdate,cedate为准
        $searchType = trim(input('searchtype'));
        $interval = intval(input('interval'));
        if($searchType){
            $csdate = trim(input('csdate'));
            $cedate = trim(input('cedate'));
        }else if($interval){
            $cedate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) + 3600 * 24 - 1);
            $csdate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) - $interval * 24 * 3600);
        }else{
            $csdate = trim(input('csdate'));
            $cedate = trim(input('cedate'));
        }
        return ['start'=>strtotime($csdate),'stop'=>strtotime($cedate)];
    }
    private function mkgoodsimgurl($param=[]){
        if(empty($param)){
            return '';
        }else{
            if($param['url']){
                $parseUrl = parse_url($param['url']);
                $path = $parseUrl['path'];
                if(file_exists(substr($path,1))){
                    return $path;
                }else{
                    return 'http://sx.img.ljk.cc'.$path;
                }
            }
        }

    }
    private function GetDistance($lat1, $lng1, $lat2, $lng2, $len_type = 1, $decimal = 2){
        //define('EARTH_RADIUS', 6378.137);//地球半径
        // define('PI', 3.1415926);
        $pi=3.1415926;
        $earth_ranius=6378.137;
        $radLat1 = $lat1 * $pi / 180.0;
        $radLat2 = $lat2 * $pi / 180.0;
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * $pi / 180.0) - ($lng2 * $pi / 180.0);
        $s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1) * cos($radLat2) * pow(sin($b/2),2)));
        $s = $s * $earth_ranius;
        $s = round($s * 1000);
        if ($len_type > 1)
        {
            $s /= 1000;
        }
        return round($s, $decimal);
    }
    private function checkGm($uidx = 0){
        $uid = !$uidx ? (int)trim(input('uid')) : $uidx;
        $guid=$this->guid;
        $gid=$this->gid;
        if(!empty($uid) && !empty($gid) && !empty($guid)){
            $whereP['uid']=$uid;
            $whereP['guid']=$guid;
            $whereP['gid']=$gid;
            $data=Db::name('user_gm')->where($whereP)->find();
            return $data;
        }else{
            return $this->fetch('common/forbidden');
        }
    }
}