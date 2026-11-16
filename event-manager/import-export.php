<?php
/** Portable, administrator-only CSV migration wizard. */
if (!defined('ABSPATH')) exit;
function caes_io_types(){return array(
 'venues'=>array('label'=>'Venues','headers'=>array('key','name','address','description')),
 'events'=>array('label'=>'Event Archives','headers'=>array('year','format','venue_key','description')),
 'speakers'=>array('label'=>'Speakers','headers'=>array('key','name','job_title','company','biography','photo_url','linkedin','website','bluesky','instagram','x','years')),
 'tracks'=>array('label'=>'Tracks','headers'=>array('year','key','name','room')),
 'agenda'=>array('label'=>'Agenda','headers'=>array('key','year','title','description','date','start','end','track_key','type','speaker_keys','moderator_key')),
 'recordings'=>array('label'=>'Recordings','headers'=>array('key','year','title','description','video_url','slides_url','speaker_keys','location','display_order')),
 'tiers'=>array('label'=>'Sponsorship Tiers','headers'=>array('key','name','priority','active')),
 'sponsors'=>array('label'=>'Sponsors (including event years)','headers'=>array('key','name','website_url','logo_url','tracking','year','tier_key','visible','year_logo_url')),
 'settings'=>array('label'=>'Agenda Colours','headers'=>array('session_type','colour')),
 );}
