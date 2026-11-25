<?php
declare(strict_types=1);
require __DIR__.'/shared.php';
$dir=$argv[1];
need(PHP_MAJOR_VERSION===8&&PHP_MINOR_VERSION===2,'PHP8.2 required');
need(extension_loaded('gd')&&extension_loaded('fileinfo'),'extensions');
out('platform',['php'=>PHP_VERSION,'gd'=>gd_info(),'memory_limit'=>ini_get('memory_limit')]);
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
$progress=picture(64,32);imageinterlace($progress,true);$progressive=encode($progress,'jpg');unset($progress);
need(str_contains($progressive,"\xff\xc2"),'fixture not progressive');
need(inspect('valid_progressive_jpeg',$progressive,'jpg',true)['selected_policy_pass'],'progressive');
$withApp=substr($jpeg,0,2)."\xff\xe1".pack('n',8)."x\xff\xd9abc".substr($jpeg,2);
need(inspect('jpeg_embedded_marker_in_app_segment',$withApp,'jpg')['selected_policy_pass'],'length-aware metadata');
$cases=[
 'jpeg_invalid_length'=>[substr($jpeg,0,4)."\x00\x01".substr($jpeg,6),'jpg'],
 'jpeg_segment_overrun'=>[substr($jpeg,0,4)."\xff\xff".substr($jpeg,6),'jpg'],
 'jpeg_concatenated'=>[$jpeg.$jpeg,'jpg'],
 'jpeg_missing_scan'=>["\xff\xd8\xff\xd9",'jpg'],
 'png_duplicate_ihdr'=>[substr($png,0,33).substr($png,8,25).substr($png,33),'png'],
 'webp_riff_bad_size'=>[substr($webp,0,4).pack('V',0).substr($webp,8),'webp'],
 'empty'=>['','png'],'spoof_jpeg_as_png'=>[$jpeg,'png'],'truncated_png'=>[substr($png,0,-8),'png'],
 'corrupt_png_crc'=>[substr($png,0,29).chr(ord($png[29])^1).substr($png,30),'png'],
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
 $r=inspect($name,$bytes,$type);need(!$r['selected_policy_pass'],"unexpected pass: $name");
}
// Animated WebP framing probe: VP8X animation flag and ANIM chunk, not a full animated image.
$vp8x="VP8X".pack('V',10)."\x02\x00\x00\x00\x07\x00\x00\x07\x00\x00";
$anim="ANIM".pack('V',6).str_repeat("\x00",6);
$riff='WEBP'.$vp8x.$anim.substr($webp,12);$animated='RIFF'.pack('V',strlen($riff)).$riff;
need(!inspect('webp_animation_marker',$animated,'webp')['selected_policy_pass'],'webp animation');
// Construct two-frame animation container fixtures from native encoded image data.
$chunks=[];$p=8;while($p+12<=strlen($png)){$len=unpack('N',substr($png,$p,4))[1];$tag=substr($png,$p+4,4);$chunks[]=[$tag,substr($png,$p+8,$len)];$p+=12+$len;}
$frameData='';$headPng=substr($png,0,8);
foreach($chunks as [$tag,$data]){if($tag==='IDAT')$frameData.=$data;elseif($tag!=='IEND')$headPng.=pngchunk($tag,$data);}
$frameControl=static fn(int $seq):string=>pack('NNNNNnnCC',$seq,8,8,0,0,1,10,0,0);
$apng=$headPng.pngchunk('acTL',pack('NN',2,0)).pngchunk('fcTL',$frameControl(0)).pngchunk('IDAT',$frameData).pngchunk('fcTL',$frameControl(1)).pngchunk('fdAT',pack('N',2).$frameData).pngchunk('IEND','');
need(!inspect('apng_two_frames',$apng,'png')['selected_policy_pass'],'apng');
$frameChunks='';$p=12;while($p+8<=strlen($webp)){$tag=substr($webp,$p,4);$len=unpack('V',substr($webp,$p+4,4))[1];$take=8+$len+($len%2);if(in_array($tag,['ALPH','VP8 ','VP8L'],true))$frameChunks.=substr($webp,$p,$take);$p+=$take;}
$riffChunk=static fn(string $tag,string $payload):string=>$tag.pack('V',strlen($payload)).$payload.(strlen($payload)%2?"\x00":'');
$vp8x=$riffChunk('VP8X',"\x12\x00\x00\x00\x07\x00\x00\x07\x00\x00");
$frameHeader="\x00\x00\x00\x00\x00\x00\x07\x00\x00\x07\x00\x00\x64\x00\x00\x02";
$frame=$riffChunk('ANMF',$frameHeader.$frameChunks);
$riff='WEBP'.$vp8x.$riffChunk('ANIM',str_repeat("\x00",6)).$frame.$frame;
need(!inspect('webp_two_frames','RIFF'.pack('V',strlen($riff)).$riff,'webp')['selected_policy_pass'],'webp frames');
unset($cases,$wideBytes,$maxBytes,$overBytes,$png,$jpeg,$webp,$animated,$riff,$apng,$chunks,$frameData,$frameChunks,$frame);
$start=hrtime(true);$hash=password_hash('synthetic-only-probe',PASSWORD_ARGON2ID,['memory_cost'=>65536,'time_cost'=>4,'threads'=>1]);need(is_string($hash)&&password_verify('synthetic-only-probe',$hash),'argon verify');
$usage=getrusage();out('memory_and_hash',['hash_verify'=>true,'argon_memory_kib'=>65536,'argon_time_cost'=>4,'threads'=>1,'hash_plus_verify_ms'=>round((hrtime(true)-$start)/1e6,2),'php_peak_bytes'=>memory_get_peak_usage(true),'max_rss_kib_linux'=>$usage['ru_maxrss']??null,'note'=>'sequential image then Argon2id; process peak RSS includes native allocations; not host capacity']);
out('images_end',['status'=>'observations_complete','not_production_validator'=>true]);
