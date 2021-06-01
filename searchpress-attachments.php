<?php
/**
 * SearchPress Attachments.
 *
 * Plugin Name: SearchPress Attachments Add-on
 * Plugin URI: https://github.com/alleyinteractive/searchpress-attachments
 * Description: SearchPress add-on which allows for the indexing of attachment content. Requires the Ingest Attachment Processor Elasticsearch plugin to be installed. For more information, see https://www.elastic.co/guide/en/elasticsearch/plugins/current/ingest-attachment.html.
 * Version: 0.1.0
 * Author: Jake Foster, Alley
 *
 * @package searchpress-attachments
 */

/*
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace Searchpress_Attachments;

// Define constants.
if ( ! defined( 'SP_ATTACHMENTS_PLUGIN_URL' ) ) {
	define( 'SP_ATTACHMENTS_PLUGIN_URL', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'SP_ATTACHMENTS_PLUGIN_DIR' ) ) {
	define( 'SP_ATTACHMENTS_PLUGIN_DIR', dirname( __FILE__ ) );
}

// Require SP Attachments class.
require_once SP_ATTACHMENTS_PLUGIN_DIR . '/lib/class-sp-attachments.php';

/**
 * Print admin notice if add-on requirements are not met.
 *
 * @TODO Create better notices.
 */
function _sp_attachments_print_admin_notice() {
	// Set up if SP is installed and ES version is at least 7.0.
	if (
		! function_exists( 'sp_es_version_compare' ) ||
		! sp_es_version_compare( '7.0' ) ||
		! \SP_Attachments::ingest_attachment_plugin_is_active()
	) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'SearchPress Attachments Add-on require ES 7+, SearchPress, and for the ES Plugin "Ingest Attachment Processor" to be installed on  your ES Node.', 'searchpress-attachments' ); ?></p>
		</div>
	<?php
	endif;
}

add_action( 'admin_notices', __NAMESPACE__ . '\_sp_attachments_print_admin_notice' );

\SP_Attachments::instance();
