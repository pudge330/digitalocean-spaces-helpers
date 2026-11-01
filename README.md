# DigitalOcean Spaces Helpers

Helper classes to interact with DigitalOcean spaces object storage using the AWS S3 SDK and Symfony HttpClient.

This library provides common functionality in regards to creating, deleting, and listing Spaces and objects. This library will also facilitate uploading and downloading objects.

This library is fairly new and the api for various methods could change in the upcoming future. I will do my best to document all changes and not introduce breaking changes without notice.

## Requirements

- PHP 8.3 or newer
- The SimpleXML extension
- Composer 2

## Installation

```shell
composer require bag/digitalocean-spaces-helpers
```

To run the test suite from a source checkout:

```shell
composer install
composer test
```

## Upcoming

- Usage examples.
- Discontinuing use of AWS S3 SDK and implementing fully with http api instead

## Classes

### BAG\Spaces\Client

Main client class that can be used to manage spaces and objects.

**Constants**

- ACL_PUBLIC = 'public-read'
- ACL_PRIVATE = 'private'

#### ```constructor($key, $secret, $region)```

| Name    | Type   | Optional| Description             |
|---------|--------|---------|-------------------------|
| $key    | string | false   | Spaces api key          |
| $secret | string | false   | Spaces api secret       |
| $region | string | false   | Spaces region, eg. nyc1 |

#### ```createSpace($name)```

Create space.

Returns true if space was created, false if it already existed and on failure.

| Name  | Type   | Optional| Description   |
|-------|--------|---------|---------------|
| $name | string | false   | Name of space |

#### ```deleteSpace($name)```

Delete space.

Returns true if the space was deleted, false otherwise.

| Name  | Type   | Optional| Description   |
|-------|--------|---------|---------------|
| $name | string | true    | Name of space |

#### ```listSpaces($names = false)```

Get list of spaces.

Returns array of available spaces.

| Name   | Type | Optional| Description       |
|--------|------|---------|-------------------|
| $names | bool | true    | Return names only |

#### ```spaceExists($space)```

Check if space exists.

Returns true if space exists, false otherwise.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |

#### ```list($space, $keys = false, $arguments = [])```

Get one page of objects. This method preserves the original API and performs a
single `ListObjectsV2` request; it does not traverse additional pages.

Returns array of objects, false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $keys      | bool   | true    | Return keys only         |
| $arguments | array  | true    | Additional api arguments |

#### ```listPage($space, $keys = false, $arguments = [], $cursor = null)```

Get one cursor-addressable page of objects. This uses legacy `ListObjects` and
its `Marker` cursor because DigitalOcean documents ListObjectsV2 pagination as
unsupported. Pass the returned `nextCursor` to the next call.

Returns an array with `items`, `isTruncated`, and `nextCursor`, or false on
request failure.

#### ```iterate($space, $keys = false, $arguments = [], $maxItems = null, $maxPages = 1000)```

Lazily iterate over object pages using legacy marker pagination. `maxItems` and
`maxPages` provide hard limits; a missing or repeated continuation marker
throws `RuntimeException` instead of looping indefinitely.

```php
foreach ($client->iterate('my-space', true, ['Prefix' => 'reports/'], maxItems: 100) as $key) {
    // Process one key without loading the complete listing into memory.
}
```

`listFoldersPage()` and `iterateFolders()` provide the equivalent behavior for
folder prefixes. See DigitalOcean's [Spaces limits documentation](https://docs.digitalocean.com/products/spaces/details/limits/)
for the compatibility note.

#### ```get($space, $key, $arguments = [])```

Get exact object metadata using `HeadObject`.

Returns an array, null if not found, or false on another request failure. The
array includes `Key` and `Size` compatibility fields in addition to the
metadata returned by `HeadObject`.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $key       | string | false   | Object key               |
| $arguments | array  | true    | Additional api arguments |

#### ```exists($space, $key)```

Check if object exists.

Returns true if object exists, false otherwise.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```getAcl($space, $key)```

Get an objects ACL.

Return string or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```setAcl($space, $key, $acl)```

Set an objects ACL.

Returns true or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |
| $acl   | string | false   | ACL value     |

