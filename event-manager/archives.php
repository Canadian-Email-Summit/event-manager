<?php
/** Event Manager: reusable event archives and YouTube recordings. */
if (!defined('ABSPATH')) exit;

add_action('init',function(){
 register_post_type('caes_event_archive',array('labels'=>array('name'=>'Event Archives','singular_name'=>'Event Archive'),'public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>array('title','editor')));
 register_post_type('caes_recording',array('labels'=>array('name'=>'Recordings','singular_name'=>'Recording'),'public'=>false,'show_ui'=>false,'show_in_menu'=>false,'show_in_rest'=>false,'supports'=>array('title','editor','revisions'),'capability_type'=>'post','map_meta_cap'=>true,'capabilities'=>array('edit_posts'=>'edit_others_posts','create_posts'=>'edit_others_posts','publish_posts'=>'edit_others_posts')));
});
add_action('admin_menu',function(){add_submenu_page('caes-event-manager','Event Archives','Event Archives','manage_options','caes-em-archives','caes_em_archive_admin');add_submenu_page('caes-event-manager','Venues','Venues','manage_options','caes-em-venues','caes_em_venues_admin');add_submenu_page('caes-event-manager','Recordings','Recordings','edit_others_posts','caes-em-recordings','caes_em_recordings_admin');},20);
function caes_em_archive_defaults(){return array(
 '2019'=>array('venue'=>'The Spoke Club, Toronto','format'=>'In person','show'=>1),
 '2020'=>array('venue'=>'Virtual Event','format'=>'Virtual','show'=>1),
 '2021'=>array('venue'=>'Virtual Event','format'=>'Virtual','show'=>1),
 '2022'=>array('venue'=>'Virtual Event','format'=>'Virtual','show'=>1),
 '2024'=>array('venue'=>'Pantages Hotel, Toronto','format'=>'In person','show'=>1),
 '2025'=>array('venue'=>"St. Paul's Bloor Street, Toronto",'format'=>'In person','show'=>0),
 '2026'=>array('venue'=>"St. Paul's Bloor Street, Toronto",'format'=>'In person','show'=>1),
 );}
function caes_em_archives(){
 $stored=get_option('caes_em_event_archives',null);
 if(!is_array($stored)){$stored=caes_em_archive_defaults();add_option('caes_em_event_archives',$stored);}
 return $stored;
}
/** Shared venue records. Existing event data remains the fallback until assigned. */
function caes_em_venues(){
 $saved=get_option('caes_em_venues',array());return is_array($saved)?$saved:array();
}
function caes_em_venue_for_year($row){
 $venues=caes_em_venues();$id=sanitize_key((string)($row['venue_id']??''));
 if($id&&isset($venues[$id]))return $venues[$id];
 return array('name'=>$row['venue']??'','address'=>$row['address']??'','details'=>'');
}
function caes_em_venue_label($row){$v=caes_em_venue_for_year($row);return $v['name']??'';}
function caes_em_venue_address($row){$v=caes_em_venue_for_year($row);return $v['address']??'';}
function caes_em_venues_admin(){
 if(!current_user_can('manage_options'))wp_die('Insufficient permissions.');
 $venues=caes_em_venues();$notice='';
 if(isset($_POST['caes_em_save_venue'])){
  check_admin_referer('caes_em_venues_save');
  $name=sanitize_text_field(wp_unslash($_POST['venue_name']??''));
  $address=sanitize_text_field(wp_unslash($_POST['venue_address']??''));
  $details=sanitize_textarea_field(wp_unslash($_POST['venue_details']??''));
  $id=sanitize_text_field(wp_unslash($_POST['venue_id']??''));
  if(!$name)$notice='Venue name is required.';
  else{
   if(!$id||!isset($venues[$id]))$id='venue_'.strtolower(wp_generate_password(10,false,false));
   $venues[$id]=array('name'=>$name,'address'=>$address,'details'=>$details);
   update_option('caes_em_venues',$venues,false);$notice='Venue saved.';
  }
 }
 if(isset($_POST['caes_em_delete_venue'])){
  check_admin_referer('caes_em_venues_delete');$id=sanitize_text_field(wp_unslash($_POST['venue_id']??''));
  $used=array();foreach(caes_em_archives() as $year=>$row)if(($row['venue_id']??'')===$id)$used[]=$year;
  if($used)$notice='Cannot delete this venue. It is assigned to event year(s): '.implode(', ',$used).'. Reassign those events first.';
  elseif(isset($venues[$id])){unset($venues[$id]);update_option('caes_em_venues',$venues,false);$notice='Venue deleted.';}
 }
 echo '<div class="wrap"><h1>Venues</h1><p>Save each physical venue once, then assign it to any number of event years. Updating a venue updates every linked event.</p>';
 if($notice)echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
 $edit=sanitize_text_field(wp_unslash($_GET['venue']??''));$current=isset($venues[$edit])?$venues[$edit]:array();
 echo '<div style="max-width:850px;background:#fff;border:1px solid #c3c4c7;padding:20px;margin:18px 0"><h2>'.($current?'Edit venue':'Add venue').'</h2><form method="post">';wp_nonce_field('caes_em_venues_save');
 echo '<input type="hidden" name="venue_id" value="'.esc_attr($current?$edit:'').'"><p><label><strong>Venue name</strong><br><input class="regular-text" name="venue_name" required value="'.esc_attr($current['name']??'').'"></label></p><p><label><strong>Street address</strong><br><input class="large-text" name="venue_address" value="'.esc_attr($current['address']??'').'"></label></p><p><label><strong>Venue details (optional)</strong><br><textarea class="large-text" rows="3" name="venue_details">'.esc_textarea($current['details']??'').'</textarea></label></p><button class="button button-primary" name="caes_em_save_venue" value="1">Save venue</button> <a class="button" href="'.esc_url(admin_url('admin.php?page=caes-em-venues')).'">Clear form</a></form></div>';
 echo '<h2>Saved venues</h2><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Venue</th><th>Address</th><th>Used by</th><th>Actions</th></tr></thead><tbody>';
 foreach($venues as $id=>$venue){$years=array();foreach(caes_em_archives() as $year=>$row)if(($row['venue_id']??'')===$id)$years[]=$year;
  echo '<tr><td>'.esc_html($venue['name']).'</td><td>'.esc_html($venue['address']).'</td><td>'.esc_html(implode(', ',$years)?:'Not assigned').'</td><td><a class="button button-small" href="'.esc_url(add_query_arg(array('page'=>'caes-em-venues','venue'=>$id),admin_url('admin.php'))).'">Edit</a> ';
  if(!$years){echo '<form method="post" style="display:inline">';wp_nonce_field('caes_em_venues_delete');echo '<input type="hidden" name="venue_id" value="'.esc_attr($id).'"><button class="button button-small" name="caes_em_delete_venue" value="1" onclick="return confirm(\'Delete this unused venue?\')">Delete</button></form>';}
  else echo '<span class="description">Reassign events to delete</span>';echo '</td></tr>';
 }
 echo '</tbody></table></div>';
}
function caes_em_archive_admin(){
 if(!current_user_can('manage_options'))wp_die('Insufficient permissions.');
 $notice='';$data=caes_em_archives();
 if(isset($_POST['caes_em_save_archives'])){
  check_admin_referer('caes_em_archive_save');
  $years=isset($_POST['years'])&&is_array($_POST['years'])?wp_unslash($_POST['years']):array();
  $out=array();$venues=caes_em_venues();
  foreach($years as $year=>$row){
   $year=sanitize_text_field($year);if(!preg_match('/^20[0-9]{2}$/D',$year)||!is_array($row))continue;
   $venue_id=sanitize_text_field($row['venue_id']??'');
   if(!isset($venues[$venue_id])){foreach(array_keys($venues) as $candidate){if(strcasecmp($candidate,$venue_id)===0){$venue_id=$candidate;break;}}}
   if(!isset($venues[$venue_id]))$venue_id='';
   $old=$data[$year]??array();
   $out[$year]=array('venue_id'=>$venue_id,'venue'=>sanitize_text_field($old['venue']??''),'address'=>sanitize_text_field($old['address']??''),'format'=>in_array(($row['format']??''),array('Virtual','In person','Hybrid'),true)?$row['format']:'In person','show'=>empty($row['show'])?0:1,'description'=>sanitize_textarea_field($row['description']??''));
  }
  $new=isset($_POST['new_year'])?sanitize_text_field(wp_unslash($_POST['new_year'])):'';
  if(preg_match('/^20[0-9]{2}$/D',$new)&&!isset($out[$new]))$out[$new]=array('venue_id'=>'','venue'=>'','format'=>'In person','show'=>0,'description'=>'','address'=>'');
  krsort($out,SORT_NUMERIC);update_option('caes_em_event_archives',$out,false);$data=$out;$notice='Event archives saved.';
 }
 if(isset($_POST['caes_em_delete_year'])){
  check_admin_referer('caes_em_archive_delete');$year=sanitize_text_field(wp_unslash($_POST['delete_year']??''));
  if(isset($data[$year])){
   $linked=new WP_Query(array('post_type'=>'caes_recording','post_status'=>array('publish','draft','private','pending','future'),'posts_per_page'=>1,'fields'=>'ids','meta_query'=>array(array('key'=>'caes_rec_year','value'=>$year))));
   $sessions=new WP_Query(array('post_type'=>'caes_session','post_status'=>array('publish','draft','private','pending','future'),'posts_per_page'=>1,'fields'=>'ids','meta_query'=>array(array('key'=>'caes_session_year','value'=>$year))));
   if($linked->have_posts()||$sessions->have_posts())$notice='Cannot delete '.$year.': linked recordings or sessions exist. Reassign or remove those first.';
   else{unset($data[$year]);update_option('caes_em_event_archives',$data,false);$notice='Event year '.$year.' deleted.';}
  }
 }
 krsort($data,SORT_NUMERIC);$venues=caes_em_venues();
 echo '<div class="wrap"><h1>Event Archives</h1><p>Manage event years, event formats, and their shared venues. Create physical venues in <a href="'.esc_url(admin_url('admin.php?page=caes-em-venues')).'">Venues</a>, then assign them below. Virtual events do not require a venue.</p>';
 if($notice)echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
 echo '<form method="post">';wp_nonce_field('caes_em_archive_save');
 echo '<div style="overflow-x:auto"><table class="widefat striped" style="table-layout:fixed;min-width:1000px"><thead><tr><th style="width:8%">Year</th><th style="width:22%">Venue</th><th style="width:23%">Address (from venue)</th><th style="width:12%">Format</th><th style="width:23%">Description</th><th style="width:12%">Show in archive</th></tr></thead><tbody>';
 foreach($data as $year=>$row){$key='years['.esc_attr($year).']';$venue=caes_em_venue_for_year($row);
  echo '<tr><th>'.esc_html($year).'</th><td><select style="width:100%" name="'.$key.'[venue_id]"><option value="">'.(empty($row['venue'])?'No venue':'Legacy: '.esc_html($row['venue'])).'</option>';
  foreach($venues as $id=>$v)echo '<option value="'.esc_attr($id).'" '.selected($row['venue_id']??'',$id,false).'>'.esc_html($v['name']).'</option>';
  echo '</select></td><td>'.esc_html($venue['address']??'').'<span class="description" style="display:block">'.(empty($row['venue_id'])?'Assign a saved venue to inherit its address.':'Managed in Venues.').'</span></td><td><select name="'.$key.'[format]">';
  foreach(array('In person','Virtual','Hybrid') as $v)echo '<option '.selected($row['format']??'',$v,false).'>'.esc_html($v).'</option>';
  echo '</select></td><td><textarea rows="3" style="width:100%" name="'.$key.'[description]">'.esc_textarea($row['description']??'').'</textarea></td><td><label><input type="checkbox" name="'.$key.'[show]" value="1" '.checked(!empty($row['show']),true,false).'> Visible</label></td></tr>';
 }
 echo '</tbody></table></div><p><label>Add event year: <input name="new_year" inputmode="numeric" pattern="20[0-9]{2}" placeholder="2027"></label> (save once to add, then edit details)</p><p><button class="button button-primary" name="caes_em_save_archives" value="1">Save event archives</button></p></form>';
 echo '<h2>Remove an event year</h2><p>Deletion is blocked when linked recordings or agenda sessions exist. Hide a year instead if you want to preserve its content.</p><form method="post">';wp_nonce_field('caes_em_archive_delete');echo '<select name="delete_year">';foreach($data as $year=>$row)echo '<option value="'.esc_attr($year).'">'.esc_html($year).'</option>';echo '</select> <button class="button" name="caes_em_delete_year" value="1" onclick="return confirm(\'Delete this event year? This cannot be undone.\')">Delete selected year</button></form>';
 caes_em_shortcode_panel('Past Events overview','[event_archive_index]');caes_em_shortcode_panel('Individual event page','[event_archive_detail]');echo '</div>';
}
/** Dedicated recording editor. Uses the same speaker records as the agenda. */
function caes_em_recordings_admin(){
 if(!current_user_can('edit_others_posts'))wp_die('Permission denied');
 if(function_exists('caes_em_styles'))caes_em_styles();
 $id=isset($_GET['recording'])?absint($_GET['recording']):0;
 if($id && (get_post_type($id)!=='caes_recording'||!current_user_can('edit_post',$id)))wp_die('Invalid recording or insufficient permissions.');
 if(isset($_POST['caes_em_save_recording'])){
  if(!current_user_can('edit_others_posts'))wp_die('Permission denied');
  check_admin_referer('caes_em_save_recording');
  $title=sanitize_text_field(wp_unslash($_POST['rec_title']??''));
  $year=sanitize_text_field(wp_unslash($_POST['rec_year']??''));
  $url=esc_url_raw(wp_unslash($_POST['rec_video']??''),array('https'));
  $status=sanitize_key(wp_unslash($_POST['rec_status']??'draft'))==='publish'?'publish':'draft';
  if(!$title||!isset(caes_em_archives()[$year])||!$url||!caes_em_youtube_id($url)){
   echo '<div class="notice notice-error"><p>Enter a session title, configured event year, and valid HTTPS video URL.</p></div>';
  }else{
   $postdata=array('post_type'=>'caes_recording','post_title'=>$title,'post_content'=>wp_kses_post(wp_unslash($_POST['rec_description']??'')),'post_status'=>$status);
   if($id)$postdata['ID']=$id;
   $result=$id?wp_update_post($postdata,true):wp_insert_post($postdata,true);
   if(is_wp_error($result))echo '<div class="notice notice-error"><p>'.esc_html($result->get_error_message()).'</p></div>';
   else{
    $id=$result;
    update_post_meta($id,'caes_rec_year',$year);
    update_post_meta($id,'caes_rec_youtube',$url); // Legacy key retained for existing recordings.
    update_post_meta($id,'caes_rec_video',$url);
    update_post_meta($id,'caes_rec_slides',esc_url_raw(wp_unslash($_POST['rec_slides']??''),array('https','http')));
    update_post_meta($id,'caes_rec_order',(int)($_POST['rec_order']??0));
    update_post_meta($id,'caes_rec_location',sanitize_text_field(wp_unslash($_POST['rec_location']??'')));
    $ids=isset($_POST['rec_speakers'])&&is_array($_POST['rec_speakers'])?array_map('absint',wp_unslash($_POST['rec_speakers'])):array();
    $ids=array_values(array_unique(array_filter($ids,function($sid){return get_post_type($sid)==='caes_speaker' && get_post_status($sid)==='publish';})));
    update_post_meta($id,'caes_rec_speaker_ids',$ids);
    echo '<div class="notice notice-success"><p>Recording saved.</p></div>';
   }
  }
 }
 if(isset($_POST['caes_em_trash_recording'])){
  check_admin_referer('caes_em_trash_recording');$trash=absint($_POST['caes_em_trash_recording']);
  if(get_post_type($trash)!=='caes_recording'||!current_user_can('delete_post',$trash))wp_die('Cannot delete recording.');
  wp_trash_post($trash);echo '<div class="notice notice-success"><p>Recording moved to Trash.</p></div>';$id=0;
 }
 echo '<div class="wrap"><h1>Recordings</h1>';
 if(!$id&&!isset($_GET['new'])){
  echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=caes-em-recordings&new=1')).'">Add recording</a></p>';
  $posts=get_posts(array('post_type'=>'caes_recording','post_status'=>array('publish','draft','private','pending'),'posts_per_page'=>500,'orderby'=>'title','order'=>'ASC'));
  echo '<table class="widefat striped"><thead><tr><th>Session title</th><th>Year</th><th>Video</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
  foreach($posts as $post){$edit=admin_url('admin.php?page=caes-em-recordings&recording='.$post->ID);echo '<tr><td><a href="'.esc_url($edit).'">'.esc_html($post->post_title).'</a></td><td>'.esc_html(get_post_meta($post->ID,'caes_rec_year',true)).'</td><td>'.(get_post_meta($post->ID,'caes_rec_youtube',true)?'Linked':'Missing').'</td><td>'.esc_html($post->post_status).'</td><td><form method="post">';wp_nonce_field('caes_em_trash_recording');echo '<button type="submit" class="button button-small" name="caes_em_trash_recording" value="'.esc_attr($post->ID).'" onclick="return confirm(\'Move this recording to Trash?\')">Move to Trash</button></form></td></tr>';}
  echo '</tbody></table>';caes_em_shortcode_panel('Recordings by year','[event_archive_detail year="2024"]');echo '</div>';return;
 }
 $post=$id?get_post($id):null;$meta=function($key)use($id){return $id?get_post_meta($id,'caes_rec_'.$key,true):'';};
 $selected=$meta('speaker_ids');if(!is_array($selected))$selected=array();
 $years=caes_em_archives();krsort($years,SORT_NUMERIC);
 echo '<p><a href="'.esc_url(admin_url('admin.php?page=caes-em-recordings')).'">&larr; All recordings</a></p><form method="post" class="caes-em-form">';wp_nonce_field('caes_em_save_recording');
 echo '<div class="caes-em-section"><h2>Session information</h2>';
 echo '<p><label for="rec_title"><strong>Session title</strong></label><br><input id="rec_title" name="rec_title" class="large-text" required value="'.esc_attr($post?$post->post_title:'').'"></p>';
 echo '<p><label for="rec_year"><strong>Event year</strong></label><br><select id="rec_year" name="rec_year" required><option value="">Select a year</option>';
 foreach($years as $year=>$row)echo '<option value="'.esc_attr($year).'" '.selected($meta('year'),$year,false).'>'.esc_html($year).' ('.esc_html(caes_em_venue_label($row)).')</option>';
 echo '</select></p>';
 echo '<p><label for="rec_video"><strong>Video URL</strong></label><br><input id="rec_video" name="rec_video" type="url" class="large-text" required placeholder="https://www.youtube.com/watch?v=..." value="'.esc_attr($meta('video')?:$meta('youtube')).'"></p><p class="description">YouTube playback is supported now. Other video providers can be added later.</p>';
 echo '<p><label for="rec_speakers"><strong>Speakers and panellists</strong></label><br><select id="rec_speakers" name="rec_speakers[]" multiple size="9" style="min-width:320px;max-width:100%">';
 foreach(get_posts(array('post_type'=>'caes_speaker','post_status'=>array('publish','draft'),'posts_per_page'=>500,'orderby'=>'title','order'=>'ASC')) as $speaker)echo '<option value="'.esc_attr($speaker->ID).'" '.selected(in_array($speaker->ID,$selected,true),true,false).'>'.esc_html($speaker->post_title).'</option>';
 echo '</select></p><p class="description">Hold Ctrl (Windows) or Command (Mac) to select multiple speakers. Add missing profiles in Event Manager > Speakers.</p>';
 echo '<p><label for="rec_location"><strong>Location override (optional)</strong></label><br><input id="rec_location" name="rec_location" class="large-text" value="'.esc_attr($meta('location')).'"></p><p class="description">Normally inherited from Event Archives. Only enter an override when this session used a different location.</p>';
 echo '<p><label for="rec_description"><strong>Session description</strong></label></p>';wp_editor($post?$post->post_content:'','rec_description',array('textarea_name'=>'rec_description','textarea_rows'=>9,'media_buttons'=>false));
 echo '<p><label for="rec_slides"><strong>Presentation slides URL (optional)</strong></label><br><input id="rec_slides" name="rec_slides" type="url" class="large-text" value="'.esc_attr($meta('slides')).'"></p>';
 echo '<p><label for="rec_order"><strong>Display order</strong></label><br><input id="rec_order" name="rec_order" type="number" value="'.esc_attr($meta('order')?:0).'" style="width:100px"></p>';
 echo '<p><label for="rec_status"><strong>Status</strong></label><br><select id="rec_status" name="rec_status"><option value="draft" '.selected($post?$post->post_status:'draft','draft',false).'>Draft</option><option value="publish" '.selected($post?$post->post_status:'draft','publish',false).'>Published</option></select></p>';
 echo '<p><button class="button button-primary" name="caes_em_save_recording" value="1">Save recording</button></p></div></form></div>';
}
function caes_em_youtube_id($url){
 if(!is_string($url)||!$url)return '';
 $u=wp_parse_url($url);if(!$u||!in_array(strtolower($u['scheme']??''),array('https'),true))return '';
 $host=strtolower($u['host']??'');$host=preg_replace('/^www\./','',$host);$id='';
 if($host==='youtu.be')$id=trim($u['path']??'','/');
 elseif(in_array($host,array('youtube.com','m.youtube.com','youtube-nocookie.com'),true)){
  $path=$u['path']??'';if($path==='/watch'){parse_str($u['query']??'',$q);$id=$q['v']??'';}
  elseif(preg_match('~^/(embed|shorts|live)/([^/]+)~',$path,$m))$id=$m[2];
 }
 return is_string($id)&&preg_match('/^[A-Za-z0-9_-]{11}$/D',$id)?$id:'';
}
function caes_em_recording_cards($year){
 $q=new WP_Query(array('post_type'=>'caes_recording','post_status'=>'publish','posts_per_page'=>200,'meta_query'=>array(array('key'=>'caes_rec_year','value'=>$year,'compare'=>'=')),'orderby'=>'title','order'=>'ASC'));
 $items=array();foreach($q->posts as $post){$id=caes_em_youtube_id(get_post_meta($post->ID,'caes_rec_youtube',true));if(!$id)continue;$items[]=array('post'=>$post,'video'=>$id,'order'=>(int)get_post_meta($post->ID,'caes_rec_order',true));}
 usort($items,function($a,$b){return $a['order']<=>$b['order']?:strcmp($a['post']->post_title,$b['post']->post_title);});
 if(!$items)return '<p>Session recordings will be added here.</p>';
 $html='<div class="caes-recording-grid">';foreach($items as $item){$post=$item['post'];$id=$item['video'];$speakers=get_post_meta($post->ID,'caes_rec_speakers',true);$speaker_ids=get_post_meta($post->ID,'caes_rec_speaker_ids',true);if(is_array($speaker_ids)&&$speaker_ids){$names=array();foreach($speaker_ids as $sid){$sp=get_post((int)$sid);if($sp&&$sp->post_type==='caes_speaker'&&$sp->post_status==='publish')$names[]=$sp->post_title;}if($names)$speakers=implode(', ',$names);}$slides=get_post_meta($post->ID,'caes_rec_slides',true);
  $html.='<article class="caes-recording-card"><button type="button" class="caes-recording-play" data-video="'.esc_attr($id).'" aria-label="Play '.esc_attr($post->post_title).' on YouTube"><img loading="lazy" src="https://i.ytimg.com/vi/'.esc_attr($id).'/hqdefault.jpg" alt=""><span class="caes-recording-play-icon" aria-hidden="true">▶</span></button><div class="caes-recording-card-body"><h3>'.esc_html($post->post_title).'</h3>';
  if($speakers)$html.='<p class="caes-recording-speakers">'.esc_html($speakers).'</p>';
  if($post->post_content)$html.='<div class="caes-recording-description">'.wp_kses_post(wpautop($post->post_content)).'</div>';
  if($slides)$html.='<p><a href="'.esc_url($slides).'" target="_blank" rel="noopener noreferrer">Presentation slides</a></p>';
  $html.='</div></article>';
 }
 $html.='</div>';return $html;
}
add_shortcode('event_archive_index',function(){
 $data=caes_em_archives();krsort($data,SORT_NUMERIC);
 $out='<div class="caes-archive-hero"><p class="caes-eyebrow">PAST EVENTS</p><h1>Explore the Summit archives</h1><p>Revisit the conversations, practical lessons, and people who have shaped the Canadian Email Summit.</p></div><h2>Browse by year</h2><div class="caes-year-grid">';
 foreach($data as $year=>$row){if(empty($row['show']))continue;$url=home_url('/past-events/summit-'.$year.'/');$out.='<a class="caes-year-card" href="'.esc_url($url).'"><span class="caes-year-card-label">CANADIAN EMAIL SUMMIT</span><strong>'.esc_html($year).'</strong><span>'.esc_html(caes_em_venue_label($row)).'</span><span>Explore recordings and sessions <span aria-hidden="true">→</span></span></a>';}
 $out.='</div>';return $out;
});
add_shortcode('event_archive_detail',function($atts){
 $atts=shortcode_atts(array('year'=>''),$atts,'event_archive_detail');$year=trim((string)$atts['year']);
 if(!$year&&is_page()){$page=get_queried_object();$year=$page instanceof WP_Post?$page->post_name:'';if(preg_match('/^(?:summit-)?(20[0-9]{2})(?:-[0-9]+)?$/D',$year,$match))$year=$match[1];}
 if(!preg_match('/^20[0-9]{2}$/D',$year))return '<p>Archive year not configured. Use a year page slug or set the shortcode year.</p>';
 $data=caes_em_archives();if(!isset($data[$year]) || (empty($data[$year]['show']) && !current_user_can('manage_options')))return '<p>This event archive is not available.</p>';
 $row=$data[$year];$out='<div class="caes-archive-hero"><p class="caes-eyebrow">PAST EVENTS · '.esc_html($year).'</p><h1>'.esc_html($year).' Canadian Email Summit</h1><p class="caes-archive-venue">'.esc_html(caes_em_venue_label($row)).'</p>';
 if(caes_em_venue_address($row))$out.='<p class="caes-archive-address">'.esc_html(caes_em_venue_address($row)).'</p>';
 $out.='<p>'.esc_html(!empty($row['description'])?$row['description']:'Explore recorded sessions and event highlights from this edition of the Summit.').'</p></div><nav class="caes-archive-year-nav" aria-label="Past event years"><a href="'.esc_url(home_url('/past-events/')).'">All years</a>';
 krsort($data,SORT_NUMERIC);foreach($data as $y=>$r){if(empty($r['show']))continue;$out.='<a href="'.esc_url(home_url('/past-events/summit-'.$y.'/')).'"'.($y===$year?' aria-current="page"':'').'>'.esc_html($y).'</a>';}
 $out.='</nav><h2>Session recordings</h2>'.caes_em_recording_cards($year);return $out;
});
add_action('wp_enqueue_scripts',function(){
 wp_register_script('caes-archive-video',false,array(),'0.1.10',true);wp_enqueue_script('caes-archive-video');
 wp_add_inline_script('caes-archive-video',"document.addEventListener('click',function(e){var b=e.target.closest('.caes-recording-play');if(!b)return;var id=b.dataset.video;if(!/^[A-Za-z0-9_-]{11}$/.test(id))return;var f=document.createElement('iframe');f.src='https://www.youtube-nocookie.com/embed/'+id+'?autoplay=1';f.title=b.getAttribute('aria-label');f.allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';f.allowFullscreen=true;f.style.width='100%';f.style.aspectRatio='16 / 9';f.style.border='0';b.replaceWith(f);});");
});

/** A reusable, accessible shortcode copy panel for Event Manager screens. */
function caes_em_shortcode_panel($title,$shortcode){
 echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:5px;padding:14px 18px;max-width:820px;margin:16px 0"><strong>'.esc_html($title).'</strong><p class="description">Paste this into a WordPress Shortcode block. Select a year in the shortcode when necessary.</p><code style="display:inline-block;padding:8px;margin-right:8px">'.esc_html($shortcode).'</code><button type="button" class="button caes-em-copy" data-copy="'.esc_attr($shortcode).'">Copy shortcode</button></div>';
}
add_action('admin_footer',function(){
 $screen=isset($_GET['page'])?sanitize_key(wp_unslash($_GET['page'])):'';
 if(strpos($screen,'caes-em-')!==0&&$screen!=='caes-event-manager')return;
 echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".caes-em-copy");if(!b)return;var value=b.getAttribute("data-copy");if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(value).then(function(){b.textContent="Copied!";setTimeout(function(){b.textContent="Copy shortcode"},1600)}).catch(function(){window.prompt("Copy shortcode:",value)})}else{window.prompt("Copy shortcode:",value)}});</script>';
});

add_action('admin_notices',function(){
 $page=isset($_GET['page'])?sanitize_key(wp_unslash($_GET['page'])):'';
 if(!in_array($page,array('caes-em-speakers','caes-em-agenda','caes-em-recordings'),true))return;
 if($page==='caes-em-speakers')caes_em_shortcode_panel('Speaker directory','[caes_speakers year="2026"]');
 if($page==='caes-em-agenda')caes_em_shortcode_panel('Public agenda','[event_agenda year="2026"]');
 if($page==='caes-em-recordings')caes_em_shortcode_panel('Recording archive (year-specific)','[event_archive_detail year="2024"]');
});
