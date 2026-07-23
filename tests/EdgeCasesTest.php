<?php

class EdgeCasesTest extends WP_UnitTestCase
{
  public function test_zero_page_value_defaults_to_one()
  {
    $_GET[AGUD_PAGE_QUERY_ARG] = 0;

    $page = agud_get_current_page();

    $this->assertSame(1, $page);
  }

  public function test_negative_page_value_defaults_to_one()
  {
    $_GET[AGUD_PAGE_QUERY_ARG] = -4;

    $page = agud_get_current_page();

    $this->assertSame(1, $page);
  }

  public function test_text_page_value_defaults_to_one()
  {
    $_GET[AGUD_PAGE_QUERY_ARG] = 'not-a-number';

    $page = agud_get_current_page();

    $this->assertSame(1, $page);
  }

  public function test_array_page_value_defaults_to_one()
  {
    $_GET[AGUD_PAGE_QUERY_ARG] = ['2'];

    $page = agud_get_current_page();

    $this->assertSame(1, $page);
  }

  public function test_null_page_value_defaults_to_one()
  {
    $_GET[AGUD_PAGE_QUERY_ARG] = null;

    $page = agud_get_current_page();

    $this->assertSame(1, $page);
  }

  public function test_empty_section_renders_none_found_message()
  {
    ob_start();
    agud_section('Empty Test', []);
    $output = ob_get_clean();

    $this->assertStringContainsString('<h2>Empty Test</h2>', $output);
    $this->assertStringContainsString('None found.', $output);
    $this->assertStringNotContainsString('<ul>', $output);
  }

  public function test_section_skips_invalid_url_values()
  {
    ob_start();
    agud_section('Invalid URLs', [
      'Empty' => '',
      'Object' => new stdClass(),
      'Valid' => home_url('/valid/'),
    ]);
    $output = ob_get_clean();

    $this->assertSame(1, substr_count($output, '<li>'));
    $this->assertStringContainsString('Valid', $output);
    $this->assertStringNotContainsString('Empty', $output);
    $this->assertStringNotContainsString('Object', $output);
  }

  public function test_date_archive_urls_include_published_years_and_months()
  {
    self::factory()->post->create([
      'post_status' => 'publish',
      'post_date' => '2024-03-15 10:00:00',
    ]);
    self::factory()->post->create([
      'post_status' => 'publish',
      'post_date' => '2024-04-20 10:00:00',
    ]);
    self::factory()->post->create([
      'post_status' => 'draft',
      'post_date' => '2025-05-10 10:00:00',
    ]);

    $urls = agud_get_date_archive_urls();

    $this->assertArrayHasKey('Year: 2024', $urls);
    $this->assertArrayHasKey('Month: March 2024', $urls);
    $this->assertArrayHasKey('Month: April 2024', $urls);
    $this->assertArrayNotHasKey('Year: 2025', $urls);
  }

  public function test_cpt_archive_urls_include_public_archives()
  {
    register_post_type('review_book', [
      'public' => true,
      'has_archive' => true,
      'label' => 'Review Books',
    ]);

    $urls = agud_get_cpt_archive_urls();

    $this->assertArrayHasKey('CPT Archive: Review Books (review_book)', $urls);
    unregister_post_type('review_book');
  }

  public function test_cpt_archive_urls_skip_invalid_links()
  {
    register_post_type('invalid_archive', [
      'public' => true,
      'has_archive' => true,
      'label' => 'Invalid Archives',
    ]);
    add_filter('post_type_archive_link', '__return_false');

    $urls = agud_get_cpt_archive_urls();

    $this->assertArrayNotHasKey('CPT Archive: Invalid Archives (invalid_archive)', $urls);
    remove_filter('post_type_archive_link', '__return_false');
    unregister_post_type('invalid_archive');
  }

  public function test_section_renders_pagination_links_for_multiple_pages()
  {
    $current_user = get_current_user_id();
    wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    add_management_page(
      'Auto-Generated URLs',
      'Auto URLs',
      'manage_options',
      'auto-generated-urls',
      'agud_display_page'
    );
    ob_start();
    agud_section('Paginated', ['Example' => home_url('/')], 101, 1);
    $output = ob_get_clean();
    wp_set_current_user($current_user);

    $this->assertStringContainsString('tablenav-pages', $output);
    $this->assertStringContainsString('agud_page=2', $output);
  }

  public function test_full_page_renders_all_sections()
  {
    ob_start();
    agud_display_page();
    $output = ob_get_clean();

    $this->assertStringContainsString('<div class="wrap">', $output);
    $this->assertSame(11, substr_count($output, '<h2>'));
  }

  public function test_page_sections_preserve_order_and_pagination_metadata()
  {
    $sections = agud_get_page_sections(2);

    $this->assertCount(11, $sections);
    $this->assertSame([
      'Author Archives',
      'Static Pages',
      'Media Attachments',
      'Category Archives',
      'Tag Archives',
      'Date Archives',
      'Feed Endpoints',
      'REST API',
      'Pingback URL',
      'Custom Post Type Archives',
      'Sitemap',
    ], array_column($sections, 'title'));

    foreach ($sections as $section) {
      $this->assertSame(['title', 'urls', 'total', 'page'], array_keys($section));
      $this->assertIsArray($section['urls']);
    }

    foreach ([0, 2, 3, 4] as $index) {
      $this->assertSame(2, $sections[$index]['page']);
    }

    $author_total = 0;
    $attachment_total = 0;
    $category_total = 0;
    $tag_total = 0;
    $author_urls = agud_get_author_urls(2, $author_total);
    $attachment_urls = agud_get_attachment_urls(2, $attachment_total);
    $category_urls = agud_get_taxonomy_urls('category', 2, $category_total);
    $tag_urls = agud_get_taxonomy_urls('post_tag', 2, $tag_total);
    $expected_paginated_sections = [
      0 => [$author_urls, $author_total],
      2 => [$attachment_urls, $attachment_total],
      3 => [$category_urls, $category_total],
      4 => [$tag_urls, $tag_total],
    ];

    foreach ($expected_paginated_sections as $index => [$urls, $total]) {
      $this->assertSame($urls, $sections[$index]['urls']);
      $this->assertSame($total, $sections[$index]['total']);
    }

    foreach ([1, 5, 6, 7, 8, 9, 10] as $index) {
      $this->assertSame(0, $sections[$index]['total']);
      $this->assertSame(1, $sections[$index]['page']);
    }
  }

  public function test_plugin_action_links_include_tools_page()
  {
    $hook = 'plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/autogenerated-urls.php');
    $links = apply_filters($hook, [
      'deactivate' => '<a href="#">Deactivate</a>',
    ]);

    $this->assertArrayHasKey('agud-view-urls', $links);
    $this->assertStringContainsString('tools.php?page=auto-generated-urls', $links['agud-view-urls']);
    $this->assertArrayHasKey('deactivate', $links);
  }
}
