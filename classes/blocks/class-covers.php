<?php
/**
 * Covers block class
 *
 * @package P4GBKS
 * @since 0.1
 */

namespace P4GBKS\Blocks;

/**
 * Class Covers
 *
 * @package P4GBKS\Blocks
 */
class Covers extends Base_Block {

	/**
	 * Block name.
	 *
	 * @const string BLOCK_NAME.
	 */
	const BLOCK_NAME = 'covers';

	/**
	 * Block version, update when changing attributes
	 *
	 * @var int VERSION.
	 */
	private const VERSION = 2;

	/**
	 * Old cover types, needed to convert existing blocks to version 2.
	 *
	 * @var array OLD_COVER_TYPES.
	 */
	private const OLD_COVER_TYPES = [
		'1' => 'take-action',
		'2' => 'campaign',
		'3' => 'content',
	];

	/**
	 * New cover types, used for version 2.
	 *
	 * @var string TAKE_ACTION_COVER_TYPE.
	 * @var string CAMPAIGN_COVER_TYPE.
	 * @var string CONTENT_COVER_TYPE.
	 */
	private const TAKE_ACTION_COVER_TYPE = 'take-action';
	private const CAMPAIGN_COVER_TYPE    = 'campaign';
	private const CONTENT_COVER_TYPE     = 'content';

	const POSTS_LIMIT = 50;

