<?php
/**
 * Adapter Interface
 *
 * @package AtomicJamstack
 */

declare(strict_types=1);

namespace AjcBridge\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access not permitted.' );
}

/**
 * Adapter interface for static site generators
 *
 * Defines the contract for converting WordPress content
 * to static site generator formats (Hugo, Astro, etc.).
 */
interface Adapter_Interface {

	/**
	 * Convert WordPress post to Markdown format
	 *
	 * Transforms post content, metadata, and structure into
	 * Markdown with appropriate front matter for the target SSG.
	 *
	 * @param \WP_Post $post                WordPress post object.
	 * @param array    $image_mapping       Optional. Array mapping original URLs to new paths.
	 * @param string   $featured_image_path Optional. Processed featured image path.
	 *
	 * @return string Complete Markdown content with front matter.
	 */
	public function convert( \WP_Post $post, array $image_mapping = array(), string $featured_image_path = '' ): string;

	/**
	 * Get repository file path for post
	 *
	 * Generates the target file path in the repository
	 * following SSG conventions (e.g., Hugo's content structure).
	 *
	 * @param \WP_Post $post WordPress post object.
	 *
	 * @return string Relative file path in repository (e.g., "content/posts/2024-01-15-my-post.md").
	 */
	public function get_file_path( \WP_Post $post ): string;

	/**
	 * Get front matter metadata for post
	 *
	 * Extracts and formats post metadata according to SSG requirements.
	 * Returns associative array ready for YAML/TOML serialization.
	 *
	 * @param \WP_Post $post                 WordPress post object.
	 * @param string   $featured_image_path  Optional. Processed featured image path.
	 *
	 * @return array Associative array of front matter fields.
	 */
	public function get_front_matter( \WP_Post $post, string $featured_image_path = '' ): array;

	/**
	 * Get featured image filename for this SSG
	 *
	 * Different SSGs have different naming conventions:
	 * - Hugo uses fixed "featured.{ext}"
	 * - Astro preserves original filename
	 *
	 * @param string $original_basename Original filename without extension.
	 * @param string $extension         New file extension (webp, avif, etc).
	 *
	 * @return string Filename to use (e.g., "featured.webp" or "my-image.webp").
	 */
	public function get_featured_image_name( string $original_basename, string $extension ): string;
}
