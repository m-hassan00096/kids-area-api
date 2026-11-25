<?php
declare(strict_types=1);
header('Content-Type: application/json');
$files=[];
foreach($_FILES as $key=>$file){
 $files[$key]=['name'=>$file['name'],'size'=>$file['size'],'error'=>$file['error'],'is_array'=>is_array($file['name'])];
}
echo json_encode(['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'method'=>$_SERVER['REQUEST_METHOD'],'post_keys'=>array_keys($_POST),'override'=>$_POST['_method']??null,'files'=>$files,'content_length'=>(int)($_SERVER['CONTENT_LENGTH']??0)],JSON_THROW_ON_ERROR);