function caes_io_steps(){return array('venues','events','speakers','tracks','agenda','recordings','tiers','sponsors','settings');}
function caes_io_key($key){return sanitize_key((string)$key);}
function caes_io_year($year){return preg_match('/^20\d{2}$/D',(string)$year)?(string)$year:'';}
function caes_io_split($v){return array_values(array_unique(array_filter(array_map('trim',explode('|',(string)$v)))));}
/** Resolve a speaker by its portable key first, then by an unambiguous full name. */
function caes_io_resolve_speaker($value){
 $value=trim((string)$value);
 if($value==='')return array('id'=>0,'error'=>'Speaker is blank');
 $id=caes_io_lookup('caes_speaker',$value);
 if($id)return array('id'=>$id,'name'=>get_the_title($id),'error'=>'');
 $needle=remove_accents($value);
 $needle=preg_replace('/\s+/u',' ',trim($needle));
 $needle=mb_strtolower($needle,'UTF-8');
 $matches=array();
 $ids=get_posts(array('post_type'=>'caes_speaker','post_status'=>array('publish','draft','pending','private','future'),'numberposts'=>-1,'fields'=>'ids'));
 foreach($ids as $candidate){
  $name=mb_strtolower(preg_replace('/\s+/u',' ',trim(remove_accents(get_the_title($candidate)))),'UTF-8');
  if($name===$needle)$matches[]=(int)$candidate;
 }
 if(count($matches)===1)return array('id'=>$matches[0],'name'=>get_the_title($matches[0]),'error'=>'');
 if(count($matches)>1)return array('id'=>0,'error'=>'Ambiguous speaker name: '.$value.' ('.count($matches).' matches). Use an exact speaker key.');
 return array('id'=>0,'error'=>'Speaker not found: '.$value);
}
function caes_io_speaker_ids($value){
 $ids=array();
 foreach(caes_io_split($value) as $part){$resolved=caes_io_resolve_speaker($part);if($resolved['id'])$ids[]=$resolved['id'];}
 return array_values(array_unique($ids));
}
function caes_io_post_key($id){$key=get_post_meta($id,'_caes_import_key',true);if(!$key){$key='wp_'.get_post_type($id).'_'.$id;update_post_meta($id,'_caes_import_key',$key);}return $key;}
function caes_io_find_post($type,$key){
 $ids=get_posts(array('post_type'=>$type,'post_status'=>'any','numberposts'=>2,'fields'=>'ids','meta_key'=>'_caes_import_key','meta_value'=>$key));
 return count($ids)===1?(int)$ids[0]:0;
}
function caes_io_lookup($type,$key){$id=caes_io_find_post($type,$key);return $id && get_post_type($id)===$type?$id:0;}
function caes_io_sample($type){$samples=array(
 'venues'=>array('venue_example','Example Conference Centre','100 Example St, Toronto, ON','Downtown event venue'),
 'events'=>array('2024','In person','venue_example','Annual conference'),
 'speakers'=>array('speaker_jane','Jane Smith','CRM Director','Example Inc.','Speaker biography','https://example.com/photo.jpg','https://linkedin.com/in/example','https://example.com','','','','2024|2026'),
 'tracks'=>array('2024','main','Main Stage','Auditorium'),
 'agenda'=>array('session_2024_001','2024','Email Strategy','Session description','2024-09-20','09:00','09:45','main','Presentation','Jane Smith|Alex Example','Morgan Example'),
 'recordings'=>array('recording_001','2024','Email Strategy','Session description','https://youtu.be/dQw4w9WgXcQ','','Jane Smith|Alex Example','', '1'),
 'tiers'=>array('presenting','Presenting Sponsor','1','1'),
 'sponsors'=>array('sponsor_example','Example Inc.','https://example.com','','1','2024','presenting','0',''),
 'settings'=>array('Presentation','#FFFFFF'));
 return $samples[$type]??array();
}
/** Samples are illustrative, not real event records. */
function caes_io_sample_rows($type){
 $first=caes_io_sample($type);
 if($type==='agenda')return array($first,array('session_2024_002','2024','Solo presentation','Another sample','2024-09-20','10:00','10:45','main','Presentation','Jane Smith',''));
 if($type==='recordings')return array($first,array('recording_002','2024','Solo recording','Another sample','https://youtu.be/dQw4w9WgXcQ','','speaker_jane','','2'));
 return array($first);
}
function caes_io_csv_safe($value){$v=(string)$value;return preg_match('/^\s*[=+@\-\t\r]/u',$v)?"'".$v:$v;}
function caes_io_export_rows($type,$year=''){
 $rows=array();$add=function($row,$y='')use(&$rows,$year){if(!$year||!$y||$y===$year)$rows[]=$row;};
 if($type==='venues')foreach(caes_em_venues() as $k=>$v)$add(array($k,$v['name']??'',$v['address']??'',$v['details']??''));
 if($type==='events')foreach(caes_em_archives() as $y=>$v)$add(array($y,$v['format']??'In person',$v['venue_id']??'',$v['description']??''),$y);
 if($type==='tiers')foreach(caes_em_sponsor_tiers() as $k=>$v)$add(array($k,$v['name']??'',$v['order']??0,$v['active']??0));
 if($type==='sponsors'){
  $assigned=array();
  foreach(caes_em_sponsor_assignments() as $a)foreach((array)($a['years']??array()) as $y){
   $k=$a['sponsor']??'';if(!isset(caes_em_sponsors()[$k]))continue;
   $v=caes_em_sponsors()[$k];$assigned[$k]=true;
   $add(array($k,$v['name']??'',$v['url']??'',!empty($v['logo'])?wp_get_attachment_url($v['logo']):'',$v['tracking']??0,$y,$a['tier']??'',!empty($a['visible'])?1:0,!empty($a['logo'])?wp_get_attachment_url($a['logo']):''),$y);
  }
  if(!$year)foreach(caes_em_sponsors() as $k=>$v)if(empty($assigned[$k]))$add(array($k,$v['name']??'',$v['url']??'',!empty($v['logo'])?wp_get_attachment_url($v['logo']):'',$v['tracking']??0,'','','',''));
 }
 if($type==='assignments')foreach(caes_em_sponsor_assignments() as $k=>$v)foreach(($v['years']??array()) as $y)$add(array($k,$v['sponsor']??'',$v['tier']??'',$y,!empty($v['logo'])?wp_get_attachment_url($v['logo']):''),$y);
 if($type==='tracks')foreach(($year?array($year):array_keys(caes_em_archives())) as $y)foreach(caes_em_tracks($y) as $v)$add(array($y,$v['id']??'',$v['name']??'',$v['room']??''),$y);
 if($type==='settings')foreach(caes_em_colours() as $k=>$v)$add(array($k,$v));
 $posttypes=array('speakers'=>'caes_speaker','agenda'=>'caes_session','recordings'=>'caes_recording');
 if(isset($posttypes[$type])){
  $posts=get_posts(array('post_type'=>$posttypes[$type],'post_status'=>array('publish','draft','pending','private','future'),'numberposts'=>-1));
  foreach($posts as $p){$id=$p->ID;
   if($type==='speakers'){$ys=wp_get_object_terms($id,'caes_speaker_year',array('fields'=>'names'));$ys=is_wp_error($ys)?array():$ys;if($year&&!in_array($year,$ys,true))continue;
    $add(array(caes_io_post_key($id),$p->post_title,get_post_meta($id,'caes_job_title',true),get_post_meta($id,'caes_company',true),$p->post_content,get_post_meta($id,'caes_photo_url',true)?:get_the_post_thumbnail_url($id,'full'),get_post_meta($id,'caes_linkedin',true),get_post_meta($id,'caes_website',true),get_post_meta($id,'caes_bluesky',true),get_post_meta($id,'caes_instagram',true),get_post_meta($id,'caes_x',true),implode('|',$ys)));
   }elseif($type==='agenda'){$y=get_post_meta($id,'caes_session_year',true);$speakers=(array)get_post_meta($id,'caes_session_speakers',true);$mod=(int)get_post_meta($id,'caes_session_moderator',true);
    $add(array(caes_io_post_key($id),$y,$p->post_title,$p->post_content,get_post_meta($id,'caes_session_date',true),get_post_meta($id,'caes_session_start',true),get_post_meta($id,'caes_session_end',true),get_post_meta($id,'caes_session_track',true),get_post_meta($id,'caes_session_type',true),implode('|',array_map('caes_io_post_key',array_filter($speakers))),$mod?caes_io_post_key($mod):''),$y);
   }else{$y=get_post_meta($id,'caes_rec_year',true);$speakers=(array)get_post_meta($id,'caes_rec_speaker_ids',true);
    $add(array(caes_io_post_key($id),$y,$p->post_title,$p->post_content,get_post_meta($id,'caes_rec_video',true),get_post_meta($id,'caes_rec_slides',true),implode('|',array_map('caes_io_post_key',array_filter($speakers))),get_post_meta($id,'caes_rec_location',true),get_post_meta($id,'caes_rec_order',true)),$y);
   }
  }
 }
 return $rows;
}
add_action('admin_post_caes_io_download',function(){
 if(!current_user_can('manage_options'))wp_die('Permission denied',403);check_admin_referer('caes_io_download');
 $type=caes_io_key($_GET['dataset']??'');$types=caes_io_types();if(!isset($types[$type]))wp_die('Unknown dataset');
 $sample=!empty($_GET['sample']);$year=caes_io_year($_GET['year']??'');
 nocache_headers();header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="event-manager-'.$type.($year?'-'.$year:'').($sample?'-sample':'').'.csv"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$types[$type]['headers']);foreach($sample?caes_io_sample_rows($type):caes_io_export_rows($type,$year) as $row)fputcsv($out,array_map('caes_io_csv_safe',$row));fclose($out);exit;
});
function caes_io_validate($type,$row,$year){
 $errors=array();$key=$row['key']??'';$y=$row['year']??'';
 if(isset($row['key'])&&!preg_match('/^[A-Za-z0-9_-]+$/D',(string)$key))$errors[]='A key containing letters (uppercase or lowercase), numbers, underscores, or hyphens is required.';
 if(isset($row['year'])&&!caes_io_year($y))$errors[]='Invalid event year.';
 if($year&&isset($row['year'])&&$y!==$year)$errors[]='Year does not match the selected import year.';
 if(isset($row['name'])&&!trim($row['name']))$errors[]='Name is required.';
 if(isset($row['title'])&&!trim($row['title']))$errors[]='Title is required.';
 if($type==='events'&&!in_array($row['format'],array('Virtual','In person','Hybrid'),true))$errors[]='Invalid event format.';
 if($type==='events'&&!empty($row['venue_key'])&&!isset(caes_em_venues()[$row['venue_key']]))$errors[]='Venue key does not exist.';
 if($type==='agenda'){
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$row['date'])||!strtotime($row['date']))$errors[]='Invalid session date.';
  foreach(array('start','end') as $field)if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$row[$field]))$errors[]='Invalid '.$field.' time.';
  if($row['end']<=$row['start'])$errors[]='End time must be later than start time.';
 }
 if($type==='recordings'&&(!function_exists('caes_em_youtube_id')||!caes_em_youtube_id($row['video_url'])))$errors[]='A supported YouTube video URL is required.';
 foreach(array('website_url','linkedin','website','bluesky','instagram','x','slides_url','logo_url','photo_url') as $field)if(!empty($row[$field])&&!wp_http_validate_url($row[$field]))$errors[]='Invalid URL: '.$field;
 if($type==='sponsors'){
  if(!wp_http_validate_url($row['website_url']))$errors[]='Valid sponsor website URL required.';
  if(empty($row['year'])||!caes_io_year($row['year']))$errors[]='A valid year is required.';
  if(empty($row['tier_key'])||!isset(caes_em_sponsor_tiers()[$row['tier_key']]))$errors[]='Existing sponsorship tier key required.';
  if(!in_array((string)$row['visible'],array('0','1'),true))$errors[]='Visible must be 0 or 1.';
  if(!in_array((string)$row['tracking'],array('0','1'),true))$errors[]='Tracking must be 0 or 1.';
  if(!empty($row['year_logo_url'])&&!wp_http_validate_url($row['year_logo_url']))$errors[]='Invalid year logo URL.';
 }
 if($type==='assignments'&&(!isset(caes_em_sponsors()[$row['sponsor_key']])||!isset(caes_em_sponsor_tiers()[$row['tier_key']])))$errors[]='Sponsor or tier key not found.';
 if(in_array($type,array('agenda','recordings'),true)){
  foreach(caes_io_split($row['speaker_keys']??'') as $k){$resolved=caes_io_resolve_speaker($k);if($resolved['error'])$errors[]=$resolved['error'];}
  if($type==='agenda'&&!empty($row['moderator_key'])){$resolved=caes_io_resolve_speaker($row['moderator_key']);if($resolved['error'])$errors[]='Moderator: '.$resolved['error'];}
 }
 if($type==='agenda'&&!in_array($row['type'],caes_em_session_types(),true))$errors[]='Invalid session type.';
 if($type==='settings'&&!preg_match('/^#[a-fA-F0-9]{6}$/D',$row['colour']))$errors[]='Invalid colour.';
 return $errors;
}
function caes_io_existing($type,$row){
 $key=$row['key']??'';
 if($type==='venues')return isset(caes_em_venues()[$key]);
 if($type==='events')return isset(caes_em_archives()[$row['year']]);
 if($type==='tiers')return isset(caes_em_sponsor_tiers()[$key]);
 if($type==='sponsors'){foreach(caes_em_sponsor_assignments() as $a)if(($a['sponsor']??'')===$key&&in_array($row['year']??'',(array)($a['years']??array()),true))return true;return false;}
 if($type==='assignments')return isset(caes_em_sponsor_assignments()[$key]);
 if($type==='tracks'){foreach(caes_em_tracks($row['year']) as $t)if($t['id']===$key)return true;return false;}
 if($type==='settings')return isset(caes_em_colours()[$row['session_type']]);
 return (bool)caes_io_find_post(array('speakers'=>'caes_speaker','agenda'=>'caes_session','recordings'=>'caes_recording')[$type],$key);
}
/** One row represents one sponsor in one year. Existing visible assignments are never overwritten. */
function caes_io_sponsor_media_id($url){
 if(!$url)return 0;
 $id=attachment_url_to_postid(esc_url_raw($url));
 return $id && get_post_type($id)==='attachment' && wp_attachment_is_image($id)?(int)$id:0;
}
function caes_io_save_sponsor($row,$mode){
 $k=$row['key'];$year=$row['year'];$sponsors=caes_em_sponsors();$assignments=caes_em_sponsor_assignments();
 $match='';foreach($assignments as $id=>$a)if(($a['sponsor']??'')===$k&&in_array($year,(array)($a['years']??array()),true)){$match=$id;break;}
 if($match!==''&&!empty($assignments[$match]['visible']))return 'skipped';
 if($mode==='duplicate')return 'skipped'; // Never create duplicate sponsor identities or year assignments.
 $org_exists=isset($sponsors[$k]);
 // Do not alter a live organization's public details through an import.
 $live=false;foreach($assignments as $a)if(($a['sponsor']??'')===$k&&!empty($a['visible'])){$live=true;break;}
 if(!$org_exists||!$live){
  $old=$sponsors[$k]??array();
  $logo=caes_io_sponsor_media_id($row['logo_url']);
  $sponsors[$k]=array('name'=>sanitize_text_field($row['name']),'url'=>esc_url_raw($row['website_url']),'logo'=>$logo?:($old['logo']??0),'tracking'=>(int)$row['tracking']);
  update_option('caes_em_sponsors',$sponsors,false);
 }
 // A historical assignment may group several years. Split out this year when its tier differs.
 if($match!==''){
  $old=$assignments[$match];$years=(array)($old['years']??array());
  if(count($years)>1){
   $assignments[$match]['years']=array_values(array_diff($years,array($year)));
   $match='';
  }
 }
 if($match===''){
  $match='csv_'.sanitize_key($k).'_'.$year;
  if(isset($assignments[$match]))$match.='_'.wp_generate_password(6,false,false);
 }
 $old=$assignments[$match]??array();$year_logo=caes_io_sponsor_media_id($row['year_logo_url']);
 $assignments[$match]=array('sponsor'=>$k,'tier'=>$row['tier_key'],'years'=>array($year),'logo'=>$year_logo?:($old['logo']??0),'visible'=>0);
 // New and changed assignments remain hidden, regardless of the requested visible column.
 update_option('caes_em_sponsor_assignments',$assignments,false);
 return $org_exists?'updated':'created';
}
function caes_io_save($type,$row,$mode,$approved=false){
 $exists=caes_io_existing($type,$row);if($exists&&$mode==='create')return 'skipped';
 if($type==='sponsors')return caes_io_save_sponsor($row,$mode);
 // Shared option records may already be used on live pages. Never silently change them.
 if($exists&&$mode==='update'){
  if($type==='venues')foreach(caes_em_archives() as $event)if(($event['venue_id']??'')===$row['key']&&!empty($event['show']))return 'skipped';
  if($type==='sponsors')foreach(caes_em_sponsor_assignments() as $assignment)if(($assignment['sponsor']??'')===$row['key']&&!empty($assignment['visible']))return 'skipped';
  if($type==='tiers'&&!empty(caes_em_sponsor_tiers()[$row['key']]['active']))return 'skipped';
  if($type==='assignments'&&!empty(caes_em_sponsor_assignments()[$row['key']]['visible']))return 'skipped';
 }

 if($type==='events'&&$exists&&$mode==='duplicate')return 'skipped';
 if($type==='venues'){$all=caes_em_venues();$k=$row['key'];if($exists&&$mode==='duplicate')$k.='_copy_'.wp_generate_password(5,false,false);$all[$k]=array('name'=>sanitize_text_field($row['name']),'address'=>sanitize_text_field($row['address']),'details'=>sanitize_textarea_field($row['description']));update_option('caes_em_venues',$all,false);}
 elseif($type==='events'){$all=caes_em_archives();$old=$all[$row['year']]??array();$all[$row['year']]=array_merge($old,array('venue_id'=>$row['venue_key'],'format'=>$row['format'],'description'=>sanitize_textarea_field($row['description']),'show'=>0));update_option('caes_em_event_archives',$all,false);}
 elseif($type==='tiers'){$all=caes_em_sponsor_tiers();$k=$row['key'];if($exists&&$mode==='duplicate')$k.='_copy_'.wp_generate_password(5,false,false);$all[$k]=array('name'=>sanitize_text_field($row['name']),'order'=>(int)$row['priority'],'active'=>0);update_option('caes_em_sponsor_tiers',$all,false);}
 elseif($type==='sponsors'){$all=caes_em_sponsors();$k=$row['key'];if($exists&&$mode==='duplicate')$k.='_copy_'.wp_generate_password(5,false,false);$old=$all[$k]??array();$all[$k]=array('name'=>sanitize_text_field($row['name']),'url'=>esc_url_raw($row['website_url']),'logo'=>$old['logo']??0,'tracking'=>!empty($row['tracking'])?1:0);update_option('caes_em_sponsors',$all,false);}
 elseif($type==='assignments'){$all=caes_em_sponsor_assignments();$k=$row['key'];if($exists&&$mode==='duplicate')return 'skipped';$old=$all[$k]??array();$years=array_unique(array_merge($old['years']??array(),array($row['year'])));$all[$k]=array('sponsor'=>$row['sponsor_key'],'tier'=>$row['tier_key'],'years'=>array_values($years),'logo'=>$old['logo']??0,'visible'=>0);update_option('caes_em_sponsor_assignments',$all,false);}
 elseif($type==='tracks'){$all=get_option('caes_em_tracks_'.$row['year'],array());if(!is_array($all))$all=array();$k=$row['key'];if($exists&&$mode==='duplicate')$k.='_copy_'.wp_generate_password(5,false,false);$item=array('id'=>$k,'name'=>sanitize_text_field($row['name']),'room'=>sanitize_text_field($row['room']));$found=false;foreach($all as &$t)if(($t['id']??'')===$k){$t=$item;$found=true;break;}unset($t);if(!$found)$all[]=$item;update_option('caes_em_tracks_'.$row['year'],$all,false);}
 elseif($type==='settings'){$all=get_option('caes_em_session_colours',array());$all[$row['session_type']]=sanitize_hex_color($row['colour']);update_option('caes_em_session_colours',$all,false);}
 else{
  $posttype=array('speakers'=>'caes_speaker','agenda'=>'caes_session','recordings'=>'caes_recording')[$type];$id=caes_io_find_post($posttype,$row['key']);
  if($id&&$mode==='duplicate')$id=0;
  if($id&&get_post_status($id)==='publish'&&!$approved){
   $pending=get_post_meta($id,'_caes_import_pending',true);if(!is_array($pending))$pending=array();$pending[]=array('dataset'=>$type,'data'=>$row,'imported_at'=>current_time('mysql'));update_post_meta($id,'_caes_import_pending',$pending);return 'staged';
  }
  $post=array('post_type'=>$posttype,'post_title'=>sanitize_text_field($row['name']??$row['title']),'post_content'=>wp_kses_post($row['biography']??$row['description']??''),'post_status'=>($id&&$approved&&get_post_status($id)==='publish'?'publish':'draft'));if($id)$post['ID']=$id;
  $result=$id?wp_update_post($post,true):wp_insert_post($post,true);if(is_wp_error($result))return 'error: '.$result->get_error_message();$id=$result;
  $key=($exists&&$mode==='duplicate')?$row['key'].'_copy_'.wp_generate_password(5,false,false):$row['key'];update_post_meta($id,'_caes_import_key',$key);
  if($type==='speakers'){
   foreach(array('job_title'=>'caes_job_title','company'=>'caes_company','linkedin'=>'caes_linkedin','website'=>'caes_website','bluesky'=>'caes_bluesky','instagram'=>'caes_instagram','x'=>'caes_x') as $field=>$meta)update_post_meta($id,$meta,in_array($field,array('linkedin','website','bluesky','instagram','x'),true)?esc_url_raw($row[$field]):sanitize_text_field($row[$field]));
   if(!empty($row['photo_url']))update_post_meta($id,'caes_photo_url',esc_url_raw($row['photo_url']));
   $existing=wp_get_object_terms($id,'caes_speaker_year',array('fields'=>'names'));$existing=is_wp_error($existing)?array():$existing;$years=array_unique(array_merge($existing,caes_io_split($row['years'])));wp_set_object_terms($id,$years,'caes_speaker_year',false);
  }elseif($type==='agenda'){
   foreach(array('year','date','start','end','type') as $f)update_post_meta($id,'caes_session_'.$f,sanitize_text_field($row[$f]));
   update_post_meta($id,'caes_session_track',sanitize_text_field($row['track_key']));update_post_meta($id,'caes_session_speakers',caes_io_speaker_ids($row['speaker_keys']));update_post_meta($id,'caes_session_moderator',empty($row['moderator_key'])?0:caes_io_resolve_speaker($row['moderator_key'])['id']);
  }else{
   update_post_meta($id,'caes_rec_year',$row['year']);update_post_meta($id,'caes_rec_video',esc_url_raw($row['video_url']));update_post_meta($id,'caes_rec_youtube',esc_url_raw($row['video_url']));update_post_meta($id,'caes_rec_slides',esc_url_raw($row['slides_url']));update_post_meta($id,'caes_rec_location',sanitize_text_field($row['location']));update_post_meta($id,'caes_rec_order',(int)$row['display_order']);update_post_meta($id,'caes_rec_speaker_ids',caes_io_speaker_ids($row['speaker_keys']));
  }
 }
 return $exists?($mode==='duplicate'?'duplicated':'updated'):'created';
}
add_action('admin_menu',function(){add_submenu_page('event-manager','Import / Export','Import / Export','manage_options','event-manager-import','caes_io_page');},25);
function caes_io_page(){
 if(!current_user_can('manage_options'))wp_die('Permission denied',403);
 $types=caes_io_types();$type=caes_io_key($_GET['dataset']??'venues');if(!isset($types[$type]))$type='venues';$year=caes_io_year($_GET['year']??'');$mode=sanitize_key($_GET['mode']??'update');if(!in_array($mode,array('create','update','duplicate'),true))$mode='update';
 echo '<div class="wrap"><h1>Import / Export Wizard</h1><p>Import by year or section. All new content is drafted or hidden. Published records are staged for review, not overwritten. Administrators only.</p>';
 if(isset($_GET['imported'])){$report=get_transient('caes_io_report_'.get_current_user_id());if($report){echo '<div class="notice notice-info"><p>'.esc_html($report).'</p></div>';delete_transient('caes_io_report_'.get_current_user_id());}}
 echo '<form method="get"><input type="hidden" name="page" value="event-manager-import"><p><label>Event year (optional): <input name="year" pattern="20[0-9]{2}" value="'.esc_attr($year).'" placeholder="All years" style="width:100px"></label> &nbsp; <label>Section: <select name="dataset">';foreach($types as $k=>$t)echo '<option value="'.esc_attr($k).'" '.selected($type,$k,false).'>'.esc_html($t['label']).'</option>';echo '</select></label> &nbsp; <label>Mode: <select name="mode"><option value="update" '.selected($mode,'update',false).'>Create or update</option><option value="create" '.selected($mode,'create',false).'>New only</option><option value="duplicate" '.selected($mode,'duplicate',false).'>Duplicate (where supported)</option></select></label> <button class="button">Select step</button></p></form>';
 echo '<h2>Recommended import order</h2><ol>';foreach(caes_io_steps() as $step)echo '<li><a href="'.esc_url(add_query_arg(array('page'=>'event-manager-import','year'=>$year,'dataset'=>$step,'mode'=>$mode),admin_url('admin.php'))).'">'.esc_html($types[$step]['label']).'</a>'.($step===$type?' <strong>(selected)</strong>':'').'</li>';echo '</ol>';
 $base=array('action'=>'caes_io_download','dataset'=>$type,'year'=>$year);$sample=wp_nonce_url(add_query_arg(array_merge($base,array('sample'=>1)),admin_url('admin-post.php')),'caes_io_download');$export=wp_nonce_url(add_query_arg($base,admin_url('admin-post.php')),'caes_io_download');
 echo '<h2>'.esc_html($types[$type]['label']).'</h2><p><a class="button" href="'.esc_url($sample).'">Download sample CSV</a> <a class="button" href="'.esc_url($export).'">Export CSV</a></p><p>Required headers: <code>'.esc_html(implode(', ',$types[$type]['headers'])).'</code></p>';
 if($type==='sponsors')echo '<p><strong>One CSV, one row per sponsor per year.</strong> Repeat the same key for returning sponsors. Create sponsorship tiers first. New or updated year assignments remain hidden until reviewed in Sponsor Management. Logo URLs only resolve when the image is already in this WordPress Media Library; external images are not downloaded. The visible column records your intended status for reference, but importing never publishes an assignment.</p>';
 if($type==='agenda')echo '<p><strong>Session keys:</strong> Use a globally unique key for each session, such as <code>session_2019_001</code> or <code>session_2025_001</code>. Numbering can restart at 001 for each year because the year is part of the key. Do not repeat a session on multiple CSV rows.</p>';
 if(in_array($type,array('agenda','recordings'),true))echo '<p><strong>Speaker assignments:</strong> Enter existing speaker full names or exact export keys in <code>speaker_keys</code>. Separate multiple speakers with <code>|</code> (pipe), with one CSV row per session or recording. Use <code>moderator_key</code> for the moderator on Agenda imports. Names must match exactly one existing speaker profile; ambiguous names are blocked. Sample names are illustrative and must be replaced before importing.</p>';
 echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="caes_io_preview"><input type="hidden" name="dataset" value="'.esc_attr($type).'"><input type="hidden" name="year" value="'.esc_attr($year).'"><input type="hidden" name="mode" value="'.esc_attr($mode).'">';wp_nonce_field('caes_io_preview');echo '<p><input type="file" name="csv" accept=".csv,text/csv" required> <button class="button button-primary">Validate and preview</button></p></form>';
 $token=sanitize_text_field($_GET['preview']??'');$preview=$token?get_transient('caes_io_preview_'.get_current_user_id().'_'.$token):false;
 if(is_array($preview)){
  echo '<h2>Import preview</h2><p>'.count($preview['rows']).' rows. Review the results before confirming. Existing published posts will be staged.</p><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Row</th><th>Record</th><th>Action</th><th>Validation</th></tr></thead><tbody>';
  foreach($preview['rows'] as $i=>$r){$errors=caes_io_validate($preview['type'],$r,$preview['year']);$existing=caes_io_existing($preview['type'],$r);$action=$errors?'Blocked':($existing?($preview['mode']==='create'?'Skip':($preview['mode']==='duplicate'?'Duplicate':'Update or stage')):'Create draft/hidden');$people=array();if(in_array($preview['type'],array('agenda','recordings'),true)){foreach(caes_io_split($r['speaker_keys']??'') as $person){$resolved=caes_io_resolve_speaker($person);if($resolved['id'])$people[]=$resolved['name'];}if($preview['type']==='agenda'&&!empty($r['moderator_key'])){$resolved=caes_io_resolve_speaker($r['moderator_key']);if($resolved['id'])$people[]='Moderator: '.$resolved['name'];}}echo '<tr><td>'.($i+2).'</td><td>'.esc_html($r['key']??$r['year']??$r['session_type']??'').($people?'<br><small>'.esc_html(implode(', ',$people)).'</small>':'').'</td><td>'.esc_html($action).'</td><td>'.esc_html($errors?implode('; ',$errors):'Valid').'</td></tr>';}
  echo '</tbody></table><form action="'.esc_url(admin_url('admin-post.php')).'" method="post" style="margin:20px 0"><input type="hidden" name="action" value="caes_io_commit"><input type="hidden" name="token" value="'.esc_attr($token).'">';wp_nonce_field('caes_io_commit');echo '<button class="button button-primary" onclick="return confirm(\'Import validated rows? Published content will not be overwritten.\')">Confirm import</button></form>';
 }
 echo '<h2>Staged updates to published content</h2><p>Review each pending update before applying it to a live record.</p>';
 foreach(array('caes_speaker','caes_session','caes_recording') as $posttype){
  $pending_posts=get_posts(array('post_type'=>$posttype,'post_status'=>'publish','numberposts'=>100,'meta_key'=>'_caes_import_pending'));
  foreach($pending_posts as $post){$pending=get_post_meta($post->ID,'_caes_import_pending',true);if(!is_array($pending)||!$pending)continue;
   echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;margin:10px 0;max-width:900px"><strong>'.esc_html($post->post_title).'</strong> ('.esc_html($posttype).'): '.count($pending).' pending update(s). <a href="'.esc_url(get_edit_post_link($post->ID)).'">Open editor</a>';
   echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post" style="margin-top:8px"><input type="hidden" name="action" value="caes_io_approve"><input type="hidden" name="post_id" value="'.(int)$post->ID.'">';wp_nonce_field('caes_io_approve_'.$post->ID);echo '<button class="button" onclick="return confirm(\'Apply the latest staged import to this published record?\')">Approve latest update</button></form></div>';
  }
 }
 echo '</div>';
}
add_action('admin_post_caes_io_approve',function(){
 if(!current_user_can('manage_options'))wp_die('Permission denied',403);$id=absint($_POST['post_id']??0);check_admin_referer('caes_io_approve_'.$id);
 if(!$id||!current_user_can('edit_post',$id)||get_post_status($id)!=='publish')wp_die('Invalid published record');
 $pending=get_post_meta($id,'_caes_import_pending',true);if(!is_array($pending)||!$pending)wp_die('No pending changes');
 $item=array_pop($pending);$type=$item['dataset']??'';$map=array('speakers'=>'caes_speaker','agenda'=>'caes_session','recordings'=>'caes_recording');
 if(!isset($map[$type])||get_post_type($id)!==$map[$type]||caes_io_find_post($map[$type],$item['data']['key']??'')!==$id)wp_die('Staged update does not match this record');
 $errors=caes_io_validate($type,$item['data'],'');if($errors)wp_die('Staged update is no longer valid: '.esc_html(implode('; ',$errors)));
 $result=caes_io_save($type,$item['data'],'update',true);if(strpos($result,'error:')===0)wp_die(esc_html($result));
 if($pending)update_post_meta($id,'_caes_import_pending',$pending);else delete_post_meta($id,'_caes_import_pending');
 wp_safe_redirect(add_query_arg(array('page'=>'event-manager-import','approved'=>1),admin_url('admin.php')));exit;
});
add_action('admin_post_caes_io_preview',function(){
 if(!current_user_can('manage_options'))wp_die('Permission denied',403);check_admin_referer('caes_io_preview');
 $type=caes_io_key($_POST['dataset']??'');$types=caes_io_types();if(!isset($types[$type]))wp_die('Invalid dataset');$year=caes_io_year($_POST['year']??'');$mode=sanitize_key($_POST['mode']??'update');if(!in_array($mode,array('create','update','duplicate'),true))wp_die('Invalid import mode');
 $file=$_FILES['csv']??array();if(($file['error']??99)!==UPLOAD_ERR_OK||($file['size']??0)>2*1024*1024||!is_uploaded_file($file['tmp_name'])||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='csv')wp_die('Upload a CSV file smaller than 2 MB.');
 $f=fopen($file['tmp_name'],'r');if(!$f)wp_die('Cannot read CSV');$headers=fgetcsv($f);if(!$headers)wp_die('Empty CSV');$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]);if($headers!==$types[$type]['headers'])wp_die('CSV headers do not match the template. Download a fresh sample CSV.');
 $rows=array();while(($values=fgetcsv($f))!==false){if($values===array(null))continue;if(count($values)!==count($headers))wp_die('Invalid CSV column count on row '.(count($rows)+2));$row=array_combine($headers,$values);if(!array_filter($row,function($v){return trim($v)!=='';}))continue;$rows[]=$row;if(count($rows)>500)wp_die('Limit: 500 rows per import.');}fclose($f);
 if(!$rows)wp_die('CSV has no records');$token=wp_generate_password(16,false,false);set_transient('caes_io_preview_'.get_current_user_id().'_'.$token,array('type'=>$type,'year'=>$year,'mode'=>$mode,'rows'=>$rows),20*MINUTE_IN_SECONDS);
 wp_safe_redirect(add_query_arg(array('page'=>'event-manager-import','dataset'=>$type,'year'=>$year,'mode'=>$mode,'preview'=>$token),admin_url('admin.php')));exit;
});
add_action('admin_post_caes_io_commit',function(){
 if(!current_user_can('manage_options'))wp_die('Permission denied',403);check_admin_referer('caes_io_commit');$token=sanitize_text_field($_POST['token']??'');$key='caes_io_preview_'.get_current_user_id().'_'.$token;$batch=get_transient($key);if(!$batch||!is_array($batch))wp_die('Preview expired. Upload again.');delete_transient($key);
 $counts=array('created'=>0,'updated'=>0,'duplicated'=>0,'staged'=>0,'skipped'=>0,'blocked'=>0);foreach($batch['rows'] as $row){if(caes_io_validate($batch['type'],$row,$batch['year'])){$counts['blocked']++;continue;}$result=caes_io_save($batch['type'],$row,$batch['mode']);if(isset($counts[$result]))$counts[$result]++;else $counts['blocked']++;}
 set_transient('caes_io_report_'.get_current_user_id(),implode(', ',array_map(function($k,$v){return $k.': '.$v;},array_keys($counts),$counts)),5*MINUTE_IN_SECONDS);
 wp_safe_redirect(add_query_arg(array('page'=>'event-manager-import','dataset'=>$batch['type'],'year'=>$batch['year'],'imported'=>1),admin_url('admin.php')));exit;
});
/** Contextual links from each existing administration section. */
add_action('admin_notices',function(){
 if(!current_user_can('manage_options'))return;
 $page=sanitize_key($_GET['page']??'');$map=array('event-manager-venues'=>'venues','event-manager-archives'=>'events','event-manager-speakers'=>'speakers','event-manager-tracks'=>'tracks','event-manager-agenda'=>'agenda','event-manager-recordings'=>'recordings','event-manager-sponsor-tiers'=>'tiers','event-manager-sponsors'=>'sponsors','event-manager-settings'=>'settings');
 if(!isset($map[$page]))return;$type=$map[$page];$url=add_query_arg(array('page'=>'event-manager-import','dataset'=>$type),admin_url('admin.php'));
 echo '<div class="notice notice-info inline"><p><strong>CSV migration:</strong> <a href="'.esc_url($url).'">Import / Export '.esc_html(caes_io_types()[$type]['label']).'</a>. All new imported content remains unpublished.</p></div>';
});
