<?php

namespace Tito10047\Geocoder\Index;

/**
 * Whether Meilisearch matches the last word of a query as a prefix (setting "prefixSearch").
 */
enum PrefixSearch: string
{
    /** Prefixes are computed while indexing: required by an autocomplete. */
    case IndexingTime = 'indexingTime';

    /** Only whole words match: a smaller index, no type-ahead. */
    case Disabled = 'disabled';
}
