<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
 register_post_type('caes_session', array(
  'labels'=>array('name'=>'Agenda Sessions','singular_name'=>'Agenda Session'),
  'public'=>false,'show_ui'=>false,'show_in_rest'=>false,
  'supports'=>array('title','editor'),
  'capability_type'=>'post','map_meta_cap'=>true,
  'capabilities'=>array('edit_posts'=>'edit_others_posts','create_posts'=>'edit_others_posts','publish_posts'=>'edit_others_posts'),
 ));
});

add_action('admin_menu',function(){
 add_menu_page('Event Manager','Event Manager','edit_others_posts','event-manager','caes_em_dashboard','dashicons-calendar-alt',26);
 add_submenu_page('event-manager','Overview','Overview','edit_others_posts','event-manager','caes_em_dashboard');
 add_submenu_page('event-manager','Speakers','Speakers','edit_others_posts','event-manager-speakers','caes_em_speakers');
 add_submenu_page('event-manager','Agenda','Agenda','edit_others_posts','event-manager-agenda','caes_em_agenda');
 add_submenu_page('event-manager','Tracks','Tracks','manage_options','event-manager-tracks','caes_em_tracks_page');
 add_submenu_page('event-manager','Settings','Settings','manage_options','event-manager-settings','caes_em_settings_page');
});
add_filter('post_row_actions',function($actions,$post){
 if($post->post_type==='caes_speaker' && isset($actions['edit'])) $actions['edit']='<a href="'.esc_url(admin_url('admin.php?page=event-manager-speakers&speaker='.$post->ID)).'">Edit speaker</a>';
 return $actions;
},10,2);

