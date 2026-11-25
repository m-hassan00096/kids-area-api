<?php
// Disposable feasibility probe. Not application code or a production validator.
declare(strict_types=1);
$dir=$argv[1];
function out(string $case,array $data):void {echo 'PROBE '.json_encode(['case'=>$case]+$data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;}
function need(bool $v,string $msg):void {if(!$v)throw new RuntimeException($msg);}
need(PHP_MAJOR_VERSION===8 && PHP_MINOR_VERSION===2,'Target PHP 8.2 required');
need(extension_loaded('gd') && extension_loaded('fileinfo'),'Required extensions missing');
$gd=gd_info();
foreach(['PNG Support','JPEG Support','WebP Support'] as $k)need(($gd[$k]??false)===true,$k);
need(in_array('argon2id',password_algos(),true),'Argon2id unavailable');
out('platform',['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'gd'=>$gd,'fileinfo'=>extension_loaded('fileinfo'),'argon2id'=>true,'memory_limit'=>ini_get('memory_limit'),'upload_max_filesize'=>ini_get('upload_max_filesize'),'post_max_size'=>ini_get('post_max_size'),'directory_mode'=>substr(sprintf('%o',fileperms($dir)),-4)]);
function pngchunk(string $type,string $body):string{return pack('N',strlen($body)).$type.$body.pack('N',crc32($type.$body));}
function encode(GdImage $im,string $format):string{
 ob_start();
 try{$ok=match($format){'png'=>imagepng($im,null,6),'jpg'=>imagejpeg($im,null,85),'webp'=>imagewebp($im,null,85)};$bytes=ob_get_contents();need($ok && is_string($bytes) && $bytes!=='','encode failed');return $bytes;}
 finally{ob_end_clean();}
}
function picture(int $w,int $h):GdImage{
 $im=imagecreatetruecolor($w,$h);imagealphablending($im,false);imagesavealpha($im,true);
 imagefill($im,0,0,imagecolorallocatealpha($im,21,80,160,40));
 imagefilledrectangle($im,0,0,max(1,intdiv($w,2)),max(1,intdiv($h,2)),imagecolorallocatealpha($im,230,170,40,0));return $im;
}
// Structural probes for selected fixtures, deliberately not a comprehensive image validator.
function structure(string $b,string $type):string{
 if($type==='png'){
  if(substr($b,0,8)!=="\x89PNG\r\n\x1a\n")return 'bad_signature';
  $pos=8;$n=strlen($b);$first=true;$idat=false;
  while($pos+12<=$n){
   $len=unpack('N',substr($b,$pos,4))[1];$tag=substr($b,$pos+4,4);
   if($len>$n-$pos-12)return 'truncated_chunk';
   $payload=substr($b,$pos+8,$len);
   if(!hash_equals(pack('N',crc32($tag.$payload)),substr($b,$pos+8+$len,4)))return 'crc_error';
   if($first && ($tag!=='IHDR'||$len!==13))return 'missing_ihdr';$first=false;
   if(in_array($tag,['acTL','fcTL','fdAT'],true))return 'animation';
   if($tag==='IDAT')$idat=true;
   $pos+=12+$len;
   if($tag==='IEND')return $len===0 && $idat && $pos===$n?'ok':'invalid_end_or_trailing';
  }
  return 'missing_end';
 }
 if($type==='webp'){
  if(substr($b,0,4)!=='RIFF'||substr($b,8,4)!=='WEBP'||strlen($b)<12)return 'bad_signature';
  if(unpack('V',substr($b,4,4))[1]!==strlen($b)-8)return 'riff_size_mismatch';
  $p=12;$n=strlen($b);$image=false;
  while($p+8<=$n){$tag=substr($b,$p,4);$len=unpack('V',substr($b,$p+4,4))[1];if($len>$n-$p-8)return 'truncated_chunk';
   if(in_array($tag,['ANIM','ANMF'],true)||($tag==='VP8X'&&$len>=1&&(ord($b[$p+8])&2)))return 'animation';
   if(in_array($tag,['VP8 ','VP8L'],true))$image=true;
   $p+=8+$len+($len%2);
  }
  return $p===$n&&$image?'ok':'invalid_end';
 }
 if($type==='jpg'){
  // Terminal EOI check alone is intentionally insufficient for arbitrary JPEG.
  return substr($b,0,2)==="\xff\xd8"&&substr($b,-2)==="\xff\xd9"?'ok':'invalid_end_or_trailing';
 }
 return 'unsupported';
}
function inspect(string $name,string $bytes,string $expected,bool $normalize=false):array{
 $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
 $warnings=[];
 set_error_handler(static function(int $no,string $msg)use(&$warnings):bool{$warnings[]=$no;return true;});
 try{
  $info=getimagesizefromstring($bytes);
  $dimensions=$info!==false?[$info[0],$info[1]]:null;
  $bounded=$dimensions!==null && $dimensions[0]>0 && $dimensions[1]>0 && $dimensions[0]<=2048 && $dimensions[1]<=2048 && $dimensions[0]*$dimensions[1]<=4194304;
  $decode=$bounded?imagecreatefromstring($bytes):false;
 }finally{restore_error_handler();}
 $expectedMime=['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][$expected]??'unsupported';
 $struct=structure($bytes,$expected);
 $ok=$mime===$expectedMime&&strlen($bytes)>0&&strlen($bytes)<=2097152&&$bounded&&$decode instanceof GdImage&&$warnings===[]&&$struct==='ok';
 $result=['bytes'=>strlen($bytes),'mime'=>$mime,'dimensions'=>$dimensions,'bounded'=>$bounded,'gd_decoded'=>$decode instanceof GdImage,'warning_count'=>count($warnings),'structure_probe'=>$struct,'selected_policy_pass'=>$ok];
 if($normalize&&$ok){
  $w=imagesx($decode);$h=imagesy($decode);$ratio=min(1,512/max($w,$h));$nw=max(1,(int)round($w*$ratio));$nh=max(1,(int)round($h*$ratio));
  $im=imagecreatetruecolor($nw,$nh);imagealphablending($im,false);imagesavealpha($im,true);
  imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));
  need(imagecopyresampled($im,$decode,0,0,0,0,$nw,$nh,$w,$h),'resample');
  $encoded=encode($im,$expected);
  $roundtrip=imagecreatefromstring($encoded);need($roundtrip instanceof GdImage,'roundtrip');
  $result['output']=['bytes'=>strlen($encoded),'dimensions'=>[imagesx($roundtrip),imagesy($roundtrip)],'mime'=>(new finfo(FILEINFO_MIME_TYPE))->buffer($encoded),'alpha_sample'=>($expected==='jpg'?null:(imagecolorat($roundtrip,$nw-1,$nh-1)>>24)&127),'contains_metadata_marker'=>str_contains($encoded,'PROBE-METADATA')];
  need(strlen($encoded)<=2097152 && max($nw,$nh)<=512,'output bounds');
  unset($im,$roundtrip,$encoded);
 }
 unset($decode);
 out($name,$result);return $result;
}
$small=picture(8,8);$png=encode($small,'png');$jpeg=encode($small,'jpg');$webp=encode($small,'webp');unset($small);
file_put_contents("$dir/first.png",$png);
$other=picture(9,8);file_put_contents("$dir/second.png",encode($other,'png'));unset($other);
foreach(['png'=>$png,'jpg'=>$jpeg,'webp'=>$webp] as $type=>$bytes)need(inspect("valid_$type",$bytes,$type,true)['selected_policy_pass'],"valid $type");
$wide=picture(2048,1024);$wideBytes=encode($wide,'png');unset($wide);need(inspect('resize_2048x1024',$wideBytes,'png',true)['output']['dimensions']===[512,256],'resize');
$max=picture(2048,2048);$maxBytes=encode($max,'png');unset($max);need(inspect('max_dimensions',$maxBytes,'png',true)['selected_policy_pass'],'max dimensions');
$over=picture(2049,1);$overBytes=encode($over,'png');unset($over);need(!inspect('over_dimensions',$overBytes,'png')['selected_policy_pass'],'over dimensions');
// A legitimate PNG ancillary text chunk, with correct length/CRC and IEND, gives exact upload size.
$prefix="Comment\0PROBE-METADATA ";
foreach([2097152,2097153] as $size){
 $payload=$prefix.str_repeat('x',$size-strlen($png)-12-strlen($prefix));
 $bytes=substr($png,0,-12).pngchunk('tEXt',$payload).substr($png,-12);
 need(strlen($bytes)===$size,'exact fixture size');
 file_put_contents("$dir/size-$size.png",$bytes);
 $r=inspect("boundary_$size",$bytes,'png',true);
 need($r['selected_policy_pass']===($size===2097152),'byte boundary');
 if($size===2097152)need($r['output']['contains_metadata_marker']===false,'metadata strip');
 unset($payload,$bytes);
}
$cases=[
 'empty'=>['','png'],'spoof_jpeg_as_png'=>[$jpeg,'png'],'truncated_png'=>[substr($png,0,-8),'png'],
 'corrupt_png_crc'=>[substr($png,0,29)."\x00".substr($png,30),'png'],
 'png_plus_jpeg'=>[$png.$jpeg,'png'],'jpeg_plus_png'=>[$jpeg.$png,'jpg'],
 'jpeg_plus_png_and_second_eoi'=>[$jpeg.$png."\xff\xd9",'jpg'],
 'webp_plus_png'=>[$webp.$png,'webp'],'truncated_webp'=>[substr($webp,0,-5),'webp'],
 'truncated_jpeg'=>[substr($jpeg,0,-2),'jpg'],
 'svg'=>['<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>','png'],
 'gif'=>[base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),'png'],
 // A validly framed animation marker inserted into synthetic PNG; not a complete APNG animation.
 'png_animation_marker'=>[substr($png,0,33).pngchunk('acTL',pack('NN',2,0)).substr($png,33),'png'],
];
foreach($cases as $name=>[$bytes,$type]){
 if($bytes===''){out($name,['bytes'=>0,'selected_policy_pass'=>false,'reason'=>'empty']);continue;}
 $r=inspect($name,$bytes,$type);if($r['selected_policy_pass'])out('policy_gap',['fixture'=>$name]);
}
// Animated WebP framing probe: VP8X animation flag and ANIM chunk, not a full animated image.
$vp8x="VP8X".pack('V',10)."\x02\x00\x00\x00\x07\x00\x00\x07\x00\x00";
$anim="ANIM".pack('V',6).str_repeat("\x00",6);
$riff='WEBP'.$vp8x.$anim.substr($webp,12);$animated='RIFF'.pack('V',strlen($riff)).$riff;
inspect('webp_animation_marker',$animated,'webp');
// Construct two-frame animation container fixtures from native encoded image data.
$chunks=[];$p=8;while($p+12<=strlen($png)){$len=unpack('N',substr($png,$p,4))[1];$tag=substr($png,$p+4,4);$chunks[]=[$tag,substr($png,$p+8,$len)];$p+=12+$len;}
$frameData='';$headPng=substr($png,0,8);
foreach($chunks as [$tag,$data]){if($tag==='IDAT')$frameData.=$data;elseif($tag!=='IEND')$headPng.=pngchunk($tag,$data);}
$frameControl=static fn(int $seq):string=>pack('NNNNNnnCC',$seq,8,8,0,0,1,10,0,0);
$apng=$headPng.pngchunk('acTL',pack('NN',2,0)).pngchunk('fcTL',$frameControl(0)).pngchunk('IDAT',$frameData).pngchunk('fcTL',$frameControl(1)).pngchunk('fdAT',pack('N',2).$frameData).pngchunk('IEND','');
inspect('apng_two_frames',$apng,'png');
$frameChunks='';$p=12;while($p+8<=strlen($webp)){$tag=substr($webp,$p,4);$len=unpack('V',substr($webp,$p+4,4))[1];$take=8+$len+($len%2);if(in_array($tag,['ALPH','VP8 ','VP8L'],true))$frameChunks.=substr($webp,$p,$take);$p+=$take;}
$riffChunk=static fn(string $tag,string $payload):string=>$tag.pack('V',strlen($payload)).$payload.(strlen($payload)%2?"\x00":'');
$vp8x=$riffChunk('VP8X',"\x12\x00\x00\x00\x07\x00\x00\x07\x00\x00");
$frameHeader="\x00\x00\x00\x00\x00\x00\x07\x00\x00\x07\x00\x00\x64\x00\x00\x02";
$frame=$riffChunk('ANMF',$frameHeader.$frameChunks);
$riff='WEBP'.$vp8x.$riffChunk('ANIM',str_repeat("\x00",6)).$frame.$frame;
inspect('webp_two_frames','RIFF'.pack('V',strlen($riff)).$riff,'webp');
unset($cases,$wideBytes,$maxBytes,$overBytes,$png,$jpeg,$webp,$animated,$riff,$apng,$chunks,$frameData,$frameChunks,$frame);
$start=hrtime(true);$hash=password_hash('synthetic-only-probe',PASSWORD_ARGON2ID,['memory_cost'=>65536,'time_cost'=>4,'threads'=>1]);need(is_string($hash)&&password_verify('synthetic-only-probe',$hash),'argon verify');
$usage=getrusage();out('memory_and_hash',['hash_verify'=>true,'argon_memory_kib'=>65536,'argon_time_cost'=>4,'threads'=>1,'hash_plus_verify_ms'=>round((hrtime(true)-$start)/1e6,2),'php_peak_bytes'=>memory_get_peak_usage(true),'max_rss_kib_linux'=>$usage['ru_maxrss']??null,'note'=>'sequential image then Argon2id; process peak RSS includes native allocations; not host capacity']);
out('images_end',['status'=>'observations_complete','not_production_validator'=>true]);