	/**
	 * Covers constructor.
	 */
	public function __construct() {
		register_block_type(
			'planet4-blocks/covers',
			[  // - Register the block for the editor
				'editor_script'   => 'planet4-blocks',
				'render_callback' => static function ( $attributes ) {
					if ( isset( $attributes['covers_view'] ) ) {
						$attributes['initialRowsLimit'] = '3' === $attributes['covers_view'] ? 0 : intval( $attributes['covers_view'] );
						unset( $attributes['covers_view'] );
					}

					if ( ! isset( $attributes['version'] ) ) {
						$attributes['version'] = self::VERSION;
					}

					if ( is_numeric( $attributes['cover_type'] ) ) {
						$old_cover_type           = $attributes['cover_type'];
						$attributes['cover_type'] = self::OLD_COVER_TYPES[ $old_cover_type ];
					}

					$attributes['covers'] = self::get_covers( $attributes );

					$json = wp_json_encode( [ 'attributes' => $attributes ] );

					return '<div data-render="' . self::get_full_block_name() . '" data-attributes="' . htmlspecialchars( $json ) . '"></div>';
				},
				// These attributes match the current fields.
				'attributes'      => [
					'cover_type'       => [
						'type'    => 'string',
						'default' => self::CONTENT_COVER_TYPE,
					],
					'initialRowsLimit' => [
						'type'    => 'integer',
						'default' => 1,
					],
					'title'            => [
						'type'    => 'string',
						'default' => '',
					],
					'description'      => [
						'type'    => 'string',
						'default' => '',
					],
					'tags'             => [
						'type'    => 'array',
						'default' => [],
						'items'   => [
							'type' => 'integer', // Array definitions require an item type.
						],
					],
					'post_types'       => [
						'type'    => 'array',
						'default' => [],
						'items'   => [
							'type' => 'integer',
						],
					],
					'posts'            => [
						'type'    => 'array',
						'default' => [],
						'items'   => [
							'type' => 'integer',
						],
					],
					'version'          => [
						'type'    => 'integer',
						'default' => self::VERSION,
					],
				],
			]
		);

		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_assets' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Required by the `Base_Block` class.
	 *
	 * @param array $fields Unused, required by the abstract function.
	 */
	public function prepare_data( $fields ): array {
		return [];
	}

	/**
	 * Get all the data that will be needed to render the block correctly.
	 *
	 * @param array $fields This is the array of fields of this block.
	 *
	 * @return array The data to be passed in the View.
	 */
	public static function get_covers( $fields ): array {
		$cover_type = $fields['cover_type'] ?? self::CONTENT_COVER_TYPE;
		$covers     = [];

		if ( self::TAKE_ACTION_COVER_TYPE === $cover_type ) {
			$covers = self::populate_posts_for_act_pages( $fields );
		} elseif ( self::CAMPAIGN_COVER_TYPE === $cover_type ) {
			$covers = self::populate_posts_for_campaigns( $fields );
		} elseif ( self::CONTENT_COVER_TYPE === $cover_type ) {
			$covers = self::populate_posts_for_cfc( $fields );
		}

		return $covers;
	}

	/**
	 * Get posts that are act page children.
	 *
	 * @param array $fields This is the array of fields of this block.
	 *
	 * @return \WP_Post[]
	 */
	private static function filter_posts_for_act_pages( $fields ) {
		$tag_ids       = $fields['tags'] ?? [];
		$options       = get_option( 'planet4_options' );
		$parent_act_id = $options['act_page'];

		if ( 0 !== absint( $parent_act_id ) ) {
			$args = [
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'post_parent'      => $parent_act_id,
				'orderby'          => [
					'menu_order' => 'ASC',
					'date'       => 'DESC',
					'title'      => 'ASC',
				],
				'suppress_filters' => false,
				'numberposts'      => self::POSTS_LIMIT,
			];
			// If user selected a tag to associate with the Take Action page covers.
			if ( ! empty( $tag_ids ) ) {
				$args['tag__in'] = $tag_ids;
			}

			// Ignore sniffer rule, arguments contain suppress_filters.
			// phpcs:ignore
			return get_posts( $args );
		}

		return [];
	}

	/**
	 * Get specific posts.
	 *
	 * @param array $fields This is the array of fields of this block.
	 *
	 * @return \WP_Post[]
	 */
	private static function filter_posts_by_ids( $fields ) {
		$post_ids = $fields['posts'] ?? [];

		if ( ! empty( $post_ids ) ) {

			// Get all posts with arguments.
			$args = [
				'orderby'          => 'post__in',
				'post_status'      => 'publish',
				'post__in'         => $post_ids,
				'suppress_filters' => false,
				'numberposts'      => self::POSTS_LIMIT,
			];

			// If cover type is take action pages set post_type to page.
			if ( isset( $fields['cover_type'] ) && self::TAKE_ACTION_COVER_TYPE === $fields['cover_type'] ) {
				$args['post_type'] = 'page';
			}

			// Ignore sniffer rule, arguments contain suppress_filters.
			// phpcs:ignore
			return get_posts( $args );
		}

		return [];
	}

	/**
	 * Get posts for content four column.
	 *
	 * @param array $fields This is the array of fields of this block.
	 *
	 * @return \WP_Post[]
	 */
	private static function filter_posts_for_cfc( $fields ) {

		$tag_ids    = $fields['tags'] ?? [];
		$post_types = $fields['post_types'] ?? [];

		$query_args = [
			'post_type'      => 'post',
			'orderby'        => [
				'date'  => 'DESC',
				'title' => 'ASC',
			],
			'no_found_rows'  => true,
			'posts_per_page' => self::POSTS_LIMIT,
		];

		// Get all posts with the specific tags.
		// Construct the arguments array for the query.
		if ( ! empty( $tag_ids ) && ! empty( $post_types ) ) {

			$query_args['tax_query'] = [
				'relation' => 'AND',
				[
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $tag_ids,
				],
				[
					'taxonomy' => 'p4-page-type',
					'field'    => 'term_id',
					'terms'    => $post_types,
				],
			];
		} elseif ( ! empty( $tag_ids ) && empty( $post_types ) ) {

			$query_args['tax_query'] = [
				[
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $tag_ids,
				],
			];
		} elseif ( empty( $tag_ids ) && ! empty( $post_types ) ) {

			$query_args['tax_query'] = [
				[
					'taxonomy' => 'p4-page-type',
					'field'    => 'term_id',
					'terms'    => $post_types,
				],
			];
		}

		// If tax_query has been defined in the arguments array, then make a query based on these arguments.
		if ( array_key_exists( 'tax_query', $query_args ) ) {

			// Construct a WP_Query object and make a query based on the arguments array.
			$query = new \WP_Query();
			$posts = $query->query( $query_args );

			return $posts;
		}

		return [];
	}

	/**
	 * Populate posts for campaign thumbnail template.
	 *
	 * @param array $fields This is the array of fields of this block.
	 *
	 * @return array
	 */
	private static function populate_posts_for_campaigns( &$fields ) {

		// Get user defined tags from backend.
		$tag_ids = $fields['tags'] ?? [];

		if ( empty( $tag_ids ) ) {
			return [];
		}

		$tags = get_tags( [ 'include' => $tag_ids ] );

		if ( ! is_array( $tags ) ) {
			return [];
		}

		$covers = [];

		foreach ( $tags as $tag ) {
			$tag_remapped  = [
				'name' => html_entity_decode( $tag->name ),
				'slug' => $tag->slug,
				'href' => get_tag_link( $tag ),
			];
			$attachment_id = get_term_meta( $tag->term_id, 'tag_attachment_id', true );

			if ( ! empty( $attachment_id ) ) {
				$tag_remapped['image']    = wp_get_attachment_image_src( $attachment_id, 'medium_large' );
				$tag_remapped['src_set']  = wp_get_attachment_image_srcset( $attachment_id, 'medium_large' );
				$tag_remapped['alt_text'] = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			}

			$covers[] = $tag_remapped;
		}

		return $covers;
	}

	/**
	 * Populate posts for take action covers template.
	 *
	 * @param array $fields This is the array of fields of this block.
	 *
	 * @return array
	 */
	private static function populate_posts_for_act_pages( &$fields ) {
		$post_ids = $fields['posts'] ?? [];
		$options  = get_option( 'planet4_options' );

		if ( ! empty( $post_ids ) ) {
			$actions = self::filter_posts_by_ids( $fields );
		} else {
			$actions = self::filter_posts_for_act_pages( $fields );
		}

		$covers = [];

		if ( $actions ) {
			$cover_button_text = $options['take_action_covers_button_text'] ?? __( 'Take action', 'planet4-blocks' );

			foreach ( $actions as $action ) {
				$tags    = [];
				$wp_tags = wp_get_post_tags( $action->ID );

				if ( is_array( $wp_tags ) && $wp_tags ) {
					foreach ( $wp_tags as $wp_tag ) {
						$tags[] = [
							'name' => $wp_tag->name,
							'href' => get_tag_link( $wp_tag ),
						];
					}
				}
				$covers[] = [
					'tags'        => $tags ?? [],
					'title'       => get_the_title( $action ),
					'excerpt'     => $action->post_excerpt,
					'image'       => get_the_post_thumbnail_url( $action, 'large' ),
					'button_text' => $cover_button_text,
					'button_link' => get_permalink( $action->ID ),
				];
			}
		}

		return $covers;
	}

	/**
	 * Populate posts for content four column template.
	 *
	 * @param array $fields This is the array of fields of this block.
	 *
	 * @return array
	 */
	private static function populate_posts_for_cfc( &$fields ) {
		$post_ids = $fields['posts'] ?? [];

		if ( ! empty( $post_ids ) ) {
			$posts = self::filter_posts_by_ids( $fields );
		} else {
			$posts = self::filter_posts_for_cfc( $fields );
		}

		$posts_array = [];

		if ( ! empty( $posts ) ) {

			foreach ( $posts as $post ) {

				$post->alt_text  = '';
				$post->thumbnail = '';
				$post->srcset    = '';

				if ( has_post_thumbnail( $post ) ) {
					$post->thumbnail = get_the_post_thumbnail_url( $post, 'medium' );
					$img_id          = get_post_thumbnail_id( $post );
					$post->srcset    = wp_get_attachment_image_srcset( $img_id, 'full', wp_get_attachment_metadata( $img_id ) );
					$post->alt_text  = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
				}

				$post->link           = get_permalink( $post );
				$post->date_formatted = get_the_date( '', $post->ID );
				$posts_array[]        = $post;
			}
		}

		return $posts_array;
	}
}