// Tracks are stored per event year; legacy Track 1/2 values remain valid identifiers.
function caes_em_tracks($year){
 $saved=get_option('caes_em_tracks_'.$year,null);
 if(!is_array($saved))return array(array('id'=>'Track 1','name'=>'Track 1','room'=>''),array('id'=>'Track 2','name'=>'Track 2','room'=>''));
 return $saved;
}
function caes_em_track_label($year,$id){
 if($id==='All Attendees')return 'All Attendees';
 foreach(caes_em_tracks($year) as $track)if($track['id']===$id)return $track['name'].(!empty($track['room'])?' ('.$track['room'].')':'');
 return $id;
}
function caes_em_track_ids($year){return array_column(caes_em_tracks($year),'id');}
function caes_em_session_types(){return array('Presentation','Keynote','Panel','Fireside Chat','Break','Lunch','Networking','Registration','Other');}
function caes_em_colour_defaults(){return array('Presentation'=>'#FFFFFF','Keynote'=>'#DCECF8','Panel'=>'#F0E4D3','Fireside Chat'=>'#F0E4D3','Break'=>'#E2E3E5','Lunch'=>'#E2E3E5','Networking'=>'#233B56','Registration'=>'#233B56','Other'=>'#FFFFFF');}
function caes_em_colours(){return array_merge(caes_em_colour_defaults(),(array)get_option('caes_em_session_colours',array()));}
function caes_em_text_colour($hex){$rgb=sscanf(ltrim($hex,'#'),'%02x%02x%02x');$l=(0.2126*$rgb[0]+0.7152*$rgb[1]+0.0722*$rgb[2]);return $l<145?'#FFFFFF':'#202B38';}
add_action('admin_post_caes_em_save_tracks',function(){
 if(!current_user_can('manage_options'))wp_die('Permission denied');check_admin_referer('caes_em_tracks');
 $year=caes_em_year(wp_unslash($_POST['event_year']??''));if(!$year)wp_die('Invalid event year');
 $ids=isset($_POST['track_id'])&&is_array($_POST['track_id'])?array_map('sanitize_text_field',wp_unslash($_POST['track_id'])):array();
 $names=isset($_POST['track_name'])&&is_array($_POST['track_name'])?array_map('sanitize_text_field',wp_unslash($_POST['track_name'])):array();
 $rooms=isset($_POST['track_room'])&&is_array($_POST['track_room'])?array_map('sanitize_text_field',wp_unslash($_POST['track_room'])):array();
 $tracks=array();$seen=array();
 foreach($names as $i=>$name){$name=trim($name);if($name==='')continue;$id=$ids[$i]??'';if(!preg_match('/^(Track [12]|track_[a-z0-9]{8,32})$/',$id))$id='track_'.wp_generate_password(12,false,false);if(isset($seen[$id]))continue;$seen[$id]=true;$tracks[]=array('id'=>$id,'name'=>substr($name,0,100),'room'=>substr($rooms[$i]??'',0,100));}
 if(count($tracks)>20)wp_die('Maximum 20 tracks per event');
 $used=get_posts(array('post_type'=>'caes_session','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'meta_query'=>array(array('key'=>'caes_session_year','value'=>$year)),'fields'=>'ids'));
 $used_ids=array();foreach($used as $sid){$t=get_post_meta($sid,'caes_session_track',true);if($t && $t!=='All Attendees')$used_ids[$t]=true;}
 $current=caes_em_tracks($year);$new_ids=array_column($tracks,'id');foreach($current as $t)if(isset($used_ids[$t['id']])&&!in_array($t['id'],$new_ids,true))$tracks[]=$t; // Preserve assigned tracks
 update_option('caes_em_tracks_'.$year,$tracks,false);
 wp_safe_redirect(add_query_arg(array('page'=>'event-manager-tracks','year'=>$year,'saved'=>1),admin_url('admin.php')));exit;
});
function caes_em_tracks_page(){
 if(!current_user_can('manage_options'))wp_die('Permission denied');$year=caes_em_year(wp_unslash($_GET['year']??''))?:date('Y');$tracks=caes_em_tracks($year);
 echo '<div class="wrap"><h1>Manage Tracks</h1><p>Set track names and rooms for each event year. Tracks with assigned sessions cannot be removed.</p>';
 if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Tracks saved.</p></div>';
 echo '<form method="get"><input type="hidden" name="page" value="event-manager-tracks"><label>Event year <input name="year" maxlength="4" pattern="[0-9]{4}" value="'.esc_attr($year).'"></label> <button class="button">Load year</button></form>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="caes_em_save_tracks"><input type="hidden" name="event_year" value="'.esc_attr($year).'">';wp_nonce_field('caes_em_tracks');
 echo '<table class="widefat striped" style="max-width:900px;margin:18px 0"><thead><tr><th>Track name</th><th>Room / location</th><th></th></tr></thead><tbody id="caes-track-rows">';
 foreach($tracks as $t)echo '<tr><td><input type="hidden" name="track_id[]" value="'.esc_attr($t['id']).'"><input type="text" name="track_name[]" value="'.esc_attr($t['name']).'" style="width:100%"></td><td><input type="text" name="track_room[]" value="'.esc_attr($t['room']).'" style="width:100%"></td><td><button type="button" class="button caes-remove-track">Remove</button></td></tr>';
 echo '</tbody></table><p><button type="button" class="button" id="caes-add-track">Add Track</button> <button type="submit" class="button button-primary">Save Tracks</button></p></form><p>Removing a track with sessions assigned will retain that track until its sessions are reassigned.</p></div>';
 echo <<<'TRACKJS'
<script>document.getElementById('caes-add-track').addEventListener('click',function(){var r=document.createElement('tr');r.innerHTML='<td><input type="hidden" name="track_id[]" value=""><input type="text" name="track_name[]" placeholder="Track name" style="width:100%"></td><td><input type="text" name="track_room[]" placeholder="Room" style="width:100%"></td><td><button type="button" class="button caes-remove-track">Remove</button></td>';document.getElementById('caes-track-rows').appendChild(r)});document.getElementById('caes-track-rows').addEventListener('click',function(e){if(e.target.classList.contains('caes-remove-track'))e.target.closest('tr').remove()});</script>
TRACKJS;
}
add_action('admin_post_caes_em_save_colours',function(){
 if(!current_user_can('manage_options'))wp_die('Permission denied');check_admin_referer('caes_em_colours');$defaults=caes_em_colour_defaults();$incoming=isset($_POST['colours'])&&is_array($_POST['colours'])?wp_unslash($_POST['colours']):array();$out=array();
 foreach($defaults as $type=>$default){$candidate=sanitize_hex_color($incoming[$type]??'');$out[$type]=$candidate?:$default;}
 update_option('caes_em_session_colours',$out,false);wp_safe_redirect(add_query_arg(array('page'=>'event-manager-settings','saved'=>1),admin_url('admin.php')));exit;
});
function caes_em_settings_page(){
 if(!current_user_can('manage_options'))wp_die('Permission denied');$colours=caes_em_colours();echo '<div class="wrap"><h1>Agenda Settings</h1><p>Choose background colours for session types. Text colour adjusts automatically for contrast.</p>';
 if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Colours saved.</p></div>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="caes_em_save_colours">';wp_nonce_field('caes_em_colours');echo '<table class="form-table">';foreach($colours as $type=>$hex)echo '<tr><th scope="row">'.esc_html($type).'</th><td><input type="color" class="caes-colour-picker" value="'.esc_attr($hex).'" data-type="'.esc_attr($type).'"> <input type="text" name="colours['.esc_attr($type).']" value="'.esc_attr($hex).'" pattern="#[0-9A-Fa-f]{6}" maxlength="7" class="regular-text caes-colour-hex"></td></tr>';echo '</table><button type="submit" class="button button-primary">Save Colours</button></form></div>';
 echo '<script>document.querySelectorAll(".caes-colour-picker").forEach(function(p){var input=p.parentNode.querySelector(".caes-colour-hex");p.addEventListener("input",function(){input.value=p.value.toUpperCase()});input.addEventListener("change",function(){if(/^#[0-9a-f]{6}$/i.test(input.value))p.value=input.value})});</script>';
}
function caes_em_normalize_time($raw){$raw=trim((string)$raw);if(preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/D',$raw,$m))return sprintf('%02d:%02d',(int)$m[1],(int)$m[2]);if(preg_match('/^([0-9]{3,4})$/D',$raw,$m)){$digits=str_pad($m[1],4,'0',STR_PAD_LEFT);$h=(int)substr($digits,0,2);$min=(int)substr($digits,2);if($h<24&&$min<60)return sprintf('%02d:%02d',$h,$min);}return '';}

function caes_em_dashboard(){
 if(!current_user_can('edit_others_posts'))wp_die('Permission denied');
 echo '<div class="wrap"><h1>Event Manager</h1><p>Manage your events in one place. Set up event years and venues, add speakers and sessions, then publish recordings and archive pages.</p>';
 $links=array(
  array('Event Archives','Manage event years, formats, visibility, and venue assignments.','event-manager-archives'),
  array('Venues','Save venue names and addresses once for reuse across events.','event-manager-venues'),
  array('Speakers','Maintain speaker profiles, photographs, and biographies.','event-manager-speakers'),
  array('Agenda','Build session schedules and assign speakers.','event-manager-agenda'),
  array('Recordings','Add video links and connect speakers to recorded sessions.','event-manager-recordings'),
  array('Sponsors','Manage sponsor profiles and multiyear assignments.','event-manager-sponsors'),
  array('Sponsorship Tiers','Configure tier names, ordering, and visibility.','event-manager-sponsor-tiers'),
  array('Tracks','Configure tracks and rooms for each event year.','event-manager-tracks'),
  array('Settings','Adjust public agenda colours and presentation.','event-manager-settings'));
 echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;max-width:1200px;margin:20px 0">';
 foreach($links as $item){if(in_array($item[2],array('event-manager-archives','event-manager-venues','event-manager-tracks','event-manager-settings','event-manager-sponsors','event-manager-sponsor-tiers'),true)&&!current_user_can('manage_options'))continue;
 echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:7px;padding:18px"><h2 style="margin-top:0">'.esc_html($item[0]).'</h2><p>'.esc_html($item[1]).'</p><a class="button" href="'.esc_url(admin_url('admin.php?page='.$item[2])).'">Open '.esc_html($item[0]).'</a></div>';}
 echo '</div><h2>Website display and shortcodes</h2><p>Use a Shortcode block in the WordPress editor. The Past Event Detail template already includes its archive shortcode, so do not add it a second time.</p>';
 if(function_exists('caes_em_shortcode_panel')){
 caes_em_shortcode_panel('Speaker directory','[event_speakers year="2026"]');
 caes_em_shortcode_panel('Agenda','[event_agenda year="2026"]');
 caes_em_shortcode_panel('Sponsors (current year)','[event_sponsors]');
 caes_em_shortcode_panel('Sponsors (specific year)','[event_sponsors year="2024"]');
 caes_em_shortcode_panel('Past Events overview','[event_archive_index]');
 caes_em_shortcode_panel('Past Event Detail (automatic year from summit-YYYY slug)','[event_archive_detail]');
 }
 echo '</div>';
}
function caes_em_styles(){echo '<style>.caes-em-form{max-width:920px}.caes-em-section{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 22px;margin:16px 0}.caes-em-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.caes-em-field{margin:12px 0}.caes-em-field label{display:block;font-weight:600;margin-bottom:6px}.caes-em-field input,.caes-em-field textarea,.caes-em-field select{width:100%;max-width:100%}.caes-em-preview{width:112px;height:112px;object-fit:cover;border-radius:50%;background:#f0f0f1}.caes-em-photo-row{display:flex;gap:22px;align-items:center}.caes-em-muted{color:#646970}.caes-em-table{max-width:1050px}.event-manager-agenda-layout{display:grid;grid-template-columns:minmax(0,920px) minmax(300px,420px);gap:24px;align-items:start;max-width:1390px}.event-manager-agenda-layout .caes-em-form{max-width:none;min-width:0}.event-manager-agenda-layout textarea[name="session_description"]{display:block;width:100%;min-height:230px;box-sizing:border-box;resize:vertical}.caes-em-live-preview{position:sticky;top:48px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-top:16px;overflow-wrap:anywhere}.caes-em-live-preview h2{margin-top:0}.caes-em-preview-card{border:1px solid #dcdcde;border-radius:8px;padding:18px;background:#fafafa}.caes-em-preview-card h3{font-size:19px;line-height:1.3;margin:12px 0}.caes-em-preview-meta{color:#646970;font-size:13px;line-height:1.6}.caes-em-preview-track{display:inline-block;background:#e7efff;color:#174ea6;border-radius:4px;padding:4px 8px;font-size:12px;font-weight:600}.caes-em-preview-description{white-space:pre-wrap;line-height:1.5;margin-top:14px}.caes-em-preview-speakers{font-weight:600;margin-top:12px}.caes-em-trash-form{display:inline-block;margin:0}.caes-em-delete-editor{grid-column:1 / -1;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px}.caes-em-preview-note{color:#646970;font-size:12px;margin-bottom:0}.caes-em-list-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,420px);gap:22px;align-items:start;max-width:1600px}.caes-em-list-preview{margin-top:0}.caes-em-list-card{margin:12px 0}.caes-em-list-card h3{font-size:15px}.caes-em-preview-person{line-height:1.45;margin:10px 0}.caes-em-preview-person br+*{font-weight:normal}.caes-em-inline-status{display:flex;gap:6px;align-items:center;flex-wrap:nowrap}.caes-em-inline-status select{width:110px;min-width:0}.caes-em-inline-status .button{white-space:nowrap}.caes-em-row-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.caes-em-row-actions .button{white-space:nowrap}.caes-em-row-actions .button-link-delete{color:#b32d2e;border:0;background:transparent;padding:0 4px;box-shadow:none;text-decoration:none}.caes-em-row-actions .button-link-delete:hover{color:#8a2424;text-decoration:underline}.caes-em-table{width:100%;max-width:1400px;table-layout:auto}.caes-em-table th:last-child{width:170px}.caes-em-table th:nth-last-child(2){width:190px}.caes-em-inline-status select{max-width:115px}.caes-em-inline-status button{cursor:pointer}.caes-em-year-filter{margin:15px 0}.caes-em-list-layout .widefat th,.caes-em-list-layout .widefat td{padding:9px 7px}.caes-em-preview-status{font-size:11px;color:#646970}@media(max-width:1250px){.caes-em-list-layout{grid-template-columns:1fr}.caes-em-list-preview{position:static}}@media(max-width:1150px){.event-manager-agenda-layout{grid-template-columns:minmax(0,1fr)}.caes-em-live-preview{position:static}}@media(max-width:700px){.caes-em-grid{grid-template-columns:1fr}.caes-em-photo-row{display:block}}</style>';}
function caes_em_field($key,$label,$value,$type='text'){
 echo '<div class="caes-em-field"><label for="'.esc_attr($key).'">'.esc_html($label).'</label><input id="'.esc_attr($key).'" name="'.esc_attr($key).'" type="'.esc_attr($type).'" value="'.esc_attr($value).'"'.($type==='url'?' placeholder="https://"':'').(in_array($key,array('session_start','session_end'),true)?' placeholder="HH:MM" inputmode="numeric" pattern="([0-9]{3,4}|([01]?[0-9]|2[0-3]):[0-5][0-9])"':'').($key==='session_year'?' maxlength="4" inputmode="numeric" pattern="[0-9]{4}"':'').(in_array($key,array('session_title','session_date','session_year','session_start','session_end'),true)?' required':'').'></div>';
}
function caes_em_speakers(){
 if(!current_user_can('edit_others_posts'))wp_die('Permission denied');
 caes_em_styles();
 $id=isset($_GET['speaker'])?absint($_GET['speaker']):0;
 if($id && (get_post_type($id)!=='caes_speaker'||!current_user_can('edit_post',$id)))wp_die('Invalid speaker or insufficient permissions');
 if(isset($_GET['trashed'])) echo '<div class="notice notice-success is-dismissible"><p>Speaker moved to Trash.</p></div>';
 if(isset($_GET['status_changed'])) echo '<div class="notice notice-success is-dismissible"><p>Speaker status updated.</p></div>';
 if(isset($_GET['merged'])) echo '<div class="notice notice-success is-dismissible"><p>Speaker profiles merged. The duplicate was moved to Trash.</p></div>';
 if(isset($_POST['caes_em_save_speaker'])){
  if(!current_user_can('edit_others_posts'))wp_die('Permission denied');
  check_admin_referer('caes_em_save_speaker');
  if($id && !current_user_can('edit_post',$id))wp_die('Permission denied');
  $name=sanitize_text_field(wp_unslash($_POST['caes_name']??''));
  if(!$name){echo '<div class="notice notice-error"><p>A speaker name is required.</p></div>';}
  else{
   $data=array('post_type'=>'caes_speaker','post_title'=>$name,'post_content'=>wp_kses_post(wp_unslash($_POST['caes_bio']??'')),'post_status'=>sanitize_key($_POST['caes_status']??'draft')==='publish'?'publish':'draft');
   if($id)$data['ID']=$id;
   $result=$id?wp_update_post($data,true):wp_insert_post($data,true);
   if(is_wp_error($result)){echo '<div class="notice notice-error"><p>'.esc_html($result->get_error_message()).'</p></div>';}
   else{
    $id=$result;
    foreach(array('caes_job_title','caes_company','caes_linkedin','caes_website','caes_bluesky','caes_instagram','caes_x') as $key){
     $value=wp_unslash($_POST[$key]??'');update_post_meta($id,$key,in_array($key,array('caes_linkedin','caes_website','caes_bluesky','caes_instagram','caes_x'),true)?esc_url_raw($value):sanitize_text_field($value));
    }
    if(isset($_POST['caes_photo_url']))update_post_meta($id,'caes_photo_url',caes_sanitize_photo_url(wp_unslash($_POST['caes_photo_url'])));
    $media=absint($_POST['caes_media_id']??0);
    if($media && current_user_can('edit_post',$media) && get_post_type($media)==='attachment' && wp_attachment_is_image($media))set_post_thumbnail($id,$media);
    elseif(isset($_POST['caes_remove_media']) && $_POST['caes_remove_media']==='1')delete_post_thumbnail($id);
    $raw_years=sanitize_text_field(wp_unslash($_POST['caes_years_text']??''));$years=array_filter(array_map('trim',explode(',',$raw_years)));$years=array_values(array_unique($years));
    if(array_filter($years,function($y){return !caes_em_year($y);}))echo '<div class="notice notice-error"><p>Enter valid four-digit years separated by commas. Event years were not changed.</p></div>';
    else wp_set_object_terms($id,$years,'caes_speaker_year',false);
    if(isset($_POST['caes_em_save_speaker']) && $_POST['caes_em_save_speaker']==='save_new'){$id=0;}
    echo '<div class="notice notice-success"><p>Speaker saved.</p></div>';
   }
  }
 }
 echo '<div class="wrap"><h1>Speakers</h1>';
 if(!$id && isset($_GET['merge'])){caes_em_speaker_merge_screen(absint($_GET['merge']));echo '</div>';return;}
 if(!$id && !isset($_GET['new'])){
  echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=event-manager-speakers&new=1')).'">Add speaker</a></p>';
  // Whitelisted GET parameters affect presentation only; no records are modified.
  $sort = isset($_GET['speaker_sort']) ? sanitize_key(wp_unslash($_GET['speaker_sort'])) : 'name_asc';
  if (!in_array($sort, array('name_asc','name_desc','updated'), true)) $sort = 'name_asc';
  $group = isset($_GET['speaker_group']) ? sanitize_key(wp_unslash($_GET['speaker_group'])) : 'none';
  if (!in_array($group, array('year','none'), true)) $group = 'year';
  echo '<form method="get" class="caes-em-year-filter" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap">';
  echo '<input type="hidden" name="page" value="event-manager-speakers">';
  echo '<label>Sort by<br><select name="speaker_sort">';
  foreach (array('name_asc'=>'Name (A to Z)','name_desc'=>'Name (Z to A)','updated'=>'Recently updated') as $value=>$label) echo '<option value="'.esc_attr($value).'"'.selected($sort,$value,false).'>'.esc_html($label).'</option>';
  echo '</select></label><label>Group by<br><select name="speaker_group">';
  foreach (array('year'=>'Event year','none'=>'No grouping') as $value=>$label) echo '<option value="'.esc_attr($value).'"'.selected($group,$value,false).'>'.esc_html($label).'</option>';
  echo '</select></label><label>Filter by year<br><select name="speaker_year"><option value="">All years</option>';
  $year_terms=get_terms(array('taxonomy'=>'caes_speaker_year','hide_empty'=>false));
  $year_filter=isset($_GET['speaker_year'])?sanitize_text_field(wp_unslash($_GET['speaker_year'])):'';
  if(!is_wp_error($year_terms))foreach($year_terms as $term)echo '<option value="'.esc_attr($term->name).'"'.selected($year_filter,$term->name,false).'>'.esc_html($term->name).'</option>';
  echo '</select></label><button type="submit" class="button">Apply</button></form>';
  $posts=get_posts(array('post_type'=>'caes_speaker','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'orderby'=>$sort==='updated'?'modified':'title','order'=>$sort==='name_desc'?'DESC':($sort==='updated'?'DESC':'ASC')));
  if($year_filter!=='') $posts=array_values(array_filter($posts,function($p)use($year_filter){$years=wp_get_object_terms($p->ID,'caes_speaker_year',array('fields'=>'names'));return !is_wp_error($years)&&in_array($year_filter,$years,true);}));
  $groups=array();
  if ($group==='year') {
   foreach ($posts as $p) {
    $years=wp_get_object_terms($p->ID,'caes_speaker_year',array('fields'=>'names'));
    if (is_wp_error($years) || !$years) $years=array('Unassigned');
    foreach (array_unique($years) as $year) $groups[(string)$year][]=$p;
   }
   uksort($groups, function($a,$b){if($a==='Unassigned')return 1;if($b==='Unassigned')return -1;return (int)$b<=>(int)$a;});
  } else $groups=array('All speakers'=>$posts);
  caes_em_bulk_toolbar('speakers');
  foreach ($groups as $heading=>$members) {
   echo '<h2 style="margin-top:24px">'.esc_html($heading).' <span class="caes-em-muted" style="font-size:13px;font-weight:normal">('.count($members).' speakers)</span></h2>';
   echo '<table class="widefat striped caes-em-table"><thead><tr><th><input type="checkbox" class="caes-em-select-all" aria-label="Select all speakers in this table"></th><th>Name</th><th>Title</th><th>Company</th><th>Event years</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
   foreach ($members as $p) {
    $years=wp_get_object_terms($p->ID,'caes_speaker_year',array('fields'=>'names'));
    if (is_wp_error($years)) $years=array();
    rsort($years,SORT_NUMERIC);
    echo '<tr><td><input type="checkbox" class="caes-em-bulk-item" name="bulk_ids[]" value="'.esc_attr($p->ID).'" form="caes-em-bulk-speakers" aria-label="Select '.esc_attr($p->post_title).'"></td><td><a href="'.esc_url(admin_url('admin.php?page=event-manager-speakers&speaker='.$p->ID)).'"><strong>'.esc_html($p->post_title).'</strong></a></td><td>'.esc_html(get_post_meta($p->ID,'caes_job_title',true)).'</td><td>'.esc_html(get_post_meta($p->ID,'caes_company',true)).'</td><td>'.esc_html(implode(', ',$years)).'</td><td>';
    echo '<form class="caes-em-inline-status" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="caes_em_speaker_status"><input type="hidden" name="speaker_id" value="'.esc_attr($p->ID).'">';
    wp_nonce_field('caes_em_speaker_status_'.$p->ID);
    echo '<select name="status" aria-label="Status for '.esc_attr($p->post_title).'"><option value="draft"'.selected($p->post_status,'draft',false).'>Draft</option><option value="publish"'.selected($p->post_status,'publish',false).'>Published</option></select> <button type="submit" class="button button-small">Update</button></form></td><td>';
    echo '<div class="caes-em-row-actions"><a class="button button-small" href="'.esc_url(admin_url('admin.php?page=event-manager-speakers&merge='.$p->ID)).'" title="Merge this duplicate into another speaker profile">Merge into…</a> '; 
    caes_em_trash_speaker_form($p->ID);
    echo '</div></td></tr>';
   }
   echo '</tbody></table>';
  }
  if (!$posts) echo '<p>No speakers found.</p>';
  echo '</div>';return;

 }
 $p=$id?get_post($id):null;
 wp_enqueue_media();wp_enqueue_editor();
 $photo_id=$id?get_post_thumbnail_id($id):0;
 $photo=$photo_id?wp_get_attachment_image_url($photo_id,'thumbnail'):($id?get_post_meta($id,'caes_photo_url',true):'');
 echo '<p><a href="'.esc_url(admin_url('admin.php?page=event-manager-speakers')).'">&larr; All speakers</a></p><form method="post" class="caes-em-form">';
 wp_nonce_field('caes_em_save_speaker');
 echo '<div class="caes-em-section"><h2>Basic information</h2>';
 caes_em_field('caes_name','Full name',$p?$p->post_title:'');
 echo '<div class="caes-em-grid">';
 foreach(array('caes_job_title'=>'Job title','caes_company'=>'Company') as $k=>$label)caes_em_field($k,$label,$id?get_post_meta($id,$k,true):'');
 echo '</div></div><div class="caes-em-section"><h2>Photograph</h2><div class="caes-em-photo-row"><img id="caes-em-photo-preview" class="caes-em-preview" src="'.esc_url($photo?:includes_url('images/media/default.png')).'" alt="Photo preview"><div><button type="button" class="button" id="caes-em-select-photo">Choose from Media Library</button><p class="caes-em-muted">Media Library photographs take priority over external URLs.</p></div></div>';
 echo '<input type="hidden" name="caes_media_id" id="caes_media_id" value="'.esc_attr($photo_id).'"><label><input type="checkbox" name="caes_remove_media" value="1"> Remove Media Library photograph</label>';
 caes_em_field('caes_photo_url','External photograph URL (optional)',$id?get_post_meta($id,'caes_photo_url',true):'','url');
 echo '</div><div class="caes-em-section"><h2>Biography</h2>';
 wp_editor($p?$p->post_content:'','caes_bio',array('textarea_name'=>'caes_bio','textarea_rows'=>12,'media_buttons'=>false,'teeny'=>true));
 echo '</div><div class="caes-em-section"><h2>Social links</h2><div class="caes-em-grid">';
 foreach(array('caes_linkedin'=>'LinkedIn','caes_website'=>'Website','caes_bluesky'=>'Bluesky','caes_instagram'=>'Instagram','caes_x'=>'X') as $k=>$label)caes_em_field($k,$label,$id?get_post_meta($id,$k,true):'','url');
 echo '</div></div><div class="caes-em-section"><h2>Publishing</h2><div class="caes-em-grid"><div class="caes-em-field"><label for="caes_status">Status</label><select name="caes_status" id="caes_status"><option value="draft"'.selected(!$p||$p->post_status!=='publish',true,false).'>Draft</option><option value="publish"'.selected($p&&$p->post_status==='publish',true,false).'>Published</option></select></div><div class="caes-em-field"><label>Event years</label>';
 $assigned=$id?wp_get_object_terms($id,'caes_speaker_year',array('fields'=>'names')):array();
 if(is_wp_error($assigned))$assigned=array();
 $available_years=array_keys(caes_em_archives());$available_years=array_values(array_unique(array_merge($available_years,$assigned)));sort($available_years,SORT_NUMERIC);
 echo '<div class="caes-speaker-years" style="display:flex;gap:9px;flex-wrap:wrap">';foreach($available_years as $year){echo '<label style="border:1px solid #ccd0d4;padding:7px 10px;border-radius:5px"><input type="checkbox" class="caes-speaker-year-check" value="'.esc_attr($year).'" '.checked(in_array((string)$year,array_map('strval',$assigned),true),true,false).'> '.esc_html($year).'</label>';}
 echo '</div><input type="hidden" id="caes-speaker-years-value" name="caes_years_text" value="'.esc_attr(implode(', ', $assigned)).'"><p class="caes-em-muted">Select existing event years. To add an additional year, use the field below.</p><input type="text" id="caes-speaker-extra-year" placeholder="Additional year (optional)" pattern="20[0-9]{2}" maxlength="4" style="max-width:200px"><script>jQuery(function($){function sync(){var years=$(".caes-speaker-year-check:checked").map(function(){return this.value}).get();var extra=$("#caes-speaker-extra-year").val().trim();if(/^20[0-9]{2}$/.test(extra)&&years.indexOf(extra)<0)years.push(extra);$("#caes-speaker-years-value").val(years.join(", "));}$(document).on("change",".caes-speaker-year-check",sync);$("#caes-speaker-extra-year").on("input",sync);});</script></div></div></div><p><button class="button button-primary button-large" type="submit" name="caes_em_save_speaker" value="1">Save speaker</button> <button class="button button-large" type="submit" name="caes_em_save_speaker" value="save_new">Save and add another</button> ';
 if($id && $p->post_status==='publish')echo '<a class="button" target="_blank" rel="noopener" href="'.esc_url(get_permalink($id)).'">View profile</a>';
 echo '</p></form>';
 if($id){echo '<div class="caes-em-form caes-em-section"><h2>Remove speaker</h2><p>Moving this speaker to Trash removes their public profile. Sessions referencing this speaker should be reviewed.</p>';caes_em_trash_speaker_form($id);echo '</div>';}
 echo '</div>';
 echo '<script>jQuery(function($){$("#caes-em-select-photo").on("click",function(){var f=wp.media({title:"Choose speaker photograph",button:{text:"Use photograph"},multiple:false,library:{type:"image"}});f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#caes_media_id").val(a.id);$("#caes-em-photo-preview").attr("src",a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url);$("input[name=caes_remove_media]").prop("checked",false)});f.open()});});</script>';
}
// Quick status updates use a dedicated action, not the edit form.
add_action('admin_post_caes_em_speaker_status',function(){
 $id=absint($_POST['speaker_id']??0);
 if(!$id||get_post_type($id)!=='caes_speaker'||!current_user_can('edit_post',$id))wp_die('Permission denied');
 check_admin_referer('caes_em_speaker_status_'.$id);
 $status=sanitize_key(wp_unslash($_POST['status']??''));
 if(!in_array($status,array('draft','publish'),true))wp_die('Invalid status');
 if($status==='publish'&&!current_user_can('publish_posts'))wp_die('Publishing permission required');
 $result=wp_update_post(array('ID'=>$id,'post_status'=>$status),true);
 if(is_wp_error($result))wp_die(esc_html($result->get_error_message()));
 wp_safe_redirect(admin_url('admin.php?page=event-manager-speakers&status_changed=1'));exit;
});
function caes_em_speaker_merge_screen($source){
 if(get_post_type($source)!=='caes_speaker'||get_post_status($source)==='trash'||!current_user_can('edit_post',$source)){echo '<p>Invalid speaker.</p>';return;}
 $posts=get_posts(array('post_type'=>'caes_speaker','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','exclude'=>array($source)));
 echo '<h2>Merge speaker profiles</h2><p><strong>How it works:</strong> You clicked Merge into… on the duplicate profile. Select the other profile below to keep. No additional row checkboxes are needed. The retained profile keeps its existing details; missing fields and event years are copied from the duplicate. Agenda and recording references are reassigned.</p>';
 echo '<p><strong>Profile to remove after merging:</strong> '.esc_html(get_the_title($source)).' (ID '.esc_html($source).')</p>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
 wp_nonce_field('caes_em_merge_speaker_'.$source);
 echo '<input type="hidden" name="action" value="caes_em_merge_speaker"><input type="hidden" name="source_id" value="'.esc_attr($source).'">';
 echo '<label for="caes_merge_target"><strong>Select the profile to keep</strong></label> <select id="caes_merge_target" name="target_id" required><option value="">Select a speaker</option>';
 foreach($posts as $post)echo '<option value="'.esc_attr($post->ID).'">'.esc_html($post->post_title.' ('.$post->post_status.', ID '.$post->ID.')').'</option>';
 echo '</select><p><label><input type="checkbox" name="confirm_merge" value="1" required> I understand that the duplicate will be moved to Trash after its references are updated.</label></p>';
 echo '<p><button class="button button-primary" type="submit">Merge profiles</button> <a class="button" href="'.esc_url(admin_url('admin.php?page=event-manager-speakers')).'">Cancel</a></p></form>';
}
add_action('admin_post_caes_em_merge_speaker',function(){
 $source=absint($_POST['source_id']??0);$target=absint($_POST['target_id']??0);
 if(!$source||!$target||$source===$target||get_post_type($source)!=='caes_speaker'||get_post_type($target)!=='caes_speaker'||get_post_status($source)==='trash'||get_post_status($target)==='trash')wp_die('Invalid merge selection');
 if(!current_user_can('edit_post',$source)||!current_user_can('edit_post',$target)||!current_user_can('delete_post',$source))wp_die('Permission denied');
 check_admin_referer('caes_em_merge_speaker_'.$source);
 if(empty($_POST['confirm_merge']))wp_die('Merge confirmation required');
 // Preserve the target profile, fill only empty fields from the duplicate.
 foreach(array('caes_job_title','caes_company','caes_linkedin','caes_website','caes_bluesky','caes_instagram','caes_x','caes_photo_url') as $key){
  if(!get_post_meta($target,$key,true)){$value=get_post_meta($source,$key,true);if($value!=='')update_post_meta($target,$key,$value);}
 }
 $t=get_post($target);$s=get_post($source);
 if(!trim($t->post_content)&&trim($s->post_content))wp_update_post(array('ID'=>$target,'post_content'=>$s->post_content));
 if(!get_post_thumbnail_id($target)&&get_post_thumbnail_id($source))set_post_thumbnail($target,get_post_thumbnail_id($source));
 $years_a=wp_get_object_terms($target,'caes_speaker_year',array('fields'=>'ids'));
 $years_b=wp_get_object_terms($source,'caes_speaker_year',array('fields'=>'ids'));
 if(is_wp_error($years_a)||is_wp_error($years_b))wp_die('Unable to retrieve speaker event years');
 $terms=wp_set_object_terms($target,array_values(array_unique(array_merge($years_a,$years_b))),'caes_speaker_year',false);
 if(is_wp_error($terms))wp_die('Unable to merge event years');
 foreach(array('caes_session'=>array('caes_session_speakers','caes_session_moderator'),'caes_recording'=>array('caes_rec_speaker_ids')) as $type=>$keys){
  $items=get_posts(array('post_type'=>$type,'post_status'=>array('publish','draft','pending','private','future'),'posts_per_page'=>-1));
  foreach($items as $item)foreach($keys as $key){
   $value=get_post_meta($item->ID,$key,true);
   if(is_array($value)){
    if(!in_array($source,array_map('intval',$value),true))continue;
    $value=array_values(array_unique(array_map('intval',array_map(function($v)use($source,$target){return (int)$v===$source?$target:$v;},$value))));
    update_post_meta($item->ID,$key,$value);
   }elseif((int)$value===$source)update_post_meta($item->ID,$key,$target);
  }
 }
 $trashed=wp_trash_post($source);
 if(!$trashed||is_wp_error($trashed))wp_die('References updated, but the duplicate could not be moved to Trash. Please review both profiles.');
 wp_safe_redirect(admin_url('admin.php?page=event-manager-speakers&merged=1'));exit;
});
// Speaker deletion is a separate, nonce-protected request. It moves the profile to Trash.
add_action('admin_post_caes_em_trash_speaker', function () {
 $id = isset($_POST['speaker_id']) ? absint($_POST['speaker_id']) : 0;
 if (!$id || get_post_type($id) !== 'caes_speaker' || !current_user_can('delete_post', $id)) wp_die('Invalid speaker or insufficient permissions');
 check_admin_referer('caes_em_trash_speaker_' . $id);
 $result=wp_trash_post($id);
 if (!$result || is_wp_error($result)) wp_die('Unable to move this speaker to Trash.');
 wp_safe_redirect(admin_url('admin.php?page=event-manager-speakers&trashed=1'));
 exit;
});
function caes_em_trash_speaker_form($id) {
 if (!current_user_can('delete_post', $id)) return;
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="caes-em-trash-form" onsubmit="return confirm(\'Move this speaker to Trash? Their public profile will disappear, and any sessions featuring them should be reviewed.\');">';
 echo '<input type="hidden" name="action" value="caes_em_trash_speaker"><input type="hidden" name="speaker_id" value="'.esc_attr($id).'">';
 wp_nonce_field('caes_em_trash_speaker_' . $id);
 echo '<button type="submit" class="button button-link-delete">Move to Trash</button></form>';
}
// Move sessions to WordPress Trash rather than permanently deleting them.
add_action('admin_post_caes_em_trash_session', function () {
 if (!current_user_can('edit_others_posts')) wp_die('Permission denied');
 $id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
 if (!$id || get_post_type($id) !== 'caes_session' || !current_user_can('delete_post', $id)) wp_die('Invalid session or insufficient permissions');
 check_admin_referer('caes_em_trash_session_' . $id);
 $result = wp_trash_post($id);
 if (!$result) wp_die('Unable to move this session to Trash.');
 wp_safe_redirect(admin_url('admin.php?page=event-manager-agenda&trashed=1'));
 exit;
});
function caes_em_trash_form($id) {
 if (!current_user_can('delete_post', $id)) return;
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="caes-em-trash-form" onsubmit="return confirm(\'Move this session to Trash? You can restore it using WordPress.\');">';
 echo '<input type="hidden" name="action" value="caes_em_trash_session"><input type="hidden" name="session_id" value="'.esc_attr($id).'">';
 wp_nonce_field('caes_em_trash_session_' . $id);
 echo '<button type="submit" class="button button-link-delete">Delete</button></form>';
}
function caes_em_year($value){
 $value=trim((string)$value);
 return preg_match('/^[0-9]{4}$/D',$value) && (int)$value>=1900 && (int)$value<=2100 ? $value : '';
}
function caes_em_agenda_url($args=array()){return add_query_arg(array_merge(array('page'=>'event-manager-agenda'),$args),admin_url('admin.php'));}
// Handle POST before WordPress outputs admin headers.
add_action('admin_post_caes_em_save_session', function () {
 if (!current_user_can('edit_others_posts')) wp_die('Permission denied');
 check_admin_referer('caes_em_save_session');
 $id=absint($_POST['session_id']??0);
 if($id && (get_post_type($id)!=='caes_session'||!current_user_can('edit_post',$id)))wp_die('Permission denied');
 $title=sanitize_text_field(wp_unslash($_POST['session_title']??''));
 $date=sanitize_text_field(wp_unslash($_POST['session_date']??''));
 $year=caes_em_year(wp_unslash($_POST['session_year']??''));
 $start=caes_em_normalize_time(wp_unslash($_POST['session_start']??''));
 $end=caes_em_normalize_time(wp_unslash($_POST['session_end']??''));
 $status=sanitize_key(wp_unslash($_POST['session_status']??'draft'))==='publish'?'publish':'draft';
 $errors=array();
 if(!$title)$errors[]='Session title is required.';
 if(!$year)$errors[]='Enter a valid four-digit event year (1900 to 2100).';
 if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))$errors[]='Enter a valid date.';
 if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$start))$errors[]='Enter a valid start time.';
 if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$end)||$end<=$start)$errors[]='End time must be later than start time.';
 if($errors){
  set_transient('caes_em_error_'.get_current_user_id(),array('errors'=>$errors,'values'=>wp_unslash($_POST)),5*MINUTE_IN_SECONDS);
  wp_safe_redirect(caes_em_agenda_url($id?array('session'=>$id,'validation'=>1):array('new'=>1,'validation'=>1)));exit;
 }
 $data=array('post_type'=>'caes_session','post_title'=>$title,'post_content'=>wp_kses_post(wp_unslash($_POST['session_description']??'')),'post_status'=>$status);
 if($id)$data['ID']=$id;
 $result=$id?wp_update_post($data,true):wp_insert_post($data,true);
 if(is_wp_error($result))wp_die(esc_html($result->get_error_message()));
 $id=$result;
 $tracks=array_merge(array('All Attendees'),caes_em_track_ids($year));
 $types=caes_em_session_types();
 $track=sanitize_text_field(wp_unslash($_POST['session_track']??''));
 $type=sanitize_text_field(wp_unslash($_POST['session_type']??''));
 foreach(array('date'=>$date,'start'=>$start,'end'=>$end,'year'=>$year,'track'=>in_array($track,$tracks,true)?$track:'All Attendees','type'=>in_array($type,$types,true)?$type:'Presentation') as $key=>$val)update_post_meta($id,'caes_session_'.$key,$val);
 $speakers=isset($_POST['session_speakers'])&&is_array($_POST['session_speakers'])?array_values(array_unique(array_filter(array_map('absint',$_POST['session_speakers']),function($sid){return get_post_type($sid)==='caes_speaker';}))):array();
 update_post_meta($id,'caes_session_speakers',$speakers);
 $moderator=absint($_POST['session_moderator']??0);
 update_post_meta($id,'caes_session_moderator',($moderator&&get_post_type($moderator)==='caes_speaker')?$moderator:0);
 $next=sanitize_key(wp_unslash($_POST['caes_em_save_session']??'save'))==='save_new';
 wp_safe_redirect($next?caes_em_agenda_url(array('new'=>1,'saved'=>1,'date'=>$date,'year'=>$year)):caes_em_agenda_url(array('session'=>$id,'saved'=>1)));exit;
});
add_action('admin_post_caes_em_change_status',function(){
 if(!current_user_can('edit_others_posts'))wp_die('Permission denied');
 $id=absint($_POST['session_id']??0);
 if(get_post_type($id)!=='caes_session'||!current_user_can('edit_post',$id))wp_die('Permission denied');
 check_admin_referer('caes_em_status_'.$id);
 $status=sanitize_key(wp_unslash($_POST['session_status']??''));
 if(!in_array($status,array('publish','draft'),true))wp_die('Invalid status');
 wp_update_post(array('ID'=>$id,'post_status'=>$status));
 $filter=caes_em_year(wp_unslash($_POST['filter_year']??''));
 wp_safe_redirect(caes_em_agenda_url(array('year'=>$filter?:'all','updated'=>1)));exit;
});
function caes_em_agenda(){
 if(!current_user_can('edit_others_posts'))wp_die('Permission denied');
 $id=absint($_GET['session']??0);
 if($id && (get_post_type($id)!=='caes_session'||!current_user_can('edit_post',$id)))wp_die('Invalid session or insufficient permissions');
 $error='';$old=array();if(isset($_GET['validation'])){$saved=get_transient('caes_em_error_'.get_current_user_id());if(is_array($saved)){$old=$saved['values'];$error=implode(' ',$saved['errors']);delete_transient('caes_em_error_'.get_current_user_id());}}
 caes_em_styles();
 if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
 if(isset($_GET['saved']))echo '<div class="notice notice-success is-dismissible"><p>Session saved.</p></div>';
 if(isset($_GET['updated']))echo '<div class="notice notice-success is-dismissible"><p>Session status updated.</p></div>';
 if(isset($_GET['trashed']))echo '<div class="notice notice-success is-dismissible"><p>Session moved to Trash.</p></div>';
 echo '<div class="wrap"><h1>Agenda</h1>';
 if(!$id&&!isset($_GET['new'])){
  $all=get_posts(array('post_type'=>'caes_session','post_status'=>array('publish','draft'),'posts_per_page'=>-1));
  $years=array();foreach($all as $p){$y=caes_em_year(get_post_meta($p->ID,'caes_session_year',true));if($y)$years[$y]=$y;}
  rsort($years,SORT_NUMERIC);
  $filter=isset($_GET['year'])?sanitize_text_field(wp_unslash($_GET['year'])):($years[0]??'all');
  if($filter!=='all'&&!caes_em_year($filter))$filter='all';
  $posts=array_values(array_filter($all,function($p)use($filter){return $filter==='all'||get_post_meta($p->ID,'caes_session_year',true)===$filter;}));
  usort($posts,function($a,$b){$a_key=get_post_meta($a->ID,'caes_session_date',true).' '.get_post_meta($a->ID,'caes_session_start',true);$b_key=get_post_meta($b->ID,'caes_session_date',true).' '.get_post_meta($b->ID,'caes_session_start',true);return strcmp($a_key,$b_key);});
  echo '<p>Manage sessions and preview the selected event year. Only published sessions appear on the public agenda.</p>';
  echo '<p><a class="button button-primary" href="'.esc_url(caes_em_agenda_url(array('new'=>1,'year'=>$filter==='all'?($years[0]??''):$filter))).'">Add session</a></p>';
  echo '<form method="get" class="caes-em-year-filter"><input type="hidden" name="page" value="event-manager-agenda"><label for="caes-filter-year"><strong>Filter year</strong></label> <select id="caes-filter-year" name="year"><option value="all"'.selected($filter,'all',false).'>All years</option>';
  foreach($years as $y)echo '<option value="'.esc_attr($y).'"'.selected($filter,$y,false).'>'.esc_html($y).'</option>';
  echo '</select> <button class="button">Filter</button></form>';
  echo '<div class="caes-em-list-layout"><div><table class="widefat striped"><thead><tr><th>Session</th><th>Date</th><th>Start</th><th>Track</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
  foreach($posts as $p){
   echo '<tr><td><a href="'.esc_url(caes_em_agenda_url(array('session'=>$p->ID))).'">'.esc_html($p->post_title).'</a></td><td>'.esc_html(get_post_meta($p->ID,'caes_session_date',true)).'</td><td>'.esc_html(get_post_meta($p->ID,'caes_session_start',true)).'</td><td>'.esc_html(get_post_meta($p->ID,'caes_session_track',true)).'</td><td>';
   if(current_user_can('edit_post',$p->ID)){
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="caes-em-inline-status"><input type="hidden" name="action" value="caes_em_change_status"> <input type="hidden" name="session_id" value="'.esc_attr($p->ID).'"><input type="hidden" name="filter_year" value="'.esc_attr($filter).'">';wp_nonce_field('caes_em_status_'.$p->ID);
    echo '<select name="session_status" aria-label="Status for '.esc_attr($p->post_title).'" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"><option value="draft"'.selected($p->post_status,'draft',false).'>Draft</option><option value="publish"'.selected($p->post_status,'publish',false).'>Published</option></select><button type="submit" name="caes_em_change_status" value="1">Update</button></form>';
   }else echo esc_html($p->post_status);
   echo '</td><td>';caes_em_trash_form($p->ID);echo '</td></tr>';
  }
  if(!$posts)echo '<tr><td colspan="6">No sessions for this year.</td></tr>';
  echo '</tbody></table></div><aside class="caes-em-live-preview caes-em-list-preview"><h2>Draft agenda preview</h2><p class="caes-em-muted">Includes draft and published sessions. This preview is visible only to editors.</p>';
  foreach($posts as $p){$date=get_post_meta($p->ID,'caes_session_date',true);$start=get_post_meta($p->ID,'caes_session_start',true);$end=get_post_meta($p->ID,'caes_session_end',true);$track=get_post_meta($p->ID,'caes_session_track',true);
   echo '<div class="caes-em-preview-card caes-em-list-card"><div class="caes-em-preview-meta">'.esc_html($date).' | '.esc_html($start).($end?' to '.esc_html($end):'').'</div><span class="caes-em-preview-track">'.esc_html($track).'</span> <span class="caes-em-preview-status">'.esc_html(ucfirst($p->post_status)).'</span><h3>'.esc_html($p->post_title).'</h3>';
   $speakers=get_post_meta($p->ID,'caes_session_speakers',true);if(is_array($speakers))foreach($speakers as $sid){if(get_post_status($sid)==='publish'){echo '<p class="caes-em-preview-person"><strong>'.esc_html(get_the_title($sid)).'</strong><br>'.esc_html(get_post_meta($sid,'caes_company',true)).'</p>';}}
   echo '</div>';
  }
  echo '</aside></div></div>';return;
 }
 $p=$id?get_post($id):null;
 $value=function($k,$fallback='')use($id,$old){if(array_key_exists($k,$old))return sanitize_text_field($old[$k]);return $id?get_post_meta($id,'caes_'.$k,true):$fallback;};
 $default_date=isset($_GET['date'])?sanitize_text_field(wp_unslash($_GET['date'])):'';
 $default_year=caes_em_year(wp_unslash($_GET['year']??''));
 echo '<p><a href="'.esc_url(caes_em_agenda_url()).'">&larr; All sessions</a></p><div class="event-manager-agenda-layout"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="caes-em-form"><input type="hidden" name="action" value="caes_em_save_session"><input type="hidden" name="session_id" value="'.esc_attr($id).'">';wp_nonce_field('caes_em_save_session');
 echo '<div class="caes-em-section"><h2>Session information</h2>';caes_em_field('session_title','Session title',isset($old['session_title'])?sanitize_text_field($old['session_title']):($p?$p->post_title:''));
 echo '<div class="caes-em-grid">';caes_em_field('session_date','Date',$value('session_date',$default_date),'date');caes_em_field('session_year','Event year',$value('session_year',$default_year));echo '</div>';
 echo '<div class="caes-em-grid">';caes_em_field('session_start','Start time (24h)',$value('session_start'),'text');caes_em_field('session_end','End time (24h)',$value('session_end'),'text');echo '</div>';
 echo '<div class="caes-em-grid">';$editor_year=caes_em_year($value('session_year',$default_year))?:date('Y');$available_tracks=caes_em_tracks($editor_year);$current_track=$value('session_track');
 echo '<div class="caes-em-field"><label for="session_track">Track</label><select name="session_track" id="session_track"><option value="All Attendees">All Attendees</option>';foreach($available_tracks as $track)echo '<option value="'.esc_attr($track['id']).'"'.selected($current_track,$track['id'],false).'>'.esc_html(caes_em_track_label($editor_year,$track['id'])).'</option>';if($current_track && $current_track!=='All Attendees'&&!in_array($current_track,array_column($available_tracks,'id'),true))echo '<option selected value="'.esc_attr($current_track).'">'.esc_html($current_track).' (historical)</option>';echo '</select><p class="caes-em-muted">Manage tracks in Event Manager &gt; Tracks.</p></div>';
 echo '<div class="caes-em-field"><label for="session_type">Session type</label><select name="session_type" id="session_type">';foreach(caes_em_session_types() as $type)echo '<option value="'.esc_attr($type).'"'.selected($value('session_type'),$type,false).'>'.esc_html($type).'</option>';echo '</select></div></div></div>';
 echo '<div class="caes-em-section"><h2>Speakers</h2><p class="caes-em-muted">Select one or more existing speaker profiles.</p><select name="session_speakers[]" multiple size="8" style="width:100%">';
 $selected=$id?get_post_meta($id,'caes_session_speakers',true):array();if(isset($old['session_speakers']))$selected=array_map('absint',$old['session_speakers']);if(!is_array($selected))$selected=array();
 foreach(get_posts(array('post_type'=>'caes_speaker','post_status'=>array('publish','draft'),'posts_per_page'=>250,'orderby'=>'title','order'=>'ASC')) as $sp)echo '<option value="'.esc_attr($sp->ID).'" data-company="'.esc_attr(get_post_meta($sp->ID,'caes_company',true)).'"'.selected(in_array($sp->ID,$selected),true,false).'>'.esc_html($sp->post_title).'</option>';
 echo '</select><p class="caes-em-muted">Hold Ctrl or Command to select multiple speakers.</p><div class="caes-em-field" id="caes-moderator-field"><label for="session_moderator">Moderator (panels and fireside chats)</label><select id="session_moderator" name="session_moderator"><option value="0">No moderator selected</option>';
 $mod=isset($old['session_moderator'])?absint($old['session_moderator']):($id?absint(get_post_meta($id,'caes_session_moderator',true)):0);
 foreach(get_posts(array('post_type'=>'caes_speaker','post_status'=>array('publish','draft'),'posts_per_page'=>250,'orderby'=>'title','order'=>'ASC')) as $sp)echo '<option value="'.esc_attr($sp->ID).'" data-company="'.esc_attr(get_post_meta($sp->ID,'caes_company',true)).'"'.selected($mod,$sp->ID,false).'>'.esc_html($sp->post_title).'</option>';
 echo '</select></div></div>';
 $description=isset($old['session_description'])?wp_kses_post($old['session_description']):($p?$p->post_content:'');
 echo '<div class="caes-em-section"><h2>Session description</h2><textarea name="session_description" rows="9">'.esc_textarea($description).'</textarea></div><div class="caes-em-section"><label for="session_status"><strong>Status</strong></label><select name="session_status" id="session_status"><option value="draft"'.selected(($old['session_status']??($p?$p->post_status:'draft')),'draft',false).'>Draft</option><option value="publish"'.selected(($old['session_status']??($p?$p->post_status:'draft')),'publish',false).'>Published</option></select></div><p><button type="submit" class="button button-primary button-large" name="caes_em_save_session" value="save">Save</button> <button type="submit" class="button button-secondary button-large" name="caes_em_save_session" value="save_new">Save and Next</button></p></form>';
 echo '<aside class="caes-em-live-preview" aria-label="Live agenda preview"><h2>Live preview</h2><p class="caes-em-muted">See the session card update as you edit.</p><div class="caes-em-preview-card"><span class="caes-em-preview-track" id="caes-preview-track"></span><div id="caes-preview-moderator"></div><h3 id="caes-preview-title"></h3><div class="caes-em-preview-meta" id="caes-preview-meta"></div><div id="caes-preview-speakers"></div><div class="caes-em-preview-description" id="caes-preview-description"></div></div><p class="caes-em-preview-note">Editor preview only. This does not publish the agenda or save changes automatically.</p></aside></div>';
 if($id){echo '<div class="caes-em-delete-editor"><strong>Delete session</strong><p class="caes-em-muted">Move this session to WordPress Trash.</p>';caes_em_trash_form($id);echo '</div>';}
 echo '</div>';
 echo <<<'JS'
<script>jQuery(function($){var form=$('.event-manager-agenda-layout form');function val(n){return form.find('[name="'+n+'"]').val()||'';}function refresh(){ $('#caes-preview-title').text(val('session_title')||'Untitled session');$('#caes-preview-track').text(val('session_track'));var parts=[val('session_date'),val('session_start')+(val('session_end')?' to '+val('session_end'):''),val('session_type'),val('session_year')&&'Event year: '+val('session_year')].filter(Boolean);$('#caes-preview-meta').text(parts.join(' | '));var target=$('#caes-preview-speakers').empty();form.find('select[name="session_speakers[]"] option:selected').each(function(){var person=$('<p class="caes-em-preview-person">');person.append($('<strong>').text($(this).text()));var company=$(this).data('company');if(company)person.append($('<br>'),document.createTextNode(company));target.append(person);});var mod=form.find('#session_moderator option:selected');$('#caes-preview-moderator').text((val('session_type')==='Panel'||val('session_type')==='Fireside Chat')&&mod.val()!=='0'?'Moderator: '+mod.text()+(mod.data('company')?' · '+mod.data('company'):''):'');$('#caes-moderator-field').toggle(val('session_type')==='Panel'||val('session_type')==='Fireside Chat');$('#caes-preview-description').text(val('session_description'));}form.on('blur','input[name="session_start"],input[name="session_end"]',function(){var v=$(this).val().trim(),m=v.match(/^(\d{3,4})$/);if(m){var z=v.padStart(4,'0'),h=+z.slice(0,2),n=+z.slice(2);if(h<24&&n<60)$(this).val(z.slice(0,2)+':'+z.slice(2))}refresh()});form.on('input change','input,select,textarea',refresh);refresh();});</script>
JS;
}

// Public agenda with shared time column and concurrent tracks.
function caes_em_time_label($time,$mode='12'){
 if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',(string)$time))return (string)$time;
 if($mode==='24')return $time;
 return date('g:i A',strtotime('2000-01-01 '.$time));
}
function caes_em_public_session($post){
 $id=$post->ID;$track=get_post_meta($id,'caes_session_track',true);$type=get_post_meta($id,'caes_session_type',true);
 $year=caes_em_year(get_post_meta($id,'caes_session_year',true));$bg=caes_em_colours()[$type]??'#FFFFFF';$fg=caes_em_text_colour($bg);
 echo '<article class="caes-agenda-session" style="background:'.esc_attr($bg).';color:'.esc_attr($fg).' ;--caes-session-text:'.esc_attr($fg).'" ><div class="caes-agenda-label">'.esc_html(caes_em_track_label($year,$track)).' · '.esc_html($type).'</div><h3>'.esc_html($post->post_title).'</h3>';
 $moderator=absint(get_post_meta($id,'caes_session_moderator',true));
 if($moderator&&get_post_status($moderator)==='publish'){
  echo '<div class="caes-agenda-moderator"><strong>Moderator:</strong> <a href="'.esc_url(get_permalink($moderator)).'">'.esc_html(get_the_title($moderator)).'</a>';
  $company=get_post_meta($moderator,'caes_company',true);
  if($company)echo '<span class="caes-agenda-company">'.esc_html($company).'</span>';
  echo '</div>';
 }
 $speakers=get_post_meta($id,'caes_session_speakers',true);
 $visible=array();
 if(is_array($speakers))foreach($speakers as $sid){
  $sid=absint($sid);
  if($sid&&$sid!==$moderator&&get_post_status($sid)==='publish')$visible[$sid]=$sid;
 }
 if($visible){
  echo '<div class="caes-agenda-speaker-grid" aria-label="Session speakers">';
  foreach($visible as $sid){
   echo '<div class="caes-agenda-speaker"><a href="'.esc_url(get_permalink($sid)).'">'.esc_html(get_the_title($sid)).'</a>';
   $company=get_post_meta($sid,'caes_company',true);
   if($company)echo '<span class="caes-agenda-company">'.esc_html($company).'</span>';
   echo '</div>';
  }
  echo '</div>';
 }
 if(trim($post->post_content))echo '<details><summary>Session details</summary><div class="caes-agenda-description">'.wp_kses_post(wpautop($post->post_content)).'</div></details>';
 echo '</article>';
}
add_shortcode('event_agenda',function($atts){
 $atts=shortcode_atts(array('year'=>''),$atts,'event_agenda');$year=caes_em_year($atts['year']);if(!$year)return '<p>Agenda year is not configured.</p>';
 $posts=get_posts(array('post_type'=>'caes_session','post_status'=>'publish','posts_per_page'=>-1,'meta_query'=>array(array('key'=>'caes_session_year','value'=>$year))));if(!$posts)return '<p>The agenda has not been announced yet.</p>';
 $tracks=caes_em_tracks($year);$track_ids=array_column($tracks,'id');$days=array();foreach($posts as $p){$d=get_post_meta($p->ID,'caes_session_date',true);$st=get_post_meta($p->ID,'caes_session_start',true);$en=get_post_meta($p->ID,'caes_session_end',true);if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d))continue;$days[$d][$st.'|'.$en][]=$p;}ksort($days);$uid='caes-agenda-'.wp_unique_id();ob_start();
 echo '<div class="caes-public-agenda" id="'.esc_attr($uid).'"><div class="caes-agenda-toolbar"><strong>Event agenda</strong><div class="caes-agenda-format" aria-label="Time format"><button type="button" class="is-active" data-format="12" aria-pressed="true">12h</button><button type="button" data-format="24" aria-pressed="false">24h</button></div></div>';
 foreach($days as $date=>$slots){ksort($slots);echo '<h2 class="caes-agenda-day">'.esc_html(date_i18n('l, F j, Y',strtotime($date))).'</h2><div class="caes-agenda-table"><div class="caes-agenda-header" style="--caes-track-count:'.max(1,count($tracks)).'"><span>Time</span>';foreach($tracks as $track)echo '<span>'.esc_html(caes_em_track_label($year,$track['id'])).'</span>';echo '</div>';
 foreach($slots as $key=>$sessions){list($st,$en)=explode('|',$key,2);$t12=caes_em_time_label($st).' to '.caes_em_time_label($en);$t24=caes_em_time_label($st,'24').' to '.caes_em_time_label($en,'24');echo '<div class="caes-agenda-row"><div class="caes-agenda-time" data-time12="'.esc_attr($t12).'" data-time24="'.esc_attr($t24).'">'.esc_html($t12).'</div><div class="caes-agenda-slots" style="--caes-track-count:'.max(1,count($tracks)).'">';
 $by_track=array();$shared=array();foreach($sessions as $session){$track=get_post_meta($session->ID,'caes_session_track',true);if(in_array($track,$track_ids,true))$by_track[$track][]=$session;else $shared[]=$session;}
 foreach($shared as $session){echo '<div class="caes-agenda-shared">';caes_em_public_session($session);echo '</div>';}
 foreach($tracks as $track){echo '<div class="caes-agenda-track">';foreach(($by_track[$track['id']]??array()) as $session)caes_em_public_session($session);echo '</div>';}
 echo '</div></div>';}
 echo '</div>';}
 echo '</div>';return ob_get_clean();
});
add_action('wp_enqueue_scripts',function(){
 wp_register_style('caes-event-agenda',false,array(),'0.1.6');wp_enqueue_style('caes-event-agenda');
 wp_add_inline_style('caes-event-agenda','.caes-public-agenda{width:100%;max-width:1500px;margin:24px auto; color:#202b38}.caes-agenda-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:15px}.caes-agenda-format{display:flex;gap:4px}.caes-agenda-format button{border:1px solid #c6ccd3;background:#fff;padding:7px 14px;cursor:pointer;border-radius:5px}.caes-agenda-format button.is-active{background:#173a64;color:#fff}.caes-agenda-day{margin:22px 0 12px}.caes-agenda-table{border:1px solid #dcdcde;border-radius:9px;overflow:hidden}.caes-agenda-header,.caes-agenda-row{display:grid;grid-template-columns:120px minmax(0,1fr)}.caes-agenda-header{grid-template-columns:120px repeat(var(--caes-track-count),minmax(0,1fr));background:#233B56;color:#fff;font-weight:700;text-align:center}.caes-agenda-header span{padding:12px}.caes-agenda-row{border-top:1px solid #dcdcde}.caes-agenda-time{padding:18px 10px;font-weight:700;font-size:.88em;background:#f5f6f7;border-right:1px solid #dcdcde}.caes-agenda-slots{display:grid;grid-template-columns:repeat(var(--caes-track-count),minmax(0,1fr))}.caes-agenda-shared{grid-column:1/-1}.caes-agenda-track{border-right:1px solid #dcdcde;min-width:0}.caes-agenda-track:last-child{border-right:0}.caes-agenda-session{padding:18px;height:100%;box-sizing:border-box;overflow-wrap:anywhere}.caes-agenda-shared .caes-agenda-session{text-align:center}.caes-agenda-session h3{font-size:1.12em;margin:8px 0 12px;color:inherit}.caes-agenda-session a{color:inherit;text-decoration:underline}.caes-agenda-label{font-size:.78em;color:inherit;opacity:.85}.caes-agenda-moderator{margin:12px 0 10px;font-size:.92em;text-align:center}.caes-agenda-speaker-grid{display:flex;flex-wrap:wrap;justify-content:space-evenly;gap:12px 18px;margin:12px 0}.caes-agenda-speaker{flex:1 1 145px;min-width:0;max-width:260px;text-align:center;font-size:.92em;line-height:1.45}.caes-agenda-company{display:block;font-size:.91em;opacity:.84;margin-top:2px}.caes-agenda-track .caes-agenda-speaker{flex-basis:110px}.caes-agenda-track .caes-agenda-speaker-grid{gap:8px 12px}.caes-agenda-person{margin:5px 0;font-size:.92em}.caes-agenda-session details{margin-top:12px}.caes-agenda-session summary{cursor:pointer;font-weight:600}.caes-agenda-description{text-align:left;margin-top:12px;line-height:1.55}@media(max-width:600px){.caes-agenda-speaker{flex-basis:42%;max-width:none}}@media(max-width:850px){.caes-agenda-header{display:none}.caes-agenda-row{grid-template-columns:1fr}.caes-agenda-time{border-right:0;border-bottom:1px solid #dcdcde}.caes-agenda-slots{grid-template-columns:1fr}.caes-agenda-shared{grid-column:1}.caes-agenda-track{border-right:0;border-bottom:1px solid #dcdcde}}');
 wp_register_script('caes-event-agenda-toggle',false,array(),'0.1.6',true);wp_enqueue_script('caes-event-agenda-toggle');wp_add_inline_script('caes-event-agenda-toggle','document.querySelectorAll(".caes-public-agenda").forEach(function(root){root.querySelectorAll("[data-format]").forEach(function(btn){btn.addEventListener("click",function(){var format=btn.getAttribute("data-format");root.querySelectorAll("[data-format]").forEach(function(b){var active=b===btn;b.classList.toggle("is-active",active);b.setAttribute("aria-pressed",active?"true":"false")});root.querySelectorAll("[data-time12]").forEach(function(el){el.textContent=el.getAttribute("data-time"+format)})})})});');
});


