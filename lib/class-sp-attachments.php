<?php
/**
 * SP Attachments main functionality.
 *
 * @package     searchpress-attachments
 */

/**
 * Class SP_Attachments.
 */
class SP_Attachments {
	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instances;

	/**
	 * Name of ES plugin associated with this SP plugin.
	 */
	const ES_PLUGIN_NAME = 'ingest-attachment';

	/**
	 * Name of the pipeline.
	 *
	 * @var string
	 */
	public $pipeline_name;

	/**
	 * Maximum allowed filesize in bytes for indexed attachments.
	 *
	 * @var int
	 */
	public $max_file_size;

	/**
	 * Ensure singletons can't be instantiated outside the `instance()` method.
	 */
	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Get an instance of the class.
	 *
	 * @return SP_Attachments
	 */
	public static function instance() {
		$class_name = get_called_class();
		if ( ! isset( self::$instances[ $class_name ] ) ) {
			self::$instances[ $class_name ] = new $class_name();
			self::$instances[ $class_name ]->setup();
		}
		return self::$instances[ $class_name ];
	}

	/**
	 * Set up SP hooks.
	 */
	public function setup() {
		/**
		 * Filter the pipeline name.
		 * Defaults to 'attachment'.
		 *
		 * @param string $pipeline_name Name of attachment pipeline.
		 */
		$this->pipeline_name = apply_filters( 'sp_attachments_pipeline_name', 'attachment' );

		/**
		 * Filter the max file size for indexed attachments.
		 * Defaults to 5MB.
		 *
		 * @param int $max_file_size Max allowed file size in bytes.
		 */
		$this->max_file_size = apply_filters( 'sp_attachments_max_file_size', 5242880 );

		// Add pipeline to index path.
		add_filter ('sp_post_index_path', [ $this, 'add_pipeline_to_post_index_path' ], 10, 2 );
		add_filter ('sp_bulk_index_path', [ $this, 'add_pipeline_to_bulk_index_path' ] );

		add_action( 'sp_config_sync_post_types', [ $this, 'sync_post_types' ] );

		add_filter( 'sp_config_mapping', [ $this, 'customize_mapping' ] );
		add_filter( 'sp_config_mapping', [ $this, 'create_sp_pipeline' ], 1 );

		add_filter( 'sp_post_pre_index', [ $this, 'add_attachment_file_content_to_data' ] );

		add_filter( 'sp_post_should_be_indexed', [ $this, 'post_should_be_indexed' ], 10, 2 );
	}

	/**
	 * Do not index attachments that are not allowed mime types.
	 *
	 * Filters `sp_post_should_be_indexed`.
	 *
	 * @param bool     $should_be_indexed Whether or not the post should be indexed.
	 * @param \SP_Post $sp_post           \SP_Post Object
	 * @return bool
	 */
	public function post_should_be_indexed( bool $should_be_indexed, \SP_Post $sp_post ): bool {
		// Do not index if attachment is not an allowed mime type.
		if (
			'attachment' === $sp_post->data['post_type'] &&
			! in_array( get_post_mime_type( $sp_post->data['post_id'] ), $this->get_allowed_sp_attachment_mime_types(), true )
		) {
			return false;
		}

		return $should_be_indexed;
	}

	/**
	 * Add pipeline query arg to bulk index path.
	 *
	 * Filters `sp_bulk_index_path`.
	 *
	 * @param $path
	 * @return string
	 */
	public function add_pipeline_to_bulk_index_path( $path ): string {
		return add_query_arg( [ 'pipeline' => $this->pipeline_name ], $path );
	}

	/**
	 * Add pipeline query arg to single post index path.
	 *
	 * Filters `sp_post_index_path`.
	 *
	 * @param string   $path    Index path
	 * @param \SP_Post $sp_post SP Post Object
	 * @return string
	 */
	public function add_pipeline_to_post_index_path( string $path, \SP_Post $sp_post ): string {
		if ( 'attachment' !== $sp_post->data['post_type'] ) {
			return $path;
		}

		return add_query_arg( [ 'pipeline' => $this->pipeline_name ], $path );
	}

	/**
	 * Add attachment to sync post types.
	 *
	 * Filters `sp_config_sync_post_types`
	 *
	 * @param $post_types
	 * @return string[]
	 */
	public function sync_post_types( $post_types ): array {
		$post_types[] = 'attachment';

		return $post_types;
	}

