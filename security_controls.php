<?php
function security_private_dir(){
 $base=getenv('RS8_PRIVATE_DIR')?:sys_get_temp_dir().'/rs8-private';
 $dir=rtrim($base,'/\\').'/'.substr(hash('sha256',__DIR__),0,16);
 if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Private storage is unavailable.');
 return $dir;
}
function security_start_session(){
 if(session_status()===PHP_SESSION_ACTIVE)return;
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
 session_start();
 if(!empty($_SESSION['uid'])&&!empty($_SESSION['last_activity'])&&time()-(int)$_SESSION['last_activity']>1800){$_SESSION=[];session_regenerate_id(true);}
 $_SESSION['last_activity']=time();
 if(PHP_SAPI!=='cli'){header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('X-Frame-Options: SAMEORIGIN');header('Cache-Control: no-store');}
}
function login_rate_key($username){return hash('sha256',strtolower(trim($username)).'|'.($_SERVER['REMOTE_ADDR']??'local'));}
function login_rate_update($username,$mode){
 $path=security_private_dir().'/login-attempts.json';$h=fopen($path,'c+');if(!$h)throw new RuntimeException('Login protection storage unavailable.');
 try{flock($h,LOCK_EX);$data=json_decode(stream_get_contents($h),true)?:[];$now=time();foreach($data as $k=>$v)if(($v['start']??0)<$now-900)unset($data[$k]);$key=login_rate_key($username);$v=$data[$key]??['start'=>$now,'count'=>0];
  if($mode==='check'&&$v['count']>=5)throw new Exception('Too many failed sign-in attempts. Try again after 15 minutes.');
  if($mode==='failure'){$v['count']++;$data[$key]=$v;}if($mode==='success')unset($data[$key]);
  rewind($h);ftruncate($h,0);fwrite($h,json_encode($data));fflush($h);
 }finally{flock($h,LOCK_UN);fclose($h);}
}
function safe_error_message(Throwable $error){
 if(!($error instanceof PDOException)&&!($error instanceof Error))return $error->getMessage();
 $ref='ERR-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
 file_put_contents(security_private_dir().'/errors.log',json_encode(['reference'=>$ref,'at'=>date(DATE_ATOM),'user'=>$_SESSION['uid']??null,'class'=>get_class($error),'message'=>$error->getMessage()])."\n",FILE_APPEND|LOCK_EX);
 return 'The request could not be completed. No partial transaction should be retried without checking its history. Error reference: '.$ref;
}
function validate_upload_file($file,$allowed=['xlsx','pdf','png','jpg','jpeg']){
 if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new Exception('Upload failed or exceeded the server limit. Select the file again.');
 $path=$file['tmp_name']??'';if(!$path||!is_file($path))throw new Exception('Select a file to upload.');
 $bytes=filesize($path);if(!$bytes||$bytes>25*1024*1024)throw new Exception('Files must be between 1 byte and 25 MB.');
 $ext=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));if(!in_array($ext,$allowed,true))throw new Exception('Unsupported file type.');
 if($ext==='xlsx')validate_xlsx_container($path);
 elseif($ext==='pdf'){if(strpos(file_get_contents($path,false,null,0,1024),'%PDF-')===false)throw new Exception('The selected file is not a valid PDF.');}
 elseif($ext==='csv'){if(strpos(file_get_contents($path,false,null,0,4096),chr(0))!==false)throw new Exception('Invalid CSV file.');}
 elseif(!@getimagesize($path))throw new Exception('The selected file is not a valid image.');
 return $ext;
}
function validate_xlsx_container($path){
 if(filesize($path)>25*1024*1024)throw new Exception('Excel file exceeds 25 MB.');
 $z=new ZipArchive();if($z->open($path)!==true)throw new Exception('The file is not a valid XLSX workbook.');
 try{if($z->numFiles>10000)throw new Exception('Workbook contains too many entries.');$sum=0;for($i=0;$i<$z->numFiles;$i++){$s=$z->statIndex($i);$sum+=(int)$s['size'];if($sum>100*1024*1024)throw new Exception('Workbook expanded size exceeds 100 MB.');if(preg_match('/vbaProject|externalLinks/i',$s['name']))throw new Exception('Use an XLSX without macros or external workbook links.');}
  if($z->locateName('xl/workbook.xml')===false)throw new Exception('Workbook structure is missing.');
 }finally{$z->close();}
}
function require_admin_post(){require_login();if(current_user()['role']!=='Admin'){http_response_code(403);exit('Administrator access required.');}if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Use the current administrator upgrade form.');}verify_csrf();}
