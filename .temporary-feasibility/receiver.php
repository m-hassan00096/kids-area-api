<?php
declare(strict_types=1);
require __DIR__.'/shared.php';
header('Content-Type: application/json');
$files=[];foreach($_FILES as $k=>$f)$files[$k]=['name'=>$f['name'],'size'=>$f['size'],'error'=>$f['error'],'is_array'=>is_array($f['name'])];
$decision='absent';$reason='no visible upload';$size=(int)($_SERVER['CONTENT_LENGTH']??0);
if($size>4194304){$decision='reject';$reason='body limit';}
elseif($_FILES){
 $f=$_FILES['avatar']??null;
 if(count($_FILES)!==1||!is_array($f)||!is_string($f['name'])||!is_int($f['error'])||!is_string($f['tmp_name'])){$decision='reject';$reason='visible file shape';}
 elseif($f['error']!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name'])||$f['size']<1||$f['size']>2097152){$decision='reject';$reason='invalid retained upload';}
 else{
  $b=file_get_contents($f['tmp_name']);$type=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));if($type==='jpeg')$type='jpg';
  $mime=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$type]??null;
  $valid=is_string($b)&&strlen($b)===$f['size']&&$mime!==null&&(new finfo(FILEINFO_MIME_TYPE))->buffer($b)===$mime&&structure($b,$type)==='ok';
  if($valid){$info=getimagesizefromstring($b);$valid=$info&&$info[0]>0&&$info[1]>0&&$info[0]<=2048&&$info[1]<=2048&&$info[0]*$info[1]<=4194304;if($valid){$warn=false;set_error_handler(static function()use(&$warn){$warn=true;return true;});try{$im=imagecreatefromstring($b);$valid=$im instanceof GdImage&&!$warn;}finally{restore_error_handler();}}}
  $decision=$valid?'accept':'reject';$reason=$valid?'validated retained file':'image validation';
 }
}
echo json_encode(['php'=>PHP_VERSION,'method'=>$_SERVER['REQUEST_METHOD'],'post_keys'=>array_keys($_POST),'override'=>$_POST['_method']??null,'files'=>$files,'raw_input_bytes'=>strlen(file_get_contents('php://input')),'content_length'=>$size,'decision'=>$decision,'reason'=>$reason],JSON_THROW_ON_ERROR);
