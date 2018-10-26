<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\m\controller;
use app\m\controller\Base;
use app\common\controller\Sign;
use think\wechatsdk\Api;
use think\Db;
class Pay extends Base{
    protected $weixin_appId_appSecret = [];
    protected $weixin_appId_Pay = [];
    public function _initialize() {
        parent::_initialize();
	$this->weixin_appId_appSecret=array(
            'appId' =>  Config('weixin_config.appId'),
            'appSecret'	=> Config('weixin_config.appSecret')
        );
        $this->weixin_appId_Pay=array(
            'appId' =>  Config('weixin_config.appId'),
            'appSecret'	=> Config('weixin_config.appSecret'),
            'mchId'	=> Config('weixin_pay_config.mchId'),
            'key'	=> Config('weixin_pay_config.key')
        );
    }
    public function index(){
        $gid = session('gid');
        $guid = session('guid');
        $gm = Db::name('group_member')->find($guid);
        $ga = Db::name('group_account')->where('gid',$gid)->where('guid',$guid)->find();
        $product = Db::name('ljkproduct')->find(3);
        //获取易品保账户信息
        $ypb = Db::name('ypb')->where('mobile',$gm['mobile'])->find();
    	session('pay.payeid',$ypb['id']);
        if($ga['exp_date'] > time()){
            $ga['has_exp'] = false;
        }else{
            $ga['has_exp'] = true;
        }
        if(is_weixin()){
        	$call_back_url = "http://".$_SERVER['HTTP_HOST']."/m/pay/weixinpredata/";
        	$this->assign('call_back_url',$call_back_url);
        }
        $this->assign('ypb',$ypb);
        $this->assign('gm',$gm);
        $this->assign('ga',$ga);
        $this->assign('product',$product);
        return $this->fetch('charge/index');
    }
    public function check(){
    	$pay = new \app\pay\controller\Index();
    	$pay->rs();
    	$expDate = Db::name('group_account')->where('gid',session('gid'))->where('guid',session('guid'))->value('exp_date');
    	session('group_account_expdate',$expDate);
    	if($expDate - time() <= 0){
    		$this->redirect(url('pay/index'));
    		//return json(['code' => -3, 'data' => url('index/index'), 'msg' => '对不起您的账户已过期，请充值！']);
    	}else{
    		$this->redirect(url('index/index').'#'.input('u'));
    		//return json(['code' => 1, 'data' => url('index/index'), 'msg' => '登录成功']);
    	}
    }
    //执行充值过程
	public function cz(){
        if(request()->isAjax() && request()->isPost()){
            session('pay.predata',null);
            $cz = controller('pay/index','controller');
            $paytype = input('m');
            $sign = input('sign')?input('sign'):1;
            switch ($paytype){
                case 1:$template = 'e';break;
                case 2:$template = 'w';break;
                default:null;
            }
            $res = $cz->wapxufei($template)->getData();
            //附加客户信息
            if($res['code']==0){
                if(is_weixin() && ($paytype==2)){
                    return json(['code'=>0,'msg'=>'即将跳转！']);
                }
            }else{
                return json(['code'=>-1,'msg'=>'系统繁忙，请稍候再试！']);
            }
            unset($res['predata']);
            return $res;
        }
    }
    private function getApiParam($ord){
        $config['body'] = '账号充值续费';
        $config['out_trade_no'] = $ord['number'];
        $config['total_fee'] = $ord['total'] * 100;
        $config['notify_url'] = 'https://www.epinbao.cc/e/weixin/weixin_notify_url.html';
        $config['trade_type'] = 'JSAPI';
        $config['openid'] = $ord['openid'];
       // $config['limit_pay'] = 'no_credit';
        $config['spbill_create_ip'] = get_client_ip();
        $api = new Api($this->weixin_appId_Pay);
        $pay=$api->wxPayUnifiedOrder($config);
       // print_r($pay);
        $pay['out_trade_no']=$config['out_trade_no'];
        if($pay['prepay_id']){
            $res = array_merge($config,$pay);
            db('weipay_request')->insert($res);
        }
        $jsApiParameters=$api->getWxPayJsApiParameters($pay['prepay_id']);
        return $jsApiParameters;
    }
    //获取待支付订单详情
    public function ypbpredata(){
        if(request()->isAjax() && request()->isPost()){
            $preData = session('pay.predata');
            $accountMemberInfo = session('pay.accountMemberInfo');
            $this->assign('accountMemberInfo',$accountMemberInfo);
            $this->assign('preData',$preData);
            return json(['code'=>0,'tpl'=>$this->fetch('renew/e')]);
        }
    }
    public function weixinpredata(){
        if(request()->isAjax() && request()->isPost()){
            $preData = session('pay.predata');
            if(is_weixin()){
                $sign = new Sign();
                $ord = $preData;
                $wx = [
                    'out_trade_no'=>$res['predata']['number'],
                    't'=> time(),
                ];
                $wx = array_merge($wx,$sign->mkSign($wx));
                $api = new Api($this->weixin_appId_appSecret);
                list($err, $user_info) = $api->get_userinfo_by_authorize('snsapi_base');
                $userinfo=json_decode(json_encode($user_info),true);
                $ord['openid']=$userinfo['openid'];
                //生成jsApiParameters
                $jsApiParameters = $this->getApiParam($ord);
                $this->assign('wx',$wx);
                $this->assign('jsApiParameters',$jsApiParameters);
            }
            $accountMemberInfo = session('pay.accountMemberInfo');
            $this->assign('accountMemberInfo',$accountMemberInfo);
            $this->assign('preData',$preData);
            return json(['code'=>0,'tpl'=>$this->fetch('renew/w')]);
        }
    }
    public function afterpay(){
        if(request()->isAjax() && request()->isPost()){
            $res = session('pay.afterpaydata');
            return json($res);
        }
    }
    public function mkurl(){
    	if(is_weixin() && request()->isAjax() && request()->isPost()){
    		$call_back_url = "https://www.epinbao.cc/e/weixin/xfweixinpay?query=";
    		$preData = session('pay.predata');
    		$accountMemberInfo = session('pay.accountMemberInfo');
    		$s = '';
    		$data['username'] = urlencode($accountMemberInfo['username']);
    		$data['expdate'] = $accountMemberInfo['exp_date'];
    		$data['afterexp'] = $accountMemberInfo['after_exp'];
    		$data['num'] = urlencode($preData['num']);
    		$data['paytype'] = urlencode($preData['payType']);
    		$data['number'] = $preData['number'];
    		$data['back_url'] = input('server.REQUEST_SCHEME').'://'.input('server.HTTP_HOST').'/m/pay/wxinsidepayorder';
    		$sign = new Sign();
    		$data = array_merge($data,$sign->mkSign($data));
    		foreach($data as $k=>$v){
    			$s .= $k . '=' . $v . '&';
    		}
    		$s = rtrim($s,'&');
    		$call_back_url .= urlencode(base64_encode($s));
    		return json(['code'=>0,'url'=>urlencode($call_back_url)]);
    	}
    }
    public function wxinsidepayorder(){
    	$queryEncode = input('param.query');
    	$query = explode('&',urldecode(base64_decode($queryEncode)));
    	$data = [];
    	for($i=0;$i<count($query);$i++){
    		$v = explode('=',$query[$i]);
    		$data[$v[0]] = $v[1];
    	}
    	$sign = new Sign();
    	if(!empty($data) && $sign->validateSign($data)){
    		$inputData['number'] = $data['number'];
    		$inputData['paytype'] = $data['type'];
    		$pay = controller('pay/index','controller');
    		$res = $pay->payOrder($inputData)->getData();
    		print_r($res); 
    		if($res['code']==0){
    			$r = ['code'=>0,'msg'=>'支付成功！'];
    		}else{
    			$r = ['code'=>-1,'msg'=>'支付失败！'];
    		}
    	}else{
    		$r = ['code'=>-1,'msg'=>'非法操作！'];
    	}
    	if($r['code'] === 0){
    		$this->redirect('m/pay/index#chengong');
    	}else{
    		$this->assign('r',$r);
    		$tpl = $this->fetch('/err');
    		return $tpl;
    	}
    }
}
