# SearchPress Attachments Add-On

The [ingest attachment plugin](https://www.elastic.co/guide/en/elasticsearch/plugins/current/ingest-attachment.html) lets Elasticsearch extract file attachments in common formats (such as PPT, XLS, and PDF) by using the Apache text extraction library Tika. The source field must be a base64 encoded binary.

## Requirements

* [SearchPress v0.4+](https://github.com/alleyinteractive/searchpress)
* Elasticsearch v7+
* [Elasticsearch's Ingest attachment plugin](https://www.elastic.co/guide/en/elasticsearch/plugins/current/ingest-attachment.html)

## Instructions

To use this plugin, you must first ensure that the ingest attachment plugin is active on the Elasticsearch node that your project is using. You can confirm which plugins are currently active on your node by sending a GET request to `<my-es-endpoint-url>/_cat/plugins`. Instructions for installing the plugin are unique to the hosting service, so be sure to confirm that the plugin can be used in advance of planning to use this add-on.

Once the ingest attachment plugin is installed on your node, you must also ensure that SearchPress is also active. Once your project meets these two requirements, simply install and activate this add-on. No further configuration is necessary.

You may find that your indexing operations fail if the index request attempts to send too much data. If this is the case, you will likely need to find a good balance between the value set for `sp_attachments_max_file_size` and SearchPress' bulk size `\SP_Sync_Meta()->bulk`. Lowering both of these will result in smaller remote request sizes sent to the ES instance, but the degree to which either (or both) are lowered will depend on project specifics.

## Filters

* `sp_attachments_max_file_size` - Filters the max file size for indexed attachments. If a file exceeds this limit, the file contents will not be added to the index for this document. The attachment data will otherwise be indexed. Defaults to 5MB.
* `sp_attachments_index_file_contents` - Filters whether or not a given file's contents should be indexed. This can be used to override indexing file contents that would otherwise be skipped by `sp_attachments_max_file_size`.
*