#### ```upload($space, $key, $content, $public = false, $arguments = [])```

Upload content.

Return key as string if successful or false on failure.

| Name       | Type                                                   | Optional| Description              |
|------------|--------------------------------------------------------|---------|--------------------------|
| $space     | string                                                 | false   | Name of space            |
| $key       | string                                                 | false   | Object key               |
| $content   | string<br>resource<br>Psr\Http\Message\StreamInterface | false   | Content to add           |
| $public    | bool                                                   | true    | Object is public         |
| $arguments | array                                                  | true    | Additional api arguments |

#### ```uploadFile($space, $key, $file, $public = false, $arguments = [])```

Upload file.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```multipartUploadFile($space, $key, $file, $public = false, $arguments = [])```

Multipart file upload.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $space     | string | false   | Name of space            |
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```download($space, $key, $destination)```

Downloads a file locally.

Returns true if file downloaded successfully or false on failure.

| Name         | Type   | Optional| Description      |
|--------------|--------|---------|------------------|
| $space       | string | false   | Name of space    |
| $key         | string | false   | Object key       |
| $destination | string | false   | File destination |

#### ```publicUrl($space, $key)```

Get object public URL.

Returns a URL string.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```presignedDownload($space, $key, $duration = '+5 minutes')```

Generate a presigned download URL.

Returns presigned download URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $space    | string | false   | Name of space                                 |
| $key      | string | false   | Object key                                    |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |

#### ```presignedUpload($space, $key, $duration = '+5 minutes', $contentType = null)```

Generate a presigned upload URL.

Returns presigned upload URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $space    | string | false   | Name of space                                 |
| $key      | string | false   | Object key                                    |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |
| $contentType | string|null | true | Content type that the upload request must use |

#### ```delete($space, $key)```

Delete object.

Returns true on success, false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $space | string | false   | Name of space |
| $key   | string | false   | Object key    |

#### ```baseKey($key)```

Get base key. Similar to PHP dirname().

Returns string.

| Name | Type   | Optional| Description |
|------|--------|---------|-------------|
| $key | string | false   | Object key  |

#### ```verifyRequest(&$request, $key, $default = [])```

Verify HTTP result has particular property.

| Name      | Type   | Optional| Description   |
|-----------|--------|---------|---------------|
| &$request | object | false   | Api result    |
| $key      | string | false   | Value key     |
| $default  | mixed  | true    | Default value |

#### ```createAuthorizationSignature($arguments)```

Create an authorization signature header.

Returns an array. First item being an array of headers use for api authorization, second item is the headers compiled to a string.

| Name       | Type   | Optional| Description                               |
|------------|--------|---------|-------------------------------------------|
| $arguments | array  | false   | Arguments required to construct signature |

#### ```getClient()```

Get S3Client instance.

Returns S3Client.

#### **static** ```createClient($key, $secret, $endpoint)```

Create S3Client.

Returns S3Client instance.

| Name      | Type   | Optional| Description       |
|-----------|--------|---------|-------------------|
| $key      | string | false   | Spaces api key    |
| $secret   | string | false   | Spaces api secret |
| $endpoint | string | false   | Api endpoint      |

#### **static** ```create($key, $secret, $endpoint)```

Create Spaces Client.

Returns Client instance.

| Name    | Type   | Optional| Description             |
|---------|--------|---------|-------------------------|
| $key    | string | false   | Spaces api key          |
| $secret | string | false   | Spaces api secret       |
| $region | string | false   | Spaces region, eg. nyc1 |

### BAG\Spaces\Space

Space class that can be used to manage a single space and its objects.

**Constants**

- ACL_PUBLIC = 'public-read'
- ACL_PRIVATE = 'private'

#### ```construct($space, $key, $secret, $region, $create = false)```

| Name    | Type   | Optional| Description                  |
|---------|--------|---------|------------------------------|
| $space  | string | false   | Name of space                |
| $key    | string | false   | Spaces api key               |
| $secret | string | false   | Spaces api secret            |
| $region | string | false   | Spaces region, eg. nyc1      |
| $create | string | true    | Create space if non-existent |

#### ```list($keys = false, $arguments = [])```