	/**
	 * Add attachment mappings.
	 *
	 * Filters `sp_config_mapping`.
	 *
	 * @param $mapping
	 * @return array
	 */
	public function customize_mapping( $mapping ): array {
		$mapping['mappings']['properties']['attachment'] = [ 'type' => 'object' ];
		return $mapping;
	}

	/**
	 * Add attachment contents to index data for attachment posts.
	 *
	 * Filters `sp_post_pre_index`.
	 *
	 * @param $data
	 * @return array Array of data containing attachment contents on success, unchanged data on failure.
	 */
	public function add_attachment_file_content_to_data( $data ): array {
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';

		do_action( 'sp_debug', '[SP_Attachment] Adding attachment file content to attachment document' );

		// Set attachment data to empty string for default.
		$data['attachment']['data'] = '';

		// Initialize filesystem.
		if ( true !== WP_Filesystem( request_filesystem_credentials( site_url() ) ) && empty( $wp_filesystem ) ) {
			return $data;
		}

		// Ensure that the file exists.
		$file_path = get_attached_file( $data['post_id'] );
		if ( ! $wp_filesystem->exists( $file_path, false, 'f' ) ) {
			return $data;
		}

		// Does the filesize meet the maximum size requirements?
		$file_is_allowed = filesize( $file_path ) < $this->max_file_size;

		/**
		 * Filter whether or not a given file's contents should be indexed.
		 *
		 * @param bool   $file_is_allowed Is the file smaller than the max allowed filesize?
		 * @param string $file_path       Path to the oversized file.
		 * @param array  $data            Post index data.
		 */
		$file_contents_should_be_indexed = apply_filters( 'sp_attachments_index_file_contents', $file_is_allowed, $file_path, $data );

		// Add file contents to the index.
		if ( $file_contents_should_be_indexed ) {
			$file_content               = $wp_filesystem->get_contents( $file_path );
			$data['attachment']['data'] = base64_encode( $file_content );
		}

		return $data;
	}

	/**
	 * Filters allowed mime types.
	 *
	 * @return array
	 */
	public function get_allowed_sp_attachment_mime_types(): array {
		/**
		 * Filters allowed mime types for attachments.
		 *
		 * @param  array $allowed_mime_types Allowed mime types.
		 * @return array
		 */
		return apply_filters(
			'sp_attachments_allowed_mime_types',
			[
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'pdf'  => 'application/pdf',
				'ppt'  => 'application/vnd.ms-powerpoint',
				'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'xls'  => 'application/vnd.ms-excel',
				'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			]
		);
	}

	/**
	 * Is the Ingest Attachment Plugin Active?
	 *
	 * @return bool
	 */
	public static function ingest_attachment_plugin_is_active(): bool {
		if ( ! class_exists( 'SP_Config' ) ) {
			return false;
		}

		$settings = \SP_Config()->get_settings();
		if ( ! isset( $settings['plugins'][ self::ES_PLUGIN_NAME ] ) ) {
			// Fetch plugins from ES endpoint if ingest attachment plugin is not yet set in SP options.
			$cat_plugins = \SP_API()->request( trailingslashit( \SP_Config()->get_setting( 'host' ) ) . '_cat/plugins/' );

			// Set settings value here so it can be returned below.
			$settings['plugins'][ self::ES_PLUGIN_NAME ] = ( false !== strpos( $cat_plugins, self::ES_PLUGIN_NAME ) );

			// Set plugins in SP config.
			\SP_Config()->update_settings( $settings );
		}

		return $settings['plugins'][ self::ES_PLUGIN_NAME ];
	}

	/**
	 * Create Elasticsearch ingest pipeline.
	 * Returns an unfiltered value for $mapping.
	 *
	 * Filters `sp_config_mapping`.
	 *
	 * TODO Create an action in SearchPress to hook this into.
	 *
	 * @param array $mapping Array of mapping data.
	 * @return array
	 */
	public function create_sp_pipeline( array $mapping ): array {
		$path = trailingslashit( \SP_Config()->get_setting( 'host' ) ) . '_ingest/pipeline/' . $this->pipeline_name;
		$body = [
			'description' => __( 'Extract attachment contents', 'searchpress-attachments' ),
			'processors'  => [
				[
					'attachment' => [
						'field' => 'attachment.data'
					],
				]
			]
		];
		$response = \SP_API()->put( $path, wp_json_encode( $body ) );
		if ( ! empty( $response->error ) ) {
			// TODO Handle error.
		}

		return $mapping;
	}
}
