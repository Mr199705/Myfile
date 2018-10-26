<?php

namespace app\admin\controller;
use \app\common\model\Storage as storageModel;
use think\Request;
use think\Db;
class Storage extends Base{
    //put your code here
    private $units = [];
    public function __construct(Request $request = null) {
        parent::__construct($request);
        $this->storageModel = new storageModel(['gid'=>$this->gid,'guid'=>$this->guid]);
        $this->initData = [
            'sign'=>$this->sign,
            'requestFunc'=>$this->requestFunc,
            'requestUrl'=>$this->requestUrl,
            'cUrl'=>$this->cUrl,
            'jsName'=>$this->jsName,
        ];
        $this->assign('initData',$this->initData);
    }
    private function showmemberlist(){
        $whereGM['gm.gid'] = $this->gid;
        $whereGM['gm.status'] = 1;
//        $whereGM['ga.exp_date'] = ['gt',time()];
        $gms = Db::name('group_member gm')
            ->field('gm.uid uid,gm.realname realname')
//            ->join('group_account ga','gm.uid=ga.guid')
            ->where($whereGM)
            ->select();
        return $gms;
    }
    public function index(){
        if(request()->isAjax()){
            $input = input();
            if(isset($input['keywords']) && strlen(trim($input['keywords']))){
                $whereS['s.name'] = ['LIKE','%' . $input['keywords'] . '%'];
            }
            if(isset($input['disable']) && strlen(trim($input['disable']))){
                $whereS['s.disable'] = intval(trim($input['disable']));
            }
            $whereS['s.gid'] = $this->gid;
            $whereS['s.type'] = 0;//大仓库
            $res = $this->storageModel->getStorages($whereS,$this->rows);
            if(!!$res){
                $pageInfo = pageInfo($res);
                $storages = $res->items();
                foreach ($storages as $k=>$v) {
                    //正则匹配得到地址
                    $storages[$k]['getaddress']=preg_replace('/(.+)((市辖区)|(县))(.+)/', '$1$5', $v['areaname'].$v['address']);
                }
            }else{
                $pageInfo = false;
                $storages = false;
            }
            $this->assign('pageInfo',$pageInfo);
            $this->assign('storages',$storages);
            return $this->fetch('/storage/ajax/index');
        }else{
            $gms = $this->showmemberlist();
            $this->assign('gms',$gms);
            return $this->fetch('/storage/index');
        }
    }
    public function addStorage(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $storage['gid'] = $this->gid;
            $storage['guid'] = isset($input['guid'])?trim($input['guid']):'';
            $storage['name'] = isset($input['name'])?trim($input['name']):'';
            if($input['areas']){
                $input['areaids'] = '';
                $input['areaname'] = '';
                foreach ($input['areas'] as $area){
                    $ar=explode(":",$area);
                    $input['areaids'].=$ar[0].',';
                    $input['areaname'].=$ar[1];
                    if(!($ar[1]=='县' || $ar[1] == '市辖区')){
                        $storage['areaname']=trim($input['areaname']);
                    }
                }
                if($input['areaids']){
                    $storage['areaids']=trim($input['areaids'],',');
                }
            }else{
                unset($input['areas']);
            }
            $storage['address'] = isset($input['address'])?trim($input['address']):'';
            $storage['phone'] = isset($input['phone'])?trim($input['phone']):'';
            $storage['location_no'] = isset($input['location_no'])?trim($input['location_no']):'';
            $storage['sort'] = isset($input['sort'])?intval(trim($input['sort'])):0;
            $storage['x'] = isset($input['x'])?trim($input['x']):0;
            $storage['y'] = isset($input['y'])?trim($input['y']):0;
            $storage['ctime'] = time();
            $validate = validate('Storage');
            $res = $validate->check($storage);
            if($res !== true){
                $msg = $validate->getError();
                $arrtips['msg'] = '新增仓库信息失败，错误信息：' . $msg;
                $arrtips['autoclose_sign'] = true;
                return $this->tips($arrtips,false);
            }else{
                if($this->storageModel->addStorage($storage)){
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['msg'] = '新增仓库成功';
                    $arrtips['close'] = ['sign'=>true];
                    $arrtips['next_action'] = "getList({sign:'storageIndex'})";
                    return $this->tips($arrtips);
                }else{
                    $msg = $validate->getError();
                    $arrtips['msg'] = '系统繁忙，请稍候再试！';
                    $arrtips['autoclose_sign'] = true;
                    return $this->tips($arrtips,false);
                }
            }
        }else{
            $arrtips['msg'] = '非法操作！';
            $arrtips['autoclose_sign'] = true;
            return $this->tips($arrtips,false);
        }
    }
    public function editStorage(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $whereS['id'] = isset($input['id'])?trim($input['id']):'';
            $whereS['gid'] = $this->gid;
            $storage['type'] = 0;
            $storage['guid'] = isset($input['guid'])?trim($input['guid']):'';
            $storage['name'] = isset($input['name'])?trim($input['name']):'';
            if($input['areas']){
                $input['areaids'] = '';
                $input['areaname'] = '';
                foreach ($input['areas'] as $area){
                    $ar=explode(":",$area);
                    $input['areaids'].=$ar[0].',';
                    $input['areaname'].=$ar[1];
                    if(!($ar[1]=='县' || $ar[1] == '市辖区')){
                        $storage['areaname']=trim($input['areaname']);
                    }
                }
                if($input['areaids']){
                    $storage['areaids']=trim($input['areaids'],',');
                }
            }else{
                unset($input['areas']);
            }
            $storage['address'] = isset($input['address'])?trim($input['address']):'';
            $storage['phone'] = isset($input['phone'])?trim($input['phone']):'';
            $storage['location_no'] = isset($input['location_no'])?trim($input['location_no']):'';
            $storage['sort'] = isset($input['sort'])?intval(trim($input['sort'])):0;
            $storage['x'] = isset($input['x'])?trim($input['x']):0;
            $storage['y'] = isset($input['y'])?trim($input['y']):0;
            $storage['mtime'] = time();
            $rule = [
                ['id','require','非法操作！'],
                ['gid','require','非法操作！'],
            ];
            $validateW = new \think\Validate($rule);
            $resW = $validateW->check($whereS);
            $validate = validate('Storage');
            if(!$resW){
                $msg = $validate->getError();
                $arrtips['msg'] = $msg;
                $arrtips['autoclose_sign'] = true;
                return $this->tips($arrtips,false);
            }
            $res = $validate->check($storage);
            if(!$res){
                $msg = $validate->getError();
                $arrtips['msg'] = '编辑仓库信息失败，错误信息：' . $msg;
                $arrtips['autoclose_sign'] = true;
                return $this->tips($arrtips,false);
            }
            if($this->storageModel->editStorage($whereS,$storage)){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '编辑仓库信息成功';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'storageIndex'})";
                return $this->tips($arrtips);
            }else{
                $msg = $validate->getError();
                $arrtips['msg'] = '系统繁忙，请稍候再试！';
                $arrtips['autoclose_sign'] = true;
                return $this->tips($arrtips,false);
            }
        }else{
            $arrtips['msg'] = '非法操作！';
            $arrtips['autoclose_sign'] = true;
            return $this->tips($arrtips,false);
        }
    }
}