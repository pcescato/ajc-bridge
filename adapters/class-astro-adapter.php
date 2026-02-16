<?php
/**
 * Astro Adapter Class
 *
 * @package AjcBridge
 */

declare(strict_types=1);

namespace AjcBridge\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access not permitted.' );
}

/**
 * Astro static site generator adapter
 *
 * Converts WordPress posts to Astro-compatible MDX format
 * with frontmatter following Astro content collections conventions.
 */
class Astro_Adapter implements Adapter_Interface {

	/**
	 * Get content directory path for Astro
	 *
	 * Astro default: src/content/posts
	 *
	 * @return string Content directory path.
	 */
	private function get_content_dir(): string {
		$settings = get_option( 'ajc_bridge_settings', array() );
		return $settings['astro_content_dir'] ?? 'src/content/posts';
	}

	/**
	 * Get images directory path for Astro
	 *
	 * Astro default: public/image
	 *
	 * @return string Images directory path.
	 */
	private function get_images_dir(): string {
		$settings = get_option( 'ajc_bridge_settings', array() );
		return $settings['astro_images_dir'] ?? 'public/image';
	}

	/**
	 * Get file extension for Astro content files
	 *
	 * Astro default: .mdx
	 *
	 * @return string File extension including dot.
	 */
	private function get_file_extension(): string {
		$settings = get_option( 'ajc_bridge_settings', array() );
		return $settings['astro_file_extension'] ?? '.mdx';
	}

	/**
	 * Convert WordPress post to Astro MDX format
	 *
	 * @param \WP_Post $post                WordPress post object.
	 * @param array    $image_mapping       Optional. Array mapping original URLs to new paths.
	 * @param string   $featured_image_path Optional. Processed featured image path.
	 *
	 * @return string Complete MDX content with frontmatter.
	 */
	public function convert( \WP_Post $post, array $image_mapping = array(), string $featured_image_path = '' ): string {
		$content      = $this->convert_content( $post->post_content, $image_mapping );
		$front_matter = $this->generate_front_matter( $post, $featured_image_path );

		return $front_matter . "\n\n" . $content;
	}

	/**
	 * Get repository file path for Astro post
	 *
	 * Generates Astro-compatible file paths:
	 * - Posts: src/content/posts/{slug}.mdx
	 * - Pages: src/content/posts/{slug}.mdx (Astro treats all as content)
	 *
	 * @param \WP_Post $post WordPress post object.
	 *
	 * @return string Relative file path in repository.
	 */
	public function get_file_path( \WP_Post $post ): string {
		$slug           = $post->post_name;
		$content_dir    = $this->get_content_dir();
		$file_extension = $this->get_file_extension();

		return sprintf( '%s/%s%s', $content_dir, $slug, $file_extension );
	}

	/**
	 * Generate Astro frontmatter for post
	 *
	 * Creates YAML frontmatter with fields required by Astro:
	 * - title: string
	 * - pubDate: ISO 8601 datetime
	 * - updated: ISO 8601 datetime
	 * - slug: string
	 * - draft: boolean
	 * - categories: array
	 * - tags: array
	 * - description: string (auto-generated from excerpt if empty)
	 * - image: string (optional, featured image path)
	 *
	 * @param \WP_Post $post                WordPress post object.
	 * @param string   $featured_image_path Optional. Processed featured image path.
	 *
	 * @return string Formatted YAML frontmatter with delimiters.
	 */
	private function generate_front_matter( \WP_Post $post, string $featured_image_path = '' ): string {
		$front_matter = array();

		// Title (required)
		$front_matter['title'] = $post->post_title;

		// pubDate in ISO 8601 format (required)
		$front_matter['pubDate'] = get_the_date( 'c', $post );

		// updated date in ISO 8601 format (required)
		$front_matter['updated'] = get_the_modified_date( 'c', $post );

		// Slug (required)
		$front_matter['slug'] = $post->post_name;

		// Draft status (required)
		$front_matter['draft'] = ( 'publish' !== $post->post_status );

		// Categories
		$categories = $this->get_terms( $post->ID, 'category' );
		if ( ! empty( $categories ) ) {
			$front_matter['categories'] = $categories;
		}

		// Tags
		$tags = $this->get_terms( $post->ID, 'post_tag' );
		if ( ! empty( $tags ) ) {
			$front_matter['tags'] = $tags;
		}

		// Description (required)
		$description = $this->get_excerpt( $post );
		if ( ! empty( $description ) ) {
			$front_matter['description'] = $description;
		}

		// Image (optional - only if featured image exists)
		// Astro preserves original filenames: /image/original-name.ext (no post ID folders)
		if ( ! empty( $featured_image_path ) ) {
			// Extract filename from the processed path (e.g., "public/image/hero-image.avif" -> "hero-image.avif")
			$filename = basename( $featured_image_path );
			$front_matter['image'] = '/image/' . $filename;
		} elseif ( $featured_image = $this->get_featured_image( $post->ID ) ) {
			// Fallback to original if no processed path provided
			$filename = basename( $featured_image );
			$front_matter['image'] = '/image/' . $filename;
		}

		// Build YAML frontmatter
		$yaml = "---\n";
		foreach ( $front_matter as $key => $value ) {
			$yaml .= $this->format_yaml_field( $key, $value );
		}
		$yaml .= "---";

		return $yaml;
	}

