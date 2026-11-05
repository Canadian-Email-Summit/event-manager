<?php
/**
 * Plugin Name: Event Manager
 * Description: Modular event management for venues, archives, speakers, sessions, and recordings.
 * Version: 0.1.15
 * Requires PHP: 7.4
 * Text Domain: caes-speakers
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('caes_speaker', array(
        'labels' => array('name'=>'Speakers','singular_name'=>'Speaker','add_new_item'=>'Add New Speaker','edit_item'=>'Edit Speaker'),
        'public'=>true, 'show_in_rest'=>true, 'show_in_menu'=>false, 'has_archive'=>false,
        'rewrite'=>array('slug'=>'speaker-profile'), 'menu_icon'=>'dashicons-businessperson',
        'supports'=>array('title','editor','thumbnail','excerpt','revisions'),
        'map_meta_cap'=>true,
        'capabilities'=>array('edit_posts'=>'edit_others_posts','create_posts'=>'edit_others_posts','publish_posts'=>'edit_others_posts'),
    ));
    register_taxonomy('caes_speaker_year', 'caes_speaker', array(
        'label'=>'Event Years','public'=>true,'show_in_rest'=>true,'hierarchical'=>false,
        'rewrite'=>array('slug'=>'speaker-year'),
    ));
    foreach (array('caes_job_title','caes_company','caes_linkedin','caes_social','caes_website','caes_bluesky','caes_instagram','caes_x','caes_photo_url','caes_featured') as $key) {
        register_post_meta('caes_speaker', $key, array('single'=>true,'show_in_rest'=>true,'type'=>$key==='caes_featured'?'boolean':'string',
            'auth_callback'=>function($allowed, $meta_key, $post_id){
                return $post_id > 0 && get_post_type($post_id) === 'caes_speaker' && current_user_can('edit_post', $post_id);
            },
            'sanitize_callback'=>$key==='caes_featured'?'rest_sanitize_boolean':($key==='caes_photo_url'?'caes_sanitize_photo_url':(in_array($key,array('caes_linkedin','caes_social','caes_website','caes_bluesky','caes_instagram','caes_x'),true)?'esc_url_raw':'sanitize_text_field'))));
    }
});
add_action('add_meta_boxes', function(){
    add_meta_box('caes_speaker_details','Speaker details','caes_speaker_meta_box','caes_speaker','normal','high');
});
function caes_speaker_meta_box($post){
    wp_nonce_field('caes_speaker_save','caes_speaker_nonce');
    $fields=array('caes_job_title'=>'Job title','caes_company'=>'Organization','caes_linkedin'=>'LinkedIn URL','caes_social'=>'Other social URL (legacy)','caes_website'=>'Website URL','caes_bluesky'=>'Bluesky URL','caes_instagram'=>'Instagram URL','caes_x'=>'X URL','caes_photo_url'=>'Speaker Photo URL');
    foreach ($fields as $key=>$label){
        echo '<p><label for="'.esc_attr($key).'"><strong>'.esc_html($label).'</strong></label><br><input style="width:100%" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr(get_post_meta($post->ID,$key,true)).'" type="'.(in_array($key,array('caes_linkedin','caes_social','caes_website','caes_bluesky','caes_instagram','caes_x'),true)?'url':'text').'"></p>';
    }
    echo '<p>Optional HTTPS image URL. Featured Image takes priority. Invalid images show initials.</p>';
    echo '<p><label><input type="checkbox" name="caes_featured" value="1" '.checked((bool)get_post_meta($post->ID,'caes_featured',true),true,false).'> Featured speaker</label></p>';
    echo '<p>Use the Featured Image for the headshot, the main editor for the biography, and Event Years to select 2026, 2027, or both.</p>';
}
function str_ends_with_compat($haystack,$needle){return substr($haystack,-strlen($needle))===$needle;}
add_action('save_post_caes_speaker', function($post_id){
    if (!isset($_POST['caes_speaker_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['caes_speaker_nonce'])),'caes_speaker_save') || !current_user_can('edit_post',$post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    foreach (array('caes_job_title','caes_company','caes_linkedin','caes_social','caes_website','caes_bluesky','caes_instagram','caes_x','caes_photo_url') as $key){
        $value=isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
        if($key==='caes_photo_url'){ $url=caes_sanitize_photo_url($value); update_post_meta($post_id,$key,$url); continue; }
        update_post_meta($post_id,$key,in_array($key,array('caes_linkedin','caes_social','caes_website','caes_bluesky','caes_instagram','caes_x'),true)?esc_url_raw($value):sanitize_text_field($value));
    }
    update_post_meta($post_id,'caes_featured',isset($_POST['caes_featured'])?'1':'0');
});

/** Consistent validation for editor saves, REST metadata, and direct metadata updates. */
function caes_sanitize_photo_url($value){
 if (!is_string($value)) return '';
 $url=esc_url_raw(trim($value),array('https'));
 if (!$url || strtolower((string)wp_parse_url($url,PHP_URL_SCHEME)) !== 'https') return '';
 return caes_verify_photo_url($url) ? $url : '';
}
function caes_verify_photo_url($url){
 if (!is_string($url) || !$url || strtolower((string)wp_parse_url($url,PHP_URL_SCHEME)) !== 'https' || !wp_http_validate_url($url)) return false;
 // Cache checks so repeat saves and REST updates do not repeatedly fetch remote files.
 $cache_key='caes_img_'.md5($url);
 $cached=get_transient($cache_key);
 if ($cached !== false) return $cached === 'ok';
 // Bound unique validation requests per editor. WordPress handles safe DNS/IP checks.
 $user_id=get_current_user_id();
 if ($user_id) {
   $rate_key='caes_img_rate_'.$user_id;
   $count=(int)get_transient($rate_key);
   if ($count>=12) return false;
   if (!$count) set_transient($rate_key,1,5*MINUTE_IN_SECONDS);
   else set_transient($rate_key,$count+1,5*MINUTE_IN_SECONDS);
 }
 // No redirects: avoid secondary destinations and reduce SSRF and resource risks.
 $r=wp_safe_remote_get($url,array('timeout'=>4,'redirection'=>0,'limit_response_size'=>262144,'headers'=>array('Accept'=>'image/jpeg,image/png,image/webp,image/gif')));
 $valid=false;
 if (!is_wp_error($r) && wp_remote_retrieve_response_code($r)===200) {
   $mime=strtolower(trim(explode(';',(string)wp_remote_retrieve_header($r,'content-type'))[0]));
   if (in_array($mime,array('image/jpeg','image/png','image/webp','image/gif'),true)) {
     $bytes=wp_remote_retrieve_body($r);
     if (is_string($bytes) && strlen($bytes)<=262144 && strlen($bytes)>0) {
       $info=@getimagesizefromstring($bytes);
       $valid=is_array($info) && isset($info['mime'],$info[0],$info[1]) && $info['mime']===$mime
         && $info[0]>0 && $info[1]>0 && $info[0]<=8000 && $info[1]<=8000
         && $info[0]*$info[1]<=24000000;
     }
   }
 }
 set_transient($cache_key,$valid?'ok':'bad',$valid?DAY_IN_SECONDS:10*MINUTE_IN_SECONDS);
 return $valid;
}
function caes_speaker_photo($id,$name,$size='medium_large'){
 $initial=substr($name,0,1);
 if(has_post_thumbnail($id))return get_the_post_thumbnail($id,$size,array('alt'=>$name,'loading'=>'lazy'));
 $url=get_post_meta($id,'caes_photo_url',true);
 if($url && strtolower((string)wp_parse_url($url,PHP_URL_SCHEME))==='https' && wp_http_validate_url($url))return '<img data-speaker-photo="1" data-initial="'.esc_attr($initial).'" src="'.esc_url($url).'" alt="'.esc_attr($name).'" loading="lazy">';
 return '<span class="caes-speaker-photo-placeholder" aria-hidden="true">'.esc_html($initial).'</span>';
}