Get one page of objects. This method does not traverse additional pages.

Returns array of objects, false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $keys      | bool   | true    | Return keys only         |
| $arguments | array  | true    | Additional api arguments |

#### ```listPage($keys = false, $arguments = [], $cursor = null)```

Get one page using legacy marker pagination. Returns `items`, `isTruncated`,
and `nextCursor`, or false on failure.

#### ```iterate($keys = false, $arguments = [], $maxItems = null, $maxPages = 1000)```

Lazily iterate over object pages with explicit item and page safety limits.
`listFoldersPage()` and `iterateFolders()` provide the same behavior for folder
prefixes.

#### ```get($key, $arguments = [])```

Get exact object metadata using `HeadObject`.

Returns an array, null if not found, or false on another request failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $key       | string | false   | Object key               |
| $arguments | array  | true    | Additional api arguments |

#### ```exists($key)```

Check if object exists.

Returns true if object exists, false otherwise.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```getAcl($key)```

Get an objects ACL.

Return string or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```setAcl($key, $acl)```

Set an objects ACL.

Returns true or false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |
| $acl   | string | false   | ACL value     |

#### ```upload($key, $content, $public = false, $arguments = [])```

Upload content.

Return key as string if successful or false on failure.

| Name       | Type                                                   | Optional| Description              |
|------------|--------------------------------------------------------|---------|--------------------------|
| $key       | string                                                 | false   | Object key               |
| $content   | string<br>resource<br>Psr\Http\Message\StreamInterface | false   | Content to add           |
| $public    | bool                                                   | true    | Object is public         |
| $arguments | array                                                  | true    | Additional api arguments |

#### ```uploadFile($key, $file, $public = false, $arguments = [])```

Upload file.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```multipartUploadFile($key, $file, $public = false, $arguments = [])```

Multipart file upload.

Return key as string if successful or false on failure.

| Name       | Type   | Optional| Description              |
|------------|--------|---------|--------------------------|
| $key       | string | false   | Object key               |
| $file      | string | false   | File path on disk        |
| $public    | bool   | true    | Object is public         |
| $arguments | array  | true    | Additional api arguments |

#### ```download($key, $destination)```

Downloads a file locally.

Returns true if file downloaded successfully or false on failure.

| Name         | Type   | Optional| Description      |
|--------------|--------|---------|------------------|
| $key         | string | false   | Object key       |
| $destination | string | false   | File destination |

#### ```publicUrl($key)```

Get object public URL.

Returns a URL string.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```presignedDownload($key, $duration = '+5 minutes')```

Generate a presigned download URL.

Returns presigned download URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $key      | string | false   | Object key                                    |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |

#### ```presignedUpload($key, $type, $duration = '+5 minutes')```

Generate a presigned upload URL.

Returns presigned upload URL as a string.

| Name      | Type   | Optional| Description                                   |
|-----------|--------|---------|-----------------------------------------------|
| $key      | string | false   | Object key                                    |
| $type     | string | false   | Content type that the upload request must use |
| $duration | string | true    | Length of time URL is valid, eg. '+5 minutes' |

#### ```delete($key)```

Delete object.

Returns true on success, false on failure.

| Name   | Type   | Optional| Description   |
|--------|--------|---------|---------------|
| $key   | string | false   | Object key    |

#### ```baseKey(string $key)```

Get base key. Similar to PHP dirname().

Returns string.

| Name | Type   | Optional| Description |
|------|--------|---------|-------------|
| $key | string | false   | Object key  |

#### ```getClient()```

Get Client instance.

Returns Client.

### BAG\Spaces\Authorization

Class that assists with generating http authorization headers.

#### ```construct($key, $secret)```

| Name    | Type   | Optional| Description                  |
|---------|--------|---------|------------------------------|
| $key    | string | false   | Spaces api key               |
| $secret | string | false   | Spaces api secret            |

#### ```create($arguments)```

Create authorization headers and return full set of headers generated.

Returns an array. First item being an array of headers use for api authorization, second item is the headers compiled to a string.

| Name       | Type  | Optional| Description       |
|------------|-------|---------|-------------------|
| $arguments | array | false   | Request arguments |