/** Publish or return imported records to draft without opening each editor. */
function caes_em_bulk_toolbar($kind){
 if(!in_array($kind,array('speakers','recordings'),true))return;
 $id='caes-em-bulk-'.$kind;
 echo '<form id="'.esc_attr($id).'" method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:14px 0">';
 wp_nonce_field('caes_em_bulk_status_'.$kind);
 echo '<input type="hidden" name="action" value="caes_em_bulk_status"><input type="hidden" name="kind" value="'.esc_attr($kind).'">';
 echo '<label for="'.esc_attr($id).'-action"><strong>Bulk action</strong></label><select id="'.esc_attr($id).'-action" name="bulk_status"><option value="">Choose action</option><option value="publish">Publish selected</option><option value="draft">Move selected to Draft</option></select>';
 echo '<button type="submit" class="button button-primary" onclick="return caesEmConfirmBulk(\''.esc_js($id).'\');">Apply to selected</button><span class="caes-em-bulk-count" data-form="'.esc_attr($id).'">0 selected</span></form>';
 static $script=false;
 if(!$script){$script=true;echo <<<'CAESJS'
<script>
function caesEmConfirmBulk(id){var f=document.getElementById(id),items=document.querySelectorAll('input.caes-em-bulk-item[form="'+id+'"]:checked'),a=f.querySelector('select[name=bulk_status]');if(!items.length){alert("Select at least one record.");return false;}if(!a.value){alert("Choose a bulk action.");return false;}return confirm((a.value==="publish"?"Publish ":"Move to Draft: ")+items.length+" selected records?");}
document.addEventListener("change",function(e){if(e.target.classList.contains("caes-em-select-all")){var t=e.target.closest("table");t.querySelectorAll(".caes-em-bulk-item").forEach(function(c){c.checked=e.target.checked;});}if(e.target.classList.contains("caes-em-select-all")||e.target.classList.contains("caes-em-bulk-item")){document.querySelectorAll(".caes-em-bulk-count").forEach(function(el){var id=el.getAttribute("data-form");el.textContent=document.querySelectorAll('input.caes-em-bulk-item[form="'+id+'"]:checked').length+" selected";});}});
</script>
CAESJS;
 }

}
add_action('admin_post_caes_em_bulk_status',function(){
 if(!current_user_can('edit_others_posts'))wp_die('Insufficient permissions.');
 $kind=sanitize_key(wp_unslash($_POST['kind']??''));
 $types=array('speakers'=>'caes_speaker','recordings'=>'caes_recording');
 if(!isset($types[$kind]))wp_die('Invalid record type.');
 check_admin_referer('caes_em_bulk_status_'.$kind);
 $status=sanitize_key(wp_unslash($_POST['bulk_status']??''));
 if(!in_array($status,array('publish','draft'),true))wp_die('Invalid bulk action.');
 if($status==='publish'&&!current_user_can('publish_posts'))wp_die('Publishing permission required.');
 $raw=isset($_POST['bulk_ids'])&&is_array($_POST['bulk_ids'])?wp_unslash($_POST['bulk_ids']):array();
 $ids=array_slice(array_unique(array_filter(array_map('absint',$raw))),0,500);
 $updated=0;$skipped=0;
 foreach($ids as $id){
  if(get_post_type($id)!==$types[$kind]||!in_array(get_post_status($id),array('draft','publish','pending','private'),true)||!current_user_can('edit_post',$id)){++$skipped;continue;}
  $result=wp_update_post(array('ID'=>$id,'post_status'=>$status),true);
  if(is_wp_error($result))++$skipped;else ++$updated;
 }
 $page=$kind==='speakers'?'event-manager-speakers':'event-manager-recordings';
 wp_safe_redirect(add_query_arg(array('page'=>$page,'bulk_updated'=>$updated,'bulk_skipped'=>$skipped),admin_url('admin.php')));exit;
});
add_action('admin_notices',function(){
 $page=isset($_GET['page'])?sanitize_key(wp_unslash($_GET['page'])):'';
 if(!in_array($page,array('event-manager-speakers','event-manager-recordings'),true)||!isset($_GET['bulk_updated']))return;
 $count=absint($_GET['bulk_updated']);$skipped=absint($_GET['bulk_skipped']??0);
 echo '<div class="notice notice-success is-dismissible"><p>'.esc_html(sprintf('%d records updated. %d skipped.', $count,$skipped)).'</p></div>';
});
