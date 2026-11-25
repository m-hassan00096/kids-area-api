<?php
// Disposable feasibility probe. Not application code or a production validator.
declare(strict_types=1);
function out(string $case,array $data):void {echo 'PROBE '.json_encode(['case'=>$case]+$data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;}
function need(bool $v,string $msg):void {if(!$v)throw new RuntimeException($msg);}
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
// Disposable format-aware structural feasibility checks; not production code.
function jpegStructure(string $b):string{
 $n=strlen($b);if($n<4||substr($b,0,2)!=="\xff\xd8")return 'bad_soi';
 $p=2;$scan=false;$scans=0;$frame=false;$frames=[0xc0,0xc1,0xc2,0xc3,0xc5,0xc6,0xc7,0xc9,0xca,0xcb,0xcd,0xce,0xcf];$restart=0;
 while($p<$n){
  if($scan){
   while($p<$n && ord($b[$p])!==255)$p++;
   if($p===$n)return 'missing_eoi';
  }elseif(ord($b[$p])!==255)return 'expected_marker';
  $p++;while($p<$n&&ord($b[$p])===255)$p++;
  if($p===$n)return 'truncated_marker';$m=ord($b[$p++]);
  if($scan&&$m===0)continue;
  if($scan&&$m>=0xd0&&$m<=0xd7){if($m!==0xd0+$restart)return 'restart_order';$restart=($restart+1)%8;continue;}
  $scan=false;
  if($m===0xd9)return $frame&&$scans>0&&$p===$n?'ok':'early_eoi_or_trailing';
  if($m===0||$m===0xd8||($m>=0xd0&&$m<=0xd7))return 'unexpected_marker';
  if($m===1)continue;
  if(!in_array($m,array_merge($frames,[0xc4,0xcc,0xda,0xdb,0xdc,0xdd,0xde,0xdf,0xfe],range(0xe0,0xef)),true))return 'unsupported_marker';
  if($p+2>$n)return 'truncated_length';$len=unpack('n',substr($b,$p,2))[1];
  if($len<2||$len>$n-$p)return 'invalid_segment_length';
  if(in_array($m,$frames,true)){
   if($frame||$len<11||$len!==8+3*ord($b[$p+7]))return 'invalid_frame';
   $frame=true;
  }
  if($m===0xda){
   if(!$frame||$len<8||$len!==6+2*ord($b[$p+2]))return 'invalid_scan';
   $scan=true;$scans++;$restart=0;
  }
  if($m===0xdd&&$len!==4)return 'invalid_restart_interval';
  $p+=$len;
 }
 return 'missing_eoi';
}

function structure(string $b,string $type):string{
 if($type==='png'){
  if(substr($b,0,8)!=="\x89PNG\r\n\x1a\n")return 'bad_signature';
  $pos=8;$n=strlen($b);$first=true;$idat=false;$idatEnded=false;$palette=false;
  while($pos+12<=$n){
   $len=unpack('N',substr($b,$pos,4))[1];$tag=substr($b,$pos+4,4);
   if($len>$n-$pos-12)return 'truncated_chunk';
   $payload=substr($b,$pos+8,$len);
   if(!hash_equals(pack('N',crc32($tag.$payload)),substr($b,$pos+8+$len,4)))return 'crc_error';
   if(!preg_match('/^[A-Za-z]{2}[A-Z][A-Za-z]$/D',$tag))return 'invalid_chunk_type';
   if($first && ($tag!=='IHDR'||$len!==13))return 'missing_ihdr';
   if(!$first&&$tag==='IHDR')return 'duplicate_ihdr';$first=false;
   if(ctype_upper($tag[0])&&!in_array($tag,['IHDR','PLTE','IDAT','IEND'],true))return 'unknown_critical';
   if($tag==='PLTE'){if($palette||$idat||$len<3||$len>768||$len%3)return 'invalid_palette';$palette=true;}
   if($idat&&$tag!=='IDAT')$idatEnded=true;
   if(in_array($tag,['acTL','fcTL','fdAT'],true))return 'animation';
   if($tag==='IDAT'){if($idatEnded)return 'noncontiguous_idat';$idat=true;}
   $pos+=12+$len;
   if($tag==='IEND')return $len===0 && $idat && $pos===$n?'ok':'invalid_end_or_trailing';
  }
  return 'missing_end';
 }
 if($type==='webp'){
  if(substr($b,0,4)!=='RIFF'||substr($b,8,4)!=='WEBP'||strlen($b)<12)return 'bad_signature';
  if(unpack('V',substr($b,4,4))[1]!==strlen($b)-8)return 'riff_size_mismatch';
  $p=12;$n=strlen($b);$image=false;$extended=false;$alpha=false;
  while($p+8<=$n){$tag=substr($b,$p,4);$len=unpack('V',substr($b,$p+4,4))[1];if($len>$n-$p-8)return 'truncated_chunk';
   if(in_array($tag,['ANIM','ANMF'],true)||($tag==='VP8X'&&$len>=1&&(ord($b[$p+8])&2)))return 'animation';
   if($tag==='VP8X'){if($extended||$p!==12||$len!==10||(ord($b[$p+8])&0xc1))return 'invalid_vp8x';$extended=true;}
   if($tag==='ALPH'){if(!$extended||$alpha||$image)return 'invalid_alpha';$alpha=true;}
   if(in_array($tag,['VP8 ','VP8L'],true)){if($image||$len<5||($tag==='VP8L'&&$alpha))return 'invalid_image_chunk';$image=true;}
   if($len%2&&($p+8+$len>=$n||ord($b[$p+8+$len])!==0))return 'invalid_padding';
   $p+=8+$len+($len%2);
  }
  return $p===$n&&$image?'ok':'invalid_end';
 }
 if($type==='jpg')return jpegStructure($b);
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
  $decode=$bounded&&strlen($bytes)>0&&strlen($bytes)<=2097152&&structure($bytes,$expected)==='ok'?imagecreatefromstring($bytes):false;
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
