<?php
namespace app\shop\controller;

use think\Controller;
use think\Db;

class Address extends Base{
   
    //  TODO: 收获地址主页
    public function index(){
        //$list=[];
        //$list[0]=array('id'=>1,'name'=>"福建的韩小姐",'phong'=>"（134****1462）",'titme'=>"2017-07-12","tedt"=>"您免费领取的 茅台保健百年庆典！ 已发货 请注意收货");
        //$this->assign('list',$list);
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	$map['delete']  = '0';  //  未删除
    	// 返回所有地址
    	$addresslist = Db::name('user_address')
    	->where( $map )
    	->order( 'isdefault', 'DESC' )
    	->select();
        $input = input();
        $autoAct = isset($input['act']) && trim($input['act']) ? trim($input['act']) : false;
        $addrId = isset($input['addrid']) && trim($input['addrid']) ? trim($input['addrid']) : false;
        $this->assign('autoAct',$autoAct);
        $this->assign('addrId',$addrId);
    	$this->assign('addresslist',$addresslist);
    	return view('/ljk/address_list');
    }

    //  TODO:: 返回状态和数据
    public function reJson( $code, $data, $error = '无' ){
        $reJson = array();
        $reJson['code'] = $code;
        $reJson['data'] = $data;
        $reJson['error'] = $error;
        return json( $reJson );
    }
    //  TODO: 地址列表
    public function lst(){
        //  当前用户账号
        $map = array();
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        $map['delete']  = '0';  //  未删除
        // 返回所有地址
        $result = Db::name('user_address')
            ->where( $map )
            ->order( 'isdefault', 'DESC' )
            ->select();
        //  整合数据
        return $this->reJson(1,$result);
    }


    //  TODO:: 地址id获取地址名
    public function idGetAddressName( $shen, $city, $area ){
        //  省
        $shen = Db::name('area')->where([ 'code' => $shen ])->find();
        $shen = $shen['code_name'];
        //  市
        $city = Db::name('area')->where([ 'code' => $city ])->find();
        $city = $city['code_name'];
        //  区
        $area = Db::name('area')->where([ 'code' => $area ])->find();
        $area = $area['code_name'];
        return $shen . $city . $area;
    }

    //  TODO:: 地址
    public function getAreas($jsign = 1){
        $input = input('post.');
        $whereA = array();
        if(!empty($input)){
            $whereA['level'] = isset($input['level']) ? trim($input['level']) : 1;
            $ar=isset($input['parent_code']) ? explode(":",$input['parent_code']): [''];
            $whereA['parent_code'] = $ar[0];
            
        }else{
            $whereA['level'] = 1;
            $whereA['parent_code'] = '';
        }
        //  查询
        $areas = Db::name('area')->where($whereA)->select();
        //查询一下下个level是否还有选项
        if(empty($areas)){
            $res = ['code' => -1,'msg' => '无数据','data' => $areas];
        }else{
            $res = ['code' => 1,'msg' => '数据加载成功','data' => $areas];
        }
        if($jsign === 1){
            return json($res);
        }else{
            return $res;
        }
    }

    //  TODO:: 设置默认地址
    public function address_default_set(  ){
        //  当前用户账号
        $map = array();
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        //  设置用户所有地址非默认状态
        $result = Db::name('user_address')->where( $map )->update( [ 'isdefault' => 0 ] );
        //  设置当前地址默认
        $map['id'] = input('id');
        $result = Db::name('user_address')->where( $map )->update( [ 'isdefault' => 1 ] );
    
        // 返回所有地址
        return $this->lst();
    }

    //  TODO:: 删除地址，如果是默认地址，则设置当前用户最新添加的的地址为默认
    public function address_del(){
        if( !$this->request->isPost() ){
            return 1;
        }
        //  当前用户id
        $map = array();
        $map['id']  = input('id');
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        //  删除
        $result = Db::name('user_address')->where( $map )->update( [ 'delete' => 1] );
        if( !$result ){ //  没有修改任何数据
            return '删除失败';
        }
        // 返回所有地址
        return $this->lst();
    }

