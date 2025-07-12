<?php
/*
Plugin Name: Auto-Generated URLs Display
Description: Finds and displays auto-generated URLs like author archives, 404, search, feeds, REST API, trackbacks, media, archives, and CPT archives.
Version: 1.0
Author: Justin Thompson
Author URI: https://justy.dev
*/

add_action('admin_menu', function () {
  add_management_page('Auto-Generated URLs', 'Auto URLs', 'manage_options', 'auto-generated-urls', 'agud_display_page');
});

function agud_display_page()
{
  echo '<div class="wrap"><h1>Auto-Generated URLs</h1>';

  agud_section('Author Archives', agud_get_author_urls());
  agud_section('Static Pages', [
    '404 Page'    => home_url('/404'),
    'Search Page' => home_url('/?s=')
  ]);
  agud_section('Media Attachments', agud_get_attachment_urls());
  agud_section('Archive Pages', agud_get_archive_urls());
  agud_section('Feed Endpoints', [
    'Main Feed'     => home_url('/feed/'),
    'Comments Feed' => home_url('/comments/feed/')
  ]);
  agud_section('REST API', [
    'REST API Root' => home_url('/wp-json/')
  ]);
  agud_section('Pingback URL', [
    'Pingback URL' => get_bloginfo('pingback_url')
  ]);
  agud_section('Custom Post Type Archives', agud_get_cpt_archive_urls());
  agud_section('Sitemap', [
    'XML Sitemap' => home_url('/wp-sitemap.xml')
  ]);

  echo '</div>';
}

function agud_section($title, $urls)
{
  echo '<h2>' . esc_html($title) . '</h2>';
  if (empty($urls)) {
    echo '<p>None found.</p>';
    return;
  }
  echo '<ul>';
  foreach ($urls as $name => $url) {
    echo '<li><strong>' . esc_html($name) . ':</strong> <a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></li>';
  }
  echo '</ul>';
}

function agud_get_author_urls()
{
  $urls = [];
  $authors = get_users(['who' => 'authors']);
  foreach ($authors as $author) {
    $urls[$author->display_name] = get_author_posts_url($author->ID);
  }
  return $urls;
}

function agud_get_attachment_urls()
{
  $urls = [];
  $attachments = get_posts([
    'post_type'      => 'attachment',
    'posts_per_page' => -1,
    'post_status'    => 'inherit'
  ]);
  foreach ($attachments as $attachment) {
    $urls[$attachment->post_title] = get_attachment_link($attachment->ID);
  }
  return $urls;
}

function agud_get_archive_urls()
{
  $urls = [];
  // Category Archives
  $categories = get_categories();
  foreach ($categories as $cat) {
    $urls['Category: ' . $cat->name] = get_category_link($cat->term_id);
  }
  // Tag Archives
  $tags = get_tags();
  foreach ($tags as $tag) {
    $urls['Tag: ' . $tag->name] = get_tag_link($tag->term_id);
  }
  // Date Archives (Year & Month)
  global $wpdb;
  $years = $wpdb->get_col("SELECT DISTINCT YEAR(post_date) FROM $wpdb->posts WHERE post_type='post' AND post_status='publish' ORDER BY post_date DESC");
  foreach ($years as $year) {
    $urls['Year: ' . $year] = get_year_link($year);
    $months = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT MONTH(post_date) FROM $wpdb->posts WHERE post_type='post' AND post_status='publish' AND YEAR(post_date) = %d ORDER BY post_date DESC", $year));
    foreach ($months as $month) {
      $monthName = date('F', mktime(0, 0, 0, $month, 10));
      $urls['Month: ' . $monthName . ' ' . $year] = get_month_link($year, $month);
    }
  }
  return $urls;
}

function agud_get_cpt_archive_urls()
{
  $urls = [];
  $post_types = get_post_types(['public' => true, 'has_archive' => true], 'objects');
  foreach ($post_types as $pt) {
    if (in_array($pt->name, ['post', 'page'])) {
      continue;
    }
    $urls['CPT Archive: ' . $pt->labels->name] = get_post_type_archive_link($pt->name);
  }
  return $urls;
}