add_shortcode('caes_speakers',function($atts){
    $atts=shortcode_atts(array('year'=>'2026','limit'=>'100'),$atts,'caes_speakers');
    $year=sanitize_text_field($atts['year']);
    $args=array('post_type'=>'caes_speaker','post_status'=>'publish','posts_per_page'=>min(200,max(1,(int)$atts['limit'])),'orderby'=>'title','order'=>'ASC');
    if ($year!=='all') $args['tax_query']=array(array('taxonomy'=>'caes_speaker_year','field'=>'slug','terms'=>$year));
    $query=new WP_Query($args);
    if (!$query->have_posts()) return '<p class="caes-speaker-empty">Speaker announcements are coming soon.</p>';
    ob_start(); echo '<div class="caes-speaker-grid">';
    while($query->have_posts()){$query->the_post();$id=get_the_ID();$name=get_the_title();
        $job=get_post_meta($id,'caes_job_title',true);$company=get_post_meta($id,'caes_company',true);
        $linkedin=get_post_meta($id,'caes_linkedin',true);$social=get_post_meta($id,'caes_social',true);
        echo '<article class="caes-speaker-card">';
        echo '<a class="caes-speaker-photo" href="'.esc_url(get_permalink()).'" aria-label="'.esc_attr('Read biography for '.$name).'">';
        echo caes_speaker_photo($id,$name);
        echo '</a><div class="caes-speaker-card-body"><h3><a href="'.esc_url(get_permalink()).'">'.esc_html($name).'</a></h3>';
        if($job) echo '<p class="caes-speaker-job">'.esc_html($job).'</p>';
        if($company) echo '<p class="caes-speaker-company">'.esc_html($company).'</p>';
        echo caes_speaker_social_links($id,$name);
        echo '<div class="caes-speaker-links"><a href="'.esc_url(get_permalink()).'">Read bio <span aria-hidden="true">&rarr;</span></a></div>';
        echo '</div></article>';
    }
    wp_reset_postdata();echo '</div>';return ob_get_clean();
});

