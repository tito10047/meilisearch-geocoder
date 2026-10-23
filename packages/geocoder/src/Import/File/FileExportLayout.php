<?php

namespace Tito10047\Geocoder\Import\File;

use Tito10047\Geocoder\Storage\TableNames;

/**
 * The files of an export: the documents in NDJSON and the tables of the build in SQLite, under
 * the default table names whatever the names of the database it comes from or goes to.
 *
 * @internal
 */
final class FileExportLayout
{
    public const DOCUMENTS = 'documents.ndjson';
    public const DATABASE = 'geocoder.sqlite';

    public static function tables(): TableNames
    {
        return new TableNames();
    }
}