    //  TODO: 单个地址信息
    public function address_get(){
        //  当前用户信息
        $map = array();
        $map['id']   = input('id');
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        $map['delete']  = '0';  //  未删除
        //
        $result = Db::name('user_address')
            ->where( $map )
            ->find();
        //  返回数据
        return $this->reJson( 10, $result);
    }
    //  TODO: 新增地址
    public function add(){
        if( !$this->request->isPost() ){
            return $this->reJson( -1, 0, '错误请求方式');
        }
        //  查询是否超过地址条数限制,最多5条
        //  当前用户账号
        $map = array();
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        $map['delete']  = '0';  //  未删除
        $result = Db::name('user_address')->where( $map )->count();
        if( $result >= 5 ){
            return $this->reJson( -1, 0, '用户最多添加5个收货地址,请删除不需要的收货地址再尝试添加！');
        }
        //  取出数据
        $province   = explode( ':', input('province'));
        $city       = explode( ':', input('city'));
        $area       = explode( ':', input('area'));
        $data = array();
        $data['isdefault']  = input('isdefault');
        $data['gid']        = $this->gid;
        $data['uid']        = $this->uid;
        $data['consignee']  = input('consignee');
        $data['areaids']    = $province[0] . ',' . $city[0] . ',' . $area[0];    //  地址ID
        $data['areaname']   = $province[1] . $city[1] . $area[1];
        $data['address']    = input('address');
        $data['mobile']        = input('mobile');
        // 验证规则
        $rule = [
            ['consignee', 'require', '收货人姓名不能为空'],
            ['areaids', 'require', '请选择省市区'],
            ['areaname', 'require', '省市区获取失败'],
            ['address', 'require', '请输入详细地址'],
            ['mobile', '/^1[3456789]\d{9}$/', '电话号码填写错误']
        ];
        $result = $this->validate( $data, $rule );
        if( $result !== true ){
            return $this->reJson( 1, 0, $result);
        }
        //  插入数据
        $db = Db::name('user_address');
        $insertId = $db->insertGetId($data);
        if( !$insertId ){
            return $this->reJson( -1, 0, '插入数据错误');
        }
        //  设置其他地址为非默认
        if( input('isdefault') == 1 ){
            $result = Db::name('user_address')->where( 'id', 'neq', $insertId )->update( [ 'isdefault' => 0 ] );
        }
        // 返回所有地址
        return $this->lst();
    }
    //  TODO: 地址修改
    public function address_update(){
        //  当前用户信息
        $map = array();
        $map['id']   = input('id');
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        //  更新数据
        $province   = explode( ':', input('province'));
        $city       = explode( ':', input('city'));
        $area       = explode( ':', input('area'));
        $data = array();
        $data['isdefault']  = input('isdefault');
        $data['gid']        = $this->gid;
        $data['uid']        = $this->uid;
        $data['consignee']  = input('consignee');
       // $data['email']      = '1062584641@qq.com';
        $data['areaids']    = $province[0] . ',' . $city[0] . ',' . $area[0];    //  地址ID
        $data['areaname']   = $province[1] . $city[1] . $area[1];
        //  地址code转换成实际地址
        $data['address']    = input('address');
        $data['zipcode']    = input('zipcode') ? input('zipcode') : '';
        $data['best_time']  = input('best_time') ? $data['best_time'] : '';
        $data['tel']        = input('tel') ? input('tel') : '';
        $data['mobile']        = input('mobile') ? input('mobile') : '';
        //$data['mobile']     = '';
        $data['sign_building']  = '';
        //$data['best_time']     = input('best_time');
        $data['recommend']     = input('recommend') ? input('recommend') : '';
        //$data['status']     = '';
        // 验证规则
        $rule = [
            ['consignee', 'require', '收货人姓名不能为空'],
            ['areaids', 'require', '请选择省市区'],
            ['areaname', 'require', '省市区获取失败'],
            ['address', 'require', '请输入详细地址'],
            ['mobile', '/^1[3456789]\d{9}$/', '电话号码填写错误']
        ];
        $result = $this->validate( $data, $rule );
        if( $result !== true ){
            return $this->reJson(1, 0, '数据错误：' . $result);
        }
        //  更新
        $result = Db::name('user_address')
            ->where( $map )
            ->update( $data );
        //  设置其他地址为非默认
        if(input('isdefault') == 1 ){
            $result = Db::name('user_address')->where(['gid'=>$this->gid,'uid'=>$this->uid,'id' => ['NEQ', $map['id']]])->update( [ 'isdefault' => 0 ] );
        }
        //  返回数据
        return $this->lst();
    }
}
