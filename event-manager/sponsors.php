<?php
/** Reusable sponsors, configurable tiers, and year-specific sponsorships. */
if (!defined('ABSPATH')) exit;
function caes_em_sponsors(){ $v=get_option('caes_em_sponsors',array());return is_array($v)?$v:array(); }
function caes_em_sponsor_tiers(){ $v=get_option('caes_em_sponsor_tiers',null);return is_array($v)?$v:array('presenting'=>array('name'=>'Presenting Sponsor','order'=>10,'active'=>1),'lead'=>array('name'=>'Lead Sponsor','order'=>20,'active'=>1),'supporting'=>array('name'=>'Supporting Sponsor','order'=>30,'active'=>1),'community'=>array('name'=>'Community Partner','order'=>40,'active'=>1)); }
function caes_em_sponsor_resolve_key($items,$key){ if(isset($items[$key]))return $key; foreach(array_keys($items) as $existing){if(strtolower((string)$existing)===strtolower((string)$key))return $existing;} return $key; }
function caes_em_sponsor_assignments(){ $v=get_option('caes_em_sponsor_assignments',array());return is_array($v)?$v:array(); }
function caes_em_sponsor_year(){ $y=get_option('caes_em_current_event_year','2026');return preg_match('/^20\d{2}$/D',(string)$y)?(string)$y:'2026'; }
add_action('admin_menu',function(){
 add_submenu_page('event-manager','Sponsors','Sponsors','manage_options','event-manager-sponsors','caes_em_sponsors_admin');
 add_submenu_page('event-manager','Sponsorship Tiers','Sponsorship Tiers','manage_options','event-manager-sponsor-tiers','caes_em_sponsor_tiers_admin');
},25);
function caes_em_sponsor_tiers_admin(){
 if(!current_user_can('manage_options'))wp_die('Insufficient permissions');$tiers=caes_em_sponsor_tiers();$message='';
 if(isset($_POST['caes_save_tier'])){check_admin_referer('caes_em_tier_save');$id=sanitize_key(wp_unslash($_POST['tier_id']??''));$name=sanitize_text_field(wp_unslash($_POST['tier_name']??''));$order=(int)($_POST['tier_order']??50);
  if(!$name)$message='Enter a tier name.';else{$id=$id?:sanitize_key($name);if(!$id)$id='tier_'.strtolower(wp_generate_password(7,false,false));$tiers[$id]=array('name'=>$name,'order'=>$order,'active'=>isset($_POST['tier_active'])?1:0);update_option('caes_em_sponsor_tiers',$tiers,false);$message='Tier saved.';}}
 if(isset($_POST['caes_delete_tier'])){check_admin_referer('caes_em_tier_delete');$id=sanitize_key(wp_unslash($_POST['tier_id']??''));$used=false;foreach(caes_em_sponsor_assignments() as $a)if(($a['tier']??'')===$id)$used=true;
  if($used)$message='Tier has sponsorship assignments. Reassign them before deleting.';elseif(isset($tiers[$id])){unset($tiers[$id]);update_option('caes_em_sponsor_tiers',$tiers,false);$message='Tier deleted.';}}
 uasort($tiers,function($a,$b){return ($a['order']??0)<=>($b['order']??0);});
 echo '<div class="wrap"><h1>Sponsorship Tiers</h1><p>Create your own tier names and display order. Existing assignments keep their tier ID when a tier is renamed. Disable a tier to hide its group without deleting its history.</p>';
 if($message)echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>';
 $edit=sanitize_key(wp_unslash($_GET['tier']??''));$t=$tiers[$edit]??array();echo '<form method="post" style="background:#fff;padding:18px;max-width:700px">';wp_nonce_field('caes_em_tier_save');echo '<h2>'.($t?'Edit tier':'Add tier').'</h2><input type="hidden" name="tier_id" value="'.esc_attr($edit).'"><p><label>Tier name<br><input class="regular-text" name="tier_name" required value="'.esc_attr($t['name']??'').'"></label></p><p><label>Display order<br><input type="number" name="tier_order" value="'.esc_attr($t['order']??50).'"></label></p><p><label><input type="checkbox" name="tier_active" '.checked(!isset($t['active'])||$t['active'],true,false).'> Active</label></p><button class="button button-primary" name="caes_save_tier" value="1">Save tier</button></form>';
 echo '<h2>Configured tiers</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>Name</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
 foreach($tiers as $id=>$row){echo '<tr><td>'.esc_html($row['name']).'</td><td>'.esc_html($row['order']).'</td><td>'.(!empty($row['active'])?'Active':'Hidden').'</td><td><a class="button button-small" href="'.esc_url(add_query_arg(array('page'=>'event-manager-sponsor-tiers','tier'=>$id),admin_url('admin.php'))).'">Edit</a> <form method="post" style="display:inline">';wp_nonce_field('caes_em_tier_delete');echo '<input type="hidden" name="tier_id" value="'.esc_attr($id).'"><button class="button button-small" name="caes_delete_tier" value="1" onclick="return confirm(\'Delete this unused tier?\')">Delete</button></form></td></tr>';}
 echo '</tbody></table></div>';
}
/** Verify a Media Library image without adding upload restrictions. */
function caes_em_valid_logo($value){
 $id=absint($value);
 return $id && wp_attachment_is_image($id) && current_user_can('edit_post',$id) ? $id : 0;
}
/** Each saved year has its own tier, visibility and optional logo. */
function caes_em_sponsors_admin(){
 if(!current_user_can('manage_options'))wp_die('Insufficient permissions');
 $sponsors=caes_em_sponsors();$assignments=caes_em_sponsor_assignments();$tiers=caes_em_sponsor_tiers();$notice='';$error=false;
 if(isset($_POST['caes_em_save_current_year'])){
  check_admin_referer('caes_em_sponsor_settings');$year=sanitize_text_field(wp_unslash($_POST['current_event_year']??''));
  if(preg_match('/^20[0-9]{2}$/D',$year)){update_option('caes_em_current_event_year',$year,false);$notice='Current event year saved.';}else{$notice='Enter a valid four-digit event year.';$error=true;}
 }
 if(isset($_POST['caes_em_delete_sponsor'])){
  check_admin_referer('caes_em_sponsor_delete');$id=caes_em_sponsor_resolve_key($sponsors,sanitize_text_field(wp_unslash($_POST['sponsor_id']??'')));$used=false;
  foreach($assignments as $a)if(($a['sponsor']??'')===$id)$used=true;
  if($used){$notice='Remove sponsor year assignments before deleting the profile.';$error=true;}
  elseif(isset($sponsors[$id])){unset($sponsors[$id]);update_option('caes_em_sponsors',$sponsors,false);$notice='Sponsor deleted.';}
 }
 if(isset($_POST['caes_em_save_sponsor_unified'])){
  check_admin_referer('caes_em_sponsor_unified');
  $id=caes_em_sponsor_resolve_key($sponsors,sanitize_text_field(wp_unslash($_POST['sponsor_id']??'')));
  $name=sanitize_text_field(wp_unslash($_POST['sponsor_name']??''));
  $url=esc_url_raw(wp_unslash($_POST['sponsor_url']??''),array('https','http'));
  $logo=caes_em_valid_logo($_POST['sponsor_logo']??0);
  $raw_groups=isset($_POST['caes_year_groups'])&&is_array($_POST['caes_year_groups'])?wp_unslash($_POST['caes_year_groups']):array();
  $rows=array();$seen=array();
  if(!$name){$notice='Sponsor name is required.';$error=true;}
  elseif(!$url||!in_array(wp_parse_url($url,PHP_URL_SCHEME),array('https','http'),true)){$notice='Enter a valid sponsor website URL.';$error=true;}
  foreach($raw_groups as $group){
   if(!is_array($group))continue;
   $tier=sanitize_key($group['tier']??'');
   $years=isset($group['years'])&&is_array($group['years'])?$group['years']:array();
   if(!$years)continue;
   if(!isset($tiers[$tier])){$notice='Choose a valid sponsorship tier for every selected year group.';$error=true;break;}
   foreach($years as $year){
    $year=sanitize_text_field($year);
    if(!preg_match('/^20[0-9]{2}$/D',$year)||isset($seen[$year])){$notice='Each event year must be valid and selected only once per sponsor.';$error=true;break 2;}
    $seen[$year]=true;
    $overrides=isset($group['logos'])&&is_array($group['logos'])?$group['logos']:array();
    $rows[]=array('year'=>$year,'tier'=>$tier,'visible'=>!empty($group['visible'])?1:0,'logo'=>caes_em_valid_logo($overrides[$year]??0));
   }
  }
  if(!$error){
   if(!$id||!isset($sponsors[$id]))$id='sponsor_'.strtolower(wp_generate_password(10,false,false));
   $sponsors[$id]=array('name'=>$name,'url'=>$url,'logo'=>$logo,'tracking'=>isset($_POST['sponsor_tracking'])?1:0);
   // Preserve assignment identifiers where possible. Existing multi-year assignments
   // are split only for the edited sponsor, and only after validation succeeds.
   $old=array();foreach($assignments as $aid=>$a){if(($a['sponsor']??'')!==$id)continue;
    foreach((array)($a['years']??array()) as $y){$y=(string)$y;if(!isset($old[$y]))$old[$y]=$aid;}
   }
   $retained=array();foreach($assignments as $aid=>$a)if(($a['sponsor']??'')!==$id)$retained[$aid]=$a;
   $used_ids=array_fill_keys(array_keys($retained),true);
   foreach($rows as $r){$aid=$old[$r['year']]??'';
    if(!$aid||isset($used_ids[$aid]))$aid='assignment_'.strtolower(wp_generate_password(10,false,false));
    $used_ids[$aid]=true;
    $retained[$aid]=array('sponsor'=>$id,'tier'=>$r['tier'],'years'=>array($r['year']),'visible'=>$r['visible'],'logo'=>$r['logo']);
   }
   update_option('caes_em_sponsors',$sponsors,false);
   update_option('caes_em_sponsor_assignments',$retained,false);
   $assignments=$retained;$notice='Sponsor and year assignments saved.';
   // Continue editing the same sponsor after saving.
   $_GET['sponsor']=$id;
  }
 }
 $edit=caes_em_sponsor_resolve_key($sponsors,sanitize_text_field(wp_unslash($_GET['sponsor']??'')));
 $current=$sponsors[$edit]??array();$is_edit=!empty($current);
 echo '<div class="wrap"><h1>Sponsors</h1><p>Manage an organization and its year-specific sponsorships in one place. Existing records are preserved until you save an edited sponsor.</p>';
 if($notice)echo '<div class="notice '.($error?'notice-error':'notice-success').'"><p>'.esc_html($notice).'</p></div>';
 echo '<form method="post" style="background:#fff;padding:16px;max-width:940px">';wp_nonce_field('caes_em_sponsor_settings');
 echo '<h2>Current event year</h2><p>Used on regular site pages. Archive pages use their own year.</p><input name="current_event_year" pattern="20[0-9]{2}" maxlength="4" value="'.esc_attr(caes_em_sponsor_year()).'"> <button class="button" name="caes_em_save_current_year" value="1">Save current year</button></form>';
 echo '<form method="post" id="caes-sponsor-editor" style="background:#fff;padding:20px;max-width:940px;margin-top:18px">';wp_nonce_field('caes_em_sponsor_unified');
 echo '<h2>'.($is_edit?'Edit sponsor: '.esc_html($current['name']):'Add sponsor').'</h2><input type="hidden" name="sponsor_id" value="'.esc_attr($is_edit?$edit:'').'">';
 echo '<p><label><strong>Sponsor name</strong><br><input class="regular-text" name="sponsor_name" required value="'.esc_attr($current['name']??'').'"></label></p>';
 echo '<p><label><strong>Website URL</strong><br><input class="large-text" type="url" name="sponsor_url" required placeholder="https://example.com" value="'.esc_attr($current['url']??'').'"></label></p>';
 echo '<div class="caes-logo-field"><strong>Default logo</strong><p class="description">Use the WordPress Media Library. Suggested shapes: square (500 × 500 px) or horizontal 16:9 (640 × 360 px). Other dimensions are accepted.</p>';
 caes_em_logo_picker('sponsor_logo',(int)($current['logo']??0),'Choose default logo');echo '</div>';
 echo '<p><label><input type="checkbox" name="sponsor_tracking" '.checked(!isset($current['tracking'])||$current['tracking'],true,false).'> Add UTM tracking to sponsor links</label></p>';
 echo '<hr><h2>Event year assignments</h2><p>Select all years sharing a tier and visibility setting. Expand a year only when its historical logo differs from the default.</p>';
 $year_rows=array();
 if($is_edit){foreach($assignments as $a)if(($a['sponsor']??'')===$edit){foreach((array)($a['years']??array()) as $year){$year=(string)$year;
  if(!isset($year_rows[$year]))$year_rows[$year]=array('year'=>$year,'tier'=>$a['tier']??'','visible'=>!empty($a['visible']),'logo'=>(int)($a['logo']??0));
 }}}
 ksort($year_rows);
 $grouped=array();foreach($year_rows as $r){$groupkey=$r['tier'].'_'.(int)$r['visible'];if(!isset($grouped[$groupkey]))$grouped[$groupkey]=array('tier'=>$r['tier'],'visible'=>$r['visible'],'years'=>array(),'logos'=>array());$grouped[$groupkey]['years'][]=$r['year'];if($r['logo'])$grouped[$groupkey]['logos'][$r['year']]=$r['logo'];}
 $year_choices=array_keys(caes_em_archives());foreach($year_rows as $y=>$unused)$year_choices[]=$y;$year_choices[]=(string)caes_em_sponsor_year();$year_choices=array_values(array_unique(array_filter($year_choices,function($y){return preg_match('/^20[0-9]{2}$/D',(string)$y);})));sort($year_choices,SORT_NUMERIC);
 echo '<div id="caes-sponsor-groups">';$i=0;foreach($grouped as $g)caes_em_sponsor_group_row($i++,$g,$tiers,$year_choices);
 echo '</div><p><button type="button" class="button" id="caes-add-group">+ Add year group</button> <a href="'.esc_url(admin_url('admin.php?page=event-manager-sponsor-tiers')).'">Manage sponsorship tiers</a></p>';
 echo '<p><button class="button button-primary" name="caes_em_save_sponsor_unified" value="1">Save sponsor and assignments</button> <a class="button" href="'.esc_url(admin_url('admin.php?page=event-manager-sponsors')).'">Add another sponsor</a></p></form>';
 echo '<h2>Sponsor directory</h2><table class="widefat striped" style="max-width:1100px"><thead><tr><th>Organization</th><th>Website</th><th>Default logo</th><th>Years</th><th>Actions</th></tr></thead><tbody>';
 foreach($sponsors as $sid=>$s){$years=array();foreach($assignments as $a)if(($a['sponsor']??'')===$sid)$years=array_merge($years,(array)($a['years']??array()));$years=array_unique($years);sort($years);
  echo '<tr><td>'.esc_html($s['name']).'</td><td><a href="'.esc_url($s['url']).'" target="_blank" rel="noopener">'.esc_html($s['url']).'</a></td><td>'.(!empty($s['logo'])?wp_get_attachment_image((int)$s['logo'],array(90,50)):'Not set').'</td><td>'.esc_html(implode(', ',$years)).'</td><td><a class="button button-small" href="'.esc_url(add_query_arg(array('page'=>'event-manager-sponsors','sponsor'=>$sid),admin_url('admin.php'))).'">Edit</a> <form method="post" style="display:inline">';wp_nonce_field('caes_em_sponsor_delete');echo '<input type="hidden" name="sponsor_id" value="'.esc_attr($sid).'"><button class="button button-small" name="caes_em_delete_sponsor" value="1" onclick="return confirm(\'Delete this sponsor? Sponsors with assignments cannot be deleted.\')">Delete</button></form></td></tr>';
 }
 echo '</tbody></table>';
 if(function_exists('caes_em_shortcode_panel'))caes_em_shortcode_panel('Sponsor display','[event_sponsors]');echo '</div>';
 ob_start();caes_em_sponsor_group_row('__INDEX__',array('tier'=>'','visible'=>true,'years'=>array(),'logos'=>array()),$tiers,$year_choices);$template=ob_get_clean();
 echo '<script type="text/template" id="caes-group-template">'.str_replace('</script','<\/script',$template).'</script>';
}
function caes_em_logo_picker($name,$logo,$button){
 echo '<div class="caes-logo-picker"><input type="hidden" class="caes-sponsor-logo-id" name="'.esc_attr($name).'" value="'.esc_attr($logo).'">';
 echo '<div class="caes-sponsor-logo-preview" style="width:150px;height:88px;border:1px solid #ddd;background:#fafafa;display:flex;align-items:center;justify-content:center;margin:8px 0">';
 if($logo)echo wp_get_attachment_image($logo,'medium',false,array('style'=>'max-width:100%;max-height:100%;object-fit:contain;width:auto;height:auto;'));
 else echo '<span class="description">No logo selected</span>';
 echo '</div><button type="button" class="button caes-sponsor-choose">'.esc_html($button).'</button> <button type="button" class="button-link caes-sponsor-clear">Remove override / logo</button></div>';
}
/** Compact year group: preserve individual year logos in the existing assignment store. */
function caes_em_sponsor_group_row($index,$g,$tiers,$year_choices){
 $prefix='caes_year_groups['.$index.']';$selected=(array)($g['years']??array());
 echo '<div class="caes-year-group" style="border:1px solid #dcdcde;border-radius:6px;padding:14px;margin:12px 0;max-width:900px">';
 echo '<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap"><label><strong>Sponsorship tier</strong><br><select name="'.esc_attr($prefix.'[tier]').'"><option value="">Choose tier</option>';
 foreach($tiers as $tid=>$t)echo '<option value="'.esc_attr($tid).'" '.selected($g['tier']??'',$tid,false).'>'.esc_html($t['name']).'</option>';
 echo '</select></label><label><input type="checkbox" name="'.esc_attr($prefix.'[visible]').'" value="1" '.checked(!empty($g['visible']),true,false).'> Publicly visible</label><button type="button" class="button-link-delete caes-remove-group" style="margin-left:auto">Remove group</button></div>';
 echo '<p><strong>Event years</strong></p><div class="caes-year-checkboxes" style="display:flex;gap:8px;flex-wrap:wrap">';
 foreach($year_choices as $year){$checked=in_array((string)$year,array_map('strval',$selected),true);echo '<label style="display:inline-flex;gap:5px;align-items:center;border:1px solid #c3c4c7;border-radius:5px;padding:7px 10px;background:'.($checked?'#edf4ff':'#fff').'"><input type="checkbox" class="caes-group-year" name="'.esc_attr($prefix.'[years][]').'" value="'.esc_attr($year).'" '.checked($checked,true,false).'> '.esc_html($year).'</label>';}
 echo '</div><details style="margin-top:12px"><summary>Historical logo overrides (optional)</summary><p class="description">Only selected years are saved. An empty override uses the default logo.</p><div class="caes-override-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:10px">';
 foreach($year_choices as $year){echo '<div class="caes-override-year" data-year="'.esc_attr($year).'" style="border:1px solid #ddd;padding:8px;border-radius:4px"><strong>'.esc_html($year).'</strong>';caes_em_logo_picker($prefix.'[logos]['.$year.']',(int)($g['logos'][$year]??0),'Choose logo');echo '</div>';}
 echo '</div></details></div>';
}
add_action('admin_enqueue_scripts',function($hook){
 if(strpos((string)$hook,'event-manager-sponsors')===false)return;
 wp_enqueue_media();wp_register_script('caes-em-sponsor-admin',false,array('jquery'),'0.1.22',true);wp_enqueue_script('caes-em-sponsor-admin');
 $js=<<<'JS'
jQuery(function($){
 var nextIndex=$('#caes-sponsor-groups .caes-year-group').length;
 $('#caes-add-group').on('click',function(){var html=$('#caes-group-template').html().replace(/__INDEX__/g,String(nextIndex++));$('#caes-sponsor-groups').append(html);});
 $(document).on('click','.caes-remove-group',function(){if(confirm('Remove these year assignments when you save?'))$(this).closest('.caes-year-group').remove();});
 $(document).on('change','.caes-group-year',function(){$(this).closest('label').css('background',this.checked?'#edf4ff':'#fff');});
 $(document).on('click','.caes-sponsor-clear',function(){var box=$(this).closest('.caes-logo-picker');box.find('.caes-sponsor-logo-id').val('');box.find('.caes-sponsor-logo-preview').html('<span class="description">No logo selected</span>');});
 $(document).on('click','.caes-sponsor-choose',function(e){e.preventDefault();var box=$(this).closest('.caes-logo-picker');var frame=wp.media({title:'Choose sponsor logo',button:{text:'Use logo'},multiple:false,library:{type:'image'}});
 frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();box.find('.caes-sponsor-logo-id').val(item.id);var src=(item.sizes&&item.sizes.medium?item.sizes.medium.url:item.url);box.find('.caes-sponsor-logo-preview').html($('<img>',{src:src,alt:item.alt||'',css:{maxWidth:'100%',maxHeight:'100%',objectFit:'contain'}}));});frame.open();});
});
JS;
 wp_add_inline_script('caes-em-sponsor-admin',$js);
});
function caes_em_sponsor_link($url,$year,$tier,$tracking){if(!$tracking)return $url;$parts=array('utm_source'=>'ca_email_summit_'.$year,'utm_medium'=>'referral','utm_campaign'=>'sponsor','utm_content'=>$tier);return add_query_arg($parts,$url);}
add_shortcode('event_sponsors',function($atts){$atts=shortcode_atts(array('year'=>''),$atts,'event_sponsors');$year=trim((string)$atts['year']);if(!$year){$page=get_queried_object();if($page instanceof WP_Post && function_exists('caes_em_archive_year_for_page') && ($archive_year=caes_em_archive_year_for_page($page)))$year=$archive_year;else $year=caes_em_sponsor_year();}if(!preg_match('/^20\d{2}$/D',$year))return '';
 $is_archive_sponsors=false;$archive_page=get_queried_object();if($archive_page instanceof WP_Post && function_exists('caes_em_archive_year_for_page') && caes_em_archive_year_for_page($archive_page)===$year)$is_archive_sponsors=true;
 $sponsors=caes_em_sponsors();$tiers=caes_em_sponsor_tiers();$groups=array();foreach(caes_em_sponsor_assignments() as $a){if(empty($a['visible'])||!in_array($year,$a['years']??array(),true))continue;$sid=$a['sponsor']??'';$tid=$a['tier']??'';if(!isset($sponsors[$sid],$tiers[$tid])||empty($tiers[$tid]['active']))continue;$groups[$tid][]=$a;}
 if(!$groups)return '';uasort($tiers,function($a,$b){return ($a['order']??0)<=>($b['order']??0);});$out='<section class="caes-section caes-sponsors caes-managed-sponsors'.($is_archive_sponsors?' caes-archive-sponsors':'').'" aria-label="'.($is_archive_sponsors?esc_attr($year).' Summit sponsors':'Event sponsors').'"><div class="caes-managed-sponsors-inner"><p class="caes-eyebrow">OUR SPONSORS</p><h2>Supported by the email community</h2>';
 foreach($tiers as $tid=>$tier){if(empty($groups[$tid]))continue;usort($groups[$tid],function($a,$b)use($sponsors){$nameA=$sponsors[$a['sponsor']]['name']??'';$nameB=$sponsors[$b['sponsor']]['name']??'';return strnatcasecmp($nameA,$nameB);});$out.='<div class="caes-managed-sponsor-tier"><h3 class="caes-tier-label">'.esc_html($tier['name']).'</h3><div class="caes-managed-sponsor-logos">';$seen=array();foreach($groups[$tid] as $a){$s=$sponsors[$a['sponsor']];if(isset($seen[$a['sponsor']]))continue;$seen[$a['sponsor']]=1;$logo=(int)(!empty($a['logo'])?$a['logo']:($s['logo']??0));$img=$logo?wp_get_attachment_image($logo,'medium',false,array('loading'=>'lazy','alt'=>$s['name'].' logo')):'';$link=caes_em_sponsor_link($s['url'],$year,$tid,!empty($s['tracking']));$out.='<a class="caes-logo-tile caes-managed-sponsor" href="'.esc_url($link).'" target="_blank" rel="noopener noreferrer sponsored" aria-label="Visit '.esc_attr($s['name']).'"><span>'.($img?:esc_html($s['name'])).'</span></a>';}$out.='</div></div>';}
 return $out.'</div></section>';});
