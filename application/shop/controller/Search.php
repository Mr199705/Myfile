<?php
namespace app\shop\controller;

use think\Db;

class Search extends Base
{
    //  TODO:: 分类
    public function index(){
        //  获取一级分类
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = [];
            if(isset($input['action']) && $action = trim($input['action'])){
                unset($input['action']);
            }
            switch($action){
                case 'searchlist':return $this->searchList($input);
            }
        }else{
            $clas = !empty(input('clas')) ? input('clas') : 0;
            $w = !empty(input('w')) ? input('w') : '';
            $this->assign('clas', $clas);
            $this->assign('w', $w);
            return view('/ljk/goodslist');
        }
    }
    private function searchList( $input = [] ){
        $g = new Goods();
        return $g->getGoodsList($input);
    }
}