<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;
use app\admin\model\UserMember;
use app\admin\model\Group;
use app\common\model\Message;
use app\common\controller\Sign;
use app\common\controller\Sendsms;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use think\Db;
class User extends Base{
    private $depts = [];
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
        $this->initData = [
            'sign'=>$this->sign,
            'requestFunc'=>$this->requestFunc,
            'requestUrl'=>$this->requestUrl,
            'jsName'=>$this->jsName,
        ];
        $this->assign('gid',$this->gid);
        $this->assign('initData',$this->initData);
    }
    public function index(){
    	$shops =Db::name('group_shop')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$shops) $shops='';
    	$this->assign('shops',$shops);
    	$members =Db::name('group_member')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$members) $members='';
    	$this->assign('members',$members);
    	
    	$Ranks =Db::name('user_rank')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$Ranks) $Ranks='';
    	$this->assign('Ranks',$Ranks);
    	
    	$types =Db::name('user_type')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$types) $types='';
    	$this->assign('types',$types);

        $stages =Db::name('user_stage')->where('gid',$this->gid)->where('status',1)->select();
        if(!$stages) $stages='';
        $this->assign('stages',$stages);
        $tag=Db::name('user_stage_tag')->where('gid',$this->gid)->value('title');
        $this->assign('tag_title',$tag);


        $Visits =Db::name('user_visit')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$Visits) $Visits='';
    	$this->assign('Visits',$Visits);

    	
    	if(request()->isAjax()){
    		$param = input('param.');
    		$limit = isset($param['limit'])?$param['limit']:$this->rows;
    		$where1 = [];
    		$where2 = [];
    		$where2['gid'] = session('gid');
    		switch($param['action']){
    			case 'mine':
    				$whereOr = [];
    				$whereOr2 = [];
    				$where1['u.gid'] = session('gid');
    				$where1['u.guid'] = session('guid');
    				$where2['guid'] = session('guid');
    				break;    				
    			case 'company':
    				$whereOr = [];
    				$whereOr2 = [];
    				$where1['u.gid'] = session('gid');
    				if($param['guid']!='all') $where1['u.guid'] = '';
    				//$where2['guid'] = '';
    				break;
    			case 'to':
    				$whereOr1 = [];
    				$where1['p.gid'] = session('gid');
    				$where1['p.guid'] = session('guid');
    				$where2['gid'] = session('gid');
    				$where2['guid'] = session('guid');
    				break;
                case 'sendemail':
                    $this->SendEmail($param['email']);
                    break;
    			default:
    				return json(['code'=>0,'msg'=>'无效操作','type'=>1]);
    		}
    		if(isset($param['keyword']) && !empty($param['keyword'])){
	    		if (isset($param['type']) && !empty($param['type'])) {
	    			$whereOr2[$param['type']] = $whereOr1[$param['type']] = $where1['u.'.$param['type']] = ['like', '%' . $param['keyword'] . '%'];
	    		}
    		}
    		if (isset($param['status'])) {
    			 $where1['u.status'] = $param['status'];
    		}else{
    			$where1['u.status'] =1;
    		}
    		if (isset($param['visitid']) && !empty($param['visitid'])) {
    			$whereOr2['visitid'] = $whereOr1['visitid'] = $where1['u.visitid'] = ['EQ',$param['visitid']];
    		}
    		if (isset($param['tpid']) && !empty($param['tpid'])) {
    			$whereOr2['tpid'] = $whereOr1['tpid'] = $where1['u.tpid'] = ['EQ',$param['tpid']];
    		}
            if (isset($param['stage_id']) && !empty($param['stage_id'])) {
                $whereOr2['stage_id'] = $whereOr1['stage_id'] = $where1['u.stage_id'] = ['EQ',$param['stage_id']];
            }
    		if (isset($param['guid']) && !empty($param['guid'])&& $param['guid']!='all') {
    			$whereOr2['guid'] = $whereOr1['guid'] = $where1['u.guid'] = $param['guid'];
    		}
    		if (isset($param['rankid']) && !empty($param['rankid'])) {
    			$whereOr2['rankid'] = $whereOr1['rankid'] = $where1['u.rankid'] = $param['rankid'];
    		}
    		if (isset($param['shopid']) && !empty($param['shopid'])) {
    			$whereOr2['shopid'] = $whereOr1['shopid'] = $where1['u.shopid'] = $param['shopid'];
    		}
    		if (isset($param['weixin']) && !empty($param['weixin'])) {
                    if(intval(trim($param['weixin'])) === 1){
                        $whereOr2['weixin'] = $whereOr1['weixin'] = $where1['u.weixin'] = ['NEQ',''];
                    }else{
                        $whereOr2['weixin'] = $whereOr1['weixin'] = $where1['u.weixin'] = ['EQ',''];
                    }
    		}
    		if (isset($param['bdate']) && !empty($param['bdate'])) {
    			$where1['u.bdate'] = ['between time',[0,time()-$param['bdate']*84000]];
    		}
    		if (isset($param['odate']) && !empty($param['odate'])) {
    			$where1['u.odate'] = ['between time',[0,time()-$param['odate']*84000]];
    		}
    		$user = new UserMember();
            $res='';
            $whereOr=[];
    		if($param['action'] === 'mine' || $param['action'] === 'company'){
    			$res = $user->getUsersByWherePage($where1,$whereOr,$limit);
    		}else{
    		    return false;
            }
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
    		$data = $res->items();
    		$status = config('user_status');
    		foreach($data as $key=>$vo){
                if($param['keyword']!='' && $param['type']=='summary'){
                    $vo['summary']=str_ireplace($param['keyword'], "<strong style='color:#f00;'>".$param['keyword']."</strong>", $vo['summary']);
                }
    			//处理跟进人是否是本人的问题
    			if($param['action'] === 'to' && $vo['guid']===session('guid')){
    				$data[$key]['ismine'] = 1;
    			}else{
    				$data[$key]['ismine'] = 0;
    			}
    			$data[$key]['host_realname']=Db::name('group_member')->where('uid',$vo['guid'])->value('realname');
    			$data[$key]['rank']=Db::name('user_rank')->where('id',$vo['rankid'])->value('rank_name');
    			$data[$key]['type_title']=Db::name('user_type')->where('id',$vo['tpid'])->value('title');
//    			$data[$key]['stage_title']=Db::name('user_stage')->where('id',$vo['stage_id'])->value('title');
    			//$data[$key]['username']=Db::name('user')->where('uid',$vo['uid'])->value('username');
    			$data[$key]['weixin']=Db::name('user_open')->where('uid',$vo['uid'])->value('weixin');
    			if($data[$key]['lastvisit']) $data[$key]['lastvisit'] = date('Y-m-d H:i:s', $vo['lastvisit']);
//    			$data[$key]['status'] = $status[$vo['status']];
    			if($data[$key]['bdate']==0&&$data[$key]['regtime']!=0){
                     Db::name('user_member')->where('uid',$vo['uid'])->update(['bdate'=>$data[$key]['regtime']]);
    			}
	    		$bdate=$data[$key]['bdate']?$data[$key]['bdate']:$data[$key]['regtime'];
	            if($bdate){
		            $bftime=round((time()-$bdate)/84000);
		            $data[$key]['bftime'] = $bftime;
	            }else{
	            	$data[$key]['bftime'] ='wuxiao';
	            }
	            $odate=$data[$key]['odate'];
	            if($odate){
	            	$otime=round((time()-$odate)/84000);
	            	$data[$key]['otime'] = $otime;
	            }else{
	            	$data[$key]['otime'] ='wuxiao';
	            }
    		}
    		$return['data'] = $data;
    		$return['code'] =1;
    		$return['msg'] ='数据加载成功！';
    		if(count($res)==0){
                    $return['code'] =0;
                    $return['msg'] ='没有数据！';
    		}
    		$this->assign('userslist',$data);
            $this->assign('pageInfo',$pageInfo);
            return $this->fetch('/user/ajax/user_index');
    	}
        return $this->fetch('/user/user_index');
    }
    public function addpost(){
    	if(!!($uid = request()->post('uid'))){
    		session('post_uid',$uid);
    	}
    	if(request()->isAjax() && input('action') === 'add'){
    		$post = request()->post();
    		$content = $post['content'];
    	    $uploadImg = controller('uploadImg','event');
    	    $file = request()->file('thumb');
    	  //  print_r($file);
            if($file){
                $imgsid = $uploadImg->index($file);
            }else{
                $imgsid['id'] = '';
            }
    		if(!$content){
    			return json(['code' => 1, 'msg' => '请添加跟进记录内容']);
    		}
    		$userPost = [
    		'gid'=>session('gid'),
    		'content'=>$content,
    		'uid'=>session('post_uid'),
    		'guid'=>session('guid'),
    		'imgsid'=>$imgsid['id'],
    		'adate'=>time(),
    		'ip'=>get_client_ip(),
    		'user_agent'=>$_SERVER['HTTP_USER_AGENT']
    		];
    		if($imgsid['id']){
    		$imgsid = array_count_values(explode('_',$imgsid['id']));
    		foreach($imgsid as $k=>$v){
    			Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
    		}
    		}
    		Db::name('user_post')->insert($userPost);
    		$fromid = Db::name('user_post')->getLastInsID();
    		if($fromid){
    			Db::name('user_member')->where('uid',$userPost['uid'])->update(['bdate'=>time()]);
    			session('post_uid',null);
    			$arrtips['msg'] = '添加跟进记录成功！';
    			//$arrtips['refresh'] = ['sign'=>true,'time'=>1000];
    			return $this->tips($arrtips);
    		}else{
    			return json(['code' => 1, 'msg' => '添加跟进记录失败']);
    			$arrtips['msg'] = '添加跟进记录失败！';
    			$arrtips['refresh'] = ['sign'=>false];
    			return $this->tips($arrtips);
    		}
    	}
    }
    public function editpost(){
    	if(request()->isAjax() && request()->isPost()){  
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch ($action){
                case 'editpost':return $this->doEditPost($input);
                case 'getonepost':return $this->getOnePost($input);
            }
        }else{
            return json(['code' => -1,'msg' => '']);
        }
    }
    private function getOnePost($input = []){
        $postid = isset($input['id']) ? (int)$input['id'] : 0;
        $whereUp['gid'] = $this->gid;
        $whereUp['id'] = $postid;
        $onePost = Db::name('user_post')->where($whereUp)->find();
        return json($onePost);
    }
    private function doEditPost($input = []){
        $postid = isset($input['id']) ? (int)$input['id'] : 0;
        $sign = isset($input['sign']) ? (int)$input['sign'] : 0;
        $content = isset($input['content']) ? htmlspecialchars(strip_tags($input['content'])) : '';
        if(!$content){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '必须输入跟进内容！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $whereUp['gid'] = $this->gid;
        $whereUp['id'] = $postid;
        if(!$postid || !Db::name('user_post')->where($whereUp)->count()){
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '请选择需要修改的跟进信息！';
            $arrtips['close'] = ['sign'=>true];
            return $this->tips($arrtips);
        }
        $gmr = Db::name('group_member')->field('username,realname')->where(['gid'=>$this->gid, 'uid'=>$this->guid])->find();
        $uUp['content'] = $content . "{[%最后修改人信息-账号:{$gmr['username']},员工名:{$gmr['realname']};修改时间：". date('Y-m-d H:i:s') ."%]}";
        Db::name('user_post')->where($whereUp)->update($uUp);
        $arrtips['msg'] = '跟进记录修改成功！';
        $arrtips['close'] = ['sign'=>true];
        if($sign === 0){
            $arrtips['next_action'] = "getList({u:'/admin/user/followsearch',sign:'userFollowsearch'});";
        }else{
            $arrtips['next_action'] = "hiddenFm('myuserFollowsearchSearchForm');getList({sign:'myuserFollowsearch'});";
        }
        return $this->tips($arrtips);
    }
    /**
     * 用户地图
     */
    public function ditu(){
    	$code=0; //是否清除到原加载点
    	if(request()->isAjax()){
    		$param = input('param.');
    		$limit = 200;
    		$where1 = [];
    		$where2 = [];
    		$where2['gid'] = session('gid');
    		switch($param['action']){
    			case 'mine':
    				$whereOr = [];
    				$whereOr2 = [];
    				$where1['u.gid'] = session('gid');
    				$where1['u.guid'] = session('guid');
    				$where2['guid'] = session('guid');
    				break;
    			case 'company':
    				$whereOr = [];
    				$whereOr2 = [];
    				$where1['u.gid'] = session('gid');
    				if($param['guid']!='all') $where1['u.guid'] = '';
    				//$where2['guid'] = '';
    				break;
    			case 'to':
    				$whereOr1 = [];
    				$where1['p.gid'] = session('gid');
    				$where1['p.guid'] = session('guid');
    				$where2['gid'] = session('gid');
    				$where2['guid'] = session('guid');
    				break;
    			default:
    				return json(['code'=>0,'msg'=>'无效操作','type'=>1]);
    		}
    		
    		
    		if(isset($param['keyword']) && !empty($param['keyword'])){
    			if (isset($param['type']) && !empty($param['type'])) {
    				$whereOr2[$param['type']] = $whereOr1[$param['type']] = $where1['u.'.$param['type']] = ['like', '%' . $param['keyword'] . '%'];
    			}
    			$code=1;
    		}
    		if (isset($param['status'])) {
    			$where1['u.status'] = $param['status'];
    		}else{
    			$where1['u.status'] =1;
    		}
    		if (isset($param['visitid']) && !empty($param['visitid'])) {
    			$whereOr2['visitid'] = $whereOr1['visitid'] = $where1['u.visitid'] = ['EQ',$param['visitid']];
    			$code=1;
    		}
    		if (isset($param['tpid']) && !empty($param['tpid'])) {
    			$whereOr2['tpid'] = $whereOr1['tpid'] = $where1['u.tpid'] = ['EQ',$param['tpid']];
    			$code=1;
    		}
    		if (isset($param['guid']) && !empty($param['guid'])&& $param['guid']!='all') {
    			$whereOr2['guid'] = $whereOr1['guid'] = $where1['u.guid'] = $param['guid'];
    			$code=1;
    		}
    		if (isset($param['rankid']) && !empty($param['rankid'])) {
    			$whereOr2['rankid'] = $whereOr1['rankid'] = $where1['u.rankid'] = $param['rankid'];
    			$code=1;
    		}
    		if (isset($param['weixin']) && !empty($param['weixin'])) {
    			if(intval(trim($param['weixin'])) === 1){
    				$whereOr2['weixin'] = $whereOr1['weixin'] = $where1['u.weixin'] = ['NEQ',''];
    			}else{
    				$whereOr2['weixin'] = $whereOr1['weixin'] = $where1['u.weixin'] = ['EQ',''];
    			}
    			$code=1;
    		}
    		if (isset($param['bdate']) && !empty($param['bdate'])) {
    			$where1['u.bdate'] = ['between time',[1,time()-$param['bdate']*84000]];
    			$code=1;
    		}
    		if (isset($param['odate']) && !empty($param['odate'])) {
    			$where1['u.odate'] = ['between time',[1,time()-$param['odate']*84000]];
    			$code=1;
    		}
    		if($code!=1){
    			$lbs['lat']=input('lat');
    			$lbs['lng']=input('lng');
    			if(!$lbs['lat']||!$lbs['lng']){
    				$lbs['lat']=session('groupinfo')['x'];
    				$lbs['lng']=session('groupinfo')['y'];
    			}
    			$xy=getbaidugps($lbs['lat'],$lbs['lng'],true);
    			$squares=GetSquarePoint($xy[1],$xy[0],5); //5000米内的的客户
    			$where1['u.x'] = ['BETWEEN',[$squares['right-bottom']['lat'],$squares['left-top']['lat']]];
    			$where1['u.y'] = ['BETWEEN',[$squares['left-top']['lng'],$squares['right-bottom']['lng']]];
    			$code=0;
    		}
    		$user = new UserMember();
    		//print_r($where1);exit;
    		if($param['action'] === 'mine' || $param['action'] === 'company'){
    			$res = $user->getUsersByWherePage($where1,$whereOr,$limit);
    		}
    	
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
    		$userList = $res->items();
    		foreach ($userList as $key=>$userinfo)
    		{
    			$bd['uid']= $userinfo['uid'];
    			$bd['title']= $userinfo['realname'];
    			$bd['point']= $userinfo['y'].'|'.$userinfo['x'];
    			$bd['icon']= '{w:21,h:21,l:0,t:0,x:6,lb:5}';
    			$basedata[]=$bd;
		      //$basedata.='{uid:'. $userinfo['uid'] .',title:"'.$userinfo['realname'].'",content:"联系人：'.$userinfo['contact'].'<br>电话：'.$userinfo['mobile'].'  '.$userinfo['phone'].$useraddress.$usertype.$userrank.'",point:"'.$userinfo['y'].'|'.$userinfo['x'].'",isOpen:1,icon:{w:21,h:21,l:0,t:0,x:6,lb:5}},';
   		    }
	    	//$basedata=substr($basedata,0,strlen($basedata)-1);
    	    return json(['code'=>$code,'data'=>$basedata]);
    	}
    	$shops =Db::name('group_shop')->where('gid',$this->gid)->where('status',1)->select();
    	if(!$shops) $shops='';
    	$this->assign('shops',$shops);
    	
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
    	/*
    	//取得数据
    	$conditionstr = trim(input('conditions'));
    	$code = trim(input('code'));
    	if(!empty($code) && strlen($conditionstr) > 0 && md5($conditionstr.$securecode)==$code)
    	{
    		$tmpconditions = explode('||', $conditionstr);
    		$conditions['type'] = trim($tmpconditions[0]);
    		$conditions['keyword'] = trim($tmpconditions[1]);
    		$conditions['shopid'] = intval($tmpconditions[2]);
    		$conditions['guid'] = trim($tmpconditions[3]);
    		$conditions['tpid'] = trim($tmpconditions[4]);
    		$conditions['visitid'] = trim($tmpconditions[5]);
    		$conditions['lastvisit'] = intval($tmpconditions[6]);
    		$conditions['rankid'] = intval($tmpconditions[7]);
    		$conditions['trust'] = $tmpconditions[8];
    	}
    	else
    	{
    		$conditions['type'] = trim(input('type'));
    		$conditions['keyword'] = trim(input('keyword'));
    		$conditions['shopid'] = intval(input('shopid'));
    		$conditions['guid'] = input('guid');
    		$conditions['tpid'] = intval(input('tpid'));
    		$conditions['visitid'] = intval(input('visitid'));
    		$conditions['lastvisit'] = intval(input('lastvisit'));
    		$conditions['rankid'] = intval(input('rankid'));
    		$conditions['trust'] =  input('trust');
    		$conditions['x'] =  input('x');
    	}
    	$whereArr = array();
    	if($conditions['csdate'])
    	{
    		$whereArr[] = array('regtime',strtotime($conditions['csdate']),'>=');
    	}
    	if($conditions['cedate']){
    		$whereArr[] = array('regtime',strtotime($conditions['cedate']),'<');
    	}
    	$whereArr[] = array('gid', $this->groupid);
    	$whereArr[] = array('x','','<>');
    	!empty($conditions['keyword']) && $whereArr[] = array($conditions['type'], $conditions['keyword'], 'LIKE');
    	if($conditions['guid']=='') $conditions['guid']='all';
    	if($conditions['guid']!='all') $whereArr[] = array('guid', $conditions['guid']);
    	if($conditions['trust']=='1'||$conditions['trust']=='0') $whereArr[] = array('trust', $conditions['trust']);
    	!empty($conditions['rankid']) && $whereArr[] = array('rankid', $conditions['rankid']);
    	!empty($conditions['visitid']) && $whereArr[] = array('visitid', $conditions['visitid']);
    	!empty($conditions['shopid']) && $whereArr[] = array('shopid', $conditions['shopid']);
    	!empty($conditions['tpid']) && $whereArr[] = array('tpid', $conditions['tpid']);
    	switch ($conditions['lastvisit'])
    	{
    		case 1:
    			$whereArr[] = array('lastvisit', strtotime('-7 day'), '>');
    			break;
    		case 2:
    			$whereArr[] = array('lastvisit', strtotime('-14 day'), '>');
    			break;
    		case 3:
    			$whereArr[] = array('lastvisit', strtotime('-30 day'), '>');
    			break;
    		case 4:
    			$whereArr[] = array('lastvisit', strtotime('-180 day'), '>');
    			break;
    		case 5:
    			$whereArr[] = array('lastvisit', strtotime('-365 day'), '>');
    			break;
    		case 6:
    			$whereArr[] = array('lastvisit', strtotime('-365 day'), '<=');
    			break;
    		default :
    			break;
    	}
    	//用户数量
    	$userCount = $userModel->getUserCount($whereArr);
    	$this->assign('userscount', $userCount);
    	//分页
    	$perpage = 20;
    	$curpage = input('page') ? intval(input('page')) : 1;
    	$totalPage = (ceil($GoodsCount / $perpage)>=1)?ceil($GoodsCount / $perpage) : 1;
    	$curpage = ($curpage > $totalPage) ? $totalPage : $curpage;
    	$conditionstr = implode('||', $conditions);
    	$md5str = md5($conditionstr.$securecode);
    	$mpurl = '/x/admin/user/ditu/conditions/'.$conditionstr.'/code/'.$md5str.'/';
    	$multipage = $this->multipage($userCount, $perpage, $curpage, $mpurl);
    	$this->assign('multipage', $multipage);
    	//$whereArr[] = array('trust', 1);
    	//接入列表
    	$shops = $clientModel->queryAll(array(array('gid', $this->groupid)), '');
    	//用户列表
    	$userList = $userModel->getUserList($whereArr, 'uid desc', array($perpage, $perpage*($curpage-1)));
    	//print_r($whereArr);
    	$userList = $userModel->getUserList($whereArr, 'uid desc');
    	foreach ($userList as $key=>$userinfo)
    	{
    		if($userinfo['type']['title']) $usertype='<br>客户类型：'.$userinfo['type']['title'];
    		if($userinfo['address']) $useraddress='<br>地址：'.$userinfo['address'];
    		if($userinfo['rank']) $userrank='<br>客户等级：'.$userinfo['rank'];
    		if($userinfo['x'])
    		{$basedata.='{uid:'. $userinfo['uid'] .',title:"'.$userinfo['realname'].'",content:"联系人：'.$userinfo['contact'].'<br>电话：'.$userinfo['mobile'].'  '.$userinfo['phone'].$useraddress.$usertype.$userrank.'<br>跟进人：'.$userinfo['host']['realname'].$userinfo['host']['mobile'].'",point:"'.$userinfo['y'].'|'.$userinfo['x'].'",isOpen:1,icon:{w:21,h:21,l:0,t:0,x:6,lb:5}},';
    		$usertype='';
    		$useraddress='';
    		$userrank='';
    		//			$zhexian.=' new BMap.Point('.$userinfo['y'].','.$userinfo['x'].'),';
    		}
    	}
    	$basedata=substr($basedata,0,strlen($basedata)-1);
    	$zhexian=substr($zhexian,0,strlen($zhexian)-1);
    
    	$this->assign('users', $userList);
    	//取得客户等级列表
    	$Rankslist = $RankModel->getRankList(array(array('gid', $this->groupid)), 'displayorder asc');
    	foreach ($Rankslist as $value)
    		$Ranks[$value['id']] = array('id'=>$value['id'], 'rank_name'=>$value['rank_name']);
    	$this->assign('Ranks', $Ranks);
    	//跟进路线列表
    	$Visitslist = $VisitModel->getVisitList(array(array('gid', $this->groupid)), 'displayorder asc');
    	foreach ($Visitslist as $value)
    		$Visits[$value['id']] = array('id'=>$value['id'], 'title'=>$value['title']);
    	$this->assign('Visits', $Visits);
    
    	//员工列表
    	$memberList = $memberModel->getMemberList(array(array('gid',$this->groupid), array('useful', '1', '<>')), 'uid asc','');
    	$this->assign('members', $memberList);
    	//客户类型列表
    	$typeList = $typeModel->getTypeList(array(array('gid', $this->groupid)), 'displayorder asc','');
    	$this->assign('types', $typeList);
    
    	$groupinfo = $groupModel->getGroupInfoByGid($this->groupid);
    	//查询条件
    	$this->assign('basedata', $basedata);
    	$this->assign('zhexian', $zhexian);
    	$this->assign('groupinfo', $groupinfo);
    	$this->assign('shops', $shops);
    	$this->assign('mapSearch',0);
    	$this->assign('conditions', $conditions);
    	*/
    	return $this->fetch('/user/user_ditu');
    }
    /**
     * 批量修改用户
     */
    public function plupdate(){
    	$t=input('t');
    	if($t=='gxuser'){
    		return $this->gxuser();
    		exit;
    	}
    	$updateids = is_array(input('updateids/a')) ? input('updateids/a') : '';
    	$conditions['guid'] = input('guid');
    	$conditions['tpid'] = input('tpid');
    	$conditions['stage_id'] = input('stage_id');
    	$conditions['rankid'] = input('rankid');
    	$conditions['trust'] = input('trust');
    	$conditions['status'] = input('status');
    	if($conditions['guid']!="") $uinfo['guid'] = $conditions['guid'];
    	if($conditions['tpid']!="") $uinfo['tpid'] = $conditions['tpid'];
    	if($conditions['stage_id']!="") $uinfo['stage_id'] = $conditions['stage_id'];
    	if($conditions['rankid']!="") $uinfo['rankid'] = $conditions['rankid'];
    	if($conditions['trust']!="") $uinfo['trust'] = $conditions['trust'];
    	if($conditions['status']!="") $uinfo['status'] = $conditions['status'];
    	if($updateids && isset($uinfo)){
    		foreach ($updateids as $id)
    		{
    			$where['uid']=$id;
    			$where['gid']=session('gid');
    			//去更新客户订单的跟进人
    			if(isset($uinfo['guid'])){
    				$old_guid=Db::name('user_member')->where($where)->value('guid');
    				$where['guid']=$old_guid;
    				Db::name('user_gm')->where($where)->delete();
    				unset($where['guid']);
    				$this->dgjr($id,$uinfo['guid']);
    				$orderinfo['guid']=$id;
    				Db::name('order')->where($where)->update($orderinfo);
    			}
    			Db::name('user_member')->where($where)->update($uinfo);
    			unset($where);
    		}
    		$arrtips['msg'] = '批量修改客户信息成功！';
    		$arrtips['refresh'] = ['sign'=>true,'time'=>1000];
            $arrtips['next_action'] ="getList({sign:'userIndex'});";
    		return $this->tips($arrtips);
    	}else{
    		$arrtips['msg'] = '批量修改客户信息失败！请选择要更新信息的客户以及需要更新的项目';
            $arrtips['next_action'] ="getList({sign:'userIndex'});";
    		return $this->tips($arrtips);
    	}
    }
    //共享转移所有客户
    private function gxuser(){
    	$group_member=input('param.');
    	if(isset($group_member['gx_guid1'])&&!$group_member['gx_guid1']){
    		$arrtips['msg'] = '请选择员工A！';
    		return $this->tips($arrtips,false);
    	}
    	if(isset($group_member['gxtype'])&&!$group_member['gxtype']){
    		$arrtips['msg'] = '请选择批量操作类型！';
    		return $this->tips($arrtips,false);
    	}
    	if(isset($group_member['gx_guid2'])&&!$group_member['gx_guid2']){
    		$arrtips['msg'] = '请选择员工B！';
    		return $this->tips($arrtips,false);
    	}    	
    	if($group_member['gx_guid1']==$group_member['gx_guid2']){
    		$arrtips['msg'] = '员工A和员工B相同，请选择不同员工！';
    		return $this->tips($arrtips,false);
    	}
    	$map['gid']=$this->gid;
    	$i=0;
    	if($group_member['gxtype']==1){
    		//共享    		
    		$map['guid']=$group_member['gx_guid1'];
    		$ulist=Db::name('user_member')->where($map)->select();
    		foreach ($ulist as $u){
    			$data['gid']=$u['gid'];
    			$data['uid']=$u['uid'];
    			$data['guid']=$group_member['gx_guid2'];
    			$id=Db::name('user_gm')->where($data)->value('id');
    			if(!$id){
    				$data['adate']=time();
    				$guid=Db::name('user_gm')->insert($data);
    				$i++;
    			}
    			unset($data);
    		}
    		$arrtips['msg'] = '共享成功'.$i.'个客户！';
    		return $this->tips($arrtips,false);
    		
    	}elseif($group_member['gxtype']==2){
    		//取消共享
    		$map['guid']=$group_member['gx_guid1'];
    		$ulist=Db::name('user_member')->where($map)->select();
    		foreach ($ulist as $u){
    			$data['gid']=$u['gid'];
    			$data['uid']=$u['uid'];
    			$data['guid']=$group_member['gx_guid2'];
    			$id=Db::name('user_gm')->where($data)->delete();
    			$i++;
    			unset($data);
    		}
    		$arrtips['msg'] = '取消共享成功'.$i.'个客户！';
    		return $this->tips($arrtips,false);
    		
    	}elseif($group_member['gxtype']==3){
    		//转移  	
    		$map['gid']=$this->gid;
    		$map['guid']=$group_member['gx_guid1'];
    		$guid2=$group_member['gx_guid2'];
    		Db::startTrans();
            try{  			
    			$ulist=Db::name('user_member')->where($map)->select();
    			Db::name('user_gm')->where($map)->delete();
	    		foreach ($ulist as $u){
	    			Db::name('user_member')->where('uid',$u['uid'])->update(['guid'=>$guid2]);
	    			$data['gid']=$u['gid'];
	    			$data['uid']=$u['uid'];
	    			$data['guid']=$guid2;
	    			$id=Db::name('user_gm')->where($data)->value('id');
	    			if(!$id){
	    				$data['adate']=time();
	    				$guid=Db::name('user_gm')->insert($data);
	    			}
	    			$i++;
	    			unset($data);
	    		}
			     // 提交事务
			    Db::commit();    
			    $arrtips['msg'] = '转移成功'.$i.'个客户！';
			    return $this->tips($arrtips,false);
			} catch (\Exception $e) {
			    // 回滚事务
			    Db::rollback();
			    $arrtips['msg'] = '操作失败请联系管理员！';
			    return $this->tips($arrtips,false);
			}
    		
    	}
    }
    //客户资料合并
    private function user_hebing(){
    	
    }
    /**
     * 客户详情
     */
    public function detail(){
        $uid = input('uid');
        $action = input('action');
        $gid = session('gid');
        $one = Db::name('user_member')->where('uid',$uid)->where('gid',$gid)->find();
        $one['hasmc'] = Db::name('mcard_um')->where('gid',$this->gid)->where('uid',$uid)->count();
        //判断是否有修改该客户的权限
        if(empty($one)){
            $arrtips['msg'] = '没有编辑该客户的权限！';
            return $this->tips($arrtips);
        }
        if(!empty($one['mtz'])){
            $whereF['gid'] = $this->gid;
            $whereF['id'] = $one['mtz'];
            $mtzurl = Db::name('file')->where($whereF)->find();
            if(!!$mtzurl){
                $one['mtzimg'] = mkgoodsimgurl($mtzurl);
            }
        }
        if($action=='updatepw'){
            return $this->passwd();
        }
        if(trim(input('action'))== 'showgmstruct'){
            return $this->gmStructure();
        }
        if($action=='edit'){
            $param=input();
            if($param['xy']){
                $xy=explode(',',$param['xy']);
                $param['y']=$xy[0];
                $param['x']=$xy[1];
            }else{
                $param['y']=0;
                $param['x']=0;
            }
            //$param['guids']=isset($param['guids'])?$param['guids']:[];
            //清除跟进人
            Db::name('user_gm')->where('gid',$gid)->where('uid',$uid)->delete();
            if(!empty($param['guids'])){
                foreach ($param['guids'] as $guid){
                    $this->dgjr($uid, $guid);
                }
                unset($param['guids']);
                unset($param['guidname']);
            }
            if($param['guid']!=0){
                $this->dgjr($uid,$param['guid']);
            }
            $whereUm = [
                'gid' => $this->gid,
                'mobile' => trim($param['mobile']),
                'uid' => ['NEQ',$uid]
            ];
            if(Db::name('user_member')->where($whereUm)->count()){
                // 验证失败 输出错误信息
                $arrtips['msg'] = '手机号码已经存在，请检查客户信息是否重复，可以修改手机号码后重新提交。';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }else{
                $param['mobile'] = trim($param['mobile']);
            }
            unset($param['uid']);
            unset($param['action']);
            unset($param['xy']);
            if($param['areas']){
                foreach ($param['areas'] as $area){
                    $ar=explode(":",$area);
                    $param['areaids'].=$ar[0].',';
                    $param['areaname'].=$ar[1];
                }
                unset($param['areas']);
                if($param['areaids']){
                    $param['areaids']=trim($param['areaids'],',');
                }
            }
            //门头照
            $file = request()->file('mtzimg');
            if(!!$file){
                $uploadImg = controller('uploadImg','event');
                $path='/uploads/mtz';
                $imginfo = $uploadImg->index($file,$path);
                $imgid =$imginfo['id'];
                if($imgid === -1){
                    //eturn json(['code' => -1, 'msg'=>'请上传图片文件！']);
                }else if($imgid === -2){
                    //return json(['code' => -1, 'msg'=>'系统繁忙，请稍候再试！']);
                }else if($imgid === -3){
                    //return json(['code' => -1, 'msg'=>'上传文件不得超过1M！']);
                }else{
                    $thumburl = $imginfo['url'];
                }
                $param['mtz'] = $imgid;
            }else if(isset($param['mtz'])){
                $param['mtz'] = intval(trim($param['mtz']));
            }
            if(isset($param['mtzimg'])){
                unset($param['mtzimg']);
            }
            $result =  Db::name('user_member')->where('uid',$uid)->update($param);
            if(false === $result){
            	// 验证失败 输出错误信息
            	$arrtips['msg'] = '编辑用户失败！';
            }else{
            	$arrtips['msg'] = '编辑用户成功！';
            }
            $arrtips['msg'] = '编辑用户成功！';
            $arrtips['close'] = ['sign' => false];
            $arrtips['next_action'] = "setTimeout(function(){window.location.reload();},1000);";
            $arrtips['autoclose_sign'] = true;
            return $this->tips($arrtips);
        }
        if(!$uid){
            $uid = session('uid');
        }else{
            session('uid',$uid);
        }
        $type = Db::name('user_type')->field('id,status,title')->where('gid',$gid)->where('status',1)->select();
        $visit = Db::name('user_visit')->field('id,status,title')->where('gid',$gid)->select();
        if($one['guid']){
                $one['host_realname']=Db::name('group_member')->where('uid',$one['guid'])->value('realname');
        }else{
                $one['host_realname']='';
        }
        //处理$type
        foreach($one as $k=>$v){
            if(!$one[$k]){
                $one[$k] = '';
            }
        }
        foreach($type as $k=>$v){
            if(!$type[$k]){
                $type[$k] = '';
            }
        }
        $one['type'] = $type;
        $one['visit'] = $visit;
        $guids=Db::name('user_gm')->where('gid',$this->gid)->where('uid',$uid)->select();
        foreach ($guids as $guid){
            $guid['realname']=Db::name('group_member')->where('uid',$guid['guid'])->value('realname');
            $one['guids'][]=$guid;
        }
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
        
        $followCount =Db::name('user_post')->where('gid',$this->gid)->where('uid',$uid)->count();
        
        $csdate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) - 7 * 3600 * 24);
        $cedate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) + 3600 * 24 - 1);
        $this->assign('csdate',$csdate);
        $this->assign('cedate',$cedate);
        $this->assign('followCount',$followCount);
        $this->assign('user',$one);
        $this->assign('ginfo',session('groupinfo'));
        return $this->fetch('/user/user_detail');
    }
    /**
     * 客户详情
     */
    public function add(){
    	$action = input('action');
    	if($action== 'showgmstruct'){
    		return $this->gmStructure();
    	}
    	if($action=='add'){
            $param=input();
            if($param['xy']){
                $xy=explode(',',$param['xy']);
                $param['y']=$xy[0];
                $param['x']=$xy[1];
            }else{
                $param['y']=0;
                $param['x']=0;
            }
            if(Db::name('user_member')->where('gid',$this->gid)->where('mobile',trim($param['mobile']))->count()){
                // 验证失败 输出错误信息
                $arrtips['msg'] = '手机号码已经存在，请检查客户信息是否重复，可以修改手机号码后重新提交。';
                return $this->tips($arrtips);
            }
            unset($param['uid']);
            unset($param['action']);
            unset($param['xy']);
            if(!empty($param['guids'])){
                $guids=$param['guids'];
                $guidname=$param['guidname'];
                unset($param['guids']);
                unset($param['guidname']);
            }
            $param['gid']=$this->gid;
            $param['regtime']=time();
            $param['regip']=get_client_ip();
            $param['mobile'] = trim($param['mobile']);
            $uid =  Db::name('user_member')->insertGetId($param);
            if($uid){
                $arrtips['msg'] = '新增客户成功！';
                if($guids){
                    foreach ($guids as $guid){
                        $this->dgjr($uid, $guid);
                    }
                }
                if($param['guid']!=0){
                    $this->dgjr($uid,$param['guid']);
                }
            }else{
                $arrtips['msg'] = '新增客户失败！';
            }
            return $this->tips($arrtips);
    	}
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
    	return $this->fetch('/user/user_add');
    }
    //设置多跟进人
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
    //组织结构
    private function gmStructure(){
    	$this->getdept();
    	$depts = array_reverse($this->depts);
    	foreach($depts as $k=>$v){
    		$depts[$k]['gmmember'] = Db::name('group_member')->field('uid,realname')->where('gid',$this->gid)->where('deptid',$v['id'])->select();
    		$depts[$k]['l'] = $v['l'] * 20;
    		$depts[$k]['x'] = ($v['l'] + 1) * 20;
    	}
    	return json(['code'=>1,'data'=>  $depts]);
    }
   //组织结构+人员
    private function getdept($pid=0,$l=0){
    	$pdept = Db::name('group_dept')->where('pid',$pid)->where('gid',$this->gid)->order('sort desc')->select();
    	if(!empty($pdept)){
    		foreach($pdept as $k=>$dept){
    			$pid = $dept['id'];
    			if(Db::name('group_member')->where('gid',$this->gid)->where('deptid',$dept['id'])->count()){
    				$dept['m'] = 1;
    			}else{
    				$dept['m'] = 0;
    			}
    			if($this->getdept($pid,$l+1)){
    				$dept['c'] = 1;
    			}else{
    				$dept['c'] = 0;
    			}
    			$dept['l'] = $l;
    			$this->depts[] = $dept;
    		}
    		return true;
    	}else{
    		return false;
    	}
    }
    /**
     * 认证用户
     */
    public function shield(){
    	$trust = intval(input('trust'));
    	$map['uid'] = intval(input('uid'));
    	$map['gid'] =session('gid');
    	$s=Db::name('user_member')->where($map)->update(['trust'=>$trust]);
    	if($s){
    		$arrtips['msg'] = '操作成功！';
    		return $this->tips($arrtips);
    	}else{
    		$arrtips['msg'] = '操作失败！';
    		return $this->tips($arrtips);
    	}
    }
    /**
     * 修改客户密码
     */
    private function passwd(){
    	$data['uid']=intval(input('uid'));
    	if(!$data['uid']){
    		$arrtips['refresh_sign']=false;
    		$arrtips['msg'] = '请重新选择客户！';
    		return $this->tips($arrtips);
    	}
    	$data['username']=input('username');
    	if(!$data['username']){
    		$arrtips['refresh_sign']=false;
    		$arrtips['msg'] = '请输入用户名！';
    		return $this->tips($arrtips);
    	}
    	$data['password']=input('password');
    	if(!$data['password']){
    		$arrtips['refresh_sign']=false;
    		$arrtips['msg'] = '请输入密码！';
    		return $this->tips($arrtips);
    	}
    	$data['salt']=substr(md5(md5($data['password'])),0,6);
    	$map['username'] =$data['username'];
    	$map['gid'] = $data['gid'] =session('gid');
    	$map['uid'] =['neq',$data['uid']];
    	$useruid=Db::name('user')->where($map)->value('uid');
    	if(!$useruid){
    		$m['uid'] = $data['uid'];
    		$m['gid'] = session('gid');
	    	$userid=Db::name('user')->where($m)->value('id');
	    	$data['password']=md5(md5($data['password']).$data['salt']);
	    	if(!$userid){
	    		$data['adate']=time();
	    		$st=Db::name('user')->insert($data);
	    	}else{
	    		$st=Db::name('user')->where('id',$userid)->update($data);
	    	}
	    	if($st){
	    		$arrtips['msg'] = '修改密码成功！';
	    		return $this->tips($arrtips);
	    	}else{
	    		$arrtips['refresh_sign']=false;
	    		$arrtips['msg'] = '修改密码失败！';
	    		return $this->tips($arrtips);
	    	}
    	}else{  
    			$arrtips['refresh_sign']=false;
	    		$arrtips['msg'] = '用户名已经存在！';
	    		return $this->tips($arrtips);
	    	}
    }
    //跟进记录
    public function followsearch(){
    	$uid=intval(input('uid'));
    	$followArr = array();
    	if($uid){
	    	$followArr['uid'] =$uid;
	    	$followArr['gid'] =$this->gid;
	    	//设置开始结束时间
	    	$interval = $this->setInterval();
	    	$limit = input('limit')?input('limit'):20;
	    	//$limit =2;
	    	//留言列表
	    	$res =Db::name('user_post')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->order('adate desc')->paginate($limit);;
	    	//处理图片
	    	$imgsurl = [];
	    	$imgsurlu = [];
	    	$url=$_SERVER['HTTP_HOST'];
	    	$urls=explode('.',$url);
	    	if($urls[1]=='b') $url='http://'.$urls[0].'.'.$urls[1].'.m.'.$urls[2].'.'.$urls[3];
	    	else $url='http://'.$urls[0].'.m.'.$urls[1].'.'.$urls[2];
	    	
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
	    	//print_r($followList); exit;
	    	foreach($followList as $k=>$follow){
	    		$followList[$k]['thumb'] = explode('||',$followList[$k]['thumb']);
	    		if(isset($follow['guid'])){
	    			$followList[$k]['host_realname'] = Db::name('group_member')->where('uid',$follow['guid'])->value('realname');
	    		}else{
	    			$followList[$k]['host_realname'] ='';
	    		}
	    		if(isset($follow['imgsid'])){
	    			$imgsid = explode('_',$follow['imgsid']);
	    			$unique  = array_values(array_unique($imgsid));
	    			for($i=0;$i<count($unique);$i++){
	    				$urll=Db::name('file')->where('id',$unique[$i])->value('url');
	    				//if($urll) $imgsurlu[$unique[$i]] =$url.$urll;
	    				if($urll) $imgsurlu[$unique[$i]] = mkurl(['url'=>$urll]);
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
	    			//$user['lbsid'] =$memberlbs['id'];
	    			 $followList[$k]['x'] =$memberlbs['x'];
	    			 $followList[$k]['y'] =$memberlbs['y'];
	    			$followList[$k]['address'] = $memberlbs['city'].$memberlbs['district'].$memberlbs['street'].$memberlbs['street_number'];
	    			$memberinfo=Db::name('user_member')->field('x,y')->where('uid',$uid)->find();
	    			if($memberinfo['x']){
                                    $juli=GetDistance($memberinfo['x'],$memberinfo['y'],$memberlbs['x'],$memberlbs['y'],1,0);
                                    if($juli<500) $followList[$k]['juli']='<font color="#006600">'.$juli.'米</font>';
                                    if($juli<1000 and $juli>500) $followList[$k]['juli']='<font color="#009900">'.$juli.'米</font>';
                                    if($juli<3000 and $juli>1000) $followList[$k]['juli']='<font color="#996600">'.$juli.'米</font>';
                                    if($juli>3000) $followList[$k]['juli']='<font color="#FF0000">'.$juli.'米</font>';
	    			}
	    		}else{
                            $followList[$k]['address'] ='';
	    		}
                        $followList[$k]['contentr'] = preg_replace('/(\{\[\%){1}(.*)(\%\]\}){1}/','【<span style="color:red;">$2</span>】', $follow['content']);
	    	}
	    	$this->assign('actName', 'follow');
	    	$this->assign('pageInfo',$pageInfo);
	    	$this->assign('follows', $followList);
	    	return $this->fetch('/user/ajax/follow_index');
    	}else{
    		return json(['code'=>0,'msg'=>'无效客户ID']);
    	}
    }
    //终端库存记录
    public function stocksearch(){
    	$uid=intval(input('uid'));
    	$followArr = array();
    	if($uid){
    		$followArr['uid'] =$uid;
    		$followArr['gid'] =$this->gid;
    		//设置开始结束时间
    		$interval = $this->setInterval();
    		$limit = input('limit')?input('limit'):$this->rows;
    		//$limit =2;
    		//留言列表
    		$res =Db::name('user_stock')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->order('adate desc')->paginate($limit);;
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
    		return $this->fetch('/user/ajax/user_stock');
    	}else{
    		return json(['code'=>0,'msg'=>'无效客户ID']);
    	}
    }
    //回收记录记录
    public function hssearch(){
    	$uid=intval(input('uid'));
    	$followArr = array();
    	if($uid){
    		$followArr['uid'] =$uid;
    		$followArr['gid'] =$this->gid;
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
    		//print_r($followList); exit;
    		foreach($hslist as $k=>$hs){
    			$hslist[$k]['goodsname']=Db::name('goods')->where('goods_id',$hs['goodsid'])->value('goods_name');
    		}
    		$this->assign('actName', 'follow');
    		$this->assign('pageInfo',$pageInfo);
    		$this->assign('hslist', $hslist);
    		return $this->fetch('/user/ajax/user_hs');
    	}else{
    		return json(['code'=>0,'msg'=>'无效客户ID']);
    	}
    }
    //订单记录
    public function ordersearch(){
    	$uid=intval(input('uid'));
    	$followArr = array();
    	if($uid){
    		$followArr['uid'] =$uid;
    		$followArr['gid'] =$this->gid;
    		//设置开始结束时间
    		$interval = $this->setInterval();
    		$limit = input('limit')?input('limit'):$this->rows;
    		//$limit =2;
    		//留言列表
    		$res =Db::name('order')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->order('adate desc')->paginate($limit);
    		$totalMoney=Db::name('order')->whereTime('adate', 'between', [$interval['start'], $interval['stop']])->where($followArr)->sum('total');
    		//处理图片
    		$imgsurl = [];
    		$imgsurlu = [];
    		$url=$_SERVER['HTTP_HOST'];
    		$urls=explode('.',$url);
    		if($urls[1]=='b') $url='http://'.$urls[0].'.'.$urls[1].'.m.'.$urls[2].'.'.$urls[3];
    		else $url='http://'.$urls[0].'.m.'.$urls[1].'.'.$urls[2];
    
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
    			$orderlist[$k]['host'] = Db::name('user_member')->where('uid',$order['uid'])->find();
    			if($order['ppsid']){
    				$orderlist[$k]['distributor'] = Db::name('group')->where('gid',$order['ppsid'])->value('title');
    			}
    		}
    		$this->assign('totalMoney', $totalMoney);
    		$this->assign('pageInfo',$pageInfo);
    		$this->assign('orderlist', $orderlist);
    		return $this->fetch('/user/ajax/user_order');
    	}else{
    		return json(['code'=>0,'msg'=>'无效客户ID']);
    	}
    }
    //出货统计记录
    public function chsearch(){
    	$uid=intval(input('uid'));
    	$followArr = array();
    	if($uid){
    		$followArr['uid'] =$uid;
    		$followArr['gid'] =$this->gid;
    		//设置开始结束时间
    		$interval = $this->setInterval();
    		$limit = input('limit')?input('limit'):$this->rows;
    		//$limit =2;
    		
    		$res = Db::name('order_goods g')
    		->field('g.oid,g.name,g.goodid,g.num,g.amount,g.unit,g.unitgid,u.coefficient,ut.coefficient ucoefficient,ut.uname')
    		->join('ljk_order o','g.oid=o.oid')
    		->join('ljk_unit_group ug','g.unitgid=ug.id','LEFT')
    		->join('ljk_unit u','g.unitid=u.id','LEFT')
    		->join('ljk_unit ut','ut.id=ug.minunitid','LEFT')
    		->where('o.uid',$uid)
    		->where('o.gid',$this->gid)
    		//->where('o.confirm','EGT',0)
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
    				];
    			}
    		}
    		$this->assign('orderjine',$orderjine);
    		$this->assign('pageInfo',$pageInfo);
    		$this->assign('orderlist', $ch);
    		return $this->fetch('/user/ajax/user_ch');
    	}else{
    		return json(['code'=>0,'msg'=>'无效客户ID']);
    	}
    }
    //这个方法作用是用来设置查询时间区间值的
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
    		$csdate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) - 7 * 3600 * 24);
    		$cedate = date('Y-m-d H:i:s',strtotime(date("Y-m-d")) + 3600 * 24 - 1);
    	}
    	return ['start'=>strtotime($csdate),'stop'=>strtotime($cedate)];
    }
    //会员卡充值
    public function mCC(){
        //同时可以充值多张卡
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $uid = isset($input['uid']) ? (int)trim($input['uid']): false;
            $mcard = isset($input['mcard']) ? (array)$input['mcard']: [];
            if(!$uid){
                $msg = '非法操作！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = $msg;
                $arrtips['close'] = array('sign'=>true);
                return $this->tips($arrtips);
            }
            //过滤无效的卡输入，重复的卡输入，只保留第一次输入的卡号
            $mcards = [];
            $sns  = [];
            foreach($mcard as $x){
                $pwd = isset($x['pwd']) ? $x['pwd'] : false;
                $sn = isset($x['sn']) ? $x['sn'] : false;
                if($pwd && $sn){
                    if(!isset($mcards[$sn])){
                        $mcards[$sn] = $pwd;
                        $sns[] = $sn;
                    }
                }
            }
            if(empty($sns)){
                $msg = '必须填写卡号及密码！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = $msg;
                $arrtips['close'] = array('sign'=>true);
                return $this->tips($arrtips);
            }
            //继续过滤符合条件的sn
            $whereMc['gid'] = $this->gid;
            $whereMc['sn'] = ['IN',$sns];
            $mcardsx = Db::name('mcard')->where($whereMc)->column('sn,id,pwd,uid,facevalue,balance,status,expiry,expirys,expirye,salt');
            if(empty($mcardsx)){
                $msg = '未查询到输入的会员卡信息！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = $msg;
                $arrtips['close'] = array('sign'=>true);
                return $this->tips($arrtips);
            }
            //验证并继续过滤
            $tips = [];
            $vv = [];
            foreach($mcardsx as $k=>$v){
                if(!!$v['uid'] && $v['uid'] != $uid){
                    $tips[] = '会员卡：' . $k . '已被占用！';
                }else if($v['balance'] == 0){
                    $tips[] = '会员卡：' . $k . '已使用完毕！';
                }else if($v['status'] == 0){
                    $tips[] = '会员卡：' . $k . '已被禁用！';
                }else if($v['expiry'] == 1){
                    if($v['expirys'] > time()){
                        //未到使用期
                        $tips[] = '会员卡：' . $k . '未到使用期！';
                    }else if($v['expirye'] < time()){
                        $tips[] = '会员卡：' . $k . '已过使用期！';
                    }else{
                        //开始验证密码
                        $p = $mcards[$k];
                        //生成密码 并 比对mpwd($string = '', $operation = 'DECODE', $key = '', $expiry = 0)
                        $pwd = mpwd($v['pwd'],'DECODE', $v['salt']);
                        if($p !== $pwd){
                            $tips[] = '会员卡：' . $k . '密码错误！';
                        }else{
                            $vv[] = $v;
                        }
                    }
                }else{
                    //开始验证密码
                    $p = $mcards[$k];
                    //生成密码 并 比对mpwd($string = '', $operation = 'DECODE', $key = '', $expiry = 0)
                    $pwd = mpwd($v['pwd'],'DECODE', $v['salt']);
                    if($p !== $pwd){
                        $tips[] = '会员卡：' . $k . '密码错误！';
                    }else{
                        $vv[] = $v;
                    }
                }
            }
            $res = $this->corecum($uid,$vv);
            $arrtips['msg'] = $res['msg'];
            $arrtips['close'] = array('sign'=>true);
            if($res['code'] === 1){
                $arrtips['next_action'] = '$(\'#modalBackdropBox\').hide();$(\'#modalBackdropBox\').html(\'\');';     
            }
            if(!empty($tips)){
                $tipss = implode('<br />', $tips);
                $arrtips['msg'] .= '<br />' . $tipss;
            }
            return $this->tips($arrtips);
        }else{
            $msg = '非法操作1！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = $msg;
            $arrtips['close'] = array('sign'=>true);
            return $this->tips($arrtips);
        }
    }
    private function corecum($uid = false,$mcards = []){
        if($uid === false){
            return ['code' => -1,'msg' => '请选择需要进行充值的会员！'];
        }
        if(empty($mcards)){
            return ['code' => -1,'msg' => '请选择会员卡！'];
        }
        $whereUm['gid'] = $this->gid;
        $whereUm['uid'] = $uid;
        $um = Db::name('user_member')->field('gid,guid,uid')->where($whereUm)->find();
        if(!$um){
            return ['code' => -1,'msg' => '不存在此客户信息，无法充值！'];
        }
        //查询该用户是否已经拥有会员卡账户
        $whereMum['gid'] = $this->gid;
        $whereMum['uid'] = $uid;
        $mum = Db::name('mcard_um')
                ->field('id,useable,disable,status')
                ->where($whereMum)
                ->find();
        if(!empty($mum)){
            if($mum['status'] == 0 ){
                return ['code' => -1,'msg' => '该会员的会员卡账户已被禁用，如需充值，请前往会员账户启用后再进行操作！'];
            }else{
                $whereMum['id'] = $mum['id'];
            }
        }else{
            $mum['status'] = 1;
            $mum['gid'] = $this->gid;
            $mum['uid'] = $uid;
            $mum['useable'] = 0;
            $mum['disable'] = 0;
            $mum['ctime'] = time();
        }
        $mum['guid'] = $um['guid'];
        $mump['gid'] = $this->gid;
        $mump['uid'] = $uid;
        $mump['auid'] = $this->guid;
        $mump['atype'] = 0;
        $mump['type'] = 2;//充值
        $mump['ctime'] = $t = time();
        $mump['ua'] = input('server.HTTP_USER_AGENT');
        $mump['ip'] = get_client_ip();
        $mump['uinc'] = 0;
        $mump['dinc'] = 0;
        $mcpsx = [];
        $mcids = [];
        $mcUpdata['uid'] = $uid;
        $mcUpdata['balance'] = 0;
        for($i = 0, $l = count($mcards); $i < $l; $i++){
            if(!!$mcards[$i]['uid'] && $mcards[$i]['uid'] != $uid){
                continue;
            }
            $mcp = [];
            $mcp['gid'] = $this->gid;
            $mcp['mcardid'] = $mcids[] = $mcards[$i]['id'];
            $mcp['atype'] = 0;
            $mcp['inc'] = -$mcards[$i]['balance'];
            $mcp['balance'] = 0;
            $mcp['auid'] = $this->guid;
            $mcp['type'] = 2;
            $mcp['ctime'] = $t;
            $mcp['desc'] = '【充值】';
            $mcpsx[] = $mcp;
            $mum['useable'] +=  $mcards[$i]['balance'];
            $mump['uinc'] +=  $mcards[$i]['balance'];
        }
        $whereMc['id'] = ['IN', $mcids];
        $mump['useable'] = $mum['useable'];
        $mump['disable'] = $mum['disable'];
        Db::startTrans();
        try{
            if(isset($mum['id']) && !!$mum['id']){
                $mumid = $mum['id'];
                unset($mum['id']);
                Db::name('mcard_um')->where($whereMum)->update($mum);
            }else{
                $mumid = Db::name('mcard_um')->insertGetId($mum);
            }
            $mumpid = Db::name('mcard_umpost')->insertGetId($mump);
            $mcps = array_map(function ($mcp) use($mumpid){
                $mcp['mumpid'] = $mumpid;
                return $mcp;
            },$mcpsx);
            Db::name('mcard_post')->insertAll($mcps);
            Db::name('mcard')->where($whereMc)->update($mcUpdata);
            Db::commit();
            $whereUm['gid'] = $this->gid;
            $whereUm['uid'] = $uid;
            $mobile = Db::name('user_member')->where($whereUm)->value('mobile');
            if(!!preg_match('/^(\+86)?1[3-9]\d{9}$/',$mobile)){
                if(!isset($sendSms)){
                    $sendSms = new Sendsms();
                }
                $input = [
                    'gid' => $this->gid,
                    'mobile' => [$mobile],
                    'type' => '10',
                    'id' => $mumpid . ''
                ];
                $sendSms->index($input);
            }
            return ['code' => 1,'msg' => '充值成功！','umc'=>['useable' => $mum['useable'],'disable' => $mum['disable']]];
        }catch(\think\Exception $e){
            Db::rollBack();
            $arrtips['msg'] = '系统繁忙！' . $e->getMessage();
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = array('sign'=>true);
            return $this->tips($arrtips);
        }
    }
    public function import(){
        //客户资料excel导入入口
        $userPort = new UserExcel();
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $input['gid'] = $this->gid;
            $res = $userPort->import($input);
            return json($res);
        }else{
            $input['gid'] = $this->gid;
            $input['action'] = 'showtpl';
            $res = $userPort->import($input);
            if($res['code'] === -1){
                $this->assign('message',$res['msg']);
                return $this->fetch('/common/forbidden');
            }else{
                return $res['data'];
            }
        }
    }
    public function rank(){
        $whereUr['gid'] = $this->gid;
        $ranks = Db::name('user_rank')->where($whereUr)->order([
            'displayorder' => 'DESC'
        ])->select();
        $this->assign('ranks',$ranks);
        return $this->fetch('/user/user_rank');
    }
    public function arank(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $delIds = isset($input['delete']) ?  (array)$input['delete'] : [];
            $isort = isset($input['displayorder']) ?  (array)$input['displayorder'] : [];
            $sortx = isset($input['displayorderx']) ?  (array)$input['displayorderx'] : [];
            $sortIds = array_diff(array_keys($isort),$delIds);
            $ndelIds = [];
            if(!empty($delIds)){
                //获取可以删除的
                $whereUm['gid'] = $this->gid;
                //过滤不可删除的
                $whereUm['rankid'] = ['IN',$delIds];
                $ndelIds = Db::name('user_member')->where($whereUm) ->group('rankid')->column('rankid');
            }
            $delIds = array_values(array_diff($delIds, $ndelIds));
            $sortIds = array_values(array_merge($sortIds,$ndelIds));
            if(!empty($delIds)){
                $whereDur['gid'] = $this->gid;
                $whereDur['id'] = ['IN',$delIds];
                Db::name('user_rank')->where($whereDur)->delete();
            }
            if(!empty($sortIds)){
                $whereUur['gid'] = $this->gid;
                for($i = 0, $l = count($sortIds); $i < $l; $i++){
                    $id = $sortIds[$i];
                    if($sortx[$id] != $isort[$id] && ($isort[$id] >= 0)){
                        $whereUur['id'] = $id;
                        Db::name('user_rank')->where($whereUur)->update(
                            [
                                'displayorder' => $isort[$id]
                            ]
                        );
                    }
                }
            }
            $arrtips['autoclose_sign'] = false;
            if(!empty($ndelIds)){
                $msg = '操作成功，其中' . count($ndelIds) . '个等级下拥有客户无法删除，页面即将刷新！';
            }else{
                $msg = '操作成功，页面即将刷新！';
            }
            $arrtips['msg'] = $msg;
            $arrtips['next_action'] = "setTimeout(function (){window.location.reload();},2000);";
            $arrtips['close'] = ['sign'=>false];
            return $this->tips($arrtips);
        }
    }
    public function editrank(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(!isset($input['id']) || !intval(trim($input['id']))){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '非法操作！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
            if(!isset($input['rank_name']) || !strlen(trim($input['rank_name']))){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：必须输入等级名称！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
            $rankName = trim($input['rank_name']);
            $rankId = intval(trim($input['id']));
            $whereUr['gid'] = $this->gid;
            $whereUr['rank_name'] = $rankName;
            $whereUr['id'] = ['NEQ',$rankId];
            if(Db::name('user_rank')->where($whereUr)->count()){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：已存在相同的客户等级！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }else{
                $whereUur['gid'] = $this->gid;
                $whereUur['id'] = $rankId;
                Db::name('user_rank')->where($whereUur)->update([
                    'rank_name' => $rankName
                ]);
                $arrtips['autoclose_sign'] = false;
                $arrtips['msg'] = '操作成功，页面即将刷新！';
                $arrtips['next_action'] = "setTimeout(function (){window.location.reload();},2000);";
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
        }
    }
    public function addrank(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(!isset($input['rank_name']) || !strlen(trim($input['rank_name']))){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：必须输入等级名称！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
            $rankName = trim($input['rank_name']);
            $whereUr['gid'] = $this->gid;
            $whereUr['rank_name'] = $rankName;
            if(Db::name('user_rank')->where($whereUr)->count()){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：已存在相同的客户等级！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }else{
                $rank['rank_name'] = $rankName;
                $rank['gid'] = $this->gid;
                $rank['min_points'] = 0;
                $rank['max_points'] = 0;
                $rank['discount'] = 0;
                $rank['displayorder'] = 0;
                $rank['status'] = 1;
                Db::name('user_rank')->insert($rank);
                $arrtips['autoclose_sign'] = false;
                $arrtips['msg'] = '操作成功，页面即将刷新！';
                $arrtips['next_action'] = "setTimeout(function (){window.location.reload();},2000);";
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
        }
    }
    public function type(){
        $whereUr['gid'] = $this->gid;
        $types = Db::name('user_type')->where($whereUr)->order([
            'displayorder' => 'DESC'
        ])->select();
        $this->assign('types',$types);
        return $this->fetch('/user/user_type');
    }
    public function atype(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $delIds = isset($input['delete']) ?  (array)$input['delete'] : [];
            $isort = isset($input['displayorder']) ?  (array)$input['displayorder'] : [];
            $sortx = isset($input['displayorderx']) ?  (array)$input['displayorderx'] : [];
            $sortIds = array_diff(array_keys($isort),$delIds);
            $ndelIds = [];
            if(!empty($delIds)){
                //获取可以删除的
                $whereUm['gid'] = $this->gid;
                //过滤不可删除的
                $whereUm['tpid'] = ['IN',$delIds];
                $ndelIds = Db::name('user_member')->where($whereUm) ->group('tpid')->column('tpid');
            }
            $delIds = array_values(array_diff($delIds, $ndelIds));
            $sortIds = array_values(array_merge($sortIds,$ndelIds));
            if(!empty($delIds)){
                $whereDur['gid'] = $this->gid;
                $whereDur['id'] = ['IN',$delIds];
                Db::name('user_type')->where($whereDur)->delete();
            }
            if(!empty($sortIds)){
                $whereUur['gid'] = $this->gid;
                for($i = 0, $l = count($sortIds); $i < $l; $i++){
                    $id = $sortIds[$i];
                    if($sortx[$id] != $isort[$id] && ($isort[$id] >= 0)){
                        $whereUur['id'] = $id;
                        Db::name('user_type')->where($whereUur)->update(
                            [
                                'displayorder' => $isort[$id]
                            ]
                        );
                    }
                }
            }
            $arrtips['autoclose_sign'] = false;
            if(!empty($ndelIds)){
                $msg = '操作成功，其中' . count($ndelIds) . '个客户类型下拥有客户无法删除，页面即将刷新！';
            }else{
                $msg = '操作成功，页面即将刷新！';
            }
            $arrtips['msg'] = $msg;
            $arrtips['next_action'] = "setTimeout(function (){window.location.reload();},2000);";
            $arrtips['close'] = ['sign'=>false];
            return $this->tips($arrtips);
        }
    }
    public function edittype(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(!isset($input['id']) || !intval(trim($input['id']))){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '非法操作！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
            if(!isset($input['type_name']) || !strlen(trim($input['type_name']))){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：必须输入类型名称！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
            $typeName = trim($input['type_name']);
            $rankId = intval(trim($input['id']));
            $whereUt['gid'] = $this->gid;
            $whereUt['title'] = $typeName;
            $whereUt['id'] = ['NEQ',$rankId];
            if(Db::name('user_type')->where($whereUt)->count()){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：已存在相同的客户类型！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }else{
                $whereUut['gid'] = $this->gid;
                $whereUut['id'] = $rankId;
                Db::name('user_type')->where($whereUut)->update([
                    'title' => $typeName
                ]);
                $arrtips['autoclose_sign'] = false;
                $arrtips['msg'] = '操作成功，页面即将刷新！';
                $arrtips['next_action'] = "setTimeout(function (){window.location.reload();},2000);";
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
        }
    }
    public function addtype(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(!isset($input['type_name']) || !strlen(trim($input['type_name']))){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：必须输入类型名称！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
            $typeName = trim($input['type_name']);
            $whereUt['gid'] = $this->gid;
            $whereUt['title'] = $typeName;
            if(Db::name('user_type')->where($whereUt)->count()){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '操作失败：已存在相同的客户类型！';
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }else{
                $type['title'] = $typeName;
                $type['gid'] = $this->gid;
                $type['type'] = 0;
                $type['displayorder'] = 0;
                $type['status'] = 1;
                Db::name('user_type')->insert($type);
                $arrtips['autoclose_sign'] = false;
                $arrtips['msg'] = '操作成功，页面即将刷新！';
                $arrtips['next_action'] = "setTimeout(function (){window.location.reload();},2000);";
                $arrtips['close'] = ['sign'=>false];
                return $this->tips($arrtips);
            }
        }
    }
    //---开始
    /**
     * 客户跟进进度分类操作
     * @return mixed|void
     */
    public function stage(){
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
        $tag_count=Db::name('user_stage_tag')->where($whereBt)->count();
        if($tag_count==0){
            //显示的默认标题 修改后就添加
            $tag=[
                'gid'=>$this->gid,
                'title'=>$title,
                'status'=>1
            ];
            Db::name('user_stage_tag')->insert($tag);
            $arrtips['autoclose_sign'] = true;
            $arrtips['msg'] = '编辑成功，页面即将刷新！';
            $arrtips['close'] = ['sign' => true];
            $arrtips['next_action'] = "window.location.reload();";
            return $this->tips($arrtips);
        }else if(count($tag_count)==1){
            //编辑
            $whereBt['id'] = $id;
            Db::name('user_stage_tag')->where($whereBt)->setField('title', $title);
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
            $arrtips['next_action'] = "getList({sign:'userStage'},1);";
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
        $whereSta['gid']=$this->gid;
        $whereSta['id']=['in',$data[1]];
        Db::name('user_stage')->where($whereSta)->select();
        switch ($data){
            case $del!=null:
                $str=implode(',',$del);
                $user_stage=[];
                foreach($del as $k=>$v){
                    $whereSt['gid']=$this->gid;
                    $whereSt['id']=$v;
                    $whereSt['status']=1;
                    $list=Db::name('user_stage')->field('id,title,displayorder')->where($whereSt)->find();
                    $whereUm['gid']=$this->gid;
                    $whereUm['stage_id']=$v;
                    $list['count']=Db::name('user_member')->where($whereUm)->count();
                    if($list['count']==0) {
                        $whereS1['gid']=$this->gid;
                        $whereS1['id'] = ['eq' ,$list['id']];
                        Db::name('user_stage')->where($whereS1)->delete();
                        unset($list);
                    }else $user_stage[]=$list;
                }
                if($re){
                    foreach($re as $k=>$v){
                        Db::name('user_stage')->where("id='$k'")->setField('displayorder',$re[$k]);
                    }
                }
                if(sizeof($user_stage)>0){
                    return json(['code' => 0, 'data' => $user_stage, 'msg' => '存在数据！']);
                }else{
                    $whereS2['gid']=$this->gid;
                    $whereS2['id'] = ['IN' ,$str];
                    Db::name('user_stage')->where($whereS2)->delete();
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                    $arrtips['close'] = ['sign'=>true];
                    $arrtips['next_action'] = "getList({sign:'userStage'},1);";
                    return $this->tips($arrtips);
                }
                break;
            default:
                foreach($re as $k=>$v){
                    Db::name('user_stage')->where("id='$k'")->setField('displayorder',$re[$k]);
                }
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '批量更新成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'userStage'},1);";
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
        $list=Db::name('user_stage_tag')->where($whereP)->find();
        $this->assign('list',$list);
        return $this->fetch('user/act/user_stage');
    }
    //-----结束
    public function SendEmail(){
        $sendemail='lsh17623887104@163.com';//定义发件人的邮箱
        $send_password='lsh17623887104.';
        $send_info='lsh17623887104@163.com';
        $send_remark='李胜网易邮箱';
        $toemail = '2636521754@qq.com';//定义收件人的邮箱
        $toname='李胜qq邮箱';

        $mail = new PHPMailer();
        try{
            $mail->isSMTP();// 使用SMTP服务
            $mail->CharSet = "utf-8";// 编码格式为utf8，不设置编码的话，中文会出现乱码
            $mail->Host = "smtp.163.com";// 发送方的SMTP服务器地址
            $mail->SMTPAuth = true;// 是否使用身份验证
            $mail->Username = $sendemail;// 发送方的QQ邮箱用户名，就是自己的邮箱名
            $mail->Password = $send_password;// 发送方的邮箱密码，不是登录密码,是qq的第三方授权登录码,要自己去开启,在邮箱的设置->账户->POP3/IMAP/SMTP/Exchange/CardDAV/CalDAV服务 里面
            $mail->SMTPSecure = "ssl";// 使用ssl协议方式,
            $mail->Port = 465;// QQ邮箱的ssl协议方式端口号是465/587  163邮箱的ssl协议方式端口号是465/994

            $mail->setFrom($send_info,$send_remark);// 设置发件人信息，如邮件格式说明中的发件人,
            $mail->addAddress($toemail,$toname);// 设置收件人信息，如邮件格式说明中的收件人
            $mail->addReplyTo($sendemail,$send_remark);// 设置回复人信息，指的是收件人收到邮件后，如果要回复，回复邮件将发送到的邮箱地址
            //$mail->addCC("xxx@163.com");// 设置邮件抄送人，可以只写地址，上述的设置也可以只写地址(这个人也能收到邮件)
            //$mail->addBCC("xxx@163.com");// 设置秘密抄送人(这个人也能收到邮件)
            //$mail->addAttachment("bug0.jpg");// 添加附件

            $num = rand(100000,999999);
            $mail->Subject = "测试邮件";// 邮件标题
            $mail->Body = "邮件内容是：我就是玩玩;你的验证码是：".$num."，哈哈哈！";// 邮件正文
            //$mail->AltBody = "This is the plain text纯文本";// 这个是设置纯文本方式显示的正文内容，如果不支持Html方式，就会用到这个，基本无用
            $mail->send();
            echo 'Message had sent.----';
        }catch (Exception $e){
            echo 'Mailer Error: ' . $mail->ErrorInfo;
        }
    }
}