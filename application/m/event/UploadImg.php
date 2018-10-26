<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\m\event;
use think\Db;
/**
 * Description of File
 *
 * @author Administrator
 */
class UploadImg {
    //put your code here
    private $sessIdName;
    private $sessUrlName;
    public function setSessName($sessName){
        if(!$sessName){
            $this->sessIdName = 'imgsid';
            $this->sessUrlName = 'imgsurl';
            $this->sessLocalIdName = 'imgslocalid';
            $this->sessServerIdName = 'imgsserverid';
        }else{
            $this->sessIdName = 'imgsid.' . $sessName;
            $this->sessUrlName = 'imgsurl.' . $sessName;
            $this->sessLocalIdName = 'imgslocalid.'. $sessName;
            $this->sessServerIdName = 'imgsserverid.'. $sessName;
        }
    }
    public function index($file){
        $sinfo = $file->getInfo();
        $smd5 = md5_file($sinfo['tmp_name']);
        $d = Db::name('file')->field('id,savename,savepath,ext')->where('md5',$smd5)->find();
        $id = $d['id'];
        if(!$id){
            $info = $file->validate(['ext'=>'jpg,png,gif'])->move(ROOT_PATH . 'public' . config('upload_path'));
		//	print_r($info);
            if($info){
                $si = $info->getInfo();
                $url = str_replace('\\','/',config('upload_path') .'/'. $info->getSaveName());
                $snp = pathinfo($url);//这个name是拿来存入数据库作为资源链接;
                $fi = array();
                $fi['savename'] = $snp['filename'];
                $fi['savepath'] = substr($snp['dirname'], strrpos($snp['dirname'],'/' ) + 1);
                $fi['name'] = $snp['basename'];
                $fi['ext'] = $snp['extension'];
                $fi['size'] = $si['size'];
                $fi['mime'] = $info->getMime();
                $fi['create_time'] = time();
                $fi['md5'] = $info->hash('md5');
                $fi['sha1'] = $info->hash('sha1');
                $fi['url'] = $url;
                $fi['guid'] = session('guid');
                $fi['gid'] = session('gid');
                //设置这个客户关联的url字符集合,判断是否已经上传过这个文件
            }else{
                return $file->getError();
            }
            $f = model('file');
            $f->save($fi);
            $id = $f->id;
            $url = mkurl($fi);
			$length = session($this->sessUrlName);
            if(!empty($length)){
                $url = session($this->sessUrlName).','.$url;
                $imgsid = session($this->sessIdName).'_'.$id;
                session($this->sessIdName,$imgsid);
                session($this->sessUrlName,$url);
            }else{
                session($this->sessIdName,$id);
                session($this->sessUrlName,$url);
            }
        }else{
            //id存在但是session内并未保存这个值
            $oldimgsid = explode('_',session($this->sessIdName));
            $url = mkurl($d);
            //就是已经存在这个文件了
 			$length = session($this->sessUrlName);
			if(!empty($length)){
                $url = session($this->sessUrlName).','.$url;
                $imgsid = session($this->sessIdName).'_'.$id;
                session($this->sessIdName,$imgsid);
                session($this->sessUrlName,$url);
            }else{
                session($this->sessIdName,$id);
                session($this->sessUrlName,$url);
            }
        }
        $urls = explode(',',session($this->sessUrlName));
        $imgsid = session($this->sessIdName);
        return $imgsid;
    }
    public function clear(){
        session($this->sessUrlName,null);
        session($this->sessIdName,null);
        session($this->sessLocalIdName,null);
        session($this->sessServerIdName,null);
    }
    public function getImgsLocalIds(){
    	return session($this->sessLocalIdName)?session($this->sessLocalIdName):'';
    }
    public function getImgsServerIds(){
    	return session($this->sessServerIdName)?implode(',',session($this->sessServerIdName)):'';
    }
    public function setImgLocalIds($localids,$serverids){
    	if($localids[0]){
    		if(session($this->sessLocalIdName)){
    			$localids=array_merge(session($this->sessLocalIdName),$localids);
    		}
    		$localids=array_unique($localids);    		
    		session($this->sessLocalIdName,$localids);
    	}
    	if($serverids[0]){
    		if(session($this->sessServerIdName)){
    			$serverids=array_merge(session($this->sessServerIdName),$serverids);
    		}
    		$serverids=array_unique($serverids);
    		session($this->sessServerIdName,$serverids);
    	}
    }
    public function getImgs(){
        if(session($this->sessIdName)){
            $urls = explode(',',session($this->sessUrlName));
        }else{
            $urls = [];
        }
        $imgsid = session($this->sessIdName);
        if(!$imgsid){
            $imgsid = '';
        }
        return ['urls'=>$urls,'imgsid'=>$imgsid];
    }
    public function upimgs(){
        $imgsid = input('imgsid');
        $imgserverid = input('imgserverid');
        if($imgserverid){
        	if(session($this->sessLocalIdName)){
	        	$LocalIdName=session($this->sessLocalIdName);
	        	$ServerIdName=session($this->sessServerIdName);
	        	foreach ($LocalIdName as $key=>$ll){
	        		if($ll!=$imgserverid){
	        			$NewLocalIdName[]=$ll;
	        			$NewServerIdName[]=$ServerIdName[$key];
	        		}
	        	}
				session($this->sessLocalIdName,$NewLocalIdName);
				session($this->sessServerIdName,$NewServerIdName);
        	}
        	return true;
        }
        if($imgsid){
            $oldids = explode('_',session($this->sessIdName));
            $newids = explode('_',$imgsid);
            $url = [];
            for($i=0;$i<count($oldids);$i++){
                if(isset($newids[$i]) && !empty($newids[$i])){
                    if($newids[$i] != $oldids[$i]){
                        break;
                    }
                }else{
                    break;
                }
            }
            $url = explode(',',session($this->sessUrlName));
            unset($url[$i]);
            $url = implode(',',$url);
        }else{
            $imgsid = null;
            $url = [];
        }
        session($this->sessUrlName,$url);
        session($this->sessIdName,$imgsid);
        return $imgsid;
    }
	public function wxupload($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $out_put = curl_exec($ch);
        if($out_put === false){
            return '';
        }else{
            $d = date('Ymd');
            $uploadPath = '.'.config('upload_path_x') . $d . '/';
            if(!file_exists($uploadPath)){
                cdir($uploadPath);
			}
            $filename = $uploadPath . md5(time() . mt_rand(0,99999)) . '.jpg';
            $fp = fopen($filename,'wb');
            fwrite($fp, $out_put);
            $cinfo = curl_getinfo($ch);
            $snp = pathinfo($filename);
            $fi['savepath'] = $d;
            $fi['savename'] = $snp['filename'];
            $fi['name'] = $snp['basename'];
            $fi['ext'] = $snp['extension'];
            $fi['size'] = filesize($filename);
            $fi['mime'] = mime_content_type($filename);
            $fi['create_time'] = time();
            $fi['md5'] = $md5 = md5_file($filename);
            $fi['sha1'] = sha1_file($filename);
            $fi['url'] = substr($filename,1);
            $fi['guid'] = session('guid');
            $fi['gid'] = session('gid');
            $fi['uid'] = session('uid');
           
			//是否已经上传了这个文件
			$d = Db::name('file')->field('id,savename,savepath,ext')->where('md5',$md5)->find();
			if(empty($d)){
				//$f = model('file');
				//$f->save($fi);
				//$id = $f->id;
				Db::name('file')->insert($fi);
				$id = Db::name('file')->getLastInsID();
				$url = mkurl($fi);
				$length = session($this->sessUrlName);
				if(!empty($length)){
					$url = session($this->sessUrlName).','.$url;
					$imgsid = session($this->sessIdName).'_'.$id;
					session($this->sessIdName,$imgsid);
					session($this->sessUrlName,$url);
				}else{
					session($this->sessIdName,$id);
					session($this->sessUrlName,$url);
				}
			}else{
				//删除刚创建的这个文件
				unlink($filename);
				$id = $d['id'];
				$oldimgsid = explode('_',session($this->sessIdName));
				$url = mkurl($d);
				//就是已经存在这个文件了
				$length = session($this->sessUrlName);
				if(!empty($length)){
					$url = session($this->sessUrlName).','.$url;
					$imgsid = session($this->sessIdName).'_'.$id;
					session($this->sessIdName,$imgsid);
					session($this->sessUrlName,$url);
				}else{
					session($this->sessIdName,$id);
					session($this->sessUrlName,$url);
				}
			}
        }
		$urls = explode(',',session($this->sessUrlName));
        $imgsid = session($this->sessIdName);
		curl_close($ch);
        return $imgsid;
    }
    