function caes_speaker_social_links($id,$name){
    $items=array('LinkedIn'=>get_post_meta($id,'caes_linkedin',true), 'Website'=>get_post_meta($id,'caes_website',true), 'Bluesky'=>get_post_meta($id,'caes_bluesky',true), 'Instagram'=>get_post_meta($id,'caes_instagram',true), 'X'=>get_post_meta($id,'caes_x',true));
    // Honour the legacy social field as a website link only if no explicit website exists.
    if(empty($items['Website'])) $items['Website']=get_post_meta($id,'caes_social',true);
    $icons=array(
      'LinkedIn'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20.45 2H3.55C2.69 2 2 2.68 2 3.53v16.94c0 .85.69 1.53 1.55 1.53h16.9c.86 0 1.55-.68 1.55-1.53V3.53c0-.85-.69-1.53-1.55-1.53ZM8 18.75H5.1V9.35H8v9.4ZM6.55 8.07a1.68 1.68 0 1 1 0-3.36 1.68 1.68 0 0 1 0 3.36Zm12.2 10.68h-2.9v-4.57c0-1.09-.02-2.49-1.52-2.49-1.53 0-1.77 1.19-1.77 2.41v4.65h-2.9v-9.4h2.78v1.28h.04c.39-.73 1.33-1.51 2.74-1.51 2.93 0 3.53 1.93 3.53 4.44v5.19Z"/></svg>',
      'Website'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c-5 5-5 13 0 18M12 3c5 5 5 13 0 18" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>',
      'Bluesky'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 10.9C10.5 8 6.4 3.4 4 3.4c-2.1 0-2.3 1.7-2.3 2.8 0 2.5.4 5.5.6 6.4.8 3.1 3.7 3.9 6.3 3.5-4.6.8-5.8 3.6-3.2 5.4 2.6 1.8 5.1 2.2 7.4-.2 1.4-1.5 2-4.4 2-6.9 0-1.1-.2-2.8-2.3-2.8-2.4 0-6.5 4.6-8 7.5Z"/></svg>',
      'Instagram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.6" cy="6.5" r="1.2" fill="currentColor"/></svg>',
      'X'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-7.4L5.5 22H2.3l7.3-8.5L1.8 2h6.5l4.4 6.7L18.9 2Zm-1.1 18h1.7L7.3 3.9H5.5L17.8 20Z"/></svg>');
    $out='';
    foreach($items as $platform=>$url){
      if(!is_string($url)||!wp_http_validate_url($url)) continue;
      $out.='<a class="caes-speaker-social-icon" href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer" aria-label="'.esc_attr($name.' on '.$platform.' (opens in new tab)').'" title="'.esc_attr($platform).'">'.$icons[$platform].'</a>';
    }
    return $out?'<nav class="caes-speaker-social" aria-label="'.esc_attr($name.' social links').'">'.$out.'</nav>':'';
}
add_filter('the_content',function($content){
  if(!is_singular('caes_speaker')||!in_the_loop()||!is_main_query())return $content;
  $id=get_the_ID();$name=get_the_title();
  $job=get_post_meta($id,'caes_job_title',true);$company=get_post_meta($id,'caes_company',true);
  $photo=caes_speaker_photo($id,$name,'large');
  $header='<div class="caes-speaker-profile-header"><div class="caes-speaker-profile-photo">'.$photo.'</div><div class="caes-speaker-profile-intro">';
  if($job)$header.='<p class="caes-speaker-profile-job">'.esc_html($job).'</p>';
  if($company)$header.='<p class="caes-speaker-profile-company">'.esc_html($company).'</p>';
  $header.=caes_speaker_social_links($id,$name).'</div></div>';
  return $header.'<div class="caes-speaker-full-bio">'.$content.'</div><p><a class="caes-speaker-back" href="'.esc_url(home_url('/speakers/')).'">&larr; Back to Speakers</a></p>';
},12);
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('caes-speakers',plugin_dir_url(__FILE__).'speakers.css',array(),'0.1.0');wp_register_script('caes-speaker-fallback',false,array(),'0.1.0',true);wp_enqueue_script('caes-speaker-fallback');wp_add_inline_script('caes-speaker-fallback','document.addEventListener("error",function(e){var i=e.target;if(!i.matches||!i.matches("img[data-speaker-photo]"))return;var p=document.createElement("span");p.className="caes-speaker-photo-placeholder";p.setAttribute("aria-hidden","true");p.textContent=i.dataset.initial||"?";i.replaceWith(p);},true);');});

require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/archives.php';

require_once __DIR__ . '/sponsors.php';