	/**
	 * Get Astro frontmatter metadata
	 *
	 * @param \WP_Post $post                 WordPress post object.
	 * @param string   $featured_image_path  Optional. Processed featured image path.
	 *
	 * @return array Associative array of frontmatter fields.
	 */
	public function get_front_matter( \WP_Post $post, string $featured_image_path = '' ): array {
		$front_matter = array(
			'title'       => $post->post_title,
			'pubDate'     => get_the_date( 'c', $post ),
			'updated'     => get_the_modified_date( 'c', $post ),
			'slug'        => $post->post_name,
			'draft'       => ( 'publish' !== $post->post_status ),
			'description' => $this->get_excerpt( $post ),
		);

		// Add categories
		$categories = $this->get_terms( $post->ID, 'category' );
		if ( ! empty( $categories ) ) {
			$front_matter['categories'] = $categories;
		}

		// Add tags
		$tags = $this->get_terms( $post->ID, 'post_tag' );
		if ( ! empty( $tags ) ) {
			$front_matter['tags'] = $tags;
		}

		// Add image (optional - only if featured image exists)
		// Astro uses flat structure: /image/filename.ext (no post ID folders)
		if ( ! empty( $featured_image_path ) ) {
			$filename = basename( $featured_image_path );
			$front_matter['image'] = '/image/' . $filename;
		} elseif ( $featured_image = $this->get_featured_image( $post->ID ) ) {
			$filename = basename( $featured_image );
			$front_matter['image'] = '/image/' . $filename;
		}

		return $front_matter;
	}

	/**
	 * Convert WordPress content to Markdown
	 *
	 * Uses League\HTMLToMarkdown for professional HTML to Markdown conversion.
	 *
	 * @param string $content       WordPress post content (HTML).
	 * @param array  $image_mapping Optional. Array mapping original URLs to new paths.
	 *
	 * @return string Markdown content.
	 */
	private function convert_content( string $content, array $image_mapping = array() ): string {
		// Apply WordPress content filters
		$content = apply_filters( 'the_content', $content );

		// Replace image URLs if mapping provided
		if ( ! empty( $image_mapping ) ) {
			$content = $this->convert_image_paths( $content, $image_mapping );
		}

		// Clean WordPress-specific HTML before conversion
		$content = $this->clean_wordpress_html( $content );

		// Convert WordPress shortcodes to readable text
		$content = strip_shortcodes( $content );

		// Handle common WordPress blocks (Gutenberg)
		$content = $this->convert_gutenberg_blocks( $content );

		// Use League\HTMLToMarkdown if available
		if ( class_exists( '\League\HTMLToMarkdown\HtmlConverter' ) ) {
			try {
				$converter = new \League\HTMLToMarkdown\HtmlConverter( array(
					'strip_tags'         => false,
					'remove_nodes'       => 'script style',
					'hard_break'         => true,
					'header_style'       => 'atx',
					'bold_style'         => '**',
					'italic_style'       => '*',
					'suppress_errors'    => true,
				) );

				$markdown = $converter->convert( $content );
			} catch ( \Exception $e ) {
				// Fall back to basic conversion on error
				$markdown = $this->basic_html_to_markdown( $content );
			}
		} else {
			// Fall back to basic conversion if library not available
			$markdown = $this->basic_html_to_markdown( $content );
		}

		// Post-process to clean up WordPress artifacts
		$markdown = $this->clean_markdown_output( $markdown );

		// Clean up extra whitespace
		$markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );
		$markdown = trim( $markdown );