    public function ybwxupload($url,$data){
    	$ch = curl_init($url);
    	curl_setopt($ch, CURLOPT_HEADER, 0);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	$out_put = curl_exec($ch);
    	if($out_put === false){
    		return '';
    	}else{
    		$d = date('Ymd');
    		$uploadPath = '.'.config('upload_path_x') . $d . '/';
    		if(!file_exists($uploadPath)){
    			cdir($uploadPath);
    		}
    		$filename = $uploadPath . md5(time() . mt_rand(0,99999)) . '.jpg';
    		$fp = fopen($filename,'wb');
    		fwrite($fp, $out_put);
    		$cinfo = curl_getinfo($ch);
    		$snp = pathinfo($filename);
    		$fi['savepath'] = $d;
    		$fi['savename'] = $snp['filename'];
    		$fi['name'] = $snp['basename'];
    		$fi['ext'] = $snp['extension'];
    		$fi['size'] = filesize($filename);
    		$fi['mime'] = mime_content_type($filename);
    		$fi['create_time'] = time();
    		$fi['md5'] = $md5 = md5_file($filename);
    		$fi['sha1'] = sha1_file($filename);
    		$fi['url'] = substr($filename,1);
    		$fi['guid'] = $data['guid'];
    		$fi['gid'] = $data['gid'];
    		$fi['uid'] = $data['uid'];
    		 
    		//是否已经上传了这个文件
    		$d = Db::name('file')->field('id,savename,savepath,ext')->where('md5',$md5)->find();
    		if(empty($d)){
    			//echo $url.'--------------<br>';
    			//print_r($fi);
    			//$f = model('file');
    			//$f->save($fi);
    			//$id = $f->id;
    			Db::name('file')->insert($fi);
    			$id = Db::name('file')->getLastInsID();
    			//echo $id.'--------------<br>';
    			$url = mkurl($fi);
    			$length = session($this->sessIdName);
    			if(!empty($length)){
    				$url = session($this->sessUrlName).','.$url;
    				$imgsid = session($this->sessIdName).'_'.$id;
    				session($this->sessIdName,$imgsid);
    				session($this->sessUrlName,$url);
    			}else{
    				session($this->sessIdName,$id);
    				session($this->sessUrlName,$url);
    			}
    		}else{
    			//删除刚创建的这个文件
    			unlink($filename);
    			$id = $d['id'];
    			$url = mkurl($d);
    			//就是已经存在这个文件了
    			$length = session($this->sessIdName);
    			if(!empty($length)){
    				$url = session($this->sessUrlName).','.$url;
    				$imgsid = session($this->sessIdName).'_'.$id;
    				session($this->sessIdName,$imgsid);
    				session($this->sessUrlName,$url);
    			}else{
    				session($this->sessIdName,$id);
    				session($this->sessUrlName,$url);
    			}
    		}
    	}
    	$urls = explode(',',session($this->sessUrlName));
    	$imgsid = session($this->sessIdName);
    	curl_close($ch);
    	return $imgsid;
    }
}