		return $markdown;
	}

	/**
	 * Convert image paths from WordPress uploads to Astro public directory
	 *
	 * Converts WordPress uploads URLs to /image/filename format.
	 * Example: /wp-content/uploads/2024/01/photo.jpg → /image/photo.jpg
	 *
	 * @param string $content       HTML content.
	 * @param array  $image_mapping Array mapping original URLs to new paths.
	 *
	 * @return string Content with converted image paths.
	 */
	private function convert_image_paths( string $content, array $image_mapping ): string {
		foreach ( $image_mapping as $original_url => $new_path ) {
			// Convert to Astro public/image path format
			$filename   = basename( $new_path );
			$astro_path = '/image/' . $filename;

			// Replace in img src attributes
			$content = str_replace(
				'src="' . $original_url . '"',
				'src="' . $astro_path . '"',
				$content
			);
			$content = str_replace(
				"src='" . $original_url . "'",
				"src='" . $astro_path . "'",
				$content
			);

			// Replace in markdown-style image links
			$content = str_replace(
				'](' . $original_url . ')',
				'](' . $astro_path . ')',
				$content
			);
		}

		return $content;
	}

	/**
	 * Clean WordPress-specific HTML before Markdown conversion
	 *
	 * Removes WordPress block wrappers, figure tags, and classes.
	 *
	 * @param string $content HTML content.
	 *
	 * @return string Cleaned HTML.
	 */
	private function clean_wordpress_html( string $content ): string {
		// Remove figure and figcaption tags but keep inner content
		$content = preg_replace( '/<figure[^>]*>/', '', $content );
		$content = preg_replace( '/<\/figure>/', '', $content );
		$content = preg_replace( '/<figcaption[^>]*>.*?<\/figcaption>/s', '', $content );

		// Remove WordPress block wrapper divs (wp-block-*)
		$content = preg_replace( '/<div[^>]*class="[^"]*wp-block-[^"]*"[^>]*>/', '', $content );

		// Remove alignment wrapper divs
		$content = preg_replace( '/<div[^>]*class="[^"]*(?:alignleft|alignright|aligncenter|alignnone)[^"]*"[^>]*>/', '', $content );

		// Remove closing divs (after removing opening tags)
		$content = preg_replace( '/<\/div>\s*/', '', $content );

		// Remove WordPress alignment classes from images
		$content = preg_replace( '/class="[^"]*(?:alignleft|alignright|aligncenter|alignnone|wp-image-\d+)[^"]*"/', '', $content );

		// Remove WordPress size classes
		$content = preg_replace( '/class="[^"]*(?:size-thumbnail|size-medium|size-large|size-full)[^"]*"/', '', $content );

		// Remove empty class attributes
		$content = preg_replace( '/\s+class=""/', '', $content );

		// Clean up WordPress srcset and sizes attributes
		$content = preg_replace( '/\s+srcset="[^"]*"/', '', $content );
		$content = preg_replace( '/\s+sizes="[^"]*"/', '', $content );

		// Remove width and height attributes from images
		$content = preg_replace( '/\s+(?:width|height)="\d+"/', '', $content );

		return $content;
	}

	/**
	 * Clean Markdown output to remove WordPress artifacts
	 *
	 * @param string $markdown Markdown content.
	 *
	 * @return string Cleaned Markdown.
	 */
	private function clean_markdown_output( string $markdown ): string {
		// Remove any remaining HTML comments
		$markdown = preg_replace( '/<!--.*?-->/s', '', $markdown );

		// Clean up image syntax - ensure images are on their own line
		$markdown = preg_replace( '/([^\n])(\!\[.*?\]\(.*?\))/', "$1\n\n$2", $markdown );
		$markdown = preg_replace( '/(\!\[.*?\]\(.*?\))([^\n])/', "$1\n\n$2", $markdown );

		// Remove WordPress-specific text artifacts
		$markdown = str_replace( '[&hellip;]', '[...]', $markdown );
		$markdown = str_replace( '&nbsp;', ' ', $markdown );

		return $markdown;
	}

	/**
	 * Convert Gutenberg blocks to Markdown
	 *
	 * @param string $content Content with Gutenberg blocks.
	 *
	 * @return string Content with blocks converted.
	 */
	private function convert_gutenberg_blocks( string $content ): string {
		// Remove block comments but keep content
		$content = preg_replace( '/<!-- wp:.*?-->/s', '', $content );
		$content = preg_replace( '/<!-- \/wp:.*?-->/s', '', $content );

		return $content;
	}

	/**
	 * Basic HTML to Markdown conversion (fallback)
	 *
	 * @param string $html HTML content.
	 *
	 * @return string Markdown content.
	 */
	private function basic_html_to_markdown( string $html ): string {
		// Convert headings
		$html = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/is', "\n\n# $1\n\n", $html );
		$html = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/is', "\n\n## $1\n\n", $html );
		$html = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/is', "\n\n### $1\n\n", $html );
		$html = preg_replace( '/<h4[^>]*>(.*?)<\/h4>/is', "\n\n#### $1\n\n", $html );
		$html = preg_replace( '/<h5[^>]*>(.*?)<\/h5>/is', "\n\n##### $1\n\n", $html );
		$html = preg_replace( '/<h6[^>]*>(.*?)<\/h6>/is', "\n\n###### $1\n\n", $html );

		// Convert bold and italic
		$html = preg_replace( '/<(strong|b)[^>]*>(.*?)<\/\1>/is', '**$2**', $html );
		$html = preg_replace( '/<(em|i)[^>]*>(.*?)<\/\1>/is', '*$2*', $html );

		// Convert links
		$html = preg_replace_callback(
			'/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
			function( $matches ) {
				return sprintf( '[%s](%s)', $matches[2], $matches[1] );
			},
			$html
		);

		// Convert images
		$html = preg_replace_callback(
			'/<img[^>]+src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*\/?>/is',
			function( $matches ) {
				return sprintf( '![%s](%s)', $matches[2], $matches[1] );
			},
			$html
		);
		$html = preg_replace_callback(
			'/<img[^>]+src=["\']([^"\']+)["\'][^>]*\/?>/is',
			function( $matches ) {
				return sprintf( '![](%s)', $matches[1] );
			},
			$html
		);

		// Convert lists
		$html = preg_replace( '/<ul[^>]*>/is', "\n", $html );
		$html = preg_replace( '/<\/ul>/is', "\n", $html );
		$html = preg_replace( '/<ol[^>]*>/is', "\n", $html );
		$html = preg_replace( '/<\/ol>/is', "\n", $html );
		$html = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $html );

		// Convert blockquotes
		$html = preg_replace( '/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n", $html );

		// Convert code blocks
		$html = preg_replace( '/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/is', "\n```\n$1\n```\n", $html );
		$html = preg_replace( '/<code[^>]*>(.*?)<\/code>/is', '`$1`', $html );

		// Convert line breaks and paragraphs
		$html = preg_replace( '/<br\s*\/?>/is', "\n", $html );
		$html = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $html );

		// Remove remaining HTML tags
		$html = wp_strip_all_tags( $html );

		// Decode HTML entities
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return $html;
	}

	/**
	 * Get post excerpt or generate from content
	 *
	 * @param \WP_Post $post WordPress post object.
	 *
	 * @return string Post description/excerpt.
	 */
	private function get_excerpt( \WP_Post $post ): string {
		if ( ! empty( $post->post_excerpt ) ) {
			return $post->post_excerpt;
		}

		// Generate excerpt from content
		$excerpt = wp_strip_all_tags( $post->post_content );
		$excerpt = wp_trim_words( $excerpt, 30, '...' );

		return $excerpt;
	}

	/**
	 * Get taxonomy terms for post
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array Array of term names.
	 */
	private function get_terms( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Get featured image URL
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string|null Featured image URL or null if not set.
	 */
	private function get_featured_image( int $post_id ): ?string {
		$thumbnail_id = get_post_thumbnail_id( $post_id );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$image_url = wp_get_attachment_url( $thumbnail_id );

		return $image_url ? $image_url : null;
	}

	/**
	 * Format YAML field for Astro frontmatter
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 *
	 * @return string Formatted YAML line.
	 */
	private function format_yaml_field( string $key, mixed $value ): string {
		if ( is_array( $value ) ) {
			// Array format
			$yaml = "$key:\n";
			foreach ( $value as $item ) {
				$yaml .= "  - " . $this->escape_yaml_value( $item ) . "\n";
			}
			return $yaml;
		}

		if ( is_bool( $value ) ) {
			return sprintf( "%s: %s\n", $key, $value ? 'true' : 'false' );
		}

		if ( is_numeric( $value ) ) {
			return sprintf( "%s: %s\n", $key, $value );
		}

		// String value - quote if contains special characters
		$escaped = $this->escape_yaml_value( $value );
		return sprintf( "%s: %s\n", $key, $escaped );
	}

	/**
	 * Escape YAML value
	 *
	 * @param mixed $value Value to escape.
	 *
	 * @return string Escaped value.
	 */
	private function escape_yaml_value( mixed $value ): string {
		$value = (string) $value;

		// Quote if contains special characters
		if ( preg_match( '/[:\[\]{},&*#?|\-<>=!%@`"]/', $value ) || strpos( $value, "\n" ) !== false ) {
			$value = '"' . str_replace( '"', '\"', $value ) . '"';
		}

		return $value;
	}

	/**
	 * Get featured image filename for Astro
	 *
	 * Astro preserves original filename: {original}.{extension}
	 *
	 * @param string $original_basename Original filename without extension.
	 * @param string $extension         New file extension (webp, avif, etc).
	 *
	 * @return string Preserved filename "{original}.{extension}".
	 */
	public function get_featured_image_name( string $original_basename, string $extension ): string {
		return $original_basename . '.' . $extension;
	}
}
